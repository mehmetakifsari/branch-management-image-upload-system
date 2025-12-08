<?php
// config.php
// Veritabanı ve proje base path ayarları.
// NOT: Güvenlik için mümkünse web root dışına taşı veya .htaccess ile erişimi engelle.

return [
    'db_host' => 'localhost',
    'db_name' => 'data_name',
    'db_user' => 'data_user',
    'db_pass' => 'data_pass',
    // Uygulamanın web dizin yolu (domain.com/pnl2 ise '/pnl2', kökse '')
    'base_url' => '/p1',
];
