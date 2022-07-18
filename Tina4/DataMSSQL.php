<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

class DataMSSQL implements Database
{
    use DataBaseCore;

    public function open()
    {

    }

    public function close()
    {
        // TODO: Implement close() method.
    }

    public function exec()
    {
        // TODO: Implement exec() method.
    }

    public function getLastId(): string
    {
        // TODO: Implement getLastId() method.
    }

    public function tableExists(string $tableName): bool
    {
        // TODO: Implement tableExists() method.
    }

    public function fetch($sql, int $noOfRecords = 10, int $offSet = 0, array $fieldMapping = []): ?DataResult
    {
        // TODO: Implement fetch() method.
    }

    public function commit($transactionId = null)
    {
        // TODO: Implement commit() method.
    }

    public function rollback($transactionId = null)
    {
        // TODO: Implement rollback() method.
    }

    public function autoCommit(bool $onState = true): void
    {
        // TODO: Implement autoCommit() method.
    }

    public function startTransaction()
    {
        // TODO: Implement startTransaction() method.
    }

    public function error()
    {
        // TODO: Implement error() method.
    }

    public function getDatabase(): array
    {
        // TODO: Implement getDatabase() method.
    }

    public function getDefaultDatabaseDateFormat(): string
    {
        return "Y-m-d";
    }

    public function getDefaultDatabasePort(): ?int
    {
        return 1433;
    }

    public function getQueryParam(string $fieldName, int $fieldIndex): string
    {
        return "?";
    }

    public function isNoSQL(): bool
    {
        return false;
    }

    public function getShortName(): string
    {
        return "mssql";
    }
}