<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class DatabaseBackupService
{
    /** @param callable(string):void $write */
    public function export(PDO $pdo, callable $write): void
    {
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') throw new \RuntimeException('No database is selected.');

        $write("-- IASLab Projects Manager SQL backup\n");
        $write("-- Generated: " . date(DATE_ATOM) . "\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $objects = $this->objects($pdo, $database);
        foreach ($objects['tables'] as $table) {
            $quoted = $this->identifier($table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_ASSOC);
            $sql = $this->createStatement($create);
            $write('DROP TABLE IF EXISTS ' . $quoted . ";\n" . $sql . ";\n\n");
            $this->data($pdo, $table, $write);
        }

        foreach ($objects['views'] as $view) {
            $quoted = $this->identifier($view);
            $create = $pdo->query('SHOW CREATE VIEW ' . $quoted)->fetch(PDO::FETCH_ASSOC);
            $sql = $this->createStatement($create);
            $write('DROP VIEW IF EXISTS ' . $quoted . ";\n" . $sql . ";\n\n");
        }
        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** @return array{tables:list<string>,views:list<string>} */
    private function objects(PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare('SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema ORDER BY TABLE_TYPE,TABLE_NAME');
        $statement->execute(['schema' => $database]);
        $result = ['tables' => [], 'views' => []];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['TABLE_TYPE'] === 'VIEW' ? 'views' : 'tables';
            $result[$key][] = (string) $row['TABLE_NAME'];
        }
        return $result;
    }

    /** @param callable(string):void $write */
    private function data(PDO $pdo, string $table, callable $write): void
    {
        $quoted = $this->identifier($table);
        $statement = $pdo->query('SELECT * FROM ' . $quoted);
        $columns = null;
        $batch = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
                $write('INSERT INTO ' . $quoted . ' (' . implode(',', array_map($this->identifier(...), $columns)) . ") VALUES\n");
            }
            $batch[] = '(' . implode(',', array_map(fn(mixed $value): string => $this->value($pdo, $value), array_values($row))) . ')';
            if (count($batch) >= 100) {
                $write(implode(",\n", $batch) . ";\n");
                $batch = [];
                $columns = null;
            }
        }
        if ($batch !== []) $write(implode(",\n", $batch) . ";\n");
        if ($columns !== null || $batch !== []) $write("\n");
    }

    /** @param array<string,mixed>|false $row */
    private function createStatement(array|false $row): string
    {
        if (!is_array($row)) throw new \RuntimeException('Unable to read database schema.');
        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, 'Create ')) return (string) $value;
        }
        throw new \RuntimeException('Database did not return a CREATE statement.');
    }

    private function identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function value(PDO $pdo, mixed $value): string
    {
        if ($value === null) return 'NULL';
        return $pdo->quote((string) $value);
    }
}
