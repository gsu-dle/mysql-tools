<?php

declare(strict_types=1);

use GAState\Tools\MySQL\MySQL;

require __DIR__ . '/../vendor/autoload.php';

$mysql = new MySQL(
    hostname: '127.0.0.1',
    username: 'user',
    password: 'pass',
    database: 'myDatabase',
    port: 3306
);

var_dump($mysql->fetchAll('SELECT * FROM TestData', ['Id', 'Name'], 'Name'));
