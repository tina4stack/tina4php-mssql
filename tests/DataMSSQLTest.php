<?php
/**
 * Tina4 - This is not a 4ramework.
 * Copy-right 2007 - current Tina4
 * License: MIT https://opensource.org/licenses/MIT
 */

use PHPUnit\Framework\TestCase;

require_once "./Tina4/DataMSSQL.php";

class DataMSSQLTest extends TestCase
{
    public $connectionString;
    public $DBA;

    final public function setUp(): void
    {
        $this->connectionString = "localhost/1433:master";
        $this->DBA = new \Tina4\DataMSSQL($this->connectionString, "sa", "Tina1234!");

    }

    final public function testOpen(): void
    {
        $this->assertNotEmpty($this->DBA);
    }

    final public function testTableExists() : void
    {
        $exists = $this->DBA->tableExists("spt_fallback_db");
        $this->assertIsBool($exists, "Not working");
        $exists = $this->DBA->tableExists("user_one");
        $this->assertEquals(false, $exists, "Test table exists");
    }

    final public function testDropCreateTable() : void
    {
        if ($this->DBA->tableExists("sub_testing")) {
            $error = $this->DBA->exec("drop table sub_testing");
        }

        if ($this->DBA->tableExists("testing")) {
            $error = $this->DBA->exec("drop table testing");
        }

        $this->DBA->commit();
        $error = $this->DBA->exec("create table testing(id integer default 0, name varchar(200) default 'Name', age integer default 22, salary numeric (10,2), my_date timestamp,  my_date_3 date, primary key(id))");
        $this->DBA->commit();
        $exists = $this->DBA->tableExists("testing");
        $error = $this->DBA->exec("create table sub_testing (id integer default 1 not null, testing_id integer default 0 not null references testing(id) on delete cascade, primary key(id))");
        $this->DBA->commit();
        $exists = $this->DBA->tableExists("sub_testing");
        $this->assertEquals(true, $exists, "Not working false table check");
    }

    final public function testRead(): void
    {
        $this->DBA->exec("insert into testing (id) values (?)", 1);
        $this->DBA->exec("insert into testing (id) values (2)");
        $records = $this->DBA->fetch("select * from testing")->asArray();
        $this->assertCount(2, $records, "Records were not 2");
    }

    final public function testGetDatabase(): void
    {
        $database = $this->DBA->getDatabase();
        $this->assertArrayHasKey("testing", $database);
        $this->assertArrayHasKey("sub_testing", $database);
    }
}
