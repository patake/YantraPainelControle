<?php

namespace Tests\Unit\Support;

use App\Support\LineDiff;
use PHPUnit\Framework\TestCase;

class LineDiffTest extends TestCase
{
    public function test_marks_the_removed_and_added_lines_and_keeps_the_common_lines(): void
    {
        $diff = LineDiff::compare("SELECT *\nFROM a\nWHERE x = 1", "SELECT *\nFROM a\nWHERE x = 2\nORDER BY 1");

        $this->assertSame(
            [['same', 1, 1], ['same', 2, 2], ['removed', 3, null], ['added', null, 3], ['added', null, 4]],
            array_map(fn (array $line) => [$line['type'], $line['old'], $line['new']], $diff),
        );
    }

    public function test_ignores_windows_line_breaks(): void
    {
        $diff = LineDiff::compare("SELECT 1\r\nFROM dual", "SELECT 1\nFROM dual");

        $this->assertSame(['same', 'same'], array_column($diff, 'type'));
    }

    public function test_compares_with_an_empty_text(): void
    {
        $this->assertSame(['added', 'added'], array_column(LineDiff::compare('', "a\nb"), 'type'));
        $this->assertSame([], LineDiff::compare('', ''));
    }
}
