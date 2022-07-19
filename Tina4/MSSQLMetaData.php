<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

/**
 * Gets the metadata for the database
 */
class MSSQLMetaData extends DataConnection implements DataBaseMetaData
{

    public function getTables(): array
    {
        $sqlTables = "sp_tables @table_type=\"'TABLE'\"";
        $tables = $this->getConnection()->fetch($sqlTables, 1000, 0);

        if (!empty($tables)) {
            return $tables->asObject();
        }

        return [];
    }

    /**
     * @todo @justin fix
     * @param string $tableName
     * @return array
     */
    public function getPrimaryKeys(string $tableName): array
    {
        return [];
    }

    /**
     * @todo @justin fix
     * @param string $tableName
     * @return array
     */
    public function getForeignKeys(string $tableName): array
    {
        return [];
    }

    public function getTableInformation(string $tableName): array
    {
        $tableInformation = [];
        $sqlColumnInfo = "sp_columns '{$tableName}'";

        $columns = $this->getConnection()->fetch($sqlColumnInfo, 1000, 0)->AsObject();

        $primaryKeys = $this->getPrimaryKeys($tableName);
        $primaryKeyLookup = [];
        foreach ($primaryKeys as $primaryKey) {
            $primaryKeyLookup[$primaryKey->fieldName] = true;
        }

        $foreignKeys = $this->getForeignKeys($tableName);
        $foreignKeyLookup = [];
        foreach ($foreignKeys as $foreignKey) {
            $foreignKeyLookup[$foreignKey->fieldName] = true;
        }

        foreach ($columns as $columnIndex => $columnData) {

            $fieldData = new \Tina4\DataField(
                $columnIndex,
                trim($columnData->columnName),
                trim($columnData->columnName),
                trim($columnData->dataType),
                (int)trim($columnData->precision),
                (int)trim($columnData->length)
            );

            $fieldData->isNotNull = false;
            if ($columnData->isNullable === "NO") {
                $fieldData->isNotNull = true;
            }

            $fieldData->isPrimaryKey = false;
            if (isset($primaryKeyLookup[$fieldData->fieldName])) {
                $fieldData->isPrimaryKey = true;
            }

            $fieldData->isForeignKey = false;
            if (isset($foreignKeyLookup[$fieldData->fieldName])) {
                $fieldData->isForeignKey = true;
            }

            $fieldData->defaultValue = $columnData->columnDef;
            $tableInformation[] = $fieldData;
        }

        return $tableInformation;

    }

    public function getDatabaseMetaData(): array
    {
        $database = [];
        $tables = $this->getTables();

        foreach ($tables as $record) {
            $tableInfo = $this->getTableInformation($record->tableName);

            $database[strtolower($record->tableName)] = $tableInfo;
        }

        return $database;
    }
}