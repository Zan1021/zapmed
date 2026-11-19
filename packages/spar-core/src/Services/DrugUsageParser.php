<?php

namespace Zapmed\SparCore\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Parses the SPAR "Drug Usage" report (.xlsx) — the file that carries patient
 * IDENTITY + CONTACT details (surname, firstname, mobile, address, medical aid).
 *
 * The sales extract (SparImportService) carries the dispense transactions but
 * NO contact info (pseudonymised on Profile Code — see Kyle's email). SPAR is
 * adding two columns to the Drug Usage report — "Profile Code" and
 * "Dependent Code" — so the two files can be joined on (profile, dependent).
 * This parser reads those rows and returns identity records keyed on that pair.
 *
 * Report shape (real file, verified):
 *   - rows 1-6 : merged preamble (pharmacy name, report date, "Drug Usage")
 *   - a header row containing "Surname" ... "Member Number" [+ "Profile Code",
 *     "Dependent Code"]
 *   - data rows below the header
 * The parser locates the header row by content (not a fixed index) so it is
 * resilient to preamble changes.
 */
class DrugUsageParser
{
    /** Header label => internal identity field. */
    private const COLUMN_MAP = [
        'surname' => 'last_name',
        'firstname' => 'first_name',
        'first name' => 'first_name',
        'mobile' => 'cellphone',
        'member mobile nr' => 'cellphone_alt',
        'address' => 'address',
        'email' => 'email',
        'email address' => 'email',
        'medical aid no' => 'medical_aid_number',
        'member number' => 'member_number',
        'profile code' => 'profile_code',
        'dependent code' => 'dependent_code',
        'dependant code' => 'dependent_code',

        // Member-extract layout (pipe-delimited text file, e.g. Wapadrand_extract.csv).
        'lastname' => 'last_name',
        'cellphone' => 'cellphone',
        'profilenumber' => 'profile_code',
        'dependantnumber' => 'dependent_code',
        'medicalaidnumber' => 'medical_aid_number',
        'consent' => 'source_consent',
        'dateofbirth' => 'date_of_birth',
    ];

    /**
     * A header row must contain one label from EACH group: a surname column and
     * a profile-code column (old Drug Usage names or the member-extract names).
     */
    private const HEADER_MARKER_GROUPS = [
        ['surname', 'lastname'],
        ['profile code', 'profilenumber'],
    ];

    /** Text values SPAR uses for "nothing here". */
    private const NULL_TOKENS = ['NULL', 'N/A'];

    /**
     * Parse the xlsx and return identity rows keyed by "profile_code|dependent_code".
     * Later rows for the same key win only for non-empty values, so a fuller
     * record isn't clobbered by a sparse one.
     *
     * @return array<string, array<string, mixed>>  key => identity fields
     */
    public function parse(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException('Drug Usage file not found.');
        }

        $size = filesize($filePath);
        if ($size > 50 * 1024 * 1024) {
            throw new \RuntimeException('Drug Usage file too large. Maximum 50MB allowed.');
        }

        $grid = $this->isSpreadsheet($filePath)
            ? $this->readSpreadsheetGrid($filePath)
            : $this->readDelimitedGrid($filePath);

        $headerRowIndex = $this->locateHeaderRow($grid);
        if ($headerRowIndex === null) {
            throw new \RuntimeException(
                'Could not locate the Drug Usage header row (expected "Surname" and "Profile Code" columns). '
                . 'Confirm SPAR has added the Profile Code + Dependent Code fields.'
            );
        }

        $header = array_map(fn ($c) => $this->normaliseHeader($c), $grid[$headerRowIndex]);
        $colToField = [];
        foreach ($header as $colIdx => $label) {
            if ($label !== '' && isset(self::COLUMN_MAP[$label])) {
                $colToField[$colIdx] = self::COLUMN_MAP[$label];
            }
        }

        // Hard requirement: the linking columns must exist.
        if (!in_array('profile_code', $colToField, true) || !in_array('dependent_code', $colToField, true)) {
            throw new \RuntimeException(
                'Drug Usage file is missing the "Profile Code" and/or "Dependent Code" columns needed to link '
                . 'patients to dispense history. Ask SPAR to include both fields in the export.'
            );
        }

        $identities = [];
        $rowCount = count($grid);
        for ($r = $headerRowIndex + 1; $r < $rowCount; $r++) {
            $raw = $grid[$r];
            $record = [];
            foreach ($colToField as $colIdx => $field) {
                $record[$field] = $this->clean($raw[$colIdx] ?? null);
            }

            if (array_key_exists('date_of_birth', $record)) {
                $record['date_of_birth'] = $this->normaliseDob($record['date_of_birth']);
            }

            $profile = trim((string) ($record['profile_code'] ?? ''));
            $dependent = $this->normaliseDependent($record['dependent_code'] ?? '');
            if ($profile === '') {
                continue; // no join key → skip
            }
            $record['profile_code'] = $profile;
            $record['dependent_code'] = $dependent;

            // Prefer "Mobile"; fall back to "Member Mobile Nr" for the cellphone.
            if (empty($record['cellphone']) && !empty($record['cellphone_alt'])) {
                $record['cellphone'] = $record['cellphone_alt'];
            }
            unset($record['cellphone_alt']);

            // Consent is a safety flag: if ANY line for this member says "N",
            // the member counts as "N" (never let a later "Y" line override it).
            $incomingConsent = isset($record['source_consent']) ? strtoupper(trim((string) $record['source_consent'])) : '';
            $record['source_consent'] = in_array($incomingConsent, ['Y', 'N'], true) ? $incomingConsent : null;

            $key = $profile . '|' . $dependent;
            $existingConsent = $identities[$key]['source_consent'] ?? null;
            $identities[$key] = $this->mergePreferringNonEmpty($identities[$key] ?? [], $record);
            if ($existingConsent === 'N' || $record['source_consent'] === 'N') {
                $identities[$key]['source_consent'] = 'N';
            }
        }

        return $identities;
    }

    private function isSpreadsheet(string $filePath): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return true;
        }
        if (in_array($ext, ['csv', 'txt'], true)) {
            return false;
        }

        // Unknown extension (e.g. an uploaded temp name): sniff the zip header.
        $fh = @fopen($filePath, 'rb');
        $magic = $fh ? fread($fh, 2) : '';
        if ($fh) {
            fclose($fh);
        }

        return $magic === 'PK';
    }

    /** @return array<int, array<int, mixed>> */
    private function readSpreadsheetGrid(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false); // 0-indexed rows/cols
    }

    /**
     * Read a delimited text member extract (pipe or comma). Tolerates a UTF-8
     * BOM, blank/broken trailing lines and padded values; the literal text
     * "NULL" is treated as empty.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readDelimitedGrid(string $filePath): array
    {
        $content = (string) file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $firstLine = strtok($content, "\n");
        $delimiter = $firstLine !== false && str_contains($firstLine, '|') ? '|' : ',';

        $grid = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(function ($c) {
                $c = trim((string) $c);

                return in_array(strtoupper($c), self::NULL_TOKENS, true) ? '' : $c;
            }, str_getcsv($line, $delimiter));

            // A line with fewer than 3 fields is a broken fragment, not a record.
            if (count($cells) < 3) {
                continue;
            }
            $grid[] = $cells;
        }

        return $grid;
    }

    private function locateHeaderRow(array $grid): ?int
    {
        foreach ($grid as $idx => $row) {
            $cells = array_map(fn ($c) => $this->normaliseHeader($c), $row);
            $present = true;
            foreach (self::HEADER_MARKER_GROUPS as $alternatives) {
                if (empty(array_intersect($alternatives, $cells))) {
                    $present = false;
                    break;
                }
            }
            if ($present) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Date of birth as Y-m-d, or null when it is blank or implausible. Accepts
     * "27 Jul 1949" text and Excel serial numbers.
     */
    private function normaliseDob(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        try {
            $date = is_numeric($v)
                ? \Carbon\Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))
                : \Carbon\Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }

        if ($date->year < 1900 || $date->isFuture()) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function normaliseHeader($value): string
    {
        return strtolower(trim((string) $value));
    }

    /**
     * Dependent code normalised to a two-char string ("0" -> "00", 1 -> "01").
     * SPAR scheme: 00 main member, 01 spouse, 02+ children.
     */
    private function normaliseDependent($value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '00';
        }
        if (ctype_digit($v)) {
            return str_pad($v, 2, '0', STR_PAD_LEFT);
        }

        return $v;
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Excel serial dates arrive as floats on date columns we don't map, so
        // this only runs on string-ish identity fields. Strip control chars.
        $str = is_string($value) ? $value : (string) $value;
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str);

        return trim($str);
    }

    private function mergePreferringNonEmpty(array $existing, array $incoming): array
    {
        foreach ($incoming as $field => $val) {
            if ($val !== null && $val !== '' && (empty($existing[$field]))) {
                $existing[$field] = $val;
            } elseif (!array_key_exists($field, $existing)) {
                $existing[$field] = $val;
            }
        }

        return $existing;
    }
}
