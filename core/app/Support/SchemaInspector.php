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
