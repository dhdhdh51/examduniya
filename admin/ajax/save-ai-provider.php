<?php
/**
 * AJAX: save a single AI provider's settings (api key, model, endpoint,
 * enabled, default). Admin only. CSRF protected. Returns JSON.
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
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh.']);
    exit;
}

$key = trim((string)($_POST['provider_key'] ?? ''));
if ($key === '') {
    echo json_encode(['success' => false, 'message' => 'Missing provider key']);
    exit;
}

$api_key  = trim((string)($_POST['api_key'] ?? ''));
$model    = trim((string)($_POST['model'] ?? ''));
$endpoint = trim((string)($_POST['endpoint'] ?? ''));
$enabled  = in_array((string)($_POST['enabled'] ?? '0'), ['1', 'on', 'true', 'yes'], true) ? 1 : 0;
$make_default = in_array((string)($_POST['is_default'] ?? '0'), ['1', 'on', 'true', 'yes'], true) ? 1 : 0;

// Basic endpoint sanity (must be http/https if provided).
if ($endpoint !== '' && !preg_match('#^https?://#i', $endpoint)) {
    echo json_encode(['success' => false, 'message' => 'Endpoint must start with http:// or https://']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM ai_providers WHERE provider_key = ? LIMIT 1");
    $stmt->execute([$key]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Unknown provider']);
        exit;
    }

    $upd = $pdo->prepare(
        "UPDATE ai_providers
         SET api_key = ?, model = ?, endpoint = ?, enabled = ?, is_default = ?
         WHERE provider_key = ?"
    );
    $upd->execute([$api_key, $model, $endpoint, $enabled, $make_default, $key]);

    // A default must be enabled, and only one provider can be default.
    if ($make_default) {
        $pdo->prepare("UPDATE ai_providers SET is_default = 0 WHERE provider_key <> ?")->execute([$key]);
        $pdo->prepare("UPDATE ai_providers SET enabled = 1 WHERE provider_key = ?")->execute([$key]);
    }

    echo json_encode(['success' => true, 'message' => 'Provider saved.']);
} catch (Throwable $e) {
    error_log('save-ai-provider: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while saving.']);
}
