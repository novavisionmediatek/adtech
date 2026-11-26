<?php
declare(strict_types=1);
/*
 * Backups. The database is copied to data/backups/ (never reachable from the web) - automatically once a day, and on demand.
 * Uploaded files are large, so they are not copied daily; the super admin downloads them as a ZIP.
 */
const BACKUP_DIR = DATA_DIR . '/backups';
const BACKUP_KEEP = ['auto' => 14, 'manual' => 10];
const BACKUP_NAME = '/^vidyasetu-\d{8}-\d{6}-(auto|manual)-[a-f0-9]{8}\.(sqlite|sql)\.gz$/';

function backup_dir(): string {
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0770, true);
    if (!is_file(BACKUP_DIR . '/.htaccess')) file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\n");
    return BACKUP_DIR;
}
/** Copies the whole database into a compressed file and returns its name. */
function backup_database(string $kind = 'manual'): string {
    @set_time_limit(0);
    $mysql = is_mysql();
    $name = 'vidyasetu-' . date('Ymd-His') . "-$kind-" . bin2hex(random_bytes(4)) . ($mysql ? '.sql.gz' : '.sqlite.gz');
    $path = backup_dir() . '/' . $name; $part = $path . '.part';
    $gz = gzopen($part, 'wb6'); if (!$gz) throw new RuntimeException('Cannot write into data/backups.');
    try {
        if ($mysql) backup_mysql_into($gz);
        else {
            $tmp = BACKUP_DIR . '/tmp-' . bin2hex(random_bytes(6)) . '.sqlite';
            db()->exec('VACUUM INTO ' . db()->quote($tmp)); // consistent copy even while the portal is in use
            $in = fopen($tmp, 'rb'); while (!feof($in)) gzwrite($gz, (string)fread($in, 1048576)); fclose($in); @unlink($tmp);
        }
    } catch (Throwable $t) { gzclose($gz); @unlink($part); throw $t; }
    gzclose($gz); rename($part, $path);
    $same = array_values(array_filter(backup_list(), fn($b) => $b['kind'] === $kind)); // newest first
    foreach (array_slice($same, BACKUP_KEEP[$kind]) as $old) @unlink(BACKUP_DIR . '/' . $old['name']);
    return $name;
}
function backup_mysql_into($gz): void {
    $pdo = db();
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    gzwrite($gz, "-- VidyaSetu backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
        $t = '`' . str_replace('`', '``', $table) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "\nDROP TABLE IF EXISTS $t;\n$create;\n");
        $rows = $pdo->query("SELECT * FROM $t"); $batch = [];
        $flush = function () use (&$batch, $gz, $t) { if ($batch) gzwrite($gz, "INSERT INTO $t VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; };
        while ($r = $rows->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ')';
            if (count($batch) >= 100) $flush();
        }
        $flush();
    }
    gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n"); $pdo->exec('COMMIT');
}
/** Existing backups, newest first. */
function backup_list(): array {
    $out = [];
    foreach (glob(BACKUP_DIR . '/vidyasetu-*.gz') ?: [] as $f) {
        $n = basename($f); if (!preg_match(BACKUP_NAME, $n, $m)) continue;
        $out[] = ['name' => $n, 'kind' => $m[1], 'size' => filesize($f), 'time' => filemtime($f)];
    }
    usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
    return $out;
}
/** One automatic backup per day, made after the first page of the day has been sent. */
function auto_backup(): void {
    if (!cfg('auto_backup', true)) return;
    $marker = DATA_DIR . '/backups/.last';
    if (is_file($marker) && trim((string)file_get_contents($marker)) >= date('Y-m-d')) return;
    register_shutdown_function(function () use ($marker) {
        $lock = @fopen(backup_dir() . '/.lock', 'c'); if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
        try {
            if (is_file($marker) && trim((string)file_get_contents($marker)) >= date('Y-m-d')) return;
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            file_put_contents($marker, date('Y-m-d')); // first, so a failing backup is not retried on every page
            backup_database('auto');
            @unlink(BACKUP_DIR . '/.error');
        } catch (Throwable $t) { @file_put_contents(BACKUP_DIR . '/.error', date('Y-m-d H:i') . ' ' . $t->getMessage()); }
        finally { flock($lock, LOCK_UN); }
    });
}
/** Streams a folder as a ZIP (files stored without compression, so memory use stays small). */
function stream_zip(string $dir, string $downloadName): never {
    @set_time_limit(0); while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $downloadName . '"'); header('Cache-Control: no-store');
    $central = ''; $offset = 0; $count = 0; $base = strlen(rtrim($dir, '/\\')) + 1;
    $files = is_dir($dir) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) : [];
    $add = function (string $name, int $crc, int $size, callable $body) use (&$central, &$offset, &$count) {
        $t = getdate(); $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | ($t['seconds'] >> 1); $date = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x800, 0, $time, $date, $crc, $size, $size, strlen($name), 0) . $name;
        echo $local; $body(); flush();
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x800, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local) + $size; $count++;
    };
    foreach ($files as $f) {
        if (!$f->isFile() || $offset + $f->getSize() > 4000000000) continue;
        $path = $f->getPathname(); $name = str_replace('\\', '/', substr($path, $base));
        $add($name, (int)hexdec((string)hash_file('crc32b', $path)), $f->getSize(), fn() => readfile($path));
    }
    if (!$count) { $note = "No uploaded files yet.\n"; $add('README.txt', crc32($note), strlen($note), function () use ($note) { echo $note; }); }
    echo $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
    exit;
}
function size_label(int $bytes): string { return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int)round($bytes / 1024)) . ' KB'; }
