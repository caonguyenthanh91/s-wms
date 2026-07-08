<?php
// setup.php
// Small helper to download required frontend libs and FontAwesome webfonts for offline intranet use.
$baseDir = __DIR__ . '/assets/';
$libs = [
    'js/jquery.min.js' => 'https://code.jquery.com/jquery-3.7.1.min.js',
    'js/tailwindcss.js' => 'https://cdn.tailwindcss.com',
    'js/bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'js/chart.umd.js' => 'https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.js',
    'js/html5-qrcode.min.js' => 'https://unpkg.com/html5-qrcode',
    'js/qrcode.min.js' => 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
    'css/bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'css/all.min.css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];
// Common FontAwesome webfonts used by all.min.css
$fonts = [
    'fa-solid-900.woff2', 'fa-solid-900.ttf',
    'fa-regular-400.woff2', 'fa-regular-400.ttf',
    'fa-brands-400.woff2', 'fa-brands-400.ttf',
    'fa-v4compatibility.woff2', 'fa-v4compatibility.ttf'
];
if (!is_dir($baseDir . 'js')) mkdir($baseDir . 'js', 0777, true);
if (!is_dir($baseDir . 'css')) mkdir($baseDir . 'css', 0777, true);
if (!is_dir($baseDir . 'webfonts')) mkdir($baseDir . 'webfonts', 0777, true);

$context = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);

echo "<h3>Setup offline assets</h3>";

foreach ($libs as $path => $url) {
    $savePath = $baseDir . $path;
    echo "Downloading $url ... ";
    $content = @file_get_contents($url, false, $context);
    if ($content !== false) {
        file_put_contents($savePath, $content);
        echo "<span style='color:green'>OK</span><br>";
    } else {
        echo "<span style='color:red'>FAILED</span><br>";
    }
}

// Download fonts from CDNJS FontAwesome package
$faBase = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/';
foreach ($fonts as $f) {
    $url = $faBase . $f;
    $savePath = $baseDir . 'webfonts/' . $f;
    echo "Downloading webfont $f ... ";
    $content = @file_get_contents($url, false, $context);
    if ($content !== false) {
        file_put_contents($savePath, $content);
        echo "<span style='color:green'>OK</span><br>";
    } else {
        echo "<span style='color:red'>FAILED</span><br>";
    }
}

echo "<br><b>Done.</b> If icons still do not appear, check that <code>assets/webfonts</code> contains downloaded files and that <code>assets/css/all.min.css</code> references the same filenames.";
?>