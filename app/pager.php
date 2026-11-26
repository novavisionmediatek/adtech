<?php
declare(strict_types=1);
/*
 * Server-side pagination for long lists. Query parameters: pg / pg2 = page number, pgs / pg2s = rows per page.
 *
 *   [$rows, $pager] = paginate('SELECT ... ORDER BY ...', $params, 20);
 *   ... show $rows ...
 *   echo $pager;            // sits under the list
 *
 * The SQL must not contain its own LIMIT, and its column names must be unique (it is counted as a sub-query).
 */
const PAGE_SIZES = [10, 20, 50, 100];

/** Returns [rows of the current page, pager html, total number of rows]. $choose = false hides the "per page" choice. */
function paginate(string $sql, array $params = [], int $per = 20, string $key = 'pg', bool $choose = true): array {
    $total = (int)val('SELECT COUNT(*) FROM (' . $sql . ') AS pg_count', $params);
    $size = (int)inp($key . 's', (string)$per);
    if (!$choose || ($size !== $per && !in_array($size, PAGE_SIZES, true))) $size = $per;
    $pages = max(1, (int)ceil($total / $size));
    $page = min($pages, max(1, (int)inp($key, '1')));
    $rows = $total ? q($sql . ' LIMIT ' . $size . ' OFFSET ' . (($page - 1) * $size), $params) : [];
    return [$rows, pager_html($total, $page, $size, $per, $key, $choose), $total];
}

/** Same as paginate() for rows that were assembled in PHP. Returns [rows of this page, pager html]. */
function paginate_array(array $all, int $per = 25, string $key = 'pg'): array {
    $total = count($all); $size = (int)inp($key . 's', (string)$per);
    if ($size !== $per && !in_array($size, PAGE_SIZES, true)) $size = $per;
    $page = min(max(1, (int)ceil($total / $size)), max(1, (int)inp($key, '1')));
    return [array_slice(array_values($all), ($page - 1) * $size, $size), pager_html($total, $page, $size, $per, $key, true)];
}

function pager_html(int $total, int $page, int $size, int $per, string $key, bool $choose): string {
    $smallest = $choose ? min($per, PAGE_SIZES[0]) : $per;
    if ($total <= $smallest) return ''; // nothing to page through
    $pages = max(1, (int)ceil($total / $size));
    $path = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $link = function (array $set) use ($path, $key): string {
        $query = array_merge($_GET, $set);
        foreach ($query as $k => $v) if ($v === null) unset($query[$k]);
        return e($path . ($query ? '?' . http_build_query($query) : '') . '#' . $key);
    };
    $item = fn(int $n, string $label, string $extra = '') => '<a href="' . $link([$key => $n > 1 ? $n : null]) . '"' . $extra . '>' . $label . '</a>';
    $from = ($page - 1) * $size + 1; $to = min($total, $page * $size);
    $o = '<nav class="pager no-print" id="' . e($key) . '" aria-label="Pagination"><span class="pg-info">Showing <b>' . $from . '&ndash;' . $to . '</b> of <b>' . $total . '</b></span>';
    if ($pages > 1) {
        $o .= '<span class="pg-pages">' . ($page > 1 ? $item($page - 1, '&lsaquo; Prev', ' rel="prev"') : '<span class="off">&lsaquo; Prev</span>');
        $last = 0;
        foreach (range(1, $pages) as $n) {
            if ($n !== 1 && $n !== $pages && abs($n - $page) > 1) continue; // first, last and neighbours only
            if ($n - $last > 1) $o .= '<span class="gap">&hellip;</span>';
            $o .= $item($n, (string)$n, $n === $page ? ' class="on" aria-current="page"' : ' class="num"'); $last = $n;
        }
        $o .= ($page < $pages ? $item($page + 1, 'Next &rsaquo;', ' rel="next"') : '<span class="off">Next &rsaquo;</span>') . '</span>';
    }
    if ($choose) {
        $options = array_unique(array_merge([$per], PAGE_SIZES)); sort($options);
        $o .= '<span class="pg-size">Per page:';
        foreach ($options as $s) $o .= ' <a href="' . $link([$key . 's' => $s === $per ? null : $s, $key => null]) . '"' . ($s === $size ? ' class="on"' : '') . '>' . $s . '</a>';
        $o .= '</span>';
    }
    return $o . '</nav>';
}
