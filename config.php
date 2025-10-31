<?php
// config.php
// Veritabanı ve proje base path ayarları.
// NOT: Güvenlik için mümkünse web root dışına taşı veya .htaccess ile erişimi engelle.

return [
    'db_host' => 'localhost',
    'db_name' => 'sirtkoyu_pnl',
    'db_user' => 'sirtkoyu_pnl',
    'db_pass' => 'UrouesQAqbOzgxa7',
    // Uygulamanın web dizin yolu (domain.com/pnl2 ise '/pnl2', kökse '')
    'base_url' => '/pnl2',
];