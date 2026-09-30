<?php
declare(strict_types=1);

/**
 * Shared database connection.
 *
 * Production: configure DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS
 * in the hosting environment. Local XAMPP defaults are used only when
 * environment variables are not present.
 */
function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('DB_PORT') ?: 3306);
    $name = getenv('DB_NAME') ?: 'sts';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $connection = new mysqli($host, $user, $pass, $name, $port);
    $connection->set_charset('utf8mb4');

    return $connection;
}
