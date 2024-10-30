<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

/**
 * Queries the MSSQL database and returns results
 */
class MSSQLQuery extends DataConnection implements DataBaseQuery
{

    /**
     * Runs a query against the database and returns a DataResult
     * @param $sql
     * @param int $noOfRecords
     * @param int $offSet
     * @param array $fieldMapping
     * @return DataResult|null
     */
    public function query($sql, int $noOfRecords = 10, int $offSet = 0, array $fieldMapping = []): ?DataResult
    {
        $initialSQL = $sql;

        //Remove all sub-queries that might confuse the checks
        $checkSql = preg_replace('/\(([^()]*+|(?R))*\)/', '', $sql);

        //calculate the order by
        //Don't add a limit if there is a limit already or if there is a stored procedure call
        if (stripos($checkSql, "offset") === false && stripos($checkSql, "@") === false && stripos($checkSql, "sp_") === false) {
            if (stripos($checkSql, "order by") !== false) {
                $sql .= " Offset {$offSet} rows fetch next {$noOfRecords} rows only";
                $initialSQL .= " Offset 0 rows";
            } else {
                $sql .= " order by 1 Offset {$offSet} rows fetch next {$noOfRecords} rows only";
            }
        }

        $recordCursor = \sqlsrv_query($this->getDbh(), $sql);
        $error = $this->getConnection()->error();

        $records = null;
        $fields = null;
        $resultCount = [];
        $resultCount["COUNT_RECORDS"] = 1;

        if ($error->getError()["errorCode"] === 0) {
            if (isset($recordCursor) && !empty($recordCursor))  {
                while ($record = \sqlsrv_fetch_array($recordCursor)) {
                    if (is_array($record)) {
                        $records[] = (new DataRecord($record,
                            $fieldMapping,
                            $this->getConnection()->getDefaultDatabaseDateFormat(),
                            $this->getConnection()->dateFormat)
                        );
                    }

                }

                if (is_array($records) && count($records) > 0) {
                    //Check to prevent second call of procedure
                    if (stripos($checkSql, "@") !== false || stripos($checkSql, "sp_") !== false) {
                        $resultCount["COUNT_RECORDS"] = count($records);
                    } else {
                        //Get position of last "order by"
                        $orderByPosition = strrpos(strtolower($initialSQL), 'order by');
                        //Separate by only the last "order by" if it exists - in case of nested queries
                        $filteredSQL = substr($initialSQL, 0, ($orderByPosition) ? $orderByPosition : strlen($initialSQL));

                        $sqlCount = "select count(*) as COUNT_RECORDS from ($filteredSQL) as tcount";

                        $recordCount = \sqlsrv_query($this->getDbh(), $sqlCount);

                        $resultCount = \sqlsrv_fetch_array($recordCount);

                        if (empty($resultCount)) {
                            $resultCount["COUNT_RECORDS"] = 0;
                        }
                    }
                } else {
                    $resultCount["COUNT_RECORDS"] = 0;
                }
            } else {
                $resultCount["COUNT_RECORDS"] = 0;
            }

            //populate the fields
            $fid = 0;
            $fields = [];

            if (!empty($records)) {
                //$record = $records[0];
                $fields = \sqlsrv_field_metadata($recordCursor);

                foreach ($fields as $fieldId => $fieldInfo) {
                    $fieldInfo = (array)json_decode(json_encode($fieldInfo));

                    $newField = (new DataField($fid, $fieldInfo["Name"], $fieldInfo["Name"], $fieldInfo["Type"], $fieldInfo["Size"]??0));
                    $newField->decimals = $fieldInfo["Precision"];
                    $newField->isNotNull = $fieldInfo["Nullable"] !== 0;
                    $fields[] = $newField;
                    $fid++;
                }
            }
        } else {
            $resultCount["COUNT_RECORDS"] = 0;
        }

        //Ensures the pointer is at the end in order to close the connection - Might be a buggy fix
        if (stripos($checkSql, "execute") !== false) {
            while (\sqlsrv_next_result($this->getDbh())) {
            }
        }

        return (new DataResult($records, $fields, $resultCount["COUNT_RECORDS"], $offSet, $error));
    }

}