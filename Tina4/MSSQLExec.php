<?php

namespace Tina4;

class MSSQLExec extends DataConnection implements DataBaseExec
{
    /**
     * Execute a MSSQL Query Statement which ordinarily does not retrieve results
     * @param $params
     * @param $tranId
     * @return DataResult|void|null
     */
    final public function exec($params, $tranId): void
    {
        if (!empty($tranId)) {
            $preparedQuery = \sqlsrv_prepare($this->getDbh(), $tranId, $params[0]);
        } else {
            $preparedQuery = \sqlsrv_prepare($this->getDbh(), $params[0]);
        }

        if (!empty($preparedQuery)) {
            $params[0] = $preparedQuery;
            \sqlsrv_execute(...$params);
        }
    }
}