<?php
/**
 * Generate an SEO blog draft (HTML) via the configured AI provider.
 * Returns JSON: { success, title, content (HTML), meta_description, provider }
 */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'POST required']); exit; }
if (!csrf_verify()) { echo json_encode(['success'=>false,'error'=>'CSRF invalid']); exit; }

$topic    = sanitize($_POST['topic'] ?? '');
$words    = max(250, min(1500, (int)($_POST['words'] ?? 700)));
$language = sanitize($_POST['language'] ?? 'English');
$tone     = sanitize($_POST['tone'] ?? 'Informative');
$category = sanitize($_POST['category'] ?? '');

if ($topic === '') { echo json_encode(['success'=>false,'error'=>'Topic is required']); exit; }

$prompt =
  "You are an expert content writer for an Indian government-exam website called Exam Duniya. "
. "Write a well-structured, original, SEO-friendly blog article.\n"
. "Topic: {$topic}\n"
. ($category ? "Category: {$category}\n" : "")
. "Approximate length: {$words} words. Language: {$language}. Tone: {$tone}.\n"
. "Requirements:\n"
. "- Use clean semantic HTML only for the body: <h2>, <h3>, <p>, <ul>/<li>, <ol>/<li>, <strong>, <blockquote>, and <table class=\"table\"> where useful.\n"
. "- Do NOT include <html>, <head>, <body>, <h1>, inline styles, scripts, or markdown.\n"
. "- Start with a short intro paragraph, then logically sectioned H2/H3 headings.\n"
. "- Be factual; do NOT invent specific exam dates, vacancy numbers or official links. Advise readers to verify on the official website.\n"
. "- End with a brief conclusion.\n"
. "Return ONLY valid minified JSON, no markdown fences, in exactly this shape: "
. "{\"title\":\"...\",\"meta_description\":\"a 150-160 char summary\",\"content\":\"<h2>...</h2><p>...</p>\"}";

$provider_key = sanitize($_POST['provider'] ?? '') ?: null;
$result = call_ai($prompt, $provider_key);
if (empty($result['success'])) {
    echo json_encode(['success'=>false,'error'=>$result['error'] ?: 'AI request failed']);
    exit;
}

$text = trim((string)$result['text']);
// Strip markdown fences if any.
$text = preg_replace('/^```(?:json)?\s*/m', '', $text);
$text = preg_replace('/```\s*$/m', '', $text);
$text = trim($text);

// Extract the JSON object.
if (preg_match('/\{[\s\S]*\}/m', $text, $m)) { $text = $m[0]; }
$data = json_decode($text, true);

if (!is_array($data) || empty($data['content'])) {
    // Fallback: treat the whole response as HTML content.
    $html = $result['text'];
    if (stripos($html, '<') === false) {
        // plain text -> wrap paragraphs
        $parts = array_filter(array_map('trim', preg_split('/\n{2,}/', $html)));
        $html = '';
        foreach ($parts as $para) { $html .= '<p>' . htmlspecialchars($para) . '</p>'; }
    }
    echo json_encode([
        'success' => true,
        'title'   => $topic,
        'meta_description' => mb_substr(trim(strip_tags($result['text'])), 0, 158),
        'content' => $html,
        'provider'=> $result['provider'],
        'note'    => 'AI did not return JSON; used raw output.',
    ]);
    exit;
}

// Light sanitisation: remove script/style/iframe tags from AI output.
$content = (string)$data['content'];
$content = preg_replace('#<\s*(script|style|iframe)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $content);

echo json_encode([
    'success' => true,
    'title'   => trim($data['title'] ?? $topic),
    'meta_description' => mb_substr(trim($data['meta_description'] ?? ''), 0, 160),
    'content' => $content,
    'provider'=> $result['provider'],
]);
