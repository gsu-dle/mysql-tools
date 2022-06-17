<?php

declare(strict_types=1);

namespace GAState\Tools\MySQL;

use Exception;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use stdClass;

class MySQL
{
    private mysqli $conn;
    private ?int $lastPing = null;

    public function __construct(
        private string $hostname,
        private string $username,
        private string $password,
        private string $database,
        private int $port,
        private bool $tls = FALSE,
        private string $key = '',
        private string $certificate = '',
        private string $cacert = ''
    ) {
        $this->init(force: true);
    }

    /**
     * @return void
     */
    public function init(bool $force = false): void
    {
        if ($force || !$this->ping()) {
            unset($this->conn);

            $conn = mysqli_init();

            if (!$conn instanceof mysqli) {
                throw new Exception(''); // TODO: add exception text
            }

            if ($this->tls === true) {
                $conn->ssl_set(
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

            $conn->real_connect(
                hostname: $this->hostname,
                username: $this->username,
                password: $this->password,
                database: $this->database,
                port: $this->port,
                socket: null,
                flags: $flags
            );

            $this->conn = $conn;

            if (!$this->ping(force: true)) {
                throw new mysqli_sql_exception('Unable to connect to MySQL database', -1);
            }
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
     * @return bool
     */
    public function ping(bool $force = false): bool
    {
        try {
            if (isset($this->conn)) {
                $rightNow = time();
                if ($force === true || $this->lastPing === null || $this->lastPing + 15 < $rightNow) {
                    $this->lastPing = ($this->conn->ping()) ? $rightNow : null;
                }
            } else {
                $this->lastPing = null;
            }
        } catch (mysqli_sql_exception $e) {
            $this->lastPing = null;
        }

        return $this->lastPing !== null;
    }

    /**
     * @return string
     */
    public function escapeString(string $string): string
    {
        return $this->conn()->real_escape_string($string);
    }

    /**
     * @return array<int, int>
     */
    public function foreach(string $query, callable $callback): array
    {
        $processed = $success = 0;
        $result = $this->conn()->query($query);
        if ($result !== false && $result instanceof mysqli_result) {
            while ($row = $result->fetch_object()) {
                $processed++;
                $success += !!$callback($row);
            }
        }

        return [$processed, $success];
    }

    /**
     * @return bool
     */
    public function exists(string $query): bool
    {
        $result = $this->conn()->query($query);
        return isset($result->num_rows) ? $result->num_rows !== 0 : false;
    }

    /**
     * @return bool
     */
    public function execute(string $query): bool
    {
        return $this->conn()->query($query) === true && $this->conn->errno === 0;
    }

    /**
     * @return bool
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
     */
    public function getAffectedRows(): int
    {
        return intval($this->conn()->affected_rows);
    }

    /**
     * @return object|null
     */
    public function fetch(string $query): object|null
    {
        $result = $this->conn()->query($query);
        if (!$result instanceof mysqli_result) {
            return null;
        }
        return $result->fetch_object();
    }

    /**
     * @param string|array<String> $queries blkah blah blah
     * @param string|array<String>|null $keyField
     * @param string|null $keyValue
     * 
     * @return array<int|string, stdClass|string>
     */
    public function fetchAll(
        string|array $queries,
        string|array|null $keyField = null,
        ?string $keyValue = null
    ): array {
        if (!is_array($queries)) $queries = [$queries];

        $records = array();
        foreach ($queries as $query) {
            $result = $this->conn()->query($query);
            if (!$result instanceof mysqli_result) {
                continue;
            }

            if ($keyValue !== null && $keyValue !== '') {
                if ($keyField !== null && (is_array($keyField) || $keyField !== '')) {
                    while ($row = $result->fetch_assoc()) {
                        if (is_array($keyField)) {
                            $keyFieldValue = "";
                            foreach ($keyField as $k) {
                                $keyFieldValue .= $row[$k];
                            }
                        } else {
                            $keyFieldValue = $row[$keyField];
                        }

                        $records[$keyFieldValue] = $row[$keyValue];
                    }
                } else {
                    while ($row = $result->fetch_assoc()) {
                        $records[] = $row[$keyValue];
                    }
                }
            } else {
                if ($keyField !== null && (is_array($keyField) || $keyField !== '')) {
                    while ($row = $result->fetch_assoc()) {
                        $record = new stdClass();
                        foreach ($row as $name => $value) {
                            $record->{$name} = $value;
                        }

                        if (is_array($keyField)) {
                            $keyFieldValue = "";
                            foreach ($keyField as $k) {
                                $keyFieldValue .= $record->{$k};
                            }
                        } else {
                            $keyFieldValue = $record->{$keyField};
                        }

                        $records[$keyFieldValue] = $record;
                    }
                } else {
                    while ($row = $result->fetch_assoc()) {
                        $record = new stdClass();
                        foreach ($row as $name => $value) {
                            $record->{$name} = $value;
                        }
                        $records[] = $record;
                    }
                }
            }

            $result->close();
        }

        return $records;
    }
}
