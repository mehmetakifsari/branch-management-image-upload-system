<?php
// inc/logger.php
// Basit log fonksiyonu: veritabanına ekler ve istersen dosyaya da yazar.
// Put this at: /home/<cpanel_user>/public_html/pnl2/inc/logger.php

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Basit device tespiti
if (!function_exists('device_from_user_agent')) {
    function device_from_user_agent($ua) {
        $ua = strtolower((string)$ua);
        if (stripos($ua, 'mobile') !== false || stripos($ua, 'iphone') !== false || stripos($ua, 'android') !== false) {
            return 'Mobile';
        }
        if (stripos($ua, 'tablet') !== false || stripos($ua, 'ipad') !== false) {
            return 'Tablet';
        }
        return 'Desktop';
    }
}

function log_action($action, $details = null) {
    // Eğer database.php yoksa veya db_connect yoksa önce sadece file logla
    $logFile = __DIR__ . '/../logs/app_actions.log';
    $line = date('Y-m-d H:i:s') . " | ACTION: {$action}";

    $userId = null;
    $username = null;
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        $userId = $_SESSION['user']['id'] ?? null;
        $username = $_SESSION['user']['username'] ?? null;
        $line .= " | USER_ID: " . ($userId ?? '-') . " | USERNAME: " . ($username ?? '-');
    } else {
        $line .= " | USER: anonymous";
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $device = device_from_user_agent($ua);
    $line .= " | IP: {$ip} | DEVICE: {$device}";

    if (is_array($details) || is_object($details)) {
        $detailsStr = json_encode($details, JSON_UNESCAPED_UNICODE);
    } else {
        $detailsStr = (string)$details;
    }
    if ($detailsStr !== '') $line .= " | DETAILS: " . $detailsStr;

    // Ensure log dir exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

    // append to file (best effort)
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Try DB insert if DB available
    $dbFile = __DIR__ . '/database.php';
    // also check parent path - some includes call from panel/
    if (!file_exists($dbFile)) {
        $dbFile = __DIR__ . '/../database.php';
    }
    if (file_exists($dbFile)) {
        try {
            require_once $dbFile;
            if (function_exists('db_connect')) {
                $db = db_connect();
                $stmt = $db->prepare("INSERT INTO logs (user_id, username, action, details, ip, user_agent, device, created_at) VALUES (:uid, :uname, :action, :details, :ip, :ua, :device, NOW())");
                $stmt->execute([
                    ':uid' => $userId,
                    ':uname' => $username,
                    ':action' => $action,
                    ':details' => $detailsStr,
                    ':ip' => $ip,
                    ':ua' => $ua,
                    ':device' => $device
                ]);
                return true;
            }
        } catch (Throwable $e) {
            // DB hatası olursa file logta detay belirt
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " | log_action DB error: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
            return false;
        }
    }
    // Eğer DB yoksa da file'a yazdık, döndürelim
    return true;
}
?>