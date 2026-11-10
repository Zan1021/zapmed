<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * PostgreSQL's LIKE is case-sensitive, whereas SQLite/MySQL (what the test suite and most
 * Laravel search code assume) are not. Every search box in the app uses ->where(col, 'like', ...),
 * so on Postgres "sema" would not find "Semaglutide" and "smith" would not find "Smith".
 * Rewrite plain LIKE / NOT LIKE to ILIKE / NOT ILIKE so search behaves identically everywhere.
 */
class CaseInsensitivePostgresGrammar extends PostgresGrammar
{
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower(trim((string) ($where['operator'] ?? '')));

        if ($operator === 'like') {
            $where['operator'] = 'ilike';
        } elseif ($operator === 'not like') {
            $where['operator'] = 'not ilike';
        }

        return parent::whereBasic($query, $where);
    }
}
