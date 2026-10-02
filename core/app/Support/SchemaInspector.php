<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2: existence-checked schema introspection for destructive DDL.
 *
 * MySQL DDL autocommits — an interrupted migration leaves half-applied
 * changes with no record in the `migrations` table. Every `dropIndex`,
 * `dropUnique`, `dropColumn` or `dropForeign` MUST be preceded by an
 * existence check from this class, so a replay lands as a clean no-op
 * instead of a SQLSTATE 1091.
 *
 * The migration-world sibling of NoBladeLeakTest's philosophy: kill the
 * class of bug, not the instance.
 */
class SchemaInspector
{
    /** Does the table have a unique index on the given column(s)? */
    public function hasUniqueIndex(string $table, array $columns): bool
    {
        foreach ($this->indexes($table) as $index) {
            if (! ($index['unique'] ?? false)) {
                continue;
            }

            if ($this->columnsMatch((array) $index['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    /** Does the table have an index with the given (logical or actual) name? */
    public function hasIndex(string $table, string $indexName): bool
    {
        foreach ($this->indexes($table) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    public function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * v1.7.5: does a FOREIGN KEY on this column still exist?
     *
     * Added when the (previously dormant) MigrationDropGuardTest was wired
     * into the suite and flagged two unguarded `dropForeign` calls in
     * `up()`. MySQL backs every FK with an index named
     * `<table>_<column>_foreign`; SQLite reports them through
     * `PRAGMA foreign_key_list`. Without this there is no honest way to
     * guard a dropForeign against the 1091 replay — and a token-level
     * workaround in the test would make the guard theatre.
     */
    public function hasForeignKey(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            foreach (DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$this->table($table), $column]
            ) as $row) {
                return true;
            }

            return false;
        }

        if ($driver === 'sqlite') {
            foreach (DB::select("PRAGMA foreign_key_list('{$this->table($table)}')") as $row) {
                if (strtolower((string) $row->from) === strtolower($column)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<array{name: string, unique: bool, columns: list<string>}> */
    public function indexes(string $table): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlIndexes($table),
            'sqlite' => $this->sqliteIndexes($table),
            default => [],
        };
    }

    /** @return list<array{name: string, unique: bool, columns: list<string>}> */
    private function mysqlIndexes(string $table): array
    {
        $rows = DB::select('SHOW INDEX FROM `'.$this->table($table).'`');

        $grouped = [];
        foreach ($rows as $row) {
            $name = $row->Key_name;
            $grouped[$name] ??= [
                'name' => $name,
                'unique' => ((int) $row->Non_unique) === 0,
                'columns' => [],
            ];
            $grouped[$name]['columns'][(int) $row->Seq_in_index] = $row->Column_name;
        }

        return array_map(function (array $index): array {
            ksort($index['columns']);

            return ['name' => $index['name'], 'unique' => $index['unique'], 'columns' => array_values($index['columns'])];
        }, array_values($grouped));
    }

    /** @return list<array{name: string, unique: bool, columns: list<string>}> */
    private function sqliteIndexes(string $table): array
    {
        $rows = DB::select("PRAGMA index_list('{$this->table($table)}')");

        $indexes = [];
        foreach ($rows as $row) {
            $name = $row->name;
            if (str_starts_with((string) $name, 'sqlite_autoindex_')) {
                continue; // implicit PK/constraint indexes cannot be dropped
            }

            $cols = DB::select("PRAGMA index_info('{$name}')");
            $indexes[] = [
                'name' => $name,
                'unique' => ((int) $row->unique) === 1,
                'columns' => array_map(fn ($c) => $c->name, $cols),
            ];
        }

        return $indexes;
    }

    /** Strip logical prefixes so callers can pass either form of the name. */
    private function table(string $table): string
    {
        $prefix = DB::connection()->getTablePrefix();

        return str_starts_with($table, $prefix) ? $table : $prefix.$table;
    }

    /** @param list<string> $a @param list<string> $b */
    private function columnsMatch(array $a, array $b): bool
    {
        $norm = fn (array $cols) => array_values(array_map(fn (string $c) => strtolower($c), $cols));

        return $norm($a) === $norm($b);
    }
}
