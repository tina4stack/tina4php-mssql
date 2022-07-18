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

    public function open() : void
    {
        if (!function_exists("sqlsrv_connect")) {
            throw new \Exception("Microsoft Sequel Server extension for PHP needs to be installed");
        }

        $serverName = $this->hostName . ", " . $this->port;

        $this->dbh = (new MSSQLConnection(
            $serverName,
            $this->databaseName,
            $this->username,
            $this->password
        ))->getConnection();
    }

    public function close()
    {
        sqlsrv_close($this->dbh);
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
        //

        if (!empty($tableName)) {
            // table name must be in upper case
            $exists = $this->fetch("sp_tables @table_name=\"{$tableName}\"");

            return !empty($exists->records());
        }
    }

    public function fetch($sql, int $noOfRecords = 10, int $offSet = 0, array $fieldMapping = []): ?DataResult
    {
        return (new MSSQLQuery($this))->query($sql, $noOfRecords, $offSet, $fieldMapping);
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
        $errorMessages = \sqlsrv_errors(SQLSRV_ERR_ALL);

        if ($errorMessages !== null) {
            return (new DataError("Error", print_r ($errorMessages, 1)));
        } else {
            return (new DataError(0, "None"));
        }
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