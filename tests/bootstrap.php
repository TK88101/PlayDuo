<?php

declare(strict_types=1);

use PlayDuo\Tests\Support\TestDb;

require __DIR__ . '/../vendor/autoload.php';

// 毎回 playduo_test を schema.sql から作り直す（開発 DB playduo には触れない）
TestDb::createSchema();
