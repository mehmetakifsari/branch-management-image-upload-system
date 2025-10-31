<?php
// public_html/pnl2/system_stats.php
// Returns JSON with system metrics: load, memory (if available), disk usage (total, free, percent)
// Used by dashboard sysbox. No HTML output.

header('Content-Type: application/json; charset=utf-8');

// Helper to get disk usage for a path
function get_disk_info($path = '/') {
    $res = [
        'path' => $path,
        'total' => null,
        'free' => null,
        'used' => null,
        'percent' => null
    ];
    if (function_exists('disk_total_space') && @disk_total_space($path) !== false) {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total > 0) {
            $used = $total - $free;
            $res['total'] = $total;
            $res['free'] = $free;
            $res['used'] = $used;
            $res['percent'] = round(($used / $total) * 100, 2);
        }
    }
    return $res;
}

// Get load average
$load = null;
if (function_exists('sys_getloadavg')) {
    $la = @sys_getloadavg();
    if (is_array($la)) $load = ['1'=>$la[0],'5'=>$la[1],'15'=>$la[2]];
}

// Memory info from /proc/meminfo (Linux)
$mem = null;
if (is_readable('/proc/meminfo')) {
    $data = @file_get_contents('/proc/meminfo');
    if ($data !== false) {
        $lines = preg_split('/\r?\n/', $data);
        $m = [];
        foreach ($lines as $ln) {
            if (preg_match('/^(\w+):\s+(\d+)/', $ln, $mm)) {
                $m[$mm[1]] = (int)$mm[2];
            }
        }
        if (!empty($m['MemTotal'])) {
            $totalKb = $m['MemTotal'];
            $freeKb = ($m['MemFree'] ?? 0) + ($m['Buffers'] ?? 0) + ($m['Cached'] ?? 0);
            $usedKb = $totalKb - $freeKb;
            $mem = [
                'total_kb' => $totalKb,
                'free_kb' => $freeKb,
                'used_kb' => $usedKb,
                'percent' => round(($usedKb / $totalKb) * 100, 2)
            ];
        }
    }
}

// Disk info for application root (use document root or script dir)
$rootPath = __DIR__;
// prefer public webroot parent to represent site disk usage, but fallback to '/'
$rootForDisk = dirname(realpath(__DIR__));
$diskRoot = get_disk_info($rootForDisk);
$diskSlash = get_disk_info('/');

// Compose response
$response = [
    'ok' => true,
    'timestamp' => date('c'),
    'load' => $load,
    'mem' => $mem,
    'disk' => [
        'root' => $diskRoot,
        'slash' => $diskSlash
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);