<?php

declare(strict_types=1);

namespace GAState\Tools\MySQL\Tests;

use Exception;
use GAState\Tools\MySQL\MySQL;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use stdClass;

final class MySQLTest extends \PHPUnit\Framework\TestCase
{
    private mysqli $mysqli;
    private mysqli_result $mysqli_result;


    public function createMysqlMocks(bool $empty = false): void
    {
        $mysqli = $this->createStub(mysqli::class);
        $mysqli_result = $this->createStub(mysqli_result::class);

        /** @var \PHPUnit\Framework\MockObject\Stub $mysqli_result */
        if ($empty) {
            $mysqli_result->method('fetch_object')->willReturn(false);
        } else {
            $mysqli_result->method('fetch_assoc')->will(self::onConsecutiveCalls(
                ['id' => 'mmouse', 'first_name' => 'Mickey', 'last_name' => 'Mouse'],
                ['id' => 'dduck', 'first_name' => 'Donald', 'last_name' => 'Duck'],
                ['id' => 'mmouse', 'first_name' => 'Mickey', 'last_name' => 'Mouse'],
                ['id' => 'ggoof', 'first_name' => 'Goofy', 'last_name' => 'Goof']
            ));
            $mysqli_result->method('fetch_object')->will(self::onConsecutiveCalls(
                (object)['id' => 'mmouse', 'first_name' => 'Mickey', 'last_name' => 'Mouse'],
                (object)['id' => 'dduck', 'first_name' => 'Donald', 'last_name' => 'Duck'],
                (object)['id' => 'mmouse', 'first_name' => 'Mickey', 'last_name' => 'Mouse'],
                (object)['id' => 'ggoof', 'first_name' => 'Goofy', 'last_name' => 'Goof']
            ));
        }

        /** @var mysqli_result $mysqli_result */

        /** @var \PHPUnit\Framework\MockObject\Stub $mysqli */
        $mysqli->method('ssl_set')->willReturn(true);
        $mysqli->method('real_connect')->willReturn(true);
        $mysqli->method('ping')->willReturn(true);
        $mysqli->method('close')->willReturn(true);
        $mysqli->method('query')->willReturn($mysqli_result);
        /** @var mysqli $mysqli */

        $this->mysqli = $mysqli;
        $this->mysqli_result = $mysqli_result;
    }


    public function testConnectErrorNoMock(): void
    {
        $this->expectException(mysqli_sql_exception::class);

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0
        );
    }


    public function testConnectError(): void
    {
        $this->expectException(mysqli_sql_exception::class);

        $this->createMysqlMocks();

        /** @var \PHPUnit\Framework\MockObject\Stub $stub */
        $stub = $this->mysqli;
        $stub->method('ping')->willThrowException(new mysqli_sql_exception());

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            tls: true,
            conn: $this->mysqli
        );
    }


    public function testPingError(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var \PHPUnit\Framework\MockObject\Stub $stub */
        $stub = $this->mysqli;
        $stub->method('ping')->willThrowException(new mysqli_sql_exception());

        self::assertFalse($mysql->ping(true));
    }


    public function testPing(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        self::assertTrue($mysql->ping());
    }


    public function testEmptyForeach(): void
    {
        $this->createMysqlMocks(true);

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        list($processed, $success) = $mysql->foreach('', function (stdClass $record) {
            return $record->id === 'mmouse';
        });

        $this->mysqli_result->close();

        self::assertEquals($processed, 0);
        self::assertEquals($success, 0);
    }


    public function testForeach(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        list($processed, $success) = $mysql->foreach('', function (stdClass $record) {
            return $record->id === 'mmouse';
        });

        self::assertEquals($processed, 4);
        self::assertEquals($success, 2);
    }


    public function testEmptyFetch(): void
    {
        $this->createMysqlMocks(true);

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        $record = $mysql->fetch('');

        self::assertFalse($record);
    }


    public function testFetch(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        $record = $mysql->fetch('');

        self::assertIsObject($record);

        /** @var stdClass $record */
        self::assertEquals($record->id, 'mmouse');
    }


    public function testFetchAllNoKeyFieldNoKeyValue(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        $records = $mysql->fetchAll('');

        self::assertIsArray($records);
        self::assertEquals(count($records), 4);
    }


    public function testFetchAllNoKeyFieldKeyValueString(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,string> */
        $records = $mysql->fetchAll('',null,'id');

        self::assertIsArray($records);
        self::assertEquals(count($records), 4);

        foreach($records as $record_id) {
            if (!in_array($record_id, ['mmouse','dduck','ggoof'], true)) {
                self::fail();
            }
        }
    }


    public function testFetchAllNoKeyFieldKeyValueArray(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,stdClass> */
        $records = $mysql->fetchAll('',null,['id','first_name', 'lname']);

        self::assertIsArray($records);
        self::assertEquals(count($records), 4);

        foreach($records as $record) {
            self::assertIsString($record->id);
            self::assertIsString($record->first_name);
            self::assertNull($record->lname);
            self::assertObjectNotHasAttribute('last_name', $record);
        }
    }


    public function testFetchAllKeyFieldStringNoKeyValue(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,stdClass> */
        $records = $mysql->fetchAll('','id',null);

        self::assertIsArray($records);
        self::assertEquals(count($records), 3);

        foreach($records as $id => $record) {
            self::assertEquals($id, $record->id);
        }
    }


    public function testFetchAllKeyFieldStringKeyValueString(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,stdClass> */
        $records = $mysql->fetchAll('','id','id');

        self::assertIsArray($records);
        self::assertEquals(count($records), 3);

        foreach($records as $id => $record) {
            self::assertEquals($id, $record);
        }
    }


    public function testFetchAllKeyFieldStringKeyValueArray(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,stdClass> */
        $records = $mysql->fetchAll('','id',['id','first_name', 'lname']);

        self::assertIsArray($records);
        self::assertEquals(count($records), 3);

        foreach($records as $id => $record) {
            self::assertEquals($id, $record->id);
            self::assertIsString($record->id);
            self::assertIsString($record->first_name);
            self::assertNull($record->lname);
            self::assertObjectNotHasAttribute('last_name', $record);
        }
    }


    public function testFetchAllKeyFieldArrayNoKeyValue(): void
    {
        $this->createMysqlMocks();

        $mysql = new MySQL(
            hostname: '',
            username: '',
            password: '',
            database: '',
            port: 0,
            conn: $this->mysqli
        );

        /** @var array<int,stdClass> */
        $records = $mysql->fetchAll('',['id','first_name'],null);

        self::assertIsArray($records);
        self::assertEquals(count($records), 3);

        foreach($records as $id => $record) {
            self::assertEquals($id, $record->id . $record->first_name);
        }
    }
}
