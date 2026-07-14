<?php
foreach (['icon-192.png', 'icon-512.png'] as $f) {
    $p = __DIR__ . '/../assets/pwa/' . $f;
    if (!is_file($p)) {
        echo "$f: MISSING\n";
        continue;
    }
    [$w, $h] = getimagesize($p);
    echo "$f: {$w}x{$h}, " . filesize($p) . " bytes\n";
}
