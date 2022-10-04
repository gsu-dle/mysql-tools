<?php

declare(strict_types=1);

namespace GAState\Tools\MySQL;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use stdClass;

class MySQL
{
    /**
     * @var mysqli $conn
     */
    private mysqli $conn;


    /**
     * @var int|null $lastPing
     */
    private ?int $lastPing = null;


    /**
     * @param string $hostname
     * @param string $username
     * @param string $password
     * @param string $database
     * @param int $port
     * @param bool $tls
     * @param string $key
     * @param string $certificate
     * @param string $cacert
     * @param mysqli|null $conn
     * 
     * @return void
     */
    public function __construct(
        private string $hostname,
        private string $username,
        private string $password,
        private string $database,
        private int $port,
        private bool $tls = FALSE,
        private string $key = '',
        private string $certificate = '',
        private string $cacert = '',
        mysqli|null $conn = null
    ) {
        if ($conn === null) {
            $conn = new mysqli();
        }
        $this->conn = $conn;
        $this->init(force: true);
    }


    /**
     * @return void
     */
    public function init(bool $force = false): void
    {
        if (!$force && !$this->ping()) return;

        if ($this->tls === true) {
            $this->conn->ssl_set(
                key: $this->key,
                certificate: $this->certificate,
                ca_certificate: $this->cacert,
                ca_path: null,
                cipher_algos: null
            );

            $flags = MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT; // Needed for self-signed certs, allows for MITM attacks
        } else {
            $flags = 0;
        }

        $this->conn->real_connect(
            hostname: $this->hostname,
            username: $this->username,
            password: $this->password,
            database: $this->database,
            port: $this->port,
            socket: null,
            flags: $flags
        );

        if (!$this->ping(force: true)) {
            throw new mysqli_sql_exception('Unable to connect to MySQL database', -1);
        }
    }


    /**
     * @return mysqli
     */
    private function conn(): mysqli
    {
        $this->init();
        return $this->conn;
    }


    /**
     * @param bool $force
     * 
     * @return bool
     */
    public function ping(bool $force = false): bool
    {
        try {
            $rightNow = time();

            if ($force === true || $this->lastPing === null || $this->lastPing + 15 < $rightNow) {
                $this->lastPing = ($this->conn->ping()) ? $rightNow : null;
            }
        } catch (mysqli_sql_exception $e) {
            $this->lastPing = null;
        }

        return $this->lastPing !== null;
    }


    /**
     * @param string $sqlString
     * 
     * @return string
     * 
     * @codeCoverageIgnore simple pass-through to mysqli
     */
    public function escapeString(string $sqlString): string
    {
        return $this->conn()->real_escape_string($sqlString);
    }


    /**
     * @return bool
     * 
     * @codeCoverageIgnore simple pass-through to mysqli
     */
    public function exists(string $query): bool
    {
        $result = $this->conn()->query($query);
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }


    /**
     * @return bool
     * 
     * @codeCoverageIgnore simple pass-through to mysqli
     */
    public function execute(string $query): bool
    {
        return $this->conn()->query($query) === true && $this->conn->errno === 0;
    }


    /**
     * @return bool
     * 
     * @codeCoverageIgnore simple pass-through to mysqli
     */
    public function multiExecute(string $query): bool
    {
        if ($this->conn()->multi_query($query) === true) {
            do {
                // loop goes spinny
            } while ($this->conn()->next_result());
        }
        return $this->conn->errno === 0;
    }


    /**
     * @return int
     * 
     * @codeCoverageIgnore
     */
    public function getAffectedRows(): int
    {
        return intval($this->conn()->affected_rows);
    }


    /**
     * @return stdClass|false
     */
    public function fetch(string $query): stdClass|false
    {
        $result = $this->conn()->query($query);
        /** @var stdClass|false */
        return ($result instanceof mysqli_result ? $result->fetch_object() : null) ?? false;
    }


    /**
     * @param string $query
     * @param callable $callback
     * 
     * @return array<int, int>
     */
    public function foreach(
        string $query,
        callable $callback
    ): array {
        $processed = $success = 0;
        $result = $this->conn()->query($query);
        if ($result !== false && $result instanceof mysqli_result) {
            while ($row = $result->fetch_object()) {
                $processed++;
                $success += ($callback($row) == true);
            }
            $result->close();
        }

        return [$processed, $success];
    }


    /**
     * @param string|array<string> $queries
     * @param string|array<string>|null $keyField
     * @param string|array<string>|null $keyValue
     * 
     * @return array<int|string, mixed>
     */
    public function fetchAll(
        string|array $queries,
        string|array|null $keyField = null,
        string|array|null $keyValue = null
    ): array {
        if (is_string($queries)) $queries = [$queries];
        if (is_string($keyField)) $keyField = [$keyField];

        $records = array();
        foreach ($queries as $query) {
            /** @var mysqli_result $result */
            $result = $this->conn()->query($query);

            switch (true) {
                case ($keyField === null) && ($keyValue === null):
                    while ($row = $result->fetch_object()) {
                        /** @var stdClass $row */
                        $records[] = $row;
                    }
                    break;

                case ($keyField === null) && is_string($keyValue):
                    /** @var string $keyValue */
                    while ($row = $result->fetch_assoc()) {
                        /** @var array<string,mixed> $row */
                        $records[] = $row[$keyValue] ?? null;
                    }
                    break;

                case ($keyField === null) && is_array($keyValue):
                    /** @var array<string> $keyValue */
                    while ($row = $result->fetch_assoc()) {
                        /** @var array<string,mixed> $row */

                        $record = new stdClass();
                        foreach ($keyValue as $v) {
                            $record->{$v} = $row[$v] ?? null;
                        }
                        $records[] = $record;
                    }
                    break;

                case ($keyField !== null) && ($keyValue === null):
                    while ($row = $result->fetch_object()) {
                        /** @var stdClass $row */
                        $keyFieldValue = "";
                        foreach ($keyField as $k) {
                            $keyFieldValue .= $row->{$k} ?? '';
                        }
                        $records[$keyFieldValue] = $row;
                    }
                    break;

                case ($keyField !== null) && is_string($keyValue):
                    /** @var string $keyValue */
                    while ($row = $result->fetch_assoc()) {
                        /** @var array<string,mixed> $row */

                        $keyFieldValue = "";
                        foreach ($keyField as $k) {
                            $keyFieldValue .= $row[$k] ?? '';
                        }
                        $records[$keyFieldValue] = $row[$keyValue] ?? null;
                    }
                    break;

                case ($keyField !== null) && is_array($keyValue):
                    /** @var array<string> $keyValue */
                    while ($row = $result->fetch_assoc()) {
                        /** @var array<string,mixed> $row */

                        $keyFieldValue = "";
                        foreach ($keyField as $k) {
                            $keyFieldValue .= $row[$k] ?? '';
                        }

                        $record = new stdClass();
                        foreach ($keyValue as $v) {
                            $record->{$v} = $row[$v] ?? null;
                        }

                        $records[$keyFieldValue] = $record;
                    }
                    break;
            }

            $result->close();
        }

        return $records;
    }
}
