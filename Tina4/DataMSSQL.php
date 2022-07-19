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

    /**
     * @var null database metadata
     */
    private $databaseMetaData;

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

    /**
     * Executes a query
     * @return array|mixed|DataError|DataResult|null
     */
    public function exec()
    {
        $params = $this->parseParams(func_get_args());
        $params = $params["params"];

        (new MSSQLExec($this))->exec($params);

        return $this->error();
    }

    /**
     * Gets the last ID
     * @return string
     */
    public function getLastId(): string
    {
        // TODO: Implement getLastId() method. @justin
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        if (!empty($tableName)) {
            // table name must be in upper case
            $exists = $this->fetch("sp_tables @table_name=\"{$tableName}\"");
            return !empty($exists->records());
        }
    }

    /**
     * Fetch some records using SQL
     * @param $sql
     * @param int $noOfRecords
     * @param int $offSet
     * @param array $fieldMapping
     * @return DataResult|null
     */
    public function fetch($sql, int $noOfRecords = 10, int $offSet = 0, array $fieldMapping = []): ?DataResult
    {
        return (new MSSQLQuery($this))->query($sql, $noOfRecords, $offSet, $fieldMapping);
    }

    /**
     * Commits a transaction
     * @param $transactionId
     * @return mixed|void
     */
    public function commit($transactionId = null)
    {
        \sqlsrv_commit($this->dbh);
    }

    public function rollback($transactionId = null)
    {
        \sqlsrv_rollback($this->dbh);
    }

    public function autoCommit(bool $onState = true): void
    {
        //MSSQL rolls back it's state after a commit or rollback so nothing to do here
    }

    public function startTransaction()
    {
       return \sqlsrv_begin_transaction($this->dbh);
    }

    public function error()
    {
        $errorMessages = \sqlsrv_errors(SQLSRV_ERR_ALL);

        if ($errorMessages !== null) {
            return (new DataError(1, print_r ($errorMessages, 1)));
        } else {
            return (new DataError(0, "None"));
        }
    }

    public function getDatabase(): array
    {
        if (!empty($this->databaseMetaData)) {
            return $this->databaseMetaData;
        }

        $this->databaseMetaData = (new MSSQLMetaData($this))->getDatabaseMetaData();

        return $this->databaseMetaData;
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