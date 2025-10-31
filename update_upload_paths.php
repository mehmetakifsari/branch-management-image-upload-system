<?php
// update_upload_paths.php
// Kullanım: FTP ile public_html/pnl2/ içine koy, tarayıcıda https://sirtkoyu.org/pnl2/update_upload_paths.php çalıştır
// İş bitince dosyayı silin.
// Bu script files_json içinde tutulan pathleri normalize eder (base_url öneki ekler).
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/database.php';

$db = db_connect();
$base = base_url(); // örn '/pnl2' veya ''

// Helper: normalize single file path which may be a full URL, root-relative or relative
function normalize_path_for_db($p) {
    // $p could be "/uploads/abc.jpg" or "uploads/abc.jpg" or "https://sirtkoyu.org/uploads/abc.jpg"
    if (!$p) return $p;
    if (preg_match('#^https?://#i', $p)) return $p; // leave full URLs
    // ensure leading slash
    if ($p[0] !== '/') $p = '/' . ltrim($p, '/');
    // if base_url present, prefix it if not already
    global $base;
    if ($base && strpos($p, $base . '/') !== 0) {
        return $base . $p;
    }
    return $p;
}

// Fetch all uploads
$stmt = $db->query("SELECT id, files_json FROM uploads");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updated = 0;
foreach ($rows as $r) {
    $id = $r['id'];
    $fj = $r['files_json'];
    $arr = json_decode($fj, true);
    if (!is_array($arr)) continue;
    $changed = false;
    foreach ($arr as $k => $file) {
        // files may be objects with path, stored, original, ext
        if (isset($file['path'])) {
            $old = $file['path'];
            $new = normalize_path_for_db($old);
            if ($new !== $old) {
                $arr[$k]['path'] = $new;
                // if stored does not include path, keep it as is
                $changed = true;
            }
        } elseif (isset($file['stored'])) {
            // Some older entries may have only stored name; we need to add path
            $stored = $file['stored'];
            // build path as /uploads/{stored} with base
            $new = normalize_path_for_db('/uploads/' . $stored);
            $arr[$k]['path'] = $new;
            $changed = true;
        }
    }
    if ($changed) {
        $newJson = json_encode($arr, JSON_UNESCAPED_UNICODE);
        $u = $db->prepare("UPDATE uploads SET files_json = :fj WHERE id = :id");
        $u->execute([':fj' => $newJson, ':id' => $id]);
        echo "Updated upload id={$id}\n";
        $updated++;
    }
}
echo "Done. Total updated: {$updated}\n";
?>