<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!csrf_verify()) {
    echo json_encode(['success' => false, 'error' => 'CSRF invalid']);
    exit;
}

$target_test_id = (int)($_POST['target_test_id'] ?? 0);
$new_test_name  = trim($_POST['new_test_name'] ?? '');
$questions_json = $_POST['questions_json'] ?? '';

$new_questions = json_decode($questions_json, true);
if (!is_array($new_questions) || empty($new_questions)) {
    echo json_encode(['success' => false, 'error' => 'No valid questions provided']);
    exit;
}

if ($target_test_id > 0) {
    // Add to existing test
    $test = $pdo->prepare("SELECT id, questions_json, total_questions FROM mock_tests WHERE id = ? LIMIT 1");
    $test->execute([$target_test_id]);
    $test = $test->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo json_encode(['success' => false, 'error' => 'Test not found']);
        exit;
    }

    $existing = [];
    if (!empty($test['questions_json'])) {
        $decoded = json_decode($test['questions_json'], true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    // Build fingerprints of existing questions, then drop any incoming
    // duplicates so the same question never repeats in one test.
    $existing_fps = [];
    foreach ($existing as $eq) {
        if (!empty($eq['question'])) {
            $existing_fps[question_fingerprint($eq)] = true;
        }
    }
    list($unique_new, $removed) = dedupe_questions($new_questions, $existing_fps);

    $merged = array_merge($existing, $unique_new);
    $total  = count($merged);

    $stmt = $pdo->prepare("UPDATE mock_tests SET questions_json=?, total_questions=? WHERE id=?");
    $stmt->execute([json_encode($merged), $total, $target_test_id]);

    echo json_encode([
        'success' => true,
        'test_id' => $target_test_id,
        'total_questions' => $total,
        'added' => count($unique_new),
        'duplicates_removed' => $removed,
    ]);
    exit;
}

// Create new test
if (empty($new_test_name)) {
    echo json_encode(['success' => false, 'error' => 'Test name is required when creating a new test']);
    exit;
}

$slug_base = slug($new_test_name);
$slug = $slug_base;
$check = $pdo->prepare("SELECT id FROM mock_tests WHERE slug = ? LIMIT 1");
$check->execute([$slug]);
if ($check->fetchColumn()) {
    $slug = $slug_base . '-' . time();
}

$created_by = (int)($_SESSION['user_id'] ?? 0);

// De-duplicate within the new batch before creating the test.
list($unique_new, $removed) = dedupe_questions($new_questions);
$total = count($unique_new);

$stmt = $pdo->prepare(
    "INSERT INTO mock_tests (title, slug, total_questions, questions_json, is_active, created_by)
     VALUES (?, ?, ?, ?, 0, ?)"
);
$stmt->execute([
    $new_test_name, $slug, $total, json_encode(array_values($unique_new)), $created_by ?: null
]);
$new_id = (int)$pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'test_id' => $new_id,
    'total_questions' => $total,
    'duplicates_removed' => $removed,
]);
