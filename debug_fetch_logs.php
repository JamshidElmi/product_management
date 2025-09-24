<?php
// Temporary debug endpoint - DO NOT LEAVE ENABLED IN PRODUCTION
// Usage: https://your-site/debug_fetch_logs.php?token=devtoken123
// After use: delete this file immediately.

$SECRET = 'devtoken123'; // Temporary token - change or delete after use
$provided = $_GET['token'] ?? '';
if ($provided !== $SECRET) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden - invalid token\n";
    exit;
}

function tailFile($file, $lines = 200) {
    if (!is_readable($file)) return "[not readable or missing] $file\n";
    $f = fopen($file, 'r');
    if (!$f) return "[could not open] $file\n";
    $pos = -1;
    $line = '';
    $linesFound = [];
    $chunk = '';
    fseek($f, 0, SEEK_END);
    $file_size = ftell($f);
    $buffer = '';
    $readSize = 4096;
    $data = '';
    while (ftell($f) > 0 && substr_count($data, "\n") <= $lines) {
        $seek = max(0, ftell($f) - $readSize);
        $len = ftell($f) - $seek;
        fseek($f, $seek);
        $chunk = fread($f, $len) . $chunk;
        $data = $chunk;
        if ($seek == 0) break;
    }
    fclose($f);
    $linesArr = preg_split('/\r?\n/', $data);
    $tail = array_slice($linesArr, -$lines);
    return implode("\n", $tail) . "\n";
}

header('Content-Type: text/plain; charset=utf-8');
echo "*** Temporary debug log dump - remove debug_fetch_logs.php after use ***\n";
echo "Generated: " . date('c') . "\n\n";

$appLog = __DIR__ . '/logs/app_debug.log';
$phpErr = ini_get('error_log');

echo "--- APP LOG: $appLog (tail 200) ---\n";
echo tailFile($appLog, 200);
echo "\n--- PHP ERROR LOG: $phpErr (tail 200) ---\n";
echo tailFile($phpErr, 200);

echo "\n*** End of dump ***\n";

?>
