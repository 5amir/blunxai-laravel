<?php

namespace Blunx\AI\Support\Database;

interface DatabaseDriverInterface
{
    /**
     * Get the list of tables in the database
     */
    public function getTables(string $database, array $excluded = []): array;

    /**
     * Get column information for a table
     */
    public function getColumnType(string $table, string $column, string $database): ?string;

    /**
     * Get foreign key relations for a table
     */
    public function getRelations(string $table, string $database): array;

    /**
     * Get the raw column type from information schema
     */
    public function getRawColumnType(string $table, string $column, string $database): ?string;
}