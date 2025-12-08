<?php
// /public_html/pnl2/inc/head.php
// Ortak <head> include: CSS, favicon ve chat widget asset'lerini ekler.

require_once __DIR__ . '/helpers.php';

// css (main)
$cssRel = '/assets/css/style.css';
$cssUrl = asset($cssRel);
$cssFilePath = asset_file_path($cssRel);
$ver = $cssFilePath ? filemtime($cssFilePath) : time();

// default favicon
$faviconUrl = asset('/assets/img/favicon.png');

// Try to read settings.favicon_path from DB if available
$dbCandidates = [
    __DIR__ . '/../database.php',
    __DIR__ . '/../../database.php',
];
foreach ($dbCandidates as $dbFile) {
    if (file_exists($dbFile)) {
        try {
            require_once $dbFile;
            if (function_exists('db_connect')) {
                try {
                    $db = db_connect();
                    $stmt = $db->query("SELECT favicon_path, logo_path, site_title FROM settings WHERE id = 1 LIMIT 1");
                    $s = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($s) {
                        if (!empty($s['favicon_path'])) {
                            // Use asset() to resolve relative or absolute paths
                            $faviconUrl = asset($s['favicon_path']);
                        }
                    }
                } catch (Throwable $e) {
                    // ignore DB errors, fallback to default favicon
                    error_log("inc/head.php settings read failed: " . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            // require failed - ignore
        }
        break;
    }
}

// Chat widget assets
$chatCssRel = '/assets/css/chat-widgets.css';
$chatJsRel  = '/assets/js/chat-widgets.js';

$chatCssUrl = asset($chatCssRel);
$chatCssFilePath = asset_file_path($chatCssRel);
$chatCssVer = $chatCssFilePath ? filemtime($chatCssFilePath) : time();

$chatJsUrl = asset($chatJsRel);
$chatJsFilePath = asset_file_path($chatJsRel);
$chatJsVer = $chatJsFilePath ? filemtime($chatJsFilePath) : time();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?php echo htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8'); ?>" type="image/png">
<link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl . '?v=' . $ver, ENT_QUOTES, 'UTF-8'); ?>">

<!-- Chat widget styles & script -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($chatCssUrl . '?v=' . $chatCssVer, ENT_QUOTES, 'UTF-8'); ?>">
<script defer src="<?php echo htmlspecialchars($chatJsUrl . '?v=' . $chatJsVer, ENT_QUOTES, 'UTF-8'); ?>"></script>