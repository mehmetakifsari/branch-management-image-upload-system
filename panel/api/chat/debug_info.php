<?php
// panel/api/chat/debug_info.php  -- yükleyin, çalıştırın, sonra silin (debug amaçlı)
// Uyarı: production ortamda bu dosyayı kaldırın.

header('Content-Type: application/json; charset=utf-8');

$out = ['time'=>date('c'), 'server'=>[]];

// 1) config detection
$config_detect = null;
$maybe = @include_once __DIR__ . '/../../../config.php';
if (is_array($maybe)) $config_detect = $maybe;
if (isset($config) && is_array($config)) $config_detect = $config;

$out['config_returned'] = is_array($maybe);
$out['config_variable_exists'] = isset($config) && is_array($config);

// try to extract known keys
$keys = ['db_host','db_user','db_pass','db_name','db_name','db_pass','db_user','db_host'];
$found = [];
foreach (['db_host','db_user','db_pass','db_name'] as $k) {
    if (isset($config_detect[$k])) $found[$k] = $config_detect[$k];
    elseif (isset($$k)) $found[$k] = $$k;
    elseif (defined(strtoupper($k))) $found[$k] = constant(strtoupper($k));
    else $found[$k] = null;
}
// mask password
if ($found['db_pass']) $found['db_pass_masked'] = str_repeat('*', 6);

// 2) session keys
if (session_status() === PHP_SESSION_NONE) session_start();
$out['session_keys'] = array_keys($_SESSION ?? []);
$out['session_sample'] = [];
foreach ($out['session_keys'] as $k) {
    // show only keys and short value hints
    $v = $_SESSION[$k];
    if (is_string($v) && strlen($v) < 40) $out['session_sample'][$k] = $v;
    else $out['session_sample'][$k] = gettype($v);
}

// 3) DB connect test (don't expose password in output)
$dbtest = ['ok'=>false,'error'=>''];
try {
    $host = $found['db_host'] ?? null;
    $user = $found['db_user'] ?? null;
    $pass = isset($found['db_pass']) ? $found['db_pass'] : null;
    $name = $found['db_name'] ?? null;
    if (!$host || !$user || !$name) throw new Exception('missing credentials in detected config');
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdoTest = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    // simple query
    $r = $pdoTest->query("SELECT 1 AS ok")->fetch(PDO::FETCH_ASSOC);
    if ($r && isset($r['ok'])) $dbtest['ok'] = true;
} catch (Exception $e) {
    $dbtest['error'] = $e->getMessage();
}

$out['config_found'] = $found;
$out['db_test'] = $dbtest;

echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);