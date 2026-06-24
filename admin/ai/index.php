<?php
/**
 * Admin: AI Providers manager.
 * Edit API keys / models for every AI provider (Gemini, ChatGPT, Claude,
 * DeepSeek, Grok, OpenRouter...). Each can be enabled, set as default,
 * saved and tested. Data lives in the ai_providers table.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'AI Providers';
$active_menu = 'ai';

// Make sure the table exists; guide the admin if it doesn't.
$table_ready = true;
try {
    $providers = $pdo->query("SELECT * FROM ai_providers ORDER BY sort_order ASC, id ASC")
                     ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $table_ready = false;
    $providers = [];
}

$type_labels = [
    'gemini'    => 'Google Gemini API',
    'openai'    => 'OpenAI-compatible',
    'anthropic' => 'Anthropic (Claude)',
];

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
$csrf = csrf_token();
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-robot me-2"></i>AI Providers</h2>
    <a href="/admin/tests/generate.php" class="btn btn-success btn-sm">
      <i class="fas fa-wand-magic-sparkles me-1"></i>Generate Questions
    </a>
  </div>

  <?php if (!$table_ready): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation me-2"></i>
      The <code>ai_providers</code> table was not found. Please import
      <code>database/schema.sql</code> into your database, then reload this page.
    </div>
  <?php else: ?>

  <p class="text-muted">
    Configure the API key and model for each AI service. Enable the ones you want,
    pick a <strong>default</strong> (used by the AI Question Generator), then
    <strong>Save</strong> and <strong>Test</strong> each one.
  </p>

  <div class="row g-3">
    <?php foreach ($providers as $p):
      $lt = json_decode($p['last_test'] ?? '', true);
      $badge = 'bg-light text-muted border';
      $badge_txt = 'Not tested';
      if (is_array($lt)) {
          $badge = !empty($lt['ok']) ? 'bg-success' : 'bg-danger';
          $badge_txt = !empty($lt['ok']) ? 'Connected' : 'Error';
      }
    ?>
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <span class="fw-semibold">
            <i class="fas fa-microchip me-2 text-primary"></i><?= htmlspecialchars($p['name']) ?>
            <?php if ($p['is_default']): ?>
              <span class="badge bg-primary ms-1">Default</span>
            <?php endif; ?>
          </span>
          <span class="badge <?= $badge ?>" id="badge-<?= htmlspecialchars($p['provider_key']) ?>"
                title="<?= htmlspecialchars(is_array($lt) ? (($lt['note'] ?? '') . ' • ' . ($lt['at'] ?? '')) : '') ?>">
            <?= $badge_txt ?>
          </span>
        </div>
        <div class="card-body">
          <form class="ai-form" data-provider="<?= htmlspecialchars($p['provider_key']) ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="provider_key" value="<?= htmlspecialchars($p['provider_key']) ?>">

            <div class="mb-2">
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($type_labels[$p['api_type']] ?? $p['api_type']) ?></span>
            </div>

            <div class="mb-3">
              <label class="form-label">API Key</label>
              <div class="input-group">
                <input type="password" class="form-control js-secret" name="api_key"
                       value="<?= htmlspecialchars($p['api_key'] ?? '') ?>"
                       placeholder="Paste API key" autocomplete="off">
                <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Model</label>
                <input type="text" class="form-control" name="model"
                       value="<?= htmlspecialchars($p['model'] ?? '') ?>" placeholder="model id">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Endpoint</label>
                <input type="text" class="form-control" name="endpoint"
                       value="<?= htmlspecialchars($p['endpoint'] ?? '') ?>" placeholder="API base URL">
              </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="enabled-<?= htmlspecialchars($p['provider_key']) ?>"
                       name="enabled" value="1" <?= $p['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="enabled-<?= htmlspecialchars($p['provider_key']) ?>">Enabled</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio"
                       id="default-<?= htmlspecialchars($p['provider_key']) ?>"
                       name="is_default" value="1" <?= $p['is_default'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="default-<?= htmlspecialchars($p['provider_key']) ?>">Use as default</label>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-floppy-disk me-1"></i>Save</button>
              <button type="button" class="btn btn-outline-success btn-sm js-test-ai"><i class="fas fa-plug me-1"></i>Test</button>
            </div>
            <div class="ai-result mt-2"></div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

</div>
</div>

<?php
$extra_js = <<<'JS'
<script>
(function () {
  'use strict';

  document.querySelectorAll('.js-toggle-secret').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.closest('.input-group').querySelector('.js-secret');
      if (!input) return;
      var icon = btn.querySelector('i');
      if (input.type === 'password') { input.type = 'text'; if (icon) icon.className = 'fas fa-eye-slash'; }
      else { input.type = 'password'; if (icon) icon.className = 'fas fa-eye'; }
    });
  });

  // Only one provider can be the default — uncheck others when one is picked.
  document.querySelectorAll('input[name="is_default"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (this.checked) {
        document.querySelectorAll('input[name="is_default"]').forEach(function (r) {
          if (r !== radio) r.checked = false;
        });
      }
    });
  });

  // Save a provider
  document.querySelectorAll('.ai-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var orig = btn.innerHTML;
      btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
      ajaxPostForm('/admin/ajax/save-ai-provider.php', new FormData(form), function (err, data) {
        btn.disabled = false; btn.innerHTML = orig;
        if (err || !data) { showToast('Save failed', 'danger'); return; }
        showToast(data.message || (data.success ? 'Saved' : 'Failed'), data.success ? 'success' : 'danger');
        if (data.success && form.querySelector('input[name="is_default"]').checked) {
          setTimeout(function () { location.reload(); }, 700);
        }
      });
    });
  });

  // Test a provider
  document.querySelectorAll('.js-test-ai').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('.ai-form');
      var key  = form.dataset.provider;
      var box  = form.querySelector('.ai-result');
      var orig = btn.innerHTML;
      btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';

      // Save first so the test uses the latest values, then test.
      var fd = new FormData(form);
      ajaxPostForm('/admin/ajax/save-ai-provider.php', fd, function () {
        var tfd = new FormData();
        tfd.append('csrf_token', form.querySelector('input[name=csrf_token]').value);
        tfd.append('provider_key', key);
        ajaxPostForm('/admin/ajax/test-ai-provider.php', tfd, function (err, data) {
          btn.disabled = false; btn.innerHTML = orig;
          var badge = document.getElementById('badge-' + key);
          if (err || !data) {
            box.innerHTML = '<div class="alert alert-danger py-2 mb-0">Request failed.</div>';
            if (badge) { badge.className = 'badge bg-danger'; badge.textContent = 'Error'; }
            return;
          }
          var ok = !!data.success;
          box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' +
            (ok ? '<i class="fas fa-circle-check me-1"></i>Connected • ' + (data.ms || 0) + ' ms' +
                  (data.reply ? ' • Reply: ' + escapeHtml(data.reply) : '')
                : '<i class="fas fa-circle-xmark me-1"></i>' + escapeHtml(data.error || 'Error')) +
            '</div>';
          if (badge) { badge.className = 'badge ' + (ok ? 'bg-success' : 'bg-danger'); badge.textContent = ok ? 'Connected' : 'Error'; }
          showToast(ok ? 'Test passed' : 'Test failed', ok ? 'success' : 'danger');
        });
      });
    });
  });
})();
</script>
JS;
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
