<?php

namespace Tests\Unit;

use App\Database\CaseInsensitivePostgresGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use PHPUnit\Framework\TestCase;

class CaseInsensitivePostgresGrammarTest extends TestCase
{
    private function builder(): Builder
    {
        $connection = new Connection(fn () => null, 'x', '', []);
        $grammar = new CaseInsensitivePostgresGrammar($connection);

        return new Builder($connection, $grammar, new PostgresProcessor());
    }

    public function test_like_becomes_ilike(): void
    {
        $sql = $this->builder()->from('medications')->where('name', 'like', '%sema%')->toSql();
        $this->assertStringContainsString('ilike', $sql);
        $this->assertStringNotContainsString(' like ', $sql);
    }

    public function test_not_like_becomes_not_ilike_and_equals_untouched(): void
    {
        $sql = $this->builder()->from('t')->where('a', 'not like', '%x%')->where('b', '=', 'y')->toSql();
        $this->assertStringContainsString('not ilike', $sql);
        $this->assertStringContainsString('"b" = ?', $sql);
    }
}
