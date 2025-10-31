<?php
// /public_html/pnl2/inc/helpers.php
// Base URL, asset() ve path çözümleme yardımcıları.

$configPathCandidates = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];

$config = null;
foreach ($configPathCandidates as $p) {
    if (file_exists($p)) {
        $tmp = include $p;
        if (is_array($tmp)) { $config = $tmp; break; }
    }
}

// Normalize base_url ('' veya '/pnl2' gibi)
function base_url() {
    global $config;
    $b = $config['base_url'] ?? '';
    $b = trim((string)$b);
    if ($b === '') return '';
    if ($b[0] !== '/') $b = '/' . $b;
    return rtrim($b, '/');
}

// Döndürülecek public URL üretir.
// path parametresi şu şekillerde gelebilir:
// - Tam URL: https://...  -> olduğu gibi döner
// - Root-relative: /assets/img/logo.png  -> base_url + /assets/...
// - Relative: assets/img/logo.png  -> base_url + /assets/...
function asset($path) {
    $path = (string)$path;
    $path = trim($path);
    if ($path === '') return '';
    // Eğer tam URL ise olduğu gibi dön
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $b = base_url();
    // Eğer path root-relative ise: "/uploads/x.jpg"
    if ($path[0] === '/') {
        return ($b === '') ? $path : $b . $path;
    }
    // relative
    return ($b === '') ? '/' . ltrim($path, '/') : $b . '/' . ltrim($path, '/');
}

// Disk üzerinde dosya olup olmadığını kontrol eden yardımcı
function asset_file_path($relPath) {
    $relPath = ltrim((string)$relPath, '/');
    $candidates = [
        __DIR__ . '/../' . $relPath,
        __DIR__ . '/../../' . $relPath,
        __DIR__ . '/../../../' . $relPath,
    ];
    foreach ($candidates as $fp) {
        if (file_exists($fp)) return $fp;
    }
    return false;
}

// uploads dizini public path'ini döndür (public URL)
function uploads_asset($filename) {
    // filename örn: 'abc.jpg' veya 'uploads/abc.jpg'
    $filename = ltrim((string)$filename, '/');
    // Eğer filename zaten tam URL ise return
    if (preg_match('#^https?://#i', $filename)) return $filename;
    // Eğer kullanıcı verdiği 'uploads/xxx' ise asset() halleder
    return asset('uploads/' . $filename);
}
?>