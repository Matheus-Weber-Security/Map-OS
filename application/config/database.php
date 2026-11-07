<?php

defined('BASEPATH') or exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

$db_ssl = (isset($_ENV['DB_SSL']) && (strtolower((string)$_ENV['DB_SSL']) === 'true' || $_ENV['DB_SSL'] === '1'));
$db_ssl_ca = $_ENV['DB_SSL_CA'] ?? null;
if ($db_ssl) {
    $encrypt_config = ['ssl_verify' => false];
    if (!empty($db_ssl_ca)) {
        $resolved_ca = file_exists($db_ssl_ca) ? $db_ssl_ca : FCPATH . $db_ssl_ca;
        if (file_exists($resolved_ca)) {
            $encrypt_config['ssl_ca'] = $resolved_ca;
            $encrypt_config['ssl_verify'] = true;
        }
    }
} else {
    $encrypt_config = false;
}

$db['default'] = [
    'dsn' => $_ENV['DB_DSN'] ?? '',
    'hostname' => $_ENV['DB_HOSTNAME'] ?? getenv('DB_HOSTNAME') ?: 'enter_hostname',
    'port' => (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
    'username' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'enter_db_username',
    'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'enter_db_password',
    'database' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'enter_database_name',
    'dbdriver' => $_ENV['DB_DRIVER'] ?? 'mysqli',
    'dbprefix' => $_ENV['DB_PREFIX'] ?? '',
    'pconnect' => filter_var($_ENV['DB_PCONNECT'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => $_ENV['DB_CHARSET'] ?? 'utf8',
    'dbcollat' => $_ENV['DB_COLLATION'] ?? 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt' => $encrypt_config,
    'compress' => false,
    'stricton' => false,
    'failover' => [],
    'save_queries' => true,
];
