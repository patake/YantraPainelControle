<?php

namespace App\Support;

/**
 * Diferença linha a linha entre dois textos, pela maior subsequência comum.
 * Os SQLs dos exames têm poucas dezenas de linhas, e a tabela O(n·m) fica pequena.
 */
class LineDiff
{
    /**
     * @return list<array{type: 'same'|'removed'|'added', old: ?int, new: ?int, text: string}>
     */
    public static function compare(string $old, string $new): array
    {
        $a = self::lines($old);
        $b = self::lines($new);
        $n = count($a);
        $m = count($b);

        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $diff = [];
        $i = 0;
        $j = 0;

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $diff[] = ['type' => 'same', 'old' => $i + 1, 'new' => $j + 1, 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($i < $n && ($j === $m || $lcs[$i + 1][$j] >= $lcs[$i][$j + 1])) {
                // A linha removida vem antes da linha nova, como no diff do Git.
                $diff[] = ['type' => 'removed', 'old' => $i + 1, 'new' => null, 'text' => $a[$i]];
                $i++;
            } else {
                $diff[] = ['type' => 'added', 'old' => null, 'new' => $j + 1, 'text' => $b[$j]];
                $j++;
            }
        }

        return $diff;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);

        return $text === '' ? [] : explode("\n", rtrim($text, "\n"));
    }
}
