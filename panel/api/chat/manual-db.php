<?php
// TEST ONLY - manual-db.php
// KULLANIM: yalnızca test amaçlı. İş bittikten sonra dosyayı silin.
// Put this file in panel/api/chat/manual-db.php

if (defined('MANUAL_DB_PHP_LOADED')) return;
define('MANUAL_DB_PHP_LOADED', true);

$pdo = null;
$conn = null;
$GLOBALS['MANUAL_DB_ERROR'] = null;

// --- MANUAL CREDENTIALS (DEĞİŞTİRİN) ---
$db_host = 'localhost';
$db_name = 'visupanel_p1';
$db_user = 'visupanel_p1';
$db_pass = 'o}QawGrmqzP7qH-U';
// -------------------------------------

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $GLOBALS['pdo'] = $pdo;
} catch (Exception $e) {
    // fallback to mysqli
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli && !$mysqli->connect_error) {
        $GLOBALS['conn'] = $mysqli;
    } else {
        $GLOBALS['MANUAL_DB_ERROR'] = $e->getMessage();
    }
}