<?php

namespace App\Support;

/**
 * Title Case for people's names, without damaging names that are already written correctly.
 *
 *  "OWUSU-ANSAH kwame"   -> "Owusu-Ansah Kwame"
 *  "  Agnes   OBENEWAA " -> "Agnes Obenewaa"
 *  "REV. DR. S. AYEYE"   -> "Rev. Dr. S. Ayeye"
 *  "McDonald", "DeGraft" -> unchanged (a name with its own capitals inside is left alone)
 */
class NameFormatter
{
    /** Roman numerals that follow a name (Kwame II) stay in capitals. */
    private const NUMERALS = ['II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

    public static function titleCase(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        // Tidy the spacing first: double spaces, tabs and line breaks become one space.
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return '';
        }

        return implode(' ', array_map(self::word(...), explode(' ', $name)));
    }

    private static function word(string $word): string
    {
        // Initials: "T.", "T.K.", "t.k" -> upper case.
        if (preg_match('/^(\p{L}\.)+\p{L}?\.?$/u', $word)) {
            return mb_strtoupper($word);
        }

        if (in_array(mb_strtoupper($word), self::NUMERALS, true) && $word === mb_strtoupper($word)) {
            return $word;
        }

        // Work on each piece of a hyphenated or apostrophe name: "OWUSU-ANSAH", "O'BRIEN".
        $parts = preg_split("/([-'’])/u", $word, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(self::part(...), $parts));
    }

    private static function part(string $part): string
    {
        if ($part === '' || ! preg_match('/\p{L}/u', $part)) {
            return $part;
        }

        $upper = mb_strtoupper($part);
        $lower = mb_strtolower($part);

        // Only shouting ("KWAME") or all-lowercase ("kwame") is rewritten; "McDonald" and "Kwame" are already fine.
        if ($part !== $upper && $part !== $lower) {
            return $part;
        }

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }
}
