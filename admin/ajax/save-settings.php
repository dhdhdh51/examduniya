<?php
/**
 * AJAX: Save a group of settings to the settings table.
 * Admin only. CSRF protected. Returns JSON.
 *
 * Expects POST: group=<group_name>, csrf_token=<token>, and the
 * group's setting_key => value pairs. Only whitelisted keys are saved.
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

$group = $_POST['group'] ?? '';

/**
 * Whitelist of editable setting keys per group. Anything not listed is ignored
 * so arbitrary settings can never be injected through this endpoint.
 */
$allowed = [
    'site' => [
        'site_name', 'site_url', 'site_description',
        'site_logo', 'google_analytics_id', 'per_page',
    ],
    'google' => [
        'google_client_id', 'google_client_secret', 'google_redirect_uri',
    ],
    'gemini' => [
        'gemini_api_key', 'gemini_model',
    ],
    'telegram' => [
        'telegram_bot_token', 'telegram_chat_ids', 'telegram_enabled',
    ],
    'smtp' => [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
        'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_enabled',
    ],
    'payu' => [
        'payu_merchant_key', 'payu_merchant_salt', 'payu_mode',
        'payu_enabled', 'plan_monthly_price', 'plan_yearly_price',
    ],
    'maintenance' => [
        'maintenance_mode',
    ],
];

if (!isset($allowed[$group])) {
    echo json_encode(['success' => false, 'message' => 'Unknown settings group']);
    exit;
}

// Keys that are on/off toggles: forced to '1' or '0'.
$toggle_keys = [
    'telegram_enabled', 'smtp_enabled', 'payu_enabled', 'maintenance_mode',
];

// Allowed enumerated values for certain keys.
$enums = [
    'payu_mode'       => ['test', 'live'],
    'smtp_encryption' => ['tls', 'ssl', 'none'],
];

try {
    $saved = 0;

    // Optional logo upload (General tab). Stored filename in site_logo.
    if ($group === 'site'
        && isset($_FILES['site_logo_file'])
        && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK
    ) {
        $logo = upload_file($_FILES['site_logo_file'], 'site', ['jpg', 'png', 'gif', 'webp']);
        if ($logo === false) {
            echo json_encode(['success' => false, 'message' => 'Logo upload failed. Use jpg, png, gif or webp.']);
            exit;
        }
        set_setting('site_logo', $logo);
        $saved++;
    }

    foreach ($allowed[$group] as $key) {
        if (in_array($key, $toggle_keys, true)) {
            // Toggle: present & truthy => '1', otherwise '0'.
            $raw   = $_POST[$key] ?? '0';
            $value = in_array((string)$raw, ['1', 'on', 'true', 'yes'], true) ? '1' : '0';
        } else {
            if (!array_key_exists($key, $_POST)) {
                continue; // field not submitted — leave existing value untouched
            }
            $value = trim((string)$_POST[$key]);

            // Enum validation
            if (isset($enums[$key]) && !in_array($value, $enums[$key], true)) {
                continue; // ignore invalid enum value
            }

            // Gemini model: accept any model name Google may offer.
            // Keep it to a safe charset (letters, numbers, dot, dash) so it is
            // always URL-safe when used in the API endpoint.
            if ($key === 'gemini_model') {
                if ($value === '' || !preg_match('/^[A-Za-z0-9.\-]{2,60}$/', $value)) {
                    continue; // ignore invalid / empty model name
                }
            }

            // Numeric normalisation
            if (in_array($key, ['per_page', 'smtp_port'], true)) {
                $value = (string)max(1, (int)$value);
            }
            if (in_array($key, ['plan_monthly_price', 'plan_yearly_price'], true)) {
                $value = (string)max(0, (float)$value);
            }
        }

        set_setting($key, $value);
        $saved++;
    }

    echo json_encode([
        'success' => true,
        'message' => ucfirst($group) . ' settings saved successfully.',
        'saved'   => $saved,
    ]);
} catch (Throwable $e) {
    error_log('save-settings error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while saving settings.']);
}
