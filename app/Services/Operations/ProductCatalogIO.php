<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Medication;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product list import and export (Craig's guide 6.4): the medication catalogue (about 15,000 products) as a CSV that
 * Operations can download, edit in a spreadsheet and load back. Admin only.
 *
 *  - Import is two steps: preview (reads the file, writes nothing, lists every problem) and import (all or nothing: one bad
 *    row stops the whole file so the catalogue is never half updated).
 *  - A row matches an existing product by NAPPI code, otherwise by name + strength + form. A match is updated, no match is added.
 *  - On an update a BLANK cell leaves the existing value alone, so a spreadsheet with a missing column cannot wipe the catalogue.
 *    The two yes/no columns only change when they are filled in.
 *  - Price is in rand in the file (299.50) and cents in the database.
 */
class ProductCatalogIO
{
    public const COLUMNS = [
        'nappi_code', 'name', 'generic_name', 'brand_name', 'form', 'strength', 'schedule', 'category',
        'price_rand', 'repeat_cycle_days', 'is_subscription', 'dosage_instructions', 'manufacturer', 'is_active',
    ];

    public const MAX_ROWS = 20000;
    public const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly AuditTrail $audit)
    {
    }

    private function assertOps(User $user): void
    {
        abort_unless($user->role === UserRole::Admin, 403);
    }

    // ---- export --------------------------------------------------------------------------------------------------

    public function export(User $ops, bool $activeOnly = false): string
    {
        $this->assertOps($ops);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, self::COLUMNS);

        Medication::query()->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('name')->orderBy('id')->chunk(1000, function ($rows) use ($out) {
            foreach ($rows as $m) {
                fputcsv($out, array_map([$this, 'safeCell'], [
                    $m->nappi_code, $m->name, $m->generic_name, $m->brand_name, $m->form, $m->strength, $m->schedule, $m->category,
                    $m->price_cents === null ? '' : number_format($m->price_cents / 100, 2, '.', ''),
                    $m->repeat_cycle_days, $m->is_subscription ? 'yes' : 'no', $m->dosage_instructions, $m->manufacturer, $m->is_active ? 'yes' : 'no',
                ]));
            }
        });

        rewind($out);

        return (string) stream_get_contents($out);
    }

    // ---- import --------------------------------------------------------------------------------------------------

    /**
     * Read the file and say what would happen. Writes nothing.
     *
     * @return array{created:int, updated:int, unchanged:int, errors:array<int,array{row:int,message:string}>, ignored_columns:string[]}
     */
    public function preview(User $ops, string $path): array
    {
        $this->assertOps($ops);

        return $this->process($path, false);
    }

    /** Apply the file. All or nothing: any error in the file stops it before anything is written. */
    public function import(User $ops, string $path): array
    {
        $this->assertOps($ops);

        $result = $this->process($path, true);

        if ($result['errors'] === []) {
            $this->audit->record('catalogue', 'products_imported', 'medications', null, null, [
                'created' => $result['created'], 'updated' => $result['updated'], 'unchanged' => $result['unchanged'],
            ], $ops->id);
        }

        return $result;
    }

    private function process(string $path, bool $write): array
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Choose a CSV file of up to 8 MB.']);
        }

        $handle = fopen($path, 'r');
        $first = fgets($handle);
        rewind($handle);
        $delimiter = ($first !== false && substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';   // Excel in some locales

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(['name', 'form', 'strength'], $header);
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'The file needs these columns: '.implode(', ', $missing).'.']);
        }
        $ignored = array_values(array_filter(array_diff($header, self::COLUMNS), fn ($h) => $h !== ''));

        $byNappi = [];
        $byKey = [];
        foreach (Medication::query()->get(['id', 'nappi_code', 'name', 'strength', 'form']) as $m) {
            if ($m->nappi_code) {
                $byNappi[strtolower($m->nappi_code)] = $m->id;
            }
            $byKey[$this->key($m->name, $m->strength, $m->form)] = $m->id;
        }

        $errors = [];
        $plan = [];
        $seen = [];
        $rowNo = 1;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNo++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;   // blank line
            }
            if ($rowNo - 1 > self::MAX_ROWS) {
                $errors[] = ['row' => $rowNo, 'message' => 'The file has more than '.self::MAX_ROWS.' rows.'];
                break;
            }

            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }

            [$data, $problems] = $this->validateRow($row);
            if ($problems) {
                foreach ($problems as $p) {
                    $errors[] = ['row' => $rowNo, 'message' => $p];
                }
                continue;
            }

            $id = null;
            if (! empty($data['nappi_code']) && isset($byNappi[strtolower($data['nappi_code'])])) {
                $id = $byNappi[strtolower($data['nappi_code'])];
            } else {
                $id = $byKey[$this->key($data['name'], $data['strength'], $data['form'])] ?? null;
            }

            $dupKey = $id ? 'id:'.$id : 'new:'.(! empty($data['nappi_code']) ? 'n:'.strtolower($data['nappi_code']) : $this->key($data['name'], $data['strength'], $data['form']));
            if (isset($seen[$dupKey])) {
                $errors[] = ['row' => $rowNo, 'message' => 'This product is in the file twice (also on row '.$seen[$dupKey].').'];
                continue;
            }
            $seen[$dupKey] = $rowNo;

            $plan[] = ['id' => $id, 'data' => $data, 'row' => $rowNo];
        }
        fclose($handle);

        $created = $updated = $unchanged = 0;
        $existing = Medication::whereIn('id', array_filter(array_column($plan, 'id')))->get()->keyBy('id');

        $writes = [];
        foreach ($plan as $item) {
            if ($item['id'] === null) {
                $created++;
                $writes[] = $item;

                continue;
            }
            $m = $existing->get($item['id']);
            $changes = array_filter($item['data'], fn ($v, $k) => $v !== null && $m->{$k} != $v, ARRAY_FILTER_USE_BOTH);
            if ($changes) {
                $updated++;
                $writes[] = ['id' => $item['id'], 'data' => $changes, 'row' => $item['row']];
            } else {
                $unchanged++;
            }
        }

        if ($write && $errors === []) {
            DB::transaction(function () use ($writes) {
                foreach ($writes as $w) {
                    if ($w['id'] === null) {
                        // blanks are left out so the database defaults apply (a new product is on and not a subscription unless the file says so)
                        Medication::create(array_filter($w['data'], fn ($v) => $v !== null));
                    } else {
                        Medication::whereKey($w['id'])->update($w['data']);
                    }
                }
            });
        }

        return ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged, 'errors' => $errors, 'ignored_columns' => $ignored];
    }

    /** @return array{0:array<string,mixed>,1:string[]} cleaned values (null = blank, leave alone) and the problems found */
    private function validateRow(array $row): array
    {
        $p = [];
        $text = function (string $col, int $max, bool $required = false) use ($row, &$p): ?string {
            $v = trim((string) ($row[$col] ?? ''));
            if ($v === '') {
                if ($required) {
                    $p[] = "{$col} is empty.";
                }

                return null;
            }
            if (mb_strlen($v) > $max) {
                $p[] = "{$col} is longer than {$max} characters.";

                return null;
            }

            return $v;
        };

        $data = [
            'nappi_code' => $text('nappi_code', 20),
            'name' => $text('name', 255, true),
            'generic_name' => $text('generic_name', 255),
            'brand_name' => $text('brand_name', 255),
            'form' => $text('form', 50, true),
            'strength' => $text('strength', 50, true),
            'schedule' => $text('schedule', 10),
            'category' => $text('category', 50),
            'dosage_instructions' => $text('dosage_instructions', 2000),
            'manufacturer' => $text('manufacturer', 255),
        ];

        $price = trim((string) ($row['price_rand'] ?? ''));
        $data['price_cents'] = null;
        if ($price !== '') {
            $clean = str_replace([' ', ','], ['', '.'], $price);
            if (! is_numeric($clean) || (float) $clean < 0 || (float) $clean > 1_000_000) {
                $p[] = 'price_rand must be an amount in rand, for example 299.50.';
            } else {
                $data['price_cents'] = (int) round(((float) $clean) * 100);
            }
        }

        $days = trim((string) ($row['repeat_cycle_days'] ?? ''));
        $data['repeat_cycle_days'] = null;
        if ($days !== '') {
            if (! ctype_digit($days) || (int) $days < 1 || (int) $days > 365) {
                $p[] = 'repeat_cycle_days must be a whole number from 1 to 365.';
            } else {
                $data['repeat_cycle_days'] = (int) $days;
            }
        }

        foreach (['is_subscription', 'is_active'] as $col) {
            $v = strtolower(trim((string) ($row[$col] ?? '')));
            $data[$col] = null;
            if ($v !== '') {
                if (in_array($v, ['yes', 'y', '1', 'true'], true)) {
                    $data[$col] = true;
                } elseif (in_array($v, ['no', 'n', '0', 'false'], true)) {
                    $data[$col] = false;
                } else {
                    $p[] = "{$col} must be yes or no.";
                }
            }
        }

        return [$data, $p];
    }

    private function key(?string $name, ?string $strength, ?string $form): string
    {
        return strtolower(trim((string) $name)).'|'.strtolower(trim((string) $strength)).'|'.strtolower(trim((string) $form));
    }

    private function safeCell(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }
}
