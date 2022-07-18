<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

class MSSQLConnection
{
    /**
     * Database connection
     * @var false|resource
     */
    private $connection;
    /**
     * Creates a Firebird Database Connection
     * @param string $serverName
     * @param string $databaseName hostname, port
     * @param string $username database username
     * @param string $password password of the user
     */
    public function __construct(string $serverName, string $databaseName, string $username, string $password)
    {
        $connectionInfo = array( "Database"=> $databaseName, "UID"=> $username, "PWD"=> $password);
        $this->connection = \sqlsrv_connect($serverName, $connectionInfo);
    }

    /**
     * Returns a databse connection or false if failed
     * @return false|resource
     */
    final public function getConnection()
    {
        return $this->connection;
    }

}