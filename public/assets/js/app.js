// VidyaSetu - tiny progressive-enhancement script (no external libraries)
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
});
document.addEventListener('click', function (e) { if (e.target.closest('[data-print]')) window.print(); });
document.querySelectorAll('[data-markall]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var v = btn.getAttribute('data-markall');
    document.querySelectorAll('input[type=radio][value="' + v + '"]').forEach(function (r) { r.checked = true; });
    updateCounts();
  });
});
function updateCounts() {
  var out = document.getElementById('att-counts'); if (!out) return;
  var c = { P: 0, A: 0, L: 0 };
  document.querySelectorAll('input[type=radio]:checked').forEach(function (r) { if (c[r.value] !== undefined) c[r.value]++; });
  out.textContent = 'Present ' + c.P + ' | Absent ' + c.A + ' | Late ' + c.L;
}
document.addEventListener('change', function (e) { if (e.target.matches('input[type=radio]')) updateCounts(); });
updateCounts();
document.querySelectorAll('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
// lectures: add more link boxes, list chosen files
(function () {
  var add = document.getElementById('add-link'), box = document.getElementById('link-list');
  if (add && box) add.addEventListener('click', function () {
    var i = document.createElement('input'); i.type = 'url'; i.name = 'links[]'; i.placeholder = 'https://...'; i.style.marginTop = '8px';
    box.appendChild(i); i.focus();
  });
  var fbox = document.getElementById('file-box'), addf = document.getElementById('add-file'), out = document.getElementById('file-list');
  function summary() {
    if (!fbox || !out) return;
    var n = [];
    fbox.querySelectorAll('input[type=file]').forEach(function (fi) { [].forEach.call(fi.files, function (f) { n.push(f.name + ' (' + (f.size / 1048576).toFixed(1) + ' MB)'); }); });
    out.textContent = n.length ? n.length + ' file(s) selected: ' + n.join(', ') : '';
  }
  if (fbox) fbox.addEventListener('change', summary);
  if (fbox && addf) addf.addEventListener('click', function () {
    var i = fbox.querySelector('input[type=file]').cloneNode(); i.value = ''; i.style.marginTop = '8px';
    fbox.appendChild(i); i.click();
  });
})();

// Motion: header shadow, reveal on scroll, number count-up and the home-page "live board".
// Everything is visible without JavaScript, and all of it is skipped when the user prefers reduced motion.
(function () {
  var head = document.querySelector('.site-header');
  if (head) {
    var onScroll = function () { head.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) return;

  function each(sel, fn) { Array.prototype.forEach.call(document.querySelectorAll(sel), fn); }
  // Run fn once when the element reaches the screen. Elements that were jumped past (anchor, End key, fast scroll)
  // also count, so nothing can stay hidden.
  var waiting = [], queued = false;
  function check() {
    queued = false;
    var limit = window.innerHeight * 0.95;
    waiting = waiting.filter(function (w) {
      if (!w.el.getClientRects().length || w.el.getBoundingClientRect().top > limit) return true;
      w.fn(w.el); return false;
    });
  }
  function schedule() { if (!queued) { queued = true; window.setTimeout(check, 60); } }
  function whenSeen(el, fn) { waiting.push({ el: el, fn: fn }); schedule(); }
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
  window.addEventListener('load', schedule);

  // reveal blocks that start below the fold
  var blocks = '.section .sh, .facts .container>div, .versus>div, .bento>article, .roles-tabs, .dayplan>.dp-row, .bells>li, .qa>aside, .faq>details, .values>.value, ' +
    '.steps4>div, .role-card, .feature, .timeline>.tl-item, .split>*, .numbers>div, .compare, .trust>span, .cta-band .container, .post-feature';
  each(blocks, function (el) {
    var below = el.getBoundingClientRect().top > window.innerHeight * 0.92;
    if (below) {
      var index = Array.prototype.indexOf.call(el.parentNode.children, el);
      el.style.setProperty('--d', (Math.min(index, 6) * 70) + 'ms');
      el.classList.add('reveal');
    }
    whenSeen(el, function () {
      el.classList.add('seen');
      if (!below) return;
      el.classList.add('in');
      el.addEventListener('animationend', function done(ev) {
        if (ev.target !== el) return;
        el.classList.remove('reveal', 'in'); el.removeEventListener('animationend', done);
      });
    });
  });

  // numbers count up the first time they are seen
  each('[data-count], .stat-v, .numbers b', function (el) {
    var text = el.textContent.trim(), m = text.match(/^(\d[\d,]*(?:\.(\d+))?)(%?)$/);
    if (!m) return;
    var end = parseFloat(m[1].replace(/,/g, '')), places = m[2] ? m[2].length : 0;
    if (!end) return;
    whenSeen(el, function () {
      var start = null;
      function step(t) {
        if (start === null) start = t;
        var k = Math.min(1, (t - start) / 900), v = end * (1 - Math.pow(1 - k, 3));
        el.textContent = k < 1 ? v.toLocaleString('en-US', { minimumFractionDigits: places, maximumFractionDigits: places }) + m[3] : text;
        if (k < 1) window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    });
  });

  // home page: periods being marked, one after another (runs once)
  var board = document.querySelector('.board');
  if (board) whenSeen(board, function () {
    var count = document.getElementById('board-count');
    Array.prototype.forEach.call(board.querySelectorAll('.cell[data-next]'), function (cell, i) {
      window.setTimeout(function () {
        var note = cell.getAttribute('data-note'), absent = parseInt(note, 10);
        cell.classList.add('flip');
        window.setTimeout(function () {
          cell.classList.remove('live'); cell.classList.add(cell.getAttribute('data-next'));
          cell.querySelector('em').textContent = note;
          if (count && /absent/.test(note)) count.textContent = parseInt(count.textContent, 10) + absent;
        }, 270);
      }, 2800 + i * 1200);
    });
  });
})();

// "Set password" fields: show/hide, and tell the user before submitting if the two entries differ.
(function () {
  document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-showpw]')) return;
    e.target.form.querySelectorAll('[data-pw]').forEach(function (i) { i.type = e.target.checked ? 'text' : 'password'; });
  });
  document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-pw]')) return;
    e.target.form.querySelectorAll('[data-pw-confirm]').forEach(function (c) {
      var first = e.target.form.elements[c.getAttribute('data-pw-confirm')];
      c.setCustomValidity(first && first.value !== c.value ? 'The two passwords do not match.' : '');
    });
  });
})();

// Pagination: after changing page, bring the top of that list into view instead of the top of the site.
(function () {
  var pager = /^#pg\d?$/.test(location.hash) && document.getElementById(location.hash.slice(1));
  if (!pager) return;
  var box = pager.closest('.card') || pager.parentElement;
  window.requestAnimationFrame(function () { box.scrollIntoView({ block: 'start', behavior: 'auto' }); });
})();
