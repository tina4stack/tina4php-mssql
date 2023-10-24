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
        $connectionInfo = array( "Database"=> $databaseName, "UID"=> $username, "PWD"=> $password, "CharacterSet" => "UTF-8");
        // Environment specific setting to allow self-signed certificates, useful on local development
        if (isset($_ENV["DB_TRUSTSERVERCERTIFICATE"])) {
            $connectionInfo["TrustServerCertificate"] = $_ENV["DB_TRUSTSERVERCERTIFICATE"] ?? false;
        }
        $this->connection = \sqlsrv_connect($serverName, $connectionInfo);
        if (!$this->connection) {
            die( print_r( sqlsrv_errors(), true));
        }
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
