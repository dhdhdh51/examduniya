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

$exam_type = sanitize($_POST['exam_type'] ?? '');
$topic     = sanitize($_POST['topic'] ?? '');
$num       = max(1, min(20, (int)($_POST['num_questions'] ?? 10)));
$difficulty= sanitize($_POST['difficulty'] ?? 'medium');
$language  = sanitize($_POST['language'] ?? 'English');

if (empty($topic)) {
    echo json_encode(['success' => false, 'error' => 'Topic is required']);
    exit;
}

$prompt = "Generate {$num} MCQ questions for {$exam_type} exam on topic: {$topic}. "
        . "Difficulty: {$difficulty}. Language: {$language}. "
        . "Return ONLY a valid JSON array with no extra text, no markdown, no explanation outside JSON: "
        . "[{\"question\":\"...\",\"options\":[\"A) ...\",\"B) ...\",\"C) ...\",\"D) ...\"],"
        . "\"correct\":\"A\",\"explanation\":\"...\"}]";

// Which AI provider to use: explicit selection, or the configured default.
$provider_key = sanitize($_POST['provider'] ?? '') ?: null;

$result = call_ai($prompt, $provider_key);
if (empty($result['success'])) {
    echo json_encode(['success' => false, 'error' => $result['error'] ?: 'AI request failed']);
    exit;
}

$text = (string) $result['text'];

// Strip markdown code fences if present
$text = preg_replace('/^```(?:json)?\s*/m', '', $text);
$text = preg_replace('/```\s*$/m', '', $text);
$text = trim($text);

// Extract JSON array
if (preg_match('/\[[\s\S]*\]/m', $text, $matches)) {
    $text = $matches[0];
}

$questions = json_decode($text, true);
if (!is_array($questions)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid JSON from AI (' . ($result['provider'] ?: 'provider') . '). Could not parse questions.',
        'raw'     => substr($text, 0, 500),
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'questions' => $questions,
    'count'     => count($questions),
    'provider'  => $result['provider'],
]);
