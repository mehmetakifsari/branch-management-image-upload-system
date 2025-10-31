<?php
// public_html/pnl2/delete_file.php
// Deletes stored file and its thumb (if any) and updates uploads.files_json to remove the file entry.
// Admin only.

require_once __DIR__ . '/inc/auth.php';
require_admin();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/logger.php';

$file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
$return = isset($_GET['return']) ? trim((string)$_GET['return']) : 'panel/branches.php';
if ($file === '') {
    header('Location: ' . $return);
    exit;
}

$basename = basename($file);
$db = db_connect();

// Find uploads rows that reference this stored name in files_json
$stmt = $db->prepare("SELECT id, files_json FROM uploads");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $id = (int)$r['id'];
    $arr = json_decode($r['files_json'], true);
    if (!is_array($arr)) continue;
    $changed = false;
    foreach ($arr as $k => $f) {
        if (!isset($f['stored'])) continue;
        if ($f['stored'] === $basename) {
            // remove file(s) from disk: original and thumb
            // original: try to compute path relative to uploads/
            $origPath = null;
            if (!empty($f['path'])) {
                // convert public path to filesystem path: if asset('/uploads/...') used, path starts with /pnl2/uploads...
                // We assume uploads directory is __DIR__ . '/uploads' root; so find the segment 'uploads/' and build local path
                $u = $f['path'];
                $pos = strpos($u, '/uploads/');
                if ($pos !== false) {
                    $rel = substr($u, $pos + 1); // remove leading slash
                    $origPath = __DIR__ . '/' . $rel;
                } else {
                    // fallback: try uploads/* paths by searching filesystem
                    $possible = glob(__DIR__ . '/uploads/*/*/*/' . $basename);
                    if (!empty($possible)) $origPath = $possible[0];
                }
            } else {
                // fallback search
                $possible = glob(__DIR__ . '/uploads/*/*/*/' . $basename);
                if (!empty($possible)) $origPath = $possible[0];
            }
            if ($origPath && file_exists($origPath)) {
                @unlink($origPath);
            }
            // thumb removal: check 'thumb' field or compute thumb filename pattern
            if (!empty($f['thumb'])) {
                $thumbUrl = $f['thumb'];
                $pos2 = strpos($thumbUrl, '/uploads/');
                if ($pos2 !== false) {
                    $rel2 = substr($thumbUrl, $pos2 + 1);
                    $thumbFs = __DIR__ . '/' . $rel2;
                    if (file_exists($thumbFs)) @unlink($thumbFs);
                }
            } else {
                // attempt to find thumb with prefix thumb- in same directory
                // e.g. uploads/2025/ISEMRI/PLAKA/thumbs/thumb-base-<n>.jpg
                $parts = glob(__DIR__ . '/uploads/*/*/*/thumbs/*' . pathinfo($basename, PATHINFO_FILENAME) . '*');
                foreach ($parts as $p) { if (file_exists($p)) @unlink($p); }
            }

            // remove from array
            unset($arr[$k]);
            $changed = true;
        }
    }
    if ($changed) {
        // reindex and update DB
        $arr = array_values($arr);
        $u = $db->prepare("UPDATE uploads SET files_json = :fj WHERE id = :id");
        $u->execute([':fj' => json_encode($arr, JSON_UNESCAPED_UNICODE), ':id' => $id]);
        if (function_exists('log_action')) log_action('delete_file', ['file'=>$basename, 'upload_id'=>$id]);
    }
}

header('Location: ' . $return);
exit;
?>