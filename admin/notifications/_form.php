<?php
/** Shared notification add/edit form. Expects:
 *  $n (assoc data), $errors[], $warnings[], $submit_label, $is_edit (bool),
 *  $slug_current (string). */
if (!defined('ROOT')) { die('No direct access'); }
$nv = fn($k) => htmlspecialchars((string)($n[$k] ?? ''), ENT_QUOTES);
$cats = function_exists('get_exam_categories') ? get_exam_categories() : [];
$post_types = ['notification'=>'Notification','admit_card'=>'Admit Card','result'=>'Result','answer_key'=>'Answer Key','syllabus'=>'Syllabus'];
$last_date_val = $n['application_last_date'] ?? $n['last_date_apply'] ?? '';
$lv = !empty($n['last_verified_at']) ? date('Y-m-d', strtotime($n['last_verified_at'])) : '';
?>
<div class="admin-content">
<form method="post" enctype="multipart/form-data" id="notif-form">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <!-- Sticky top action bar (Save on top) -->
  <div class="sticky-top bg-white py-2 px-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
       style="top:0;z-index:1020;box-shadow:0 2px 6px rgba(0,0,0,.04);">
    <h2 class="h5 mb-0"><i class="fas fa-bell me-2 text-primary"></i><?= htmlspecialchars($admin_page_title) ?></h2>
    <div class="d-flex gap-2">
      <a href="/admin/notifications/list.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
      <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-save me-1"></i><?= htmlspecialchars($submit_label) ?></button>
    </div>
  </div>

<div class="container-fluid pb-4">
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if (!empty($warnings)): ?>
    <div class="alert alert-warning"><strong><i class="fas fa-triangle-exclamation me-1"></i>Please review:</strong>
      <ul class="mb-0"><?php foreach ($warnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Basic -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-circle-info me-2"></i>Basic Details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
              <input type="text" name="title" id="notif-title" class="form-control form-control-lg" value="<?= $nv('title') ?>" required>
              <div class="form-text">Slug: <code id="slug-preview"><?= htmlspecialchars($slug_current ?? '') ?></code></div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <input type="text" name="category" id="notif-category" class="form-control" required list="category-list"
                     value="<?= $nv('category') ?>" placeholder="e.g. UP Police, UPSSSC, SSC">
              <datalist id="category-list">
                <?php foreach ($cats as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"></option><?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Post Type <span class="text-danger">*</span></label>
              <select name="post_type" class="form-select">
                <?php foreach ($post_types as $val=>$lbl): ?>
                  <option value="<?= $val ?>" <?= ($n['post_type'] ?? 'notification')===$val?'selected':'' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Organization Name <span class="text-danger">*</span></label>
              <input type="text" name="organization_name" class="form-control" value="<?= $nv('organization_name') ?>"
                     placeholder="e.g. Staff Selection Commission">
            </div>
            <div class="col-md-6">
              <label class="form-label">Conducting Body (short)</label>
              <input type="text" name="conducting_body" class="form-control" value="<?= $nv('conducting_body') ?>" placeholder="e.g. SSC, UPSC, RRB">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Short Description</label>
              <textarea name="short_desc" class="form-control" rows="2" maxlength="300"><?= $nv('short_desc') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Content with HTML editor -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="fw-semibold"><i class="fas fa-code me-2"></i>Full Content (HTML supported)</span>
          <div class="btn-group btn-group-sm" id="nf-toolbar">
            <button type="button" class="btn btn-light" data-tag="h2">H2</button>
            <button type="button" class="btn btn-light" data-tag="h3">H3</button>
            <button type="button" class="btn btn-light" data-tag="p">¶</button>
            <button type="button" class="btn btn-light" data-tag="b"><b>B</b></button>
            <button type="button" class="btn btn-light" data-tag="i"><i>i</i></button>
            <button type="button" class="btn btn-light" data-tag="ul"><i class="fas fa-list-ul"></i></button>
            <button type="button" class="btn btn-light" data-tag="ol"><i class="fas fa-list-ol"></i></button>
            <button type="button" class="btn btn-light" id="nf-link"><i class="fas fa-link"></i></button>
            <button type="button" class="btn btn-light" id="nf-table"><i class="fas fa-table"></i></button>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-tabs mb-2" id="nf-tabs">
            <li class="nav-item"><button type="button" class="nav-link active" data-pane="write">Write</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-pane="preview">Preview</button></li>
          </ul>
          <div id="nf-write">
            <textarea name="full_content" id="notif-content" class="form-control" rows="12"
                      style="font-family:ui-monospace,Menlo,monospace;font-size:.9rem;"><?= $nv('full_content') ?></textarea>
          </div>
          <div id="nf-preview" class="d-none"><div class="prose border rounded p-3 bg-light" id="notif-prose" style="min-height:250px;"></div></div>
        </div>
      </div>

      <!-- Details -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-list me-2"></i>Eligibility &amp; Fees</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Vacancies</label>
              <input type="number" name="vacancies" class="form-control" min="0" value="<?= $nv('vacancies') ?>"></div>
            <div class="col-md-6"><label class="form-label">Age Limit</label>
              <input type="text" name="age_limit" class="form-control" value="<?= $nv('age_limit') ?>" placeholder="e.g. 18-27 years"></div>
            <div class="col-12"><label class="form-label">Eligibility Summary</label>
              <textarea name="eligibility_summary" class="form-control" rows="2"><?= $nv('eligibility_summary') ?></textarea></div>
            <div class="col-12"><label class="form-label">Qualification</label>
              <textarea name="qualification" class="form-control" rows="2"><?= $nv('qualification') ?></textarea></div>
            <div class="col-md-4"><label class="form-label small">Fee — General</label>
              <input type="number" step="0.01" min="0" name="fee_general" class="form-control" value="<?= $nv('fee_general') ?>"></div>
            <div class="col-md-4"><label class="form-label small">Fee — OBC</label>
              <input type="number" step="0.01" min="0" name="fee_obc" class="form-control" value="<?= $nv('fee_obc') ?>"></div>
            <div class="col-md-4"><label class="form-label small">Fee — SC/ST</label>
              <input type="number" step="0.01" min="0" name="fee_sc_st" class="form-control" value="<?= $nv('fee_sc_st') ?>"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-gear me-2"></i>Publish</div>
        <div class="card-body">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select mb-3">
            <?php foreach (['upcoming'=>'Upcoming','active'=>'Active','result'=>'Result Out','admitcard'=>'Admit Card'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= ($n['status'] ?? 'upcoming')===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_homepage_visible" value="1" id="hp"
                   <?= (!isset($n['is_homepage_visible']) || $n['is_homepage_visible']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="hp">Show on homepage / Latest</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="ft" <?= !empty($n['is_featured'])?'checked':'' ?>>
            <label class="form-check-label" for="ft">Featured</label>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="send_telegram" value="1" id="send-tg">
            <label class="form-check-label" for="send-tg"><i class="fab fa-telegram text-primary me-1"></i>Send Telegram</label>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= htmlspecialchars($submit_label) ?></button>
        </div>
      </div>

      <!-- Dates -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-calendar-days me-2"></i>Important Dates</div>
        <div class="card-body">
          <div class="mb-2"><label class="form-label small">Notification Date</label>
            <input type="date" name="notification_date" class="form-control" value="<?= $nv('notification_date') ?>"></div>
          <div class="mb-2"><label class="form-label small">Application Start</label>
            <input type="date" name="application_start_date" class="form-control" value="<?= $nv('application_start_date') ?>"></div>
          <div class="mb-2"><label class="form-label small">Last Date to Apply</label>
            <input type="date" name="application_last_date" class="form-control" value="<?= htmlspecialchars((string)$last_date_val, ENT_QUOTES) ?>"></div>
          <div class="mb-2"><label class="form-label small">Exam Date</label>
            <input type="date" name="exam_date" class="form-control" value="<?= $nv('exam_date') ?>"></div>
          <div class="mb-2"><label class="form-label small">Admit Card Date</label>
            <input type="date" name="admit_card_date" class="form-control" value="<?= $nv('admit_card_date') ?>"></div>
          <div class="mb-0"><label class="form-label small">Result Date</label>
            <input type="date" name="result_date" class="form-control" value="<?= $nv('result_date') ?>"></div>
        </div>
      </div>

      <!-- Official links + verification -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-link me-2"></i>Official Source</div>
        <div class="card-body">
          <div class="mb-2"><label class="form-label small">Official Website URL</label>
            <input type="url" name="official_website_url" class="form-control" value="<?= htmlspecialchars((string)($n['official_website_url'] ?? $n['official_url'] ?? ''), ENT_QUOTES) ?>" placeholder="https://..."></div>
          <div class="mb-2"><label class="form-label small">Apply / Download URL</label>
            <input type="url" name="official_apply_url" class="form-control" value="<?= $nv('official_apply_url') ?>" placeholder="https://..."></div>
          <div class="mb-2"><label class="form-label small">Notification PDF URL</label>
            <input type="url" name="official_notification_pdf_url" class="form-control" value="<?= $nv('official_notification_pdf_url') ?>" placeholder="https://..."></div>
          <div class="mb-2"><label class="form-label small">Or upload PDF</label>
            <input type="file" name="pdf" class="form-control" accept=".pdf">
            <?php if (!empty($n['pdf_path'])): ?><div class="form-text">Current: <?= htmlspecialchars($n['pdf_path']) ?></div><?php endif; ?></div>
          <hr>
          <label class="form-label small">Last Verified</label>
          <input type="date" name="last_verified_date" class="form-control" value="<?= htmlspecialchars($lv, ENT_QUOTES) ?>">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="verify_now" value="1" id="vn">
            <label class="form-check-label small" for="vn">Mark as verified today</label>
          </div>
        </div>
      </div>

      <!-- Featured image -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-image me-2"></i>Featured Image</div>
        <div class="card-body">
          <input type="file" name="featured_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if (!empty($n['featured_image'])): ?>
            <div class="mt-2"><img src="/uploads/notifications/<?= htmlspecialchars($n['featured_image']) ?>" class="img-fluid rounded border" style="max-height:110px;"></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- SEO -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-magnifying-glass me-2"></i>SEO</div>
        <div class="card-body">
          <label class="form-label small">SEO Title</label>
          <input type="text" name="seo_title" id="seo-title" class="form-control" maxlength="70" value="<?= $nv('seo_title') ?>">
          <div class="form-text"><span id="st-count">0</span>/60 ideal</div>
          <label class="form-label small mt-2">Meta Description</label>
          <textarea name="meta_description" id="seo-desc" class="form-control" rows="3" maxlength="320"><?= $nv('meta_description') ?></textarea>
          <div class="form-text"><span id="sd-count">0</span>/160 ideal</div>
          <div class="border rounded p-2 mt-2">
            <div class="text-primary text-truncate" id="g-title" style="font-size:1rem;">Title</div>
            <div class="text-success small" id="g-url">examduniya.in › exams › <?= htmlspecialchars($slug_current ?? 'slug') ?></div>
            <div class="small text-muted" id="g-desc">Description</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
</div>

<script>
(function(){
  var ta=document.getElementById('notif-content'), prose=document.getElementById('notif-prose');
  var titleEl=document.getElementById('notif-title'), slugEl=document.getElementById('slug-preview');
  function slugify(s){return s.toLowerCase().trim().replace(/[^a-z0-9\s\-]/g,'').replace(/[\s\-]+/g,'-').replace(/^-+|-+$/g,'');}
  function preview(){ prose.innerHTML = ta.value; }
  <?php if (!$is_edit): ?>
  if(titleEl){ titleEl.addEventListener('input',function(){ if(slugEl) slugEl.textContent=slugify(this.value); g(); }); if(slugEl&&!slugEl.textContent) slugEl.textContent=slugify(titleEl.value); }
  <?php endif; ?>
  document.querySelectorAll('#nf-tabs [data-pane]').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#nf-tabs .nav-link').forEach(function(x){x.classList.remove('active');});
      this.classList.add('active');
      var w=this.getAttribute('data-pane')==='write';
      document.getElementById('nf-write').classList.toggle('d-none',!w);
      document.getElementById('nf-preview').classList.toggle('d-none',w);
      if(!w) preview();
    });
  });
  function wrap(o,c){var s=ta.selectionStart,e=ta.selectionEnd,sel=ta.value.substring(s,e)||'';ta.value=ta.value.substring(0,s)+o+sel+c+ta.value.substring(e);ta.focus();ta.selectionStart=s+o.length;ta.selectionEnd=s+o.length+sel.length;}
  document.querySelectorAll('#nf-toolbar [data-tag]').forEach(function(b){b.addEventListener('click',function(){var t=this.getAttribute('data-tag');if(t==='ul'||t==='ol'){wrap('\n<'+t+'>\n  <li>','</li>\n</'+t+'>\n');}else{wrap('<'+t+'>','</'+t+'>');}});});
  document.getElementById('nf-link').addEventListener('click',function(){var u=prompt('URL:','https://');if(u)wrap('<a href="'+u+'" target="_blank" rel="noopener">','</a>');});
  document.getElementById('nf-table').addEventListener('click',function(){wrap('\n<table class="table table-bordered">\n  <thead><tr><th>Event</th><th>Date</th></tr></thead>\n  <tbody><tr><td>Apply Start</td><td>...</td></tr></tbody>\n</table>\n','');});

  var st=document.getElementById('seo-title'), sd=document.getElementById('seo-desc');
  function g(){
    var t=(st&&st.value)?st.value:(titleEl?titleEl.value:'');
    document.getElementById('g-title').textContent=t||'Title';
    document.getElementById('g-desc').textContent=(sd&&sd.value)?sd.value:'Meta description preview appears here.';
    document.getElementById('st-count').textContent=st?st.value.length:0;
    document.getElementById('sd-count').textContent=sd?sd.value.length:0;
  }
  if(st) st.addEventListener('input',g); if(sd) sd.addEventListener('input',g); g();
})();
</script>
