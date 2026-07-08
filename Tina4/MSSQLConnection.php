<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

namespace Tina4;

/**
 * MSSQLConnection
 * Establishes a connection to a Microsoft SQL Server database using sqlsrv.
 */
class MSSQLConnection
{
    /**
     * Database connection
     * @var false|resource
     */
    private $connection;

    /**
     * Creates a MSSQL Database Connection
     * @param string $serverName Server hostname
     * @param string $databaseName Database name
     * @param string $username Database username
     * @param string $password Password of the user
     * @throws \RuntimeException When connection fails
     */
    public function __construct(string $serverName, string $databaseName, string $username, string $password)
    {
        $connectionInfo = ["Database" => $databaseName, "UID" => $username, "PWD" => $password, "CharacterSet" => "UTF-8"];
        // Environment specific setting to allow self-signed certificates, useful on local development
        if (isset($_ENV["DB_TRUSTSERVERCERTIFICATE"])) {
            $connectionInfo["TrustServerCertificate"] = $_ENV["DB_TRUSTSERVERCERTIFICATE"] ?? false;
        }
        $this->connection = \sqlsrv_connect($serverName, $connectionInfo);
        if (!$this->connection) {
            $errors = \sqlsrv_errors();
            $message = is_array($errors) ? $errors[0]['message'] ?? 'Unknown error' : 'Unknown error';
            throw new \RuntimeException("MSSQL connection failed: {$message}");
        }
    }

    /**
     * Returns a database connection or false if failed
     * @return false|resource
     */
    final public function getConnection()
    {
        return $this->connection;
    }
}
