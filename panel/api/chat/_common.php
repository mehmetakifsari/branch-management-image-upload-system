<?php
// (Optional) common helper file you can place in panel/api/chat/_common.php
// If you prefer not to use, just copy functions below into each API file.
// This file is not required but helpful.

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * get_session_user() - esnek session user algılama
 * returns ['user_id'=>int,'username'=>string] or null
 */
function get_session_user() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $idKeys = ['user_id','id','uid','userid','ID','admin_id','member_id'];
    $nameKeys = ['username','user_name','name','display_name','fullname','login','email'];

    // top-level
    foreach ($idKeys as $k) if (isset($_SESSION[$k]) && is_numeric($_SESSION[$k])) return ['user_id'=> (int)$_SESSION[$k], 'username'=> (string)($_SESSION[$k] ?? '')];
    foreach ($nameKeys as $k) if (isset($_SESSION[$k]) && is_string($_SESSION[$k]) && $_SESSION[$k] !== '') { /* continue to find id too */ }

    // nested user array
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $u = $_SESSION['user'];
        $uid = null; $uname = null;
        foreach ($idKeys as $k) if ($uid === null && isset($u[$k]) && is_numeric($u[$k])) $uid = (int)$u[$k];
        foreach ($nameKeys as $k) if ($uname === null && isset($u[$k]) && is_string($u[$k]) && $u[$k] !== '') $uname = $u[$k];
        // try alternatives in nested arrays/objects
        if ($uid === null) {
            // common: $u['id'] sometimes exists
            if (isset($u['id']) && is_numeric($u['id'])) $uid = (int)$u['id'];
            if (isset($u['ID']) && is_numeric($u['ID'])) $uid = (int)$u['ID'];
        }
        if ($uname === null) {
            if (isset($u['username'])) $uname = $u['username'];
            elseif (isset($u['name'])) $uname = $u['name'];
            elseif (isset($u['email'])) $uname = $u['email'];
        }
        if ($uid !== null && $uname !== null) return ['user_id'=>$uid,'username'=>$uname];
    }

    // fallback null
    return null;
}

/**
 * ensure_db_connection() - config.php'den DB bilgilerini tespit edip PDO veya mysqli döndürür
 * returns ['type'=>'pdo','pdo'=>$pdo] or ['type'=>'mysqli','conn'=>$conn] or throws Exception
 */
function ensure_db_connection() {
    global $pdo, $conn, $config;
    if (isset($pdo) && $pdo instanceof PDO) return ['type'=>'pdo','pdo'=>$pdo];
    if (isset($conn) && $conn instanceof mysqli) return ['type'=>'mysqli','conn'=>$conn];

    // include config and accept return array or $config variable
    $maybe = @include_once __DIR__ . '/../../../config.php';
    $cfg = null;
    if (is_array($maybe)) $cfg = $maybe;
    if (isset($config) && is_array($config)) $cfg = $config;

    $host = $cfg['db_host'] ?? null;
    $user = $cfg['db_user'] ?? null;
    $pass = $cfg['db_pass'] ?? null;
    $name = $cfg['db_name'] ?? null;

    if (empty($host) && isset($db_host)) $host = $db_host;
    if (empty($user) && isset($db_user)) $user = $db_user;
    if (empty($pass) && isset($db_pass)) $pass = $db_pass;
    if (empty($name) && isset($db_name)) $name = $db_name;

    if (empty($host) && defined('DB_HOST')) $host = DB_HOST;
    if (empty($user) && defined('DB_USER')) $user = DB_USER;
    if (empty($pass) && defined('DB_PASS')) $pass = DB_PASS;
    if (empty($name) && defined('DB_NAME')) $name = DB_NAME;

    if (!$host || !$user || !$name) throw new Exception('Database credentials not found in config.php');

    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        return ['type'=>'pdo','pdo'=>$pdo];
    } catch (Exception $e) {
        $conn = @new mysqli($host, $user, $pass, $name);
        if ($conn && !$conn->connect_error) return ['type'=>'mysqli','conn'=>$conn];
        throw $e;
    }
}