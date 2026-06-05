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
// Subject for grouping/section tabs; defaults to the topic if left blank.
$subject   = sanitize($_POST['subject'] ?? '') ?: $topic;

if (empty($topic)) {
    echo json_encode(['success' => false, 'error' => 'Topic is required']);
    exit;
}

// Optional: questions already in the target test, so the AI is told to avoid
// repeating them and we can filter any that still slip through.
$avoid_list = [];
$existing_fps = [];
$target_test_id = (int)($_POST['target_test_id'] ?? 0);
if ($target_test_id > 0) {
    try {
        $st = $pdo->prepare("SELECT questions_json FROM mock_tests WHERE id = ? LIMIT 1");
        $st->execute([$target_test_id]);
        $ex = json_decode((string)$st->fetchColumn(), true);
        if (is_array($ex)) {
            foreach ($ex as $eq) {
                if (!empty($eq['question'])) {
                    $existing_fps[question_fingerprint($eq)] = true;
                    $avoid_list[] = $eq['question'];
                }
            }
        }
    } catch (Throwable $e) {
        // ignore — table/row may not exist yet
    }
}

$avoid_text = '';
if (!empty($avoid_list)) {
    // Keep the prompt size sane: only send the most recent ~40 questions.
    $recent = array_slice($avoid_list, -40);
    $avoid_text = " Do NOT repeat or rephrase any of these existing questions: "
                . json_encode(array_values($recent)) . ".";
}

// Over-generate a little so that, after removing duplicates, we still have enough.
$ask = min(30, $num + 5);

$prompt = "Generate {$ask} UNIQUE MCQ questions for {$exam_type} exam on subject/topic: {$topic}. "
        . "Difficulty: {$difficulty}. Language: {$language}. "
        . "Every question must be distinct — no duplicates, no near-duplicates, no rephrasing of the same fact."
        . $avoid_text
        . " Return ONLY a valid JSON array with no extra text, no markdown, no explanation outside JSON: "
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

// Tag every question with the chosen subject (used for section tabs) and
// keep the data model clean.
$clean = [];
foreach ($questions as $q) {
    if (!is_array($q) || empty($q['question']) || empty($q['options'])) {
        continue;
    }
    $q['subject'] = $subject;
    $clean[] = $q;
}

// Remove duplicates within this batch AND against the target test's existing
// questions (if a target test was provided).
list($deduped, $removed) = dedupe_questions($clean, $existing_fps);

// Trim to the number the admin actually asked for.
if (count($deduped) > $num) {
    $deduped = array_slice($deduped, 0, $num);
}

echo json_encode([
    'success'      => true,
    'questions'    => array_values($deduped),
    'count'        => count($deduped),
    'subject'      => $subject,
    'duplicates_removed' => $removed,
    'provider'     => $result['provider'],
]);
