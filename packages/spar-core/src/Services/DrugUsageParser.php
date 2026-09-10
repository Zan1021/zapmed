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
    ];

    /** Cells that must be present for a row to be a header row. */
    private const HEADER_MARKERS = ['surname', 'profile code'];

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

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $grid = $sheet->toArray(null, true, false, false); // 0-indexed rows/cols

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

            $key = $profile . '|' . $dependent;
            $identities[$key] = $this->mergePreferringNonEmpty($identities[$key] ?? [], $record);
        }

        return $identities;
    }

    private function locateHeaderRow(array $grid): ?int
    {
        foreach ($grid as $idx => $row) {
            $cells = array_map(fn ($c) => $this->normaliseHeader($c), $row);
            $present = true;
            foreach (self::HEADER_MARKERS as $marker) {
                if (!in_array($marker, $cells, true)) {
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
