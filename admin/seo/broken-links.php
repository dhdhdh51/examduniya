<?php
/** Admin — Broken-Link & Missing-Data Checker. */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Broken Link Checker';

function ql($sql){ global $pdo; try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e){ return null; } }

$missing_source = ql("SELECT id, title FROM notifications
    WHERE COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) IS NULL
      AND (pdf_path IS NULL OR pdf_path = '') ORDER BY created_at DESC LIMIT 100");
$missing_image  = ql("SELECT id, title FROM notifications WHERE featured_image IS NULL OR featured_image = '' ORDER BY created_at DESC LIMIT 100");
$missing_cat    = ql("SELECT id, title FROM notifications WHERE category IS NULL OR category = '' ORDER BY created_at DESC LIMIT 100");

// External official links to verify (dedudup by URL).
$links = ql("SELECT id, title, COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) AS url
    FROM notifications
    WHERE COALESCE(NULLIF(official_website_url,''), NULLIF(official_url,'')) IS NOT NULL
    ORDER BY created_at DESC LIMIT 200");

$migrated = ($missing_source !== null);

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$card = function($title,$rows,$icon){
    echo '<div class="col-md-4"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white fw-semibold"><i class="fas '.$icon.' me-2"></i>'.$title.' <span class="badge bg-danger ms-1">'.($rows===null?'N/A':count($rows)).'</span></div><div class="card-body p-0" style="max-height:280px;overflow:auto;">';
    if ($rows === null) { echo '<p class="text-muted small p-3 mb-0">Run database/schema.sql to enable.</p>'; }
    elseif (empty($rows)) { echo '<p class="text-success small p-3 mb-0"><i class="fas fa-check me-1"></i>All good.</p>'; }
    else { echo '<ul class="list-group list-group-flush">'; foreach ($rows as $r){ echo '<li class="list-group-item small d-flex justify-content-between"><span>'.htmlspecialchars(mb_substr($r['title'],0,42)).'</span><a href="/admin/notifications/edit.php?id='.(int)$r['id'].'" class="text-decoration-none"><i class="fas fa-edit"></i></a></li>'; } echo '</ul>'; }
    echo '</div></div></div>';
};
?>
<div class="admin-content">
<div class="container-fluid py-4">
  <h2 class="mb-3"><i class="fas fa-link-slash me-2"></i>Broken Link &amp; Missing-Data Checker</h2>
  <?php if (!$migrated): ?><div class="alert alert-warning">Import <code>database/schema.sql</code> to enable all checks.</div><?php endif; ?>

  <div class="row g-3 mb-4">
    <?php $card('Missing Official Source', $missing_source, 'fa-link'); ?>
    <?php $card('Missing Featured Image', $missing_image, 'fa-image'); ?>
    <?php $card('Missing Category', $missing_cat, 'fa-tags'); ?>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><i class="fas fa-globe me-2"></i>External Official Links (<?= $links===null?0:count($links) ?>)</span>
      <button id="checkAll" class="btn btn-primary btn-sm"><i class="fas fa-play me-1"></i>Check All Links</button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th>Notification</th><th>URL</th><th style="width:130px;">Status</th></tr></thead>
          <tbody>
          <?php if (empty($links)): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">No official links found.</td></tr>
          <?php else: foreach ($links as $l): ?>
            <tr data-url="<?= htmlspecialchars($l['url'], ENT_QUOTES) ?>">
              <td class="small"><?= htmlspecialchars(mb_substr($l['title'],0,40)) ?></td>
              <td class="small text-break" style="max-width:360px;"><a href="<?= htmlspecialchars($l['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(mb_substr($l['url'],0,60)) ?></a></td>
              <td class="status-cell"><span class="badge bg-light text-dark">not checked</span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<script>
(function(){
  var csrf = '<?= csrf_token() ?>';
  function check(row){
    var url = row.getAttribute('data-url');
    var cell = row.querySelector('.status-cell');
    cell.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    var fd = new FormData(); fd.append('csrf_token', csrf); fd.append('url', url);
    return fetch('/admin/ajax/check-link.php',{method:'POST',body:fd}).then(function(r){return r.json();})
      .then(function(d){
        if (d && d.ok) cell.innerHTML = '<span class="badge bg-success">OK '+(d.code||'')+'</span>';
        else cell.innerHTML = '<span class="badge bg-danger">'+(d && d.code ? d.code : 'FAIL')+'</span>';
      }).catch(function(){ cell.innerHTML = '<span class="badge bg-warning text-dark">error</span>'; });
  }
  document.getElementById('checkAll').addEventListener('click', function(){
    var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-url]'));
    this.disabled = true; var self = this;
    (function next(i){
      if (i >= rows.length) { self.disabled = false; return; }
      check(rows[i]).then(function(){ next(i+1); }); // sequential to be gentle
    })(0);
  });
})();
</script>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
