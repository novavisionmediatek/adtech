<?php
$slug = explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[1] ?? '';
if ($slug !== '') {
    $p = row('SELECT * FROM posts WHERE slug=? AND published=1', [$slug]);
    if (!$p) render_error(404, 'Post not found', 'This article does not exist.');
    $body = post_body_html((string)$p['body']);
    $mins = max(1, (int)ceil(str_word_count(strip_tags($body)) / 200));
    $more = q('SELECT slug,title,category FROM posts WHERE published=1 AND id<>? ORDER BY created_at DESC, id DESC LIMIT 3', [$p['id']]);
    public_header($p['title'], $p['excerpt']);
    echo '<section class="page-hero"><div class="container"><span class="badge b-amber">' . e($p['category']) . '</span><h1>' . e($p['title']) . '</h1><p>' . e(fmt_date($p['created_at'])) . ' &middot; ' . $mins . ' min read</p></div></section>';
    echo '<section class="section"><div class="container prose">' . $body;
    echo '<hr><p><a class="btn" href="/blog">&larr; All articles</a></p></div></section>';
    if ($more) {
        echo '<section class="section alt"><div class="container">' . section_head('keep reading', 'More articles') . '<div class="grid g3" style="margin-top:30px">';
        foreach ($more as $m) echo '<article class="feature post-card"><span class="badge b-blue">' . e($m['category']) . '</span><h3 style="margin-top:10px"><a href="/blog/' . e($m['slug']) . '">' . e($m['title']) . '</a></h3><p class="small"><a href="/blog/' . e($m['slug']) . '">Read article &rarr;</a></p></article>';
        echo '</div></div></section>';
    }
    echo cta_band('Bring this to your school', 'See period-wise attendance and parent alerts in action.', 'Try the demo', '/login', 'Contact us', '/contact');
    public_footer(); return;
}
$cat = trim((string)($_GET['cat'] ?? ''));
$cats = q('SELECT category, COUNT(*) n FROM posts WHERE published=1 AND category<>\'\' GROUP BY category ORDER BY category');
[$posts, $pager] = paginate('SELECT * FROM posts WHERE published=1' . ($cat !== '' ? ' AND category=?' : '') . ' ORDER BY created_at DESC, id DESC', $cat !== '' ? [$cat] : [], 9, 'pg', false);
$feat = ($cat === '' && (int)inp('pg', '1') <= 1 && $posts) ? array_shift($posts) : null; // highlight the newest post on page 1
$icons = ['Attendance' => '&#9989;', 'Parents' => '&#128106;', 'Guides' => '&#128214;', 'Teaching' => '&#127891;', 'Security' => '&#128274;', 'Compliance' => '&#128220;'];
public_header('Blog', 'Articles on school management, attendance, parent communication and data protection.');
?>
<section class="page-hero"><div class="container"><h1>Blog</h1><p>Ideas for better school administration and parent engagement.</p></div></section>
<section class="section"><div class="container">
  <div class="chips"><a href="/blog"<?= $cat === '' ? ' class="on"' : '' ?>>All</a><?php foreach ($cats as $c) echo '<a href="/blog?cat=' . urlencode($c['category']) . '"' . ($cat === $c['category'] ? ' class="on"' : '') . '>' . e($c['category']) . ' (' . (int)$c['n'] . ')</a>'; ?></div>
  <?php if ($feat): ?>
  <article class="post-feature"><div class="art"><?= ico($icons[$feat['category']] ?? '&#9998;') ?></div><div class="txt"><span class="badge b-amber">Featured &middot; <?= e($feat['category']) ?></span>
    <h2><a href="/blog/<?= e($feat['slug']) ?>"><?= e($feat['title']) ?></a></h2><p><?= e($feat['excerpt']) ?></p><p class="small muted"><?= e(fmt_date($feat['created_at'])) ?> &middot; <a href="/blog/<?= e($feat['slug']) ?>"><b>Read article &rarr;</b></a></p></div></article>
  <?php endif; ?>
  <div class="grid g3">
  <?php foreach ($posts as $p): ?>
    <article class="feature post-card"><span class="badge b-blue"><?= e($p['category']) ?></span>
      <h3 style="margin-top:10px"><a href="/blog/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></h3>
      <p><?= e($p['excerpt']) ?></p><p class="small muted"><?= e(fmt_date($p['created_at'])) ?> &middot; <a href="/blog/<?= e($p['slug']) ?>">Read more</a></p></article>
  <?php endforeach; if (!$posts && !$feat) echo '<p class="muted">No articles yet.</p>'; ?>
  </div>
  <?= $pager ?>
</div></section>
<?= cta_band('Have a topic in mind?', 'Tell us what you would like to read about school management.', 'Suggest a topic', '/contact', 'Try the demo', '/login') ?>
<?php public_footer(); ?>
