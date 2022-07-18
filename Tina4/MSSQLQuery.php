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

        //calculate the order by


        //Don't add a limit if there is a limit already or if there is a stored procedure call
        if (stripos($sql, "offset") === false && stripos($sql, "sp_") === false) {
            if (stripos($sql,"order by") !== false) {
                $sql .= " Offset {$offSet} rows fetch next {$noOfRecords} rows only";
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

                if (is_array($records) && count($records) > 0 && stripos($sql, "returning") === false) {
                    //Check to prevent second call of procedure
                    if (stripos($sql, "call") !== false) {
                        $resultCount["COUNT_RECORDS"] = count($records);
                    } else {
                        $sqlCount = "select count(*) as COUNT_RECORDS from ($initialSQL) tcount";

                        $recordCount = \sqlsrv_query($this->getDbh(), $sqlCount);

                        $resultCount = \sqlsrv_fetch_assoc($recordCount);

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

                    $fields[] = (new DataField($fid, $fieldInfo["name"], $fieldInfo["orgname"], $fieldInfo["type"], $fieldInfo["length"]));
                    $fid++;
                }
            }
        } else {
            $resultCount["COUNT_RECORDS"] = 0;
        }

        //Ensures the pointer is at the end in order to close the connection - Might be a buggy fix
        if (stripos($sql, "call") !== false) {
            while (mysqli_next_result($this->getDbh())) {
            }
        }

        return (new DataResult($records, $fields, $resultCount["COUNT_RECORDS"], $offSet, $error));
    }

}