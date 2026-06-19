<?php
/** Shared blog add/edit form. Expects: $blog (assoc), $errors, $submit_label. */
if (!defined('ROOT')) { die('No direct access'); }
$cats = function_exists('get_exam_categories') ? get_exam_categories() : [];
$ev = fn($k) => htmlspecialchars((string)($blog[$k] ?? ''), ENT_QUOTES);
?>
<div class="admin-content">
<form method="post" enctype="multipart/form-data" id="blogForm">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <!-- Sticky top action bar (Save button on top, as requested) -->
  <div class="sticky-top bg-white border-bottom py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
       style="top:0;z-index:1020;box-shadow:0 2px 6px rgba(0,0,0,.04);">
    <h2 class="h5 mb-0"><i class="fas fa-pen-nib me-2 text-primary"></i><?= htmlspecialchars($admin_page_title) ?></h2>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#aiModal">
        <i class="fas fa-wand-magic-sparkles me-1"></i>Write with AI
      </button>
      <a href="/admin/blogs/list.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
      </a>
      <button type="submit" class="btn btn-primary btn-sm px-3">
        <i class="fas fa-save me-1"></i><?= htmlspecialchars($submit_label) ?>
      </button>
    </div>
  </div>

<div class="container-fluid pb-4">
  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Main column -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
          <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" id="blog-title" class="form-control form-control-lg"
                 value="<?= $ev('title') ?>" required placeholder="e.g. SSC CGL 2026 Preparation Strategy">
          <div class="form-text">Slug: <code id="slug-preview"></code></div>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span class="fw-semibold"><i class="fas fa-code me-2"></i>Content (HTML supported)</span>
          <div class="btn-group btn-group-sm" role="group" id="editorToolbar">
            <button type="button" class="btn btn-light" data-tag="h2" title="Heading 2">H2</button>
            <button type="button" class="btn btn-light" data-tag="h3" title="Heading 3">H3</button>
            <button type="button" class="btn btn-light" data-tag="p" title="Paragraph">¶</button>
            <button type="button" class="btn btn-light" data-tag="b" title="Bold"><b>B</b></button>
            <button type="button" class="btn btn-light" data-tag="i" title="Italic"><i>i</i></button>
            <button type="button" class="btn btn-light" data-tag="ul" title="Bulleted list"><i class="fas fa-list-ul"></i></button>
            <button type="button" class="btn btn-light" data-tag="ol" title="Numbered list"><i class="fas fa-list-ol"></i></button>
            <button type="button" class="btn btn-light" data-tag="blockquote" title="Quote"><i class="fas fa-quote-right"></i></button>
            <button type="button" class="btn btn-light" id="btnLink" title="Link"><i class="fas fa-link"></i></button>
            <button type="button" class="btn btn-light" id="btnImg" title="Image"><i class="fas fa-image"></i></button>
            <button type="button" class="btn btn-light" id="btnTable" title="Table"><i class="fas fa-table"></i></button>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-tabs mb-2" id="editorTabs">
            <li class="nav-item"><button type="button" class="nav-link active" data-pane="write">Write</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-pane="preview">Preview</button></li>
          </ul>
          <div id="pane-write">
            <textarea name="content" id="blog-content" class="form-control" rows="18"
                      style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.9rem;"
                      required placeholder="Write your article here. You can use HTML tags like <h2>, <p>, <ul>, <a>, <img>, <table>..."><?= $ev('content') ?></textarea>
            <div class="form-text">Tip: select text then click a toolbar button to wrap it. HTML is rendered as-is on the public blog page.</div>
          </div>
          <div id="pane-preview" class="d-none">
            <div class="prose border rounded p-3 bg-light" id="blog-preview" style="min-height:300px;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar column -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-gear me-2"></i>Publish</div>
        <div class="card-body">
          <label class="form-label fw-semibold">Status</label>
          <select name="is_published" class="form-select mb-3">
            <option value="0" <?= empty($blog['is_published']) ? 'selected' : '' ?>>Draft</option>
            <option value="1" <?= !empty($blog['is_published']) ? 'selected' : '' ?>>Published</option>
          </select>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-1"></i><?= htmlspecialchars($submit_label) ?>
          </button>
        </div>
      </div>

      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-folder me-2"></i>Organize</div>
        <div class="card-body">
          <label class="form-label fw-semibold">Category</label>
          <input type="text" name="category" class="form-control mb-3" list="catList"
                 value="<?= $ev('category') ?>" placeholder="e.g. Current Affairs">
          <datalist id="catList">
            <?php foreach ($cats as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
          </datalist>
          <label class="form-label fw-semibold">Tags</label>
          <input type="text" name="tags" class="form-control" value="<?= $ev('tags') ?>"
                 placeholder="tag1, tag2, tag3">
        </div>
      </div>

      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-image me-2"></i>Cover Image</div>
        <div class="card-body">
          <input type="file" name="cover_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($blog['featured_image'])): ?>
            <div class="mt-2"><img src="/uploads/blogs/<?= htmlspecialchars($blog['featured_image']) ?>"
                 class="img-fluid rounded border" style="max-height:120px;"></div>
          <?php endif; ?>
          <div class="form-text">JPG / PNG / WebP.</div>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-magnifying-glass me-2"></i>SEO</div>
        <div class="card-body">
          <label class="form-label fw-semibold">Meta Title</label>
          <input type="text" name="meta_title" id="meta-title" class="form-control" maxlength="70"
                 value="<?= htmlspecialchars($blog['meta_title'] ?? $blog['title'] ?? '', ENT_QUOTES) ?>">
          <div class="form-text"><span id="mt-count">0</span>/60 chars ideal</div>
          <label class="form-label fw-semibold mt-2">Meta Description</label>
          <textarea name="meta_description" id="meta-desc" class="form-control" rows="3" maxlength="320"><?= htmlspecialchars($blog['meta_description'] ?? '', ENT_QUOTES) ?></textarea>
          <div class="form-text"><span id="md-count">0</span>/160 chars ideal</div>
          <hr>
          <div class="small text-muted">Google preview</div>
          <div class="border rounded p-2 mt-1">
            <div class="text-primary text-truncate" id="g-title" style="font-size:1rem;">Title</div>
            <div class="text-success small" id="g-url">examduniya.in › blog</div>
            <div class="small text-muted" id="g-desc">Description</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
</div>

<!-- AI Write Modal -->
<div class="modal fade" id="aiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-wand-magic-sparkles me-2 text-primary"></i>Write Blog with AI</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Topic / Title</label>
          <input type="text" id="ai-topic" class="form-control" placeholder="e.g. How to prepare for SSC CGL 2026">
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Length (words)</label>
            <select id="ai-words" class="form-select">
              <option value="400">~400 (short)</option>
              <option value="700" selected>~700 (medium)</option>
              <option value="1100">~1100 (long)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Language</label>
            <select id="ai-language" class="form-select">
              <option>English</option>
              <option>Hindi</option>
              <option>Hinglish</option>
            </select>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Tone</label>
          <select id="ai-tone" class="form-select">
            <option>Informative</option>
            <option>Friendly &amp; simple</option>
            <option>Exam-prep guide</option>
            <option>News update</option>
          </select>
        </div>
        <div id="ai-msg" class="small mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ai-generate">
          <i class="fas fa-wand-magic-sparkles me-1"></i>Generate
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var ta = document.getElementById('blog-content');
  var preview = document.getElementById('blog-preview');
  var titleEl = document.getElementById('blog-title');
  var slugEl = document.getElementById('slug-preview');

  function updatePreview() { preview.innerHTML = ta.value; }
  function slugify(s){ return s.toLowerCase().trim().replace(/[^a-z0-9\s\-]/g,'').replace(/[\s\-]+/g,'-').replace(/^-+|-+$/g,''); }

  if (titleEl) {
    titleEl.addEventListener('input', function(){ if (slugEl) slugEl.textContent = slugify(this.value); updateGoogle(); });
    if (slugEl) slugEl.textContent = slugify(titleEl.value);
  }

  // Tabs
  document.querySelectorAll('#editorTabs [data-pane]').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('#editorTabs .nav-link').forEach(function(b){ b.classList.remove('active'); });
      this.classList.add('active');
      var write = this.getAttribute('data-pane') === 'write';
      document.getElementById('pane-write').classList.toggle('d-none', !write);
      document.getElementById('pane-preview').classList.toggle('d-none', write);
      if (!write) updatePreview();
    });
  });

  // Toolbar wrap/insert
  function wrap(open, close) {
    var s = ta.selectionStart, e = ta.selectionEnd;
    var sel = ta.value.substring(s, e) || '';
    var ins = open + sel + close;
    ta.value = ta.value.substring(0, s) + ins + ta.value.substring(e);
    ta.focus();
    ta.selectionStart = s + open.length;
    ta.selectionEnd = s + open.length + sel.length;
  }
  document.querySelectorAll('#editorToolbar [data-tag]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = this.getAttribute('data-tag');
      if (t === 'ul' || t === 'ol') { wrap('\n<'+t+'>\n  <li>', '</li>\n</'+t+'>\n'); }
      else { wrap('<'+t+'>', '</'+t+'>'); }
    });
  });
  document.getElementById('btnLink').addEventListener('click', function(){
    var url = prompt('Link URL:', 'https://'); if (url) wrap('<a href="'+url+'" target="_blank" rel="noopener">', '</a>');
  });
  document.getElementById('btnImg').addEventListener('click', function(){
    var url = prompt('Image URL:', 'https://'); if (url) { var alt = prompt('Alt text:', '') || ''; wrap('<img src="'+url+'" alt="'+alt+'" loading="lazy" style="max-width:100%;">', ''); }
  });
  document.getElementById('btnTable').addEventListener('click', function(){
    wrap('\n<table class="table table-bordered">\n  <thead><tr><th>Head 1</th><th>Head 2</th></tr></thead>\n  <tbody><tr><td>Cell</td><td>Cell</td></tr></tbody>\n</table>\n', '');
  });

  // SEO counters + google preview
  var mt = document.getElementById('meta-title'), md = document.getElementById('meta-desc');
  function updateGoogle(){
    var t = (mt && mt.value) ? mt.value : (titleEl ? titleEl.value : '');
    document.getElementById('g-title').textContent = t || 'Title';
    document.getElementById('g-desc').textContent = (md && md.value) ? md.value : 'Description preview will appear here.';
    document.getElementById('mt-count').textContent = (mt ? mt.value.length : 0);
    document.getElementById('md-count').textContent = (md ? md.value.length : 0);
  }
  if (mt) mt.addEventListener('input', updateGoogle);
  if (md) md.addEventListener('input', updateGoogle);
  updateGoogle();

  // AI generation
  var aiBtn = document.getElementById('ai-generate');
  aiBtn.addEventListener('click', function(){
    var msg = document.getElementById('ai-msg');
    var topic = document.getElementById('ai-topic').value.trim() || (titleEl ? titleEl.value.trim() : '');
    if (!topic) { msg.className='small mt-2 text-danger'; msg.textContent='Please enter a topic.'; return; }
    aiBtn.disabled = true;
    msg.className='small mt-2 text-muted';
    msg.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Generating… this can take 10–30s.';
    var fd = new FormData();
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fd.append('topic', topic);
    fd.append('words', document.getElementById('ai-words').value);
    fd.append('language', document.getElementById('ai-language').value);
    fd.append('tone', document.getElementById('ai-tone').value);
    fd.append('category', document.querySelector('[name=category]').value);
    fetch('/admin/ajax/generate-blog.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){
        aiBtn.disabled = false;
        if (d && d.success) {
          if (titleEl && !titleEl.value.trim() && d.title) { titleEl.value = d.title; if (slugEl) slugEl.textContent = slugify(d.title); }
          ta.value = d.content || ta.value;
          if (md && !md.value.trim() && d.meta_description) md.value = d.meta_description;
          if (mt && !mt.value.trim()) mt.value = (titleEl ? titleEl.value : (d.title||''));
          updatePreview(); updateGoogle();
          msg.className='small mt-2 text-success'; msg.textContent='Draft inserted! Review & edit before publishing.';
          var m = bootstrap.Modal.getInstance(document.getElementById('aiModal')); if (m) m.hide();
        } else {
          msg.className='small mt-2 text-danger'; msg.textContent = (d && d.error) ? d.error : 'AI request failed.';
        }
      })
      .catch(function(){ aiBtn.disabled=false; msg.className='small mt-2 text-danger'; msg.textContent='Network error.'; });
  });
})();
</script>
