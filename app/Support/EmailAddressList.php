<?php

namespace App\Support;

final class EmailAddressList
{
    /**
     * Split a pasted list into trimmed tokens (comma, newline, semicolon, whitespace).
     *
     * @return list<string>
     */
    public static function parseTokens(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $t = strtolower(trim((string) $part));
            if ($t !== '') {
                $tokens[] = $t;
            }
        }

        return array_values(array_unique($tokens));
    }
}
