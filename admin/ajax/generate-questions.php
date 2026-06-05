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

$api_key = get_setting('gemini_api_key');
if (empty($api_key)) {
    echo json_encode(['success' => false, 'error' => 'Gemini API key not configured in settings']);
    exit;
}

$model = get_setting('gemini_model') ?: 'gemini-3.5-flash';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model)
     . ':generateContent?key=' . urlencode($api_key);

$payload = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 45,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err || $response === false) {
    echo json_encode(['success' => false, 'error' => 'cURL error: ' . $curl_err]);
    exit;
}

if ($http_code !== 200) {
    $err_data = json_decode($response, true);
    $err_msg = $err_data['error']['message'] ?? "HTTP $http_code";
    echo json_encode(['success' => false, 'error' => $err_msg]);
    exit;
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

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
        'error'   => 'Invalid JSON from Gemini. Could not parse questions.',
        'raw'     => substr($text, 0, 500),
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'questions' => $questions,
    'count'     => count($questions),
]);
