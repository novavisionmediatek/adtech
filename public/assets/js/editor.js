// VidyaSetu - small WordPress-style visual editor (no external libraries).
// Turns <textarea data-editor> into: toolbar + visual area + "Code" (HTML) view. Output is sanitised again on the server.
(function () {
  var ta = document.querySelector('textarea[data-editor]');
  if (!ta) return;
  var form = ta.form, csrf = (form.querySelector('input[name=_csrf]') || {}).value || '';
  var code = false, saved = null;

  var wrap = el('div', 'wp-editor'), bar = el('div', 'wp-toolbar'), area = el('div', 'wp-area prose'), src = el('textarea', 'wp-source'), foot = el('div', 'wp-foot');
  area.contentEditable = 'true'; area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true'); area.innerHTML = ta.value;
  src.hidden = true; src.spellcheck = false;
  var count = el('span'), status = el('span', 'wp-status'); foot.appendChild(count); foot.appendChild(status);
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); document.execCommand('styleWithCSS', false, false); } catch (e) {}
  if (!area.innerHTML.trim()) area.innerHTML = '<p><br></p>';

  function el(t, c) { var n = document.createElement(t); if (c) n.className = c; return n; }
  function saveSel() { var s = window.getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); }
  function restoreSel() { area.focus(); if (saved) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(saved); } }
  function sync() { ta.value = code ? src.value : area.innerHTML; var t = (area.innerText || '').trim(); count.textContent = (t ? t.split(/\s+/).length : 0) + ' words'; }
  function run(cmd, val) { if (code) return; restoreSel(); document.execCommand(cmd, false, val || null); saveSel(); sync(); }
  function flashMsg(m) { status.textContent = m; if (m) setTimeout(function () { if (status.textContent === m) status.textContent = ''; }, 4000); }

  // --- toolbar ---------------------------------------------------------------
  var fmt = el('select', 'wp-select'); fmt.title = 'Paragraph format';
  [['p', 'Paragraph'], ['h1', 'Heading 1'], ['h2', 'Heading 2'], ['h3', 'Heading 3'], ['h4', 'Heading 4'], ['h5', 'Heading 5'], ['h6', 'Heading 6'], ['blockquote', 'Quote'], ['pre', 'Preformatted']].forEach(function (o) { var op = el('option'); op.value = o[0]; op.textContent = o[1]; fmt.appendChild(op); });
  fmt.addEventListener('change', function () { run('formatBlock', '<' + fmt.value + '>'); });
  bar.appendChild(fmt);

  var imgInput = el('input'); imgInput.type = 'file'; imgInput.accept = 'image/jpeg,image/png,image/gif,image/webp'; imgInput.hidden = true;
  imgInput.addEventListener('change', function () {
    var f = imgInput.files[0]; if (!f) return;
    var fd = new FormData(); fd.append('image', f); fd.append('_csrf', csrf); flashMsg('Uploading image...');
    fetch('/super/blogimage', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Upload failed'); return j; }); })
      .then(function (j) { restoreSel(); document.execCommand('insertImage', false, j.url); saveSel(); sync(); flashMsg('Image added.'); })
      .catch(function (e) { flashMsg(e.message); alert(e.message); })
      .then(function () { imgInput.value = ''; });
  });

  var colour = el('input'); colour.type = 'color'; colour.value = '#c0392b'; colour.hidden = true;
  colour.addEventListener('change', function () { run('foreColor', colour.value); });

  var btns = [
    ['B', 'Bold (Ctrl+B)', 'bold', 'b'], ['I', 'Italic (Ctrl+I)', 'italic', 'i'], ['U', 'Underline (Ctrl+U)', 'underline', 'u'], ['S', 'Strikethrough', 'strikeThrough', 's'], '|',
    ['• List', 'Bulleted list', 'insertUnorderedList'], ['1. List', 'Numbered list', 'insertOrderedList'], ['❝', 'Quote', function () { run('formatBlock', '<blockquote>'); }], '|',
    ['x₂', 'Subscript', 'subscript'], ['x²', 'Superscript', 'superscript'],
    ['A', 'Text colour', function () { saveSel(); colour.click(); }], '|',
    ['⬅', 'Align left', 'justifyLeft'], ['↔', 'Align centre', 'justifyCenter'], ['➡', 'Align right', 'justifyRight'], ['☰', 'Justify', 'justifyFull'], '|',
    ['🔗', 'Insert link', function () { saveSel(); var u = prompt('Link address (https://...)', 'https://'); if (u && /^(https?:\/\/|mailto:|\/)/i.test(u.trim())) run('createLink', u.trim()); }],
    ['Unlink', 'Remove link', 'unlink'],
    ['🖼 Image', 'Insert image (upload)', function () { saveSel(); imgInput.click(); }],
    ['―', 'Horizontal line', 'insertHorizontalRule'], '|',
    ['↶', 'Undo', 'undo'], ['↷', 'Redo', 'redo'], ['Tx', 'Clear formatting', function () { run('removeFormat'); run('formatBlock', '<p>'); }]
  ];
  btns.forEach(function (b) {
    if (b === '|') { bar.appendChild(el('span', 'wp-sep')); return; }
    var x = el('button', 'wp-btn'); x.type = 'button'; x.textContent = b[0]; x.title = b[1];
    if (b[3]) x.classList.add('f-' + b[3]);
    x.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the text selection
    x.addEventListener('click', function () { typeof b[2] === 'function' ? b[2]() : run(b[2]); });
    bar.appendChild(x);
  });
  var mode = el('button', 'wp-btn wp-mode'); mode.type = 'button'; mode.textContent = '</> Code'; mode.title = 'Switch between visual and HTML view';
  mode.addEventListener('click', function () {
    code = !code;
    if (code) { src.value = area.innerHTML.replace(/<\/(p|h[1-6]|ul|ol|li|blockquote|pre)>/g, '</$1>\n'); area.hidden = true; src.hidden = false; bar.classList.add('is-code'); mode.textContent = 'Visual'; }
    else { area.innerHTML = src.value; area.hidden = false; src.hidden = true; bar.classList.remove('is-code'); mode.textContent = '</> Code'; }
    sync();
  });
  var full = el('button', 'wp-btn'); full.type = 'button'; full.textContent = '⛶'; full.title = 'Fullscreen';
  full.addEventListener('click', function () { var on = wrap.classList.toggle('wp-full'); document.body.style.overflow = on ? 'hidden' : ''; full.title = on ? 'Exit fullscreen' : 'Fullscreen'; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && wrap.classList.contains('wp-full')) full.click(); });
  bar.appendChild(full); bar.appendChild(mode); bar.appendChild(imgInput); bar.appendChild(colour);

  // keep the format dropdown in step with the caret
  function updateFmt() {
    saveSel(); var n = window.getSelection().anchorNode; while (n && n !== area) { var t = (n.nodeName || '').toLowerCase(); if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre'].indexOf(t) > -1) { fmt.value = t; return; } n = n.parentNode; }
    fmt.value = 'p';
  }
  area.addEventListener('keyup', updateFmt); area.addEventListener('mouseup', updateFmt); area.addEventListener('input', sync); src.addEventListener('input', sync);
  area.addEventListener('blur', saveSel);

  ta.hidden = true; ta.removeAttribute('required');
  wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(src); wrap.appendChild(foot);
  ta.parentNode.insertBefore(wrap, ta); sync();

  form.addEventListener('submit', function (e) {
    sync(); var probe = el('div'); probe.innerHTML = ta.value;
    if (!(probe.textContent || '').trim() && !probe.querySelector('img')) { e.preventDefault(); alert('Please write some content for the post.'); }
  });
})();
