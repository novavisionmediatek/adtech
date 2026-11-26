<?php
declare(strict_types=1);
/*
 * Hindi interface. Pages are written in English; when Hindi is chosen, fixed interface text (menus, headings, buttons,
 * labels, table headings) is swapped using app/lang_hi.php. Only a COMPLETE piece of text that is in the dictionary is
 * replaced, so names, notices and other typed data stay as they are. Anything not in the dictionary stays English.
 */
function lang(): string { return ($_COOKIE['vs_lang'] ?? '') === 'hi' ? 'hi' : 'en'; }

/** Start translating the page being printed (called by the page headers, never for downloads). */
function i18n_start(): void {
    static $on = false;
    if ($on || lang() !== 'hi') return;
    $on = true; ob_start('translate_html');
}
function hi_lookup(string $text): ?string {
    static $d = null; $d ??= require APP . '/lang_hi.php';
    $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if ($plain === '') return null;
    // keep icons, arrows and a trailing "*" or ":" around the words that are looked up
    if (!preg_match('/^([^\p{L}\p{N}]*)(.*?)((?:\s*[\*:\?\x{2190}-\x{21ff}\x{2039}\x{203a}]|\s*\(\d+\))*)$/u', $plain, $m)) return null;
    [, $before, $core, $after] = $m;
    $hit = $d[$core] ?? null;
    if ($hit === null && $after !== '' && isset($d[$core . trim($after)])) { $hit = $d[$core . trim($after)]; $after = ''; } // e.g. "Forgot password?"
    return $hit === null ? null : htmlspecialchars($before . $hit . $after, ENT_QUOTES, 'UTF-8');
}
function translate_html(string $html): string {
    $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;
    $skip = 0; // inside tags whose text must stay as typed
    foreach ($parts as $i => &$part) {
        if ($i % 2) { // a tag
            if (preg_match('#^<(/?)(script|style|textarea|option|code|svg)\b#i', $part, $m)) { if ($m[1] === '') { if (!str_ends_with($part, '/>')) $skip++; } elseif ($skip) $skip--; continue; }
            if (preg_match('/\s(data-label|placeholder)="/', $part)) $part = preg_replace_callback('/\s(data-label|placeholder)="([^"]*)"/', fn($a) => ' ' . $a[1] . '="' . (hi_lookup($a[2]) ?? $a[2]) . '"', $part);
            if (str_starts_with($part, '<html lang="en"')) $part = '<html lang="hi">';
            continue;
        }
        if ($skip || trim($part) === '') continue;
        $hit = hi_lookup($part);
        if ($hit !== null) { preg_match('/^(\s*).*?(\s*)$/s', $part, $sp); $part = ($sp[1] ?? '') . $hit . ($sp[2] ?? ''); }
    }
    unset($part);
    return implode('', $parts);
}
/** Link that switches the language and comes back to the same page. */
function lang_switch_link(): string {
    $to = lang() === 'hi' ? 'en' : 'hi';
    return '<a class="lang-link" href="/lang?to=' . $to . '&amp;back=' . e(rawurlencode((string)($_SERVER['REQUEST_URI'] ?? '/'))) . '" hreflang="' . $to . '">' . ($to === 'hi' ? 'हिंदी' : 'English') . '</a>';
}
