<?php
// database.php
// config.php'den okur, db_connect() fonksiyonunu tanımlar.
// Proje kökünde veya panel dizinine göre include edilmelidir.

$configPathCandidates = [
    __DIR__ . '/config.php',        // proje kökü ile aynı dizin
    __DIR__ . '/../config.php',     // panel/ içinden include için üst dizin
    __DIR__ . '/../../config.php',  // alternatif
];

$config = null;
foreach ($configPathCandidates as $p) {
    if (file_exists($p)) {
        $cfg = include $p;
        if (is_array($cfg)) {
            $config = $cfg;
            break;
        }
    }
}

if (!is_array($config)) {
    // fallback: environment değişkenlerinden oku
    if (getenv('DB_HOST')) {
        $config = [
            'db_host'   => getenv('DB_HOST'),
            'db_port'   => getenv('DB_PORT') ?: null,
            'db_name'   => getenv('DB_NAME'),
            'db_user'   => getenv('DB_USER'),
            'db_pass'   => getenv('DB_PASS'),
            'db_socket' => getenv('DB_SOCKET') ?: null,
        ];
    } else {
        error_log('database.php: config.php bulunamadı ve environment değişkenleri yok.');
        die('Sunucu yapılandırma hatası. Sistem yöneticisine başvurun.');
    }
}

function db_connect() {
    global $config;
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = $config['db_host'] ?? '127.0.0.1';
    $db   = $config['db_name'] ?? '';
    $user = $config['db_user'] ?? '';
    $pass = $config['db_pass'] ?? '';
    $port = $config['db_port'] ?? null;
    $socket = $config['db_socket'] ?? null;
    $charset = 'utf8mb4';

    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $errors = [];

    // 1) Eğer socket belirtilmişse önce socket ile dene
    if (!empty($socket)) {
        $dsn = "mysql:unix_socket={$socket};dbname={$db};charset={$charset}";
        try {
            $pdo = new PDO($dsn, $user, $pass, $opts);
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = "socket failed: " . $e->getMessage();
            // devam et, diğer DSN'leri dene
        }
    }

    // 2) Host ile deneme (verilen host)
    $hostsToTry = [];
    $hostsToTry[] = $host;
    // Eğer host 'localhost' ise ek olarak 127.0.0.1 dene; tam tersi durumda da dene
    if ($host === 'localhost') {
        $hostsToTry[] = '127.0.0.1';
    } elseif ($host === '127.0.0.1') {
        $hostsToTry[] = 'localhost';
    }

    foreach ($hostsToTry as $h) {
        $dsn = "mysql:host={$h};dbname={$db};charset={$charset}";
        if (!empty($port)) $dsn .= ";port={$port}";
        try {
            $pdo = new PDO($dsn, $user, $pass, $opts);
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = "host {$h} failed: " . $e->getMessage();
            // diğer host'a geç
        }
    }

    // 3) Eğer hala başarısızsa hata logla ve istisna fırlat
    foreach ($errors as $err) {
        error_log("database.php: " . $err);
    }
    // Genel hata mesajı döndür (detayları loglarda var)
    throw new PDOException('Veritabanına bağlanılamadı. Detaylar sunucu loglarında.');
}