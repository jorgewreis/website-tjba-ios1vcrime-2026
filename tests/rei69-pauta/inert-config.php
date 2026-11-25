<?php
declare(strict_types=1);
// Only satisfies transitive bootstrap imports. Any unexpected Conection use
// targets a closed loopback port inside a network=none container and fails.
const DB_HOST = '127.0.0.1';
const DB_PORT = 1;
const DB_NAME = 'inert_fixture_never_connect';
const DB_CHARSET = 'utf8mb4';
const DB_USER = 'inert';
const DB_PASS = '';
