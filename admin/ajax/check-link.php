<?php
/** Admin — check a single URL's reachability (used by Broken-Link Checker). */
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    echo json_encode(['ok'=>false,'error'=>'Bad request']); exit;
}
$url = trim($_POST['url'] ?? '');
if (!preg_match('#^https?://#i', $url)) { echo json_encode(['ok'=>false,'code'=>0,'error'=>'Invalid URL']); exit; }

function probe($url, $method) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => ($method === 'HEAD'),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'ExamDuniya-LinkChecker/1.0',
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $err];
}

if (!function_exists('curl_init')) { echo json_encode(['ok'=>false,'code'=>0,'error'=>'cURL not available on server']); exit; }

[$code, $err] = probe($url, 'HEAD');
if ($code === 0 || $code === 405 || $code === 403) {
    // Some servers block HEAD — retry with GET.
    [$code, $err] = probe($url, 'GET');
}
$ok = ($code >= 200 && $code < 400);
echo json_encode(['ok'=>$ok, 'code'=>$code, 'error'=>$ok ? null : ($err ?: ('HTTP ' . $code))]);
