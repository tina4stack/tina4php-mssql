<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

class MSSQLExec extends DataConnection implements DataBaseExec
{
    /**
     * Execute a MSSQL Query Statement which ordinarily does not retrieve results
     * @param $params
     * @param null $tranId
     * @return DataResult|void|null
     */
    final public function exec($params, $tranId=null): void
    {
        $sql = $params[0];
        unset($params[0]);


        if (!empty($params)) {
            $preparedQuery = \sqlsrv_prepare($this->getDbh(), $sql, [...$params]);
        } else {
            $preparedQuery = \sqlsrv_prepare($this->getDbh(), $sql);
        }

        if (!empty($preparedQuery)) {

            \sqlsrv_execute($preparedQuery);
        }
    }
}