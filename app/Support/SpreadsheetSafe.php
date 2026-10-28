<?php

namespace App\Support;

/**
 * Keeps exported CSV cells from being run as formulas.
 *
 * Party names, docket numbers and the like come from imported spreadsheets
 * and user input. A cell such as =HYPERLINK("http://…"&A2) would otherwise
 * execute when the export is opened in Excel, so any text starting with a
 * formula trigger is written with a leading apostrophe and shown as text.
 */
final class SpreadsheetSafe
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], self::TRIGGERS, true)
            ? "'" . $value
            : $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    public static function row(array $values): array
    {
        return array_map(self::cell(...), $values);
    }
}
