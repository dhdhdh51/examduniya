<?php
/**
 * Admin Settings Panel
 * Tabbed interface to manage every site/integration setting stored in the DB.
 * All API keys and credentials live in the `settings` table (never in config).
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Settings';
$active_menu = 'settings';

// Current values
$s = [
    // site / general
    'site_name'            => get_setting('site_name'),
    'site_url'             => get_setting('site_url'),
    'site_description'     => get_setting('site_description'),
    'site_logo'            => get_setting('site_logo'),
    'google_analytics_id'  => get_setting('google_analytics_id'),
    'per_page'             => get_setting('per_page') ?: '12',
    // google
    'google_client_id'     => get_setting('google_client_id'),
    'google_client_secret' => get_setting('google_client_secret'),
    'google_redirect_uri'  => get_setting('google_redirect_uri'),
    // gemini
    'gemini_api_key'       => get_setting('gemini_api_key'),
    'gemini_model'         => get_setting('gemini_model') ?: 'gemini-2.0-flash',
    // telegram
    'telegram_bot_token'   => get_setting('telegram_bot_token'),
    'telegram_chat_ids'    => get_setting('telegram_chat_ids'),
    'telegram_enabled'     => get_setting('telegram_enabled'),
    // smtp
    'smtp_host'            => get_setting('smtp_host'),
    'smtp_port'            => get_setting('smtp_port') ?: '587',
    'smtp_encryption'      => get_setting('smtp_encryption') ?: 'tls',
    'smtp_username'        => get_setting('smtp_username'),
    'smtp_password'        => get_setting('smtp_password'),
    'smtp_from_email'      => get_setting('smtp_from_email'),
    'smtp_from_name'       => get_setting('smtp_from_name') ?: 'GovExam Portal',
    'smtp_enabled'         => get_setting('smtp_enabled'),
    // payu
    'payu_merchant_key'    => get_setting('payu_merchant_key'),
    'payu_merchant_salt'   => get_setting('payu_merchant_salt'),
    'payu_mode'            => get_setting('payu_mode') ?: 'test',
    'payu_enabled'         => get_setting('payu_enabled'),
    'plan_monthly_price'   => get_setting('plan_monthly_price') ?: '99',
    'plan_yearly_price'    => get_setting('plan_yearly_price') ?: '799',
    // maintenance
    'maintenance_mode'     => get_setting('maintenance_mode'),
];

$admin_email = $_SESSION['email'] ?? '';

// Derive the OAuth callback URL shown to the admin.
$base_url = rtrim($s['site_url'] ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'example.com')), '/');
$google_callback = $base_url . '/auth/google-callback.php';

/**
 * Render a "last tested" status badge from a stored *_last_test JSON value.
 */
function test_badge($group)
{
    $raw = get_setting($group . '_last_test');
    if (empty($raw)) {
        return '<span class="badge bg-light text-muted border" id="badge-' . $group . '">Not tested yet</span>';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return '<span class="badge bg-light text-muted border" id="badge-' . $group . '">Not tested yet</span>';
    }
    $ok  = !empty($d['ok']);
    $cls = $ok ? 'bg-success' : 'bg-danger';
    $txt = $ok ? 'Connected' : 'Error';
    $tip = trim(($d['note'] ?? '') . ' • ' . ($d['at'] ?? ''), ' •');
    return '<span class="badge ' . $cls . '" id="badge-' . $group . '" title="' . htmlspecialchars($tip) . '">'
         . $txt . '</span>'
         . '<small class="text-muted ms-2" id="badge-time-' . $group . '">' . htmlspecialchars($d['at'] ?? '') . '</small>';
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';

$csrf = csrf_token();
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-gear me-2"></i>Settings</h2>
    <span class="text-muted small">
      <i class="fas fa-lock me-1"></i>All credentials are stored securely in the database.
    </span>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="row g-0">

        <!-- Vertical tab nav -->
        <div class="col-12 col-lg-3 border-end">
          <div class="nav flex-lg-column nav-pills p-3 gap-1 flex-row flex-wrap" id="settingsTab" role="tablist" aria-orientation="vertical">
            <button class="nav-link active text-start" id="tab-general" data-bs-toggle="pill" data-bs-target="#pane-general" type="button" role="tab">
              <i class="fas fa-sliders me-2"></i>General
            </button>
            <button class="nav-link text-start" id="tab-google" data-bs-toggle="pill" data-bs-target="#pane-google" type="button" role="tab">
              <i class="fab fa-google me-2"></i>Google OAuth
            </button>
            <button class="nav-link text-start" id="tab-gemini" data-bs-toggle="pill" data-bs-target="#pane-gemini" type="button" role="tab">
              <i class="fas fa-robot me-2"></i>Gemini AI
            </button>
            <button class="nav-link text-start" id="tab-telegram" data-bs-toggle="pill" data-bs-target="#pane-telegram" type="button" role="tab">
              <i class="fab fa-telegram me-2"></i>Telegram
            </button>
            <button class="nav-link text-start" id="tab-smtp" data-bs-toggle="pill" data-bs-target="#pane-smtp" type="button" role="tab">
              <i class="fas fa-envelope me-2"></i>SMTP Email
            </button>
            <button class="nav-link text-start" id="tab-payu" data-bs-toggle="pill" data-bs-target="#pane-payu" type="button" role="tab">
              <i class="fas fa-credit-card me-2"></i>PayU Payment
            </button>
            <button class="nav-link text-start" id="tab-maintenance" data-bs-toggle="pill" data-bs-target="#pane-maintenance" type="button" role="tab">
              <i class="fas fa-screwdriver-wrench me-2"></i>Maintenance
            </button>
          </div>
        </div>

        <!-- Tab panes -->
        <div class="col-12 col-lg-9">
          <div class="tab-content p-4" id="settingsTabContent">

            <!-- ============ GENERAL ============ -->
            <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
              <h5 class="mb-3">General Site Settings</h5>
              <form id="settings-form-site" class="settings-form" data-group="site" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="site">

                <div class="mb-3">
                  <label class="form-label">Site Name</label>
                  <input type="text" class="form-control" name="site_name" value="<?= sanitize($s['site_name']) ?>" placeholder="GovExam Portal">
                </div>
                <div class="mb-3">
                  <label class="form-label">Site URL</label>
                  <input type="url" class="form-control" name="site_url" value="<?= sanitize($s['site_url']) ?>" placeholder="https://yourdomain.com">
                  <div class="form-text">Used for absolute links in emails, Telegram messages and OAuth redirects.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Site Description</label>
                  <textarea class="form-control" name="site_description" rows="2" placeholder="Short description for SEO"><?= sanitize($s['site_description']) ?></textarea>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Site Logo</label>
                    <?php if (!empty($s['site_logo'])): ?>
                      <div class="mb-2">
                        <img src="/uploads/site/<?= sanitize($s['site_logo']) ?>" alt="logo" style="max-height:42px;" class="border rounded p-1 bg-light">
                      </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="site_logo_file" accept="image/png,image/jpeg,image/gif,image/webp">
                    <div class="form-text">PNG, JPG, GIF or WEBP.</div>
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Items / Page</label>
                    <input type="number" min="1" max="100" class="form-control" name="per_page" value="<?= sanitize($s['per_page']) ?>">
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Google Analytics ID (GA4)</label>
                  <input type="text" class="form-control" name="google_analytics_id" value="<?= sanitize($s['google_analytics_id']) ?>" placeholder="G-XXXXXXXXXX">
                  <div class="form-text">When set, the GA4 tag is injected automatically on every public page.</div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
              </form>
            </div>

            <!-- ============ GOOGLE OAUTH ============ -->
            <div class="tab-pane fade" id="pane-google" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Google OAuth 2.0</h5>
                <?= test_badge('google') ?>
              </div>
              <form id="settings-form-google" class="settings-form" data-group="google">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="google">

                <div class="mb-3">
                  <label class="form-label">Client ID</label>
                  <input type="text" class="form-control" name="google_client_id" value="<?= sanitize($s['google_client_id']) ?>" placeholder="xxxxx.apps.googleusercontent.com">
                </div>
                <div class="mb-3">
                  <label class="form-label">Client Secret</label>
                  <div class="input-group">
                    <input type="password" class="form-control js-secret" name="google_client_secret" value="<?= sanitize($s['google_client_secret']) ?>" placeholder="GOCSPX-...">
                    <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Authorized Redirect URI</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="google-callback" value="<?= sanitize($google_callback) ?>" readonly>
                    <button class="btn btn-outline-secondary js-copy" type="button" data-copy-target="#google-callback"><i class="fas fa-copy"></i></button>
                  </div>
                  <div class="form-text">Add this exact URL to "Authorized redirect URIs" in your Google Cloud Console.</div>
                </div>
                <input type="hidden" name="google_redirect_uri" value="<?= sanitize($google_callback) ?>">

                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
                  <button type="button" class="btn btn-outline-success js-test" data-test="google" data-target="#result-google">
                    <i class="fas fa-plug me-2"></i>Verify Google OAuth
                  </button>
                </div>
                <div id="result-google" class="mt-3"></div>
              </form>
            </div>

            <!-- ============ GEMINI AI ============ -->
            <div class="tab-pane fade" id="pane-gemini" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Gemini AI</h5>
                <?= test_badge('gemini') ?>
              </div>
              <form id="settings-form-gemini" class="settings-form" data-group="gemini">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="gemini">

                <div class="mb-3">
                  <label class="form-label">Gemini API Key</label>
                  <div class="input-group">
                    <input type="password" class="form-control js-secret" name="gemini_api_key" value="<?= sanitize($s['gemini_api_key']) ?>" placeholder="AIza...">
                    <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                  </div>
                  <div class="form-text">Get a key from Google AI Studio. Stored encrypted-at-rest in your database.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Model</label>
                  <input type="text" class="form-control" name="gemini_model" list="geminiModels"
                         value="<?= sanitize($s['gemini_model']) ?>" placeholder="gemini-2.5-flash" autocomplete="off">
                  <datalist id="geminiModels">
                    <option value="gemini-2.5-pro">Gemini 2.5 Pro (most capable)</option>
                    <option value="gemini-2.5-flash">Gemini 2.5 Flash (fast, latest)</option>
                    <option value="gemini-2.0-flash">Gemini 2.0 Flash (fast, recommended)</option>
                    <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                    <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                    <option value="gemini-pro">Gemini Pro (legacy)</option>
                  </datalist>
                  <div class="form-text">
                    Type any model name your API key supports, or pick a suggestion. The latest available family
                    is <strong>Gemini 2.5</strong> (there is no "3.5" yet). Use <strong>Test Gemini API</strong>
                    below to confirm the model works.
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
                  <button type="button" class="btn btn-outline-success js-test" data-test="gemini" data-target="#result-gemini">
                    <i class="fas fa-robot me-2"></i>Test Gemini API
                  </button>
                </div>
                <div id="result-gemini" class="mt-3"></div>
              </form>
            </div>

            <!-- ============ TELEGRAM ============ -->
            <div class="tab-pane fade" id="pane-telegram" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Telegram Notifications</h5>
                <?= test_badge('telegram') ?>
              </div>
              <form id="settings-form-telegram" class="settings-form" data-group="telegram">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="telegram">

                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="telegram_enabled" name="telegram_enabled" value="1" <?= $s['telegram_enabled'] === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="telegram_enabled">Enable Telegram auto-posting on new notifications</label>
                </div>
                <div class="mb-3">
                  <label class="form-label">Bot Token</label>
                  <div class="input-group">
                    <input type="password" class="form-control js-secret" name="telegram_bot_token" value="<?= sanitize($s['telegram_bot_token']) ?>" placeholder="123456:ABC-DEF...">
                    <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                  </div>
                  <div class="form-text">Create a bot with <strong>@BotFather</strong> and paste the token here.</div>
                </div>
                <div class="mb-2">
                  <label class="form-label">Chat IDs</label>
                  <textarea class="form-control" name="telegram_chat_ids" rows="2" placeholder="-100123456789, -100987654321"><?= sanitize($s['telegram_chat_ids']) ?></textarea>
                  <div class="form-text">
                    Comma-separated. Add your bot to the group, make it an admin, then paste the group chat ID.
                    <a href="#" data-bs-toggle="modal" data-bs-target="#chatIdModal">How to get Chat ID?</a>
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-3">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
                  <button type="button" class="btn btn-outline-success js-test" data-test="telegram" data-target="#result-telegram">
                    <i class="fab fa-telegram me-2"></i>Send Test Message
                  </button>
                </div>
                <div id="result-telegram" class="mt-3"></div>
              </form>
            </div>

            <!-- ============ SMTP ============ -->
            <div class="tab-pane fade" id="pane-smtp" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">SMTP Email</h5>
                <?= test_badge('smtp') ?>
              </div>

              <div class="mb-3">
                <span class="text-muted small me-2">Quick presets:</span>
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-outline-secondary js-smtp-preset" data-host="smtp.gmail.com" data-port="587" data-enc="tls">Gmail</button>
                  <button type="button" class="btn btn-outline-secondary js-smtp-preset" data-host="smtp.office365.com" data-port="587" data-enc="tls">Outlook</button>
                  <button type="button" class="btn btn-outline-secondary js-smtp-preset" data-host="mail.yourdomain.com" data-port="465" data-enc="ssl">cPanel Mail</button>
                </div>
              </div>

              <form id="settings-form-smtp" class="settings-form" data-group="smtp">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="smtp">

                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="smtp_enabled" name="smtp_enabled" value="1" <?= $s['smtp_enabled'] === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="smtp_enabled">Enable email sending</label>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" class="form-control" name="smtp_host" id="smtp_host" value="<?= sanitize($s['smtp_host']) ?>" placeholder="smtp.gmail.com">
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Port</label>
                    <input type="number" class="form-control" name="smtp_port" id="smtp_port" value="<?= sanitize($s['smtp_port']) ?>" placeholder="587">
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label">Encryption</label>
                    <select class="form-select" name="smtp_encryption" id="smtp_encryption">
                      <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $val => $label):
                          $sel = ($s['smtp_encryption'] === $val) ? ' selected' : ''; ?>
                        <option value="<?= $val ?>"<?= $sel ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="smtp_username" value="<?= sanitize($s['smtp_username']) ?>" placeholder="you@gmail.com">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                      <input type="password" class="form-control js-secret" name="smtp_password" value="<?= sanitize($s['smtp_password']) ?>" placeholder="App password">
                      <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">From Email</label>
                    <input type="email" class="form-control" name="smtp_from_email" value="<?= sanitize($s['smtp_from_email']) ?>" placeholder="noreply@yourdomain.com">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">From Name</label>
                    <input type="text" class="form-control" name="smtp_from_name" value="<?= sanitize($s['smtp_from_name']) ?>" placeholder="GovExam Portal">
                  </div>
                </div>

                <div class="row align-items-end">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Send test email to</label>
                    <input type="email" class="form-control" id="smtp-test-email" value="<?= sanitize($admin_email) ?>" placeholder="test@example.com">
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
                  <button type="button" class="btn btn-outline-success js-test" data-test="smtp" data-target="#result-smtp">
                    <i class="fas fa-paper-plane me-2"></i>Send Test Email
                  </button>
                </div>
                <div id="result-smtp" class="mt-3"></div>
              </form>
            </div>

            <!-- ============ PAYU ============ -->
            <div class="tab-pane fade" id="pane-payu" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">PayU Payment Gateway</h5>
                <?= test_badge('payu') ?>
              </div>
              <form id="settings-form-payu" class="settings-form" data-group="payu">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="payu">

                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" role="switch" id="payu_enabled" name="payu_enabled" value="1" <?= $s['payu_enabled'] === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="payu_enabled">Enable PayU payments</label>
                </div>

                <div class="mb-3">
                  <label class="form-label">Merchant Key</label>
                  <input type="text" class="form-control" name="payu_merchant_key" value="<?= sanitize($s['payu_merchant_key']) ?>" placeholder="gtKFFx">
                </div>
                <div class="mb-3">
                  <label class="form-label">Merchant Salt</label>
                  <div class="input-group">
                    <input type="password" class="form-control js-secret" name="payu_merchant_salt" value="<?= sanitize($s['payu_merchant_salt']) ?>" placeholder="eCwWELxi">
                    <button class="btn btn-outline-secondary js-toggle-secret" type="button" tabindex="-1"><i class="fas fa-eye"></i></button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label d-block">Mode</label>
                  <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="payu_mode" id="payu_mode_test" value="test" <?= $s['payu_mode'] !== 'live' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-success" for="payu_mode_test"><i class="fas fa-flask me-1"></i>Test</label>
                    <input type="radio" class="btn-check" name="payu_mode" id="payu_mode_live" value="live" <?= $s['payu_mode'] === 'live' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-danger" for="payu_mode_live"><i class="fas fa-bolt me-1"></i>Live</label>
                  </div>
                  <div id="payu-live-warning" class="alert alert-warning mt-2 mb-0 py-2 <?= $s['payu_mode'] === 'live' ? '' : 'd-none' ?>">
                    <i class="fas fa-triangle-exclamation me-1"></i>You are in <strong>LIVE</strong> mode — real payments will be processed.
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Monthly Plan Price (&#8377;)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="plan_monthly_price" value="<?= sanitize($s['plan_monthly_price']) ?>">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Yearly Plan Price (&#8377;)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="plan_yearly_price" value="<?= sanitize($s['plan_yearly_price']) ?>">
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
                  <button type="button" class="btn btn-outline-success js-test" data-test="payu" data-target="#result-payu">
                    <i class="fas fa-shield-halved me-2"></i>Verify PayU Credentials
                  </button>
                  <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons@v11/icons/payu.svg" alt="PayU" height="20" style="opacity:.7;">
                </div>
                <div id="result-payu" class="mt-3"></div>
              </form>
            </div>

            <!-- ============ MAINTENANCE ============ -->
            <div class="tab-pane fade" id="pane-maintenance" role="tabpanel">
              <h5 class="mb-3">Maintenance Mode</h5>
              <form id="settings-form-maintenance" class="settings-form" data-group="maintenance">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="group" value="maintenance">

                <div class="alert alert-info">
                  When enabled, all visitors except logged-in admins will see a maintenance page.
                </div>
                <div class="form-check form-switch mb-3 fs-5">
                  <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?= $s['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                </div>
                <div id="maintenance-warning" class="alert alert-warning <?= $s['maintenance_mode'] === '1' ? '' : 'd-none' ?>">
                  <i class="fas fa-triangle-exclamation me-1"></i>Maintenance mode is <strong>ON</strong>. The public site is hidden from non-admin visitors.
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-2"></i>Save Settings</button>
              </form>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.container-fluid -->
</div><!-- /.admin-content -->

<!-- How to get Chat ID modal -->
<div class="modal fade" id="chatIdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fab fa-telegram text-info me-2"></i>How to get a Chat ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ol class="mb-0 ps-3">
          <li class="mb-2">Create a bot with <strong>@BotFather</strong> and copy its token into the Bot Token field.</li>
          <li class="mb-2">Create a Telegram group (or channel) and add your bot to it.</li>
          <li class="mb-2">Make the bot an <strong>administrator</strong> of the group.</li>
          <li class="mb-2">Send any message in the group.</li>
          <li class="mb-2">Open <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> in your browser.</li>
          <li class="mb-2">Find <code>"chat":{"id":-100...}</code> — that number (with the minus sign) is your Chat ID.</li>
          <li>Paste it into the Chat IDs field. Separate multiple IDs with commas.</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

<?php
$extra_js = <<<'JS'
<script>
(function () {
  'use strict';

  // Open a specific tab from the URL hash, e.g. /admin/settings.php#payu
  function openTabFromHash() {
    var hash = (location.hash || '').replace('#', '').replace('pane-', '').replace('tab-', '');
    if (!hash) return;
    var btn = document.getElementById('tab-' + hash);
    if (btn && window.bootstrap) {
      try { bootstrap.Tab.getOrCreateInstance(btn).show(); } catch (e) { btn.click(); }
      btn.scrollIntoView({ block: 'nearest' });
    }
  }
  openTabFromHash();
  window.addEventListener('hashchange', openTabFromHash);

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  // ---- Show/hide secret fields ----
  document.querySelectorAll('.js-toggle-secret').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.closest('.input-group').querySelector('.js-secret');
      if (!input) return;
      var icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'fas fa-eye-slash';
      } else {
        input.type = 'password';
        if (icon) icon.className = 'fas fa-eye';
      }
    });
  });

  // ---- Copy-to-clipboard ----
  document.querySelectorAll('.js-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.dataset.copyTarget);
      if (!target) return;
      target.select();
      try {
        navigator.clipboard.writeText(target.value);
      } catch (e) {
        document.execCommand('copy');
      }
      if (window.showToast) showToast('Copied to clipboard', 'info', 2000);
    });
  });

  // ---- SMTP presets ----
  document.querySelectorAll('.js-smtp-preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var host = document.getElementById('smtp_host');
      var port = document.getElementById('smtp_port');
      var enc  = document.getElementById('smtp_encryption');
      if (host) host.value = btn.dataset.host;
      if (port) port.value = btn.dataset.port;
      if (enc)  enc.value  = btn.dataset.enc;
      if (window.showToast) showToast('Preset applied — review and save', 'info', 2500);
    });
  });

  // ---- PayU live-mode warning toggle ----
  document.querySelectorAll('input[name="payu_mode"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var warn = document.getElementById('payu-live-warning');
      if (warn) warn.classList.toggle('d-none', this.value !== 'live');
    });
  });

  // ---- Maintenance warning toggle ----
  var maint = document.getElementById('maintenance_mode');
  if (maint) {
    maint.addEventListener('change', function () {
      var warn = document.getElementById('maintenance-warning');
      if (warn) warn.classList.toggle('d-none', !this.checked);
    });
  }

  // ---- Save any settings form via AJAX ----
  document.querySelectorAll('.settings-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var group = form.dataset.group;
      var btn = form.querySelector('button[type="submit"]');
      var original = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...'; }

      var fd = new FormData(form);
      ajaxPostForm('/admin/ajax/save-settings.php', fd, function (err, data) {
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
        if (err || !data) { showToast('Failed to save settings', 'danger'); return; }
        if (data.success) {
          showToast(data.message || 'Settings saved', 'success');
        } else {
          showToast(data.message || 'Save failed', 'danger');
        }
      });
    });
  });

  // ---- Render a test result block ----
  function renderResult(targetSel, ok, title, detail) {
    var el = document.querySelector(targetSel);
    if (!el) return;
    var cls = ok ? 'alert-success' : 'alert-danger';
    var icon = ok ? 'fa-circle-check' : 'fa-circle-xmark';
    el.innerHTML =
      '<div class="alert ' + cls + ' mb-0 py-2">' +
      '<i class="fas ' + icon + ' me-2"></i><strong>' + escapeHtml(title) + '</strong>' +
      (detail ? '<div class="small mt-1">' + escapeHtml(detail) + '</div>' : '') +
      '</div>';
  }

  function updateBadge(group, ok) {
    var badge = document.getElementById('badge-' + group);
    if (badge) {
      badge.className = 'badge ' + (ok ? 'bg-success' : 'bg-danger');
      badge.textContent = ok ? 'Connected' : 'Error';
    }
    var t = document.getElementById('badge-time-' + group);
    if (t) t.textContent = new Date().toLocaleString();
  }

  // ---- Test-connection buttons ----
  document.querySelectorAll('.js-test').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group  = btn.dataset.test;
      var target = btn.dataset.target;
      var original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';

      var fd = new FormData();
      fd.append('csrf_token', csrf());
      if (group === 'smtp') {
        var em = document.getElementById('smtp-test-email');
        fd.append('test_email', em ? em.value : '');
      }

      ajaxPostForm('/admin/ajax/test-' + group + '.php', fd, function (err, data) {
        btn.disabled = false;
        btn.innerHTML = original;
        if (err || !data) {
          renderResult(target, false, 'Request failed', 'Could not reach the test endpoint.');
          updateBadge(group, false);
          return;
        }

        var ok = !!data.success;
        var title, detail = '';

        if (group === 'gemini') {
          title  = ok ? 'Connected' : ('Error: ' + (data.error || 'unknown'));
          if (ok) detail = 'Model: ' + (data.model || '') + ' • ' + (data.response_time_ms || 0) + ' ms'
                           + (data.reply ? ' • Reply: ' + data.reply : '');
        } else if (group === 'telegram') {
          title  = ok ? ('Message sent to ' + (data.sent_count || 0) + ' group(s)') : ('Error: ' + (data.error || 'unknown'));
          if (data.bot_username) detail = 'Bot: @' + data.bot_username;
        } else if (group === 'smtp') {
          title  = ok ? ('Email sent to ' + (data.sent_to || '')) : ('SMTP Error: ' + (data.error || 'unknown'));
        } else if (group === 'payu') {
          title  = ok ? (data.message || 'Credentials look valid') : ('Error: ' + (data.error || 'unknown'));
          var bits = [];
          if (typeof data.key_length !== 'undefined')  bits.push('key: ' + data.key_length + ' chars');
          if (typeof data.salt_length !== 'undefined') bits.push('salt: ' + data.salt_length + ' chars');
          if (data.mode) bits.push('mode: ' + data.mode);
          detail = bits.join(' • ');
          if (ok && data.warning) { showToast(data.warning, 'warning', 6000); }
        } else if (group === 'google') {
          title  = ok ? (data.message || 'Client ID is valid') : ('Error: ' + (data.error || 'unknown'));
        } else {
          title = ok ? 'Success' : ('Error: ' + (data.error || 'unknown'));
        }

        renderResult(target, ok, title, detail);
        updateBadge(group, ok);
        showToast(ok ? 'Test passed' : 'Test failed', ok ? 'success' : 'danger');
      });
    });
  });
})();
</script>
JS;
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
