<?php
declare(strict_types=1);
/* Small line-icon set (24x24, stroke only). Templates pass the old HTML entity; ico() returns a matching inline SVG. */
function ico(string $entity): string {
    static $paths = [
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'school' => 'M3 10l9-6 9 6M5 10v10h14V10M10 20v-5h4v5',
        'grad' => 'M2 9l10-5 10 5-10 5zM6 11.5V16c3 3 9 3 12 0v-4.5M22 9v6',
        'video' => 'M3 7h12v10H3zM15 10.5l6-3.5v10l-6-3.5',
        'users' => 'M9 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.8a3.5 3.5 0 0 1 0 6.6M18 14.3c2 .9 3.5 3 3.5 5.7',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5',
        'briefcase' => 'M3 8h18v12H3zM9 8V5h6v3M3 13h18',
        'calendar' => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
        'chart' => 'M4 4v16h16M8 15l3.5-4.5 3 2.5L20 7',
        'book' => 'M4 5c3-1.5 6-1.5 8 0 2-1.5 5-1.5 8 0v14c-3-1.5-6-1.5-8 0-2-1.5-5-1.5-8 0zM12 5v14',
        'pen' => 'M4 20h4L19.5 8.5l-4-4L4 16zM13.5 6.5l4 4',
        'horn' => 'M3 10v4h4l9 5V5L7 10zM19 9c1.6 1.6 1.6 4.4 0 6',
        'mail' => 'M3 5h18v14H3zM3.5 6.5L12 13l8.5-6.5',
        'lock' => 'M5 11h14v10H5zM8 11V8a4 4 0 0 1 8 0v3',
        'check' => 'M4 4h16v16H4zM8 12.5l3 3 5.5-6.5',
        'crown' => 'M3 8l4.5 3.5L12 5l4.5 6.5L21 8l-2 11H5z',
        'building' => 'M4 21V5l8-2v18M12 8h8v13M2 21h20M7 8h2M7 12h2M7 16h2M15 12h2M15 16h2',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM16.5 16.5L21 21',
        'refresh' => 'M20 11a8 8 0 0 0-14.5-3.5L4 9M4 4v5h5M4 13a8 8 0 0 0 14.5 3.5L20 15M20 20v-5h-5',
        'heart' => 'M12 20s-8-4.8-8-10.8A4.3 4.3 0 0 1 12 6.6a4.3 4.3 0 0 1 8 2.6C20 15.2 12 20 12 20z',
        'home' => 'M3 11l9-7 9 7M5 10v10h14V10',
        'smile' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM8 14c1 1.5 2.4 2.2 4 2.2s3-.7 4-2.2M9 9.5h.01M15 9.5h.01',
        'wrench' => 'M14.5 4a5 5 0 0 0-5.600 6.600L3 16.5 7.5 21l5.9-5.9A5 5 0 0 0 20 9.5l-3.2 3.2-3.500-3.500L16.500 6z',
        'file' => 'M6 3h8l4 4v14H6zM14 3v5h4M9 13h6M9 17h6',
        'folder' => 'M3 6h6l2 2.5h10V19H3z',
        'key' => 'M8 19a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM11 12l9-9M16.500 6.500l3 3M14 9l2.500 2.500',
        'shield' => 'M12 3l8 3v6c0 5-3.500 8-8 9-4.500-1-8-4-8-9V6zM8.500 12l2.500 2.500 4.500-5',
        'download' => 'M12 4v11M7 11l5 5 5-5M5 20h14',
        'link' => 'M10 14a4 4 0 0 0 5.700 0l3-3A4 4 0 0 0 13 5.300l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1',
        'sun' => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3.5 2',
        'spark' => 'M11 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2zM19 16v5M16.500 18.500h5',
    ];
    static $map = [
        9776 => 'grid', 127979 => 'school', 127891 => 'grad', 127909 => 'video', 127916 => 'video', 128101 => 'users', 128106 => 'users', 128104 => 'user', 128105 => 'user',
        128188 => 'briefcase', 128197 => 'calendar', 128200 => 'chart', 128214 => 'book', 128218 => 'book', 128221 => 'pen', 9998 => 'pen', 128226 => 'horn', 128231 => 'mail', 9993 => 'mail',
        128274 => 'lock', 9989 => 'check', 128081 => 'crown', 127970 => 'building', 127963 => 'building', 128269 => 'search', 128260 => 'refresh', 129309 => 'heart', 127968 => 'home',
        127881 => 'smile', 128295 => 'wrench', 128196 => 'file', 128220 => 'file', 128193 => 'folder', 128273 => 'key', 128737 => 'shield', 11015 => 'download', 128279 => 'link', 10024 => 'spark', 9728 => 'sun', 128339 => 'clock',
    ];
    if (!preg_match('/&#(\d+);/', $entity, $m) || !isset($map[(int)$m[1]])) return $entity;
    return '<svg class="ic-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $paths[$map[(int)$m[1]]] . '"/></svg>';
}
