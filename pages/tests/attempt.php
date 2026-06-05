<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$test_id = (int)($_GET['test_id'] ?? 0);
if (!$test_id) {
    header('Location: /pages/tests/listing.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM mock_tests WHERE id = ? AND is_active = 1");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

// Access check: free tests or users with successful payment (premium)
$has_access = false;
if ($test['access_type'] === 'free') {
    $has_access = true;
} else {
    // Check if user has a successful payment (premium plan)
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE user_id = ? AND status = 'success' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $has_access = true;
    }
}

if (!$has_access) {
    header('Location: /pages/tests/purchase.php?test_id=' . $test_id);
    exit;
}

// Parse questions
$questions = [];
if (!empty($test['questions_json'])) {
    $questions = json_decode($test['questions_json'], true) ?: [];
}

// Check if there is an existing incomplete attempt (for answer restoration)
$stmt = $pdo->prepare("SELECT id, answers_json FROM user_attempts WHERE user_id = ? AND test_id = ? AND completed_at IS NULL ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $test_id]);
$existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
$saved_answers = [];
if ($existing_attempt && !empty($existing_attempt['answers_json'])) {
    $saved_answers = json_decode($existing_attempt['answers_json'], true) ?: [];
}

$site_name = get_setting('site_name') ?: 'GovExam Portal';

// Show payment success message if coming from payment
$payment_success = '';
if (!empty($_SESSION['payment_success'])) {
    $payment_success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($test['title']) ?> &mdash; <?= htmlspecialchars($site_name) ?></title>
<meta name="csrf-token" content="<?= csrf_token() ?>">

<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome 6 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<!-- Google Fonts Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; }
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: 'Inter', sans-serif;
    background: #f1f5f9;
    overflow: hidden;
}
/* ---- TOP BAR ---- */
.exam-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: #1e293b;
    color: #fff;
    z-index: 1000;
    display: flex;
    align-items: center;
    padding: 0 1rem;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.exam-topbar .exam-title {
    font-weight: 600;
    font-size: 0.95rem;
    max-width: 50%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#timer {
    font-size: 1.2rem;
    letter-spacing: 1px;
    font-variant-numeric: tabular-nums;
    transition: color 0.3s;
}
#timer.timer-warning { color: #f97316; }
#timer.timer-danger  { color: #ef4444; animation: blink 0.8s step-end infinite; }
@keyframes blink { 50% { opacity: 0.3; } }

/* ---- MAIN LAYOUT ---- */
.exam-layout {
    display: flex;
    margin-top: 56px;
    height: calc(100vh - 56px);
    overflow: hidden;
}

/* ---- SECTION TABS ---- */
.section-tabs {
    display: flex;
    gap: .4rem;
    overflow-x: auto;
    padding: .6rem 1rem;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    -webkit-overflow-scrolling: touch;
}
.section-tab {
    flex: 0 0 auto;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: .82rem;
    padding: .4rem .9rem;
    border-radius: 50px;
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.section-tab:hover { border-color: #93c5fd; }
.section-tab.active {
    background: #2563EB;
    border-color: #2563EB;
    color: #fff;
}
.section-tab .sec-count {
    font-size: .7rem;
    opacity: .8;
    margin-left: .2rem;
}
/* ---- PALETTE SIDEBAR ---- */
.exam-palette {
    width: 220px;
    min-width: 220px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    padding: 1rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.exam-palette h6 {
    font-weight: 600;
    color: #334155;
    margin-bottom: .75rem;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.palette-legend {
    display: flex;
    flex-direction: column;
    gap: .35rem;
    margin-bottom: .75rem;
    font-size: .7rem;
    color: #64748b;
}
.palette-legend .btn-q {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: inline-block;
    flex-shrink: 0;
}
.palette-legend span { display: flex; align-items: center; gap: .4rem; }
.palette-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 4px;
    flex-grow: 1;
}
/* ---- PALETTE BUTTON STATES ---- */
.btn-q {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: .75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform .1s;
}
.btn-q:hover { transform: scale(1.1); }
.btn-q.unattempted    { background: #e2e8f0; color: #475569; }
.btn-q.answered       { background: #059669; color: #fff; }
.btn-q.marked         { background: #7c3aed; color: #fff; }
.btn-q.marked-answered{ background: #b45309; color: #fff; }
.btn-q.current        { outline: 3px solid #2563EB; outline-offset: 1px; }

/* ---- QUESTION AREA ---- */
.exam-question-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f8fafc;
}
.question-header {
    display: flex;
    gap: .5rem;
    margin-bottom: 1rem;
    align-items: center;
}
.question-text {
    font-size: 1rem;
    line-height: 1.7;
    color: #1e293b;
    background: #fff;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid #e2e8f0;
    min-height: 80px;
}
/* ---- OPTIONS ---- */
.option-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1rem;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    background: #fff;
    margin-bottom: .5rem;
    transition: border-color .15s, background .15s;
    min-height: 48px;
    user-select: none;
}
.option-item:hover { border-color: #93c5fd; background: #eff6ff; }
.option-item.selected { border-color: #2563EB; background: #eff6ff; }
.option-item input[type=radio] { display: none; }
.option-letter {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .8rem;
    flex-shrink: 0;
    color: #475569;
}
.option-item.selected .option-letter { background: #2563EB; color: #fff; }
.option-text { flex: 1; font-size: .95rem; color: #334155; }

/* ---- NAVIGATION ---- */
.question-nav {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

/* ---- SUBMIT BUTTON ---- */
#btn-submit-main {
    width: 100%;
    margin-top: 1rem;
}

/* ---- MODALS ---- */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.show { display: flex; }
.modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    max-width: 480px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
    animation: slide-up .2s ease;
}
@keyframes slide-up { from { transform: translateY(20px); opacity:0; } to { transform: translateY(0); opacity:1; } }

/* ---- SUMMARY TABLE ---- */
.summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin: 1rem 0; }
.summary-item { background: #f8fafc; border-radius: 6px; padding: .5rem .75rem; font-size: .85rem; }
.summary-item .label { color: #64748b; font-size: .75rem; }
.summary-item .value { font-weight: 600; }

/* ---- AUTO-SUBMIT COUNTDOWN ---- */
#auto-submit-modal .countdown-big { font-size: 3rem; font-weight: 700; color: #ef4444; text-align: center; }

/* ---- SCROLLBAR ---- */
.exam-palette::-webkit-scrollbar, .exam-question-area::-webkit-scrollbar { width: 4px; }
.exam-palette::-webkit-scrollbar-track, .exam-question-area::-webkit-scrollbar-track { background: transparent; }
.exam-palette::-webkit-scrollbar-thumb, .exam-question-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

/* ---- TOAST ---- */
.toast-container { position: fixed; top: 70px; right: 1rem; z-index: 3000; display: flex; flex-direction: column; gap: .5rem; }
.toast-item { background: #1e293b; color: #fff; border-radius: 6px; padding: .6rem 1rem; font-size: .85rem; display: flex; align-items: center; gap: .5rem; box-shadow: 0 4px 12px rgba(0,0,0,.2); animation: fade-in .2s ease; }
.toast-item.toast-warning { background: #b45309; }
.toast-item.toast-danger  { background: #dc2626; }
.toast-item.toast-success { background: #059669; }
@keyframes fade-in { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }

/* ---- MOBILE PALETTE TOGGLE (hidden on desktop) ---- */
.palette-fab {
    display: none;
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 1500;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #2563EB;
    color: #fff;
    border: none;
    box-shadow: 0 6px 18px rgba(37,99,235,.5);
    font-size: 1.1rem;
}
.palette-backdrop {
    display: none;
    position: fixed;
    inset: 56px 0 0 0;
    background: rgba(0,0,0,.45);
    z-index: 1400;
}
.palette-backdrop.show { display: block; }

@media (max-width: 768px) {
    .exam-layout { position: relative; }

    /* Palette becomes a slide-in drawer from the left */
    .exam-palette {
        position: fixed;
        top: 56px;
        left: 0;
        bottom: 0;
        width: 260px;
        min-width: 260px;
        z-index: 1450;
        transform: translateX(-100%);
        transition: transform .25s ease;
        box-shadow: 4px 0 18px rgba(0,0,0,.15);
    }
    .exam-palette.open { transform: translateX(0); }
    .exam-palette h6, .palette-legend, #btn-submit-main { display: flex; }
    .palette-grid { grid-template-columns: repeat(6, 1fr); }

    .palette-fab { display: flex; align-items: center; justify-content: center; }

    .exam-question-area { padding: 1rem .9rem 5rem; }
    .question-text { padding: 1rem; font-size: .98rem; }
    .option-item { padding: .7rem .85rem; }
    .option-text { font-size: .9rem; }

    /* Stack nav buttons, full width, easy to tap */
    .question-nav { gap: .5rem; }
    .question-nav .btn { flex: 1 1 calc(50% - .5rem); font-size: .85rem; padding: .55rem .5rem; }
    .question-nav #btn-next { flex-basis: 100%; margin-left: 0 !important; }

    .exam-topbar .exam-title { max-width: 40%; font-size: .82rem; }
    #timer { font-size: 1.05rem; }

    .section-tabs { padding: .5rem .75rem; }
}

@media (max-width: 380px) {
    .palette-grid { grid-template-columns: repeat(5, 1fr); }
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="exam-topbar">
    <div class="exam-title">
        <i class="fa-solid fa-file-alt me-2"></i><?= htmlspecialchars($test['title']) ?>
    </div>
    <div class="d-flex gap-3 align-items-center">
        <div id="timer" class="fw-bold">
            <?php
            $h = intdiv((int)$test['duration_minutes'], 60);
            $m = (int)$test['duration_minutes'] % 60;
            echo ($h > 0 ? sprintf('%02d:', $h) : '') . sprintf('%02d:00', $m);
            ?>
        </div>
        <button class="btn btn-sm btn-outline-light" id="btn-fullscreen" title="Toggle Fullscreen">
            <i class="fas fa-expand" id="fullscreen-icon"></i>
        </button>
    </div>
</div>

<!-- MAIN LAYOUT -->
<div class="exam-layout">

    <!-- Mobile palette backdrop -->
    <div class="palette-backdrop" id="palette-backdrop"></div>

    <!-- PALETTE SIDEBAR -->
    <div class="exam-palette" id="exam-palette">
        <h6>Question Palette</h6>
        <div class="palette-legend">
            <span><span class="btn-q answered"></span> Answered</span>
            <span><span class="btn-q marked"></span> Marked</span>
            <span><span class="btn-q marked-answered"></span> Marked &amp; Answered</span>
            <span><span class="btn-q unattempted"></span> Not Visited</span>
        </div>
        <div class="palette-grid" id="palette-grid"></div>
        <button class="btn btn-danger btn-sm w-100 mt-3" id="btn-submit-main">
            <i class="fa-solid fa-paper-plane me-1"></i>Submit Test
        </button>
    </div>

    <!-- QUESTION AREA -->
    <div class="exam-question-area">

        <!-- Section tabs (only shown when the test has multiple subjects) -->
        <div class="section-tabs" id="section-tabs" style="display:none"></div>

        <?php if ($payment_success): ?>
            <div class="alert alert-success mb-3">
                <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($payment_success) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                This test has no questions loaded. Please contact admin.
            </div>
        <?php else: ?>

        <div class="question-header">
            <span class="badge bg-primary fs-6">
                Question <span id="current-q-num">1</span> of <?= count($questions) ?>
            </span>
            <span class="badge bg-secondary" id="q-status-badge">Not Visited</span>
        </div>

        <div class="question-text" id="question-text">Loading...</div>

        <div class="options-list" id="options-list"></div>

        <div class="question-nav">
            <button class="btn btn-outline-secondary" id="btn-prev">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <button class="btn btn-outline-danger" id="btn-clear">
                <i class="fas fa-eraser"></i> Clear
            </button>
            <button class="btn btn-outline-info" id="btn-mark">
                <i class="fas fa-bookmark"></i> Mark for Review
            </button>
            <button class="btn btn-primary ms-auto" id="btn-next">
                Save &amp; Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- ============== TAB SWITCH WARNING MODAL ============== -->
<div class="modal-overlay" id="tab-warn-modal">
    <div class="modal-box text-center">
        <i class="fa-solid fa-triangle-exclamation fa-3x text-warning mb-3"></i>
        <h5 class="fw-bold">Tab Switch Detected!</h5>
        <p class="text-muted" id="tab-warn-msg">You switched tabs. This activity is being recorded.</p>
        <p class="small text-danger">Warning <strong id="switch-count-display">1</strong> of 3. Auto-submit after 3 switches.</p>
        <button class="btn btn-primary" onclick="document.getElementById('tab-warn-modal').classList.remove('show')">
            Return to Test
        </button>
    </div>
</div>

<!-- ============== AUTO-SUBMIT COUNTDOWN MODAL ============== -->
<div class="modal-overlay" id="auto-submit-modal">
    <div class="modal-box text-center">
        <i class="fa-solid fa-clock fa-2x text-danger mb-2"></i>
        <h5 class="fw-bold" id="auto-submit-title">Time is Up!</h5>
        <p class="text-muted" id="auto-submit-msg">Your test will be submitted automatically in:</p>
        <div class="countdown-big" id="auto-submit-countdown">10</div>
        <p class="small text-muted mt-2">Submitting your answers...</p>
    </div>
</div>

<!-- ============== CONFIRM SUBMIT MODAL ============== -->
<div class="modal-overlay" id="confirm-submit-modal">
    <div class="modal-box">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Submit Test?</h5>
        <div class="summary-grid" id="submit-summary"></div>
        <p class="text-muted small mb-3">Once submitted, you cannot change your answers.</p>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary flex-fill" onclick="document.getElementById('confirm-submit-modal').classList.remove('show')">
                <i class="fas fa-arrow-left me-1"></i>Back to Test
            </button>
            <button class="btn btn-danger flex-fill" id="btn-confirm-submit">
                <i class="fas fa-check me-1"></i>Submit Now
            </button>
        </div>
    </div>
</div>

<!-- Mobile palette toggle button -->
<button class="palette-fab" id="palette-fab" title="Question Palette" aria-label="Question Palette">
    <i class="fa-solid fa-table-cells"></i>
</button>

<!-- Toast container -->
<div class="toast-container" id="exam-toast-container"></div>

<script>
'use strict';

// ---- DATA FROM PHP ----
const questions   = <?= json_encode(array_values($questions)) ?>;
const testId      = <?= (int)$test_id ?>;
const totalTime   = <?= (int)$test['duration_minutes'] ?> * 60;
const savedAnswers = <?= json_encode($saved_answers) ?>;

// ---- STATE ----
let currentQ    = 0;
let answers     = {};  // index -> letter (A/B/C/D)
let marked      = {};  // index -> bool
let tabSwitches = 0;
let startTime   = Date.now();
let timerObj    = null;
let autoSubmitting = false;

// ---- SECTIONS (group questions by subject) ----
// Each question keeps its original index; we just group them for navigation.
let sections   = [];   // [{ name, indices: [globalIdx,...] }]
let activeSection = 0;

function buildSections() {
    var map = {};
    var order = [];
    questions.forEach(function (q, i) {
        var name = (q && (q.subject || q.section || q.topic)) ? String(q.subject || q.section || q.topic) : 'General';
        if (!map[name]) { map[name] = []; order.push(name); }
        map[name].push(i);
    });
    sections = order.map(function (name) { return { name: name, indices: map[name] }; });
}

function sectionOfQuestion(index) {
    for (var s = 0; s < sections.length; s++) {
        if (sections[s].indices.indexOf(index) !== -1) return s;
    }
    return 0;
}

function renderSectionTabs() {
    var wrap = document.getElementById('section-tabs');
    if (!wrap) return;
    // Only show tabs when there is more than one section.
    if (sections.length <= 1) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'flex';
    wrap.innerHTML = '';
    sections.forEach(function (sec, idx) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'section-tab' + (idx === activeSection ? ' active' : '');
        btn.innerHTML = escapeText(sec.name) + '<span class="sec-count">(' + sec.indices.length + ')</span>';
        btn.addEventListener('click', function () {
            activeSection = idx;
            loadQuestion(sec.indices[0]); // jump to first question of this section
        });
        wrap.appendChild(btn);
    });
}

// ---- RESTORE SAVED ANSWERS ----
if (savedAnswers && typeof savedAnswers === 'object') {
    Object.keys(savedAnswers).forEach(function(k) {
        var idx = parseInt(k, 10);
        if (!isNaN(idx)) answers[idx] = savedAnswers[k];
    });
}

// ---- INIT ----
document.addEventListener('DOMContentLoaded', function() {
    if (questions.length === 0) return;
    buildSections();
    renderSectionTabs();
    renderPalette();
    loadQuestion(0);
    initTimer();
    initFullscreen();
    initAntiCheat();
    initAutoSave();
    initPaletteDrawer();

    document.getElementById('btn-next').addEventListener('click', function() {
        if (currentQ < questions.length - 1) loadQuestion(currentQ + 1);
    });
    document.getElementById('btn-prev').addEventListener('click', function() {
        if (currentQ > 0) loadQuestion(currentQ - 1);
    });
    document.getElementById('btn-clear').addEventListener('click', function() {
        delete answers[currentQ];
        loadQuestion(currentQ);
    });
    document.getElementById('btn-mark').addEventListener('click', function() {
        marked[currentQ] = !marked[currentQ];
        renderPalette();
        updateStatusBadge();
    });
    document.getElementById('btn-submit-main').addEventListener('click', showConfirmSubmit);
    document.getElementById('btn-confirm-submit').addEventListener('click', submitTest);
});

// ---- PALETTE ----
function renderPalette() {
    var grid = document.getElementById('palette-grid');
    if (!grid) return;
    grid.innerHTML = '';
    questions.forEach(function(q, i) {
        var btn = document.createElement('button');
        btn.className = 'btn-q ' + getStatus(i) + (i === currentQ ? ' current' : '');
        btn.textContent = i + 1;
        btn.title = 'Question ' + (i + 1);
        (function(idx) {
            btn.addEventListener('click', function() { loadQuestion(idx); });
        })(i);
        grid.appendChild(btn);
    });
}

function getStatus(i) {
    if (answers[i] && marked[i]) return 'marked-answered';
    if (marked[i])   return 'marked';
    if (answers[i])  return 'answered';
    return 'unattempted';
}

// ---- LOAD QUESTION ----
function loadQuestion(index) {
    currentQ = index;
    var q = questions[index];
    if (!q) return;

    document.getElementById('current-q-num').textContent = index + 1;

    var qtEl = document.getElementById('question-text');
    if (qtEl) qtEl.innerHTML = q.question || q.text || '';

    var optionsEl = document.getElementById('options-list');
    if (optionsEl) {
        var opts = q.options || [];
        var letters = ['A', 'B', 'C', 'D'];
        var html = '';
        opts.forEach(function(opt, i) {
            var letter  = letters[i] || String.fromCharCode(65 + i);
            var sel     = (answers[index] === letter) ? ' selected' : '';
            html += '<label class="option-item' + sel + '" data-letter="' + letter + '">' +
                    '<input type="radio" name="answer" value="' + letter + '"' + (sel ? ' checked' : '') + '>' +
                    '<span class="option-letter">' + letter + '</span>' +
                    '<span class="option-text">' + escapeText(String(opt)) + '</span>' +
                    '</label>';
        });
        optionsEl.innerHTML = html;

        optionsEl.querySelectorAll('.option-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var letter = this.dataset.letter;
                answers[currentQ] = letter;
                optionsEl.querySelectorAll('.option-item').forEach(function(x) {
                    x.classList.remove('selected');
                    x.querySelector('input').checked = false;
                });
                this.classList.add('selected');
                this.querySelector('input').checked = true;
                renderPalette();
                updateStatusBadge();
            });
        });
    }

    renderPalette();
    updateStatusBadge();

    // Keep the active section tab in sync with the current question.
    var sec = sectionOfQuestion(index);
    if (sec !== activeSection) {
        activeSection = sec;
    }
    renderSectionTabs();

    // On mobile, close the palette drawer after picking a question.
    closePaletteDrawer();
}

// ---- MOBILE PALETTE DRAWER ----
function initPaletteDrawer() {
    var fab      = document.getElementById('palette-fab');
    var backdrop = document.getElementById('palette-backdrop');
    if (fab) {
        fab.addEventListener('click', function () {
            var pal = document.getElementById('exam-palette');
            if (pal) pal.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('show');
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closePaletteDrawer);
    }
}

function closePaletteDrawer() {
    var pal      = document.getElementById('exam-palette');
    var backdrop = document.getElementById('palette-backdrop');
    if (pal) pal.classList.remove('open');
    if (backdrop) backdrop.classList.remove('show');
}

function updateStatusBadge() {
    var badge = document.getElementById('q-status-badge');
    if (!badge) return;
    var s = getStatus(currentQ);
    var labels = {
        'answered': 'Answered',
        'marked': 'Marked for Review',
        'marked-answered': 'Marked & Answered',
        'unattempted': 'Not Visited'
    };
    badge.textContent = labels[s] || 'Not Visited';
    badge.className   = 'badge ' + ({
        'answered': 'bg-success',
        'marked': 'bg-purple',
        'marked-answered': 'bg-warning text-dark',
        'unattempted': 'bg-secondary'
    }[s] || 'bg-secondary');
}

// ---- TIMER ----
function initTimer() {
    var timerEl = document.getElementById('timer');
    if (!timerEl) return;

    timerObj = new CountdownTimer(totalTime,
        function(remaining) {
            timerEl.textContent = formatSeconds(remaining);
            if (remaining <= 0) {
                timerEl.className = 'fw-bold timer-danger';
            } else if (remaining <= 300) {
                timerEl.className = 'fw-bold timer-danger';
            } else if (remaining <= 600) {
                timerEl.className = 'fw-bold timer-warning';
            }
        },
        function() {
            triggerAutoSubmit('Time is Up! Submitting your test...');
        }
    );
    timerObj.start();
}

function formatSeconds(seconds) {
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    var parts = [];
    if (h > 0) parts.push(pad2(h));
    parts.push(pad2(m));
    parts.push(pad2(s));
    return parts.join(':');
}

function pad2(n) { return n < 10 ? '0' + n : String(n); }

// ---- FULLSCREEN ----
function initFullscreen() {
    if (typeof enterFullscreen === 'function') {
        try { enterFullscreen(document.documentElement); } catch(e) {}
    }

    document.addEventListener('fullscreenchange', function() {
        var icon = document.getElementById('fullscreen-icon');
        if (document.fullscreenElement) {
            if (icon) { icon.className = 'fas fa-compress'; }
        } else {
            if (icon) { icon.className = 'fas fa-expand'; }
            showExamToast('Warning: Please stay in fullscreen for best exam experience', 'warning');
        }
    });

    var fsBtn = document.getElementById('btn-fullscreen');
    if (fsBtn) {
        fsBtn.addEventListener('click', function() {
            if (document.fullscreenElement) {
                if (typeof exitFullscreen === 'function') exitFullscreen();
            } else {
                if (typeof enterFullscreen === 'function') {
                    try { enterFullscreen(document.documentElement); } catch(e) {}
                }
            }
        });
    }
}

// ---- ANTI-CHEAT ----
function initAntiCheat() {
    // Disable right-click
    document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
    // Disable text selection
    document.body.style.userSelect = 'none';
    document.body.style.webkitUserSelect = 'none';

    // Tab switch detection
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            tabSwitches++;
            if (tabSwitches >= 3) {
                triggerAutoSubmit('Too many tab switches! Auto-submitting...');
            } else {
                document.getElementById('switch-count-display').textContent = tabSwitches;
                document.getElementById('tab-warn-modal').classList.add('show');
            }
        }
    });
}

// ---- AUTO-SAVE ----
function initAutoSave() {
    setInterval(function() {
        if (autoSubmitting) return;
        var token = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = token ? token.content : '';
        fetch('/api/save-answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ test_id: testId, answers: answers, marked: marked })
        }).catch(function() {});
    }, 30000);
}

// ---- AUTO-SUBMIT ----
function triggerAutoSubmit(message) {
    if (autoSubmitting) return;
    autoSubmitting = true;
    if (timerObj) timerObj.stop();

    var modal    = document.getElementById('auto-submit-modal');
    var titleEl  = document.getElementById('auto-submit-title');
    var countEl  = document.getElementById('auto-submit-countdown');
    if (titleEl) titleEl.textContent = message || 'Submitting...';
    if (modal)   modal.classList.add('show');

    var count = 10;
    if (countEl) countEl.textContent = count;
    var iv = setInterval(function() {
        count--;
        if (countEl) countEl.textContent = count;
        if (count <= 0) {
            clearInterval(iv);
            submitTest();
        }
    }, 1000);
}

// ---- CONFIRM SUBMIT ----
function showConfirmSubmit() {
    var answered    = Object.keys(answers).length;
    var markedCount = Object.keys(marked).filter(function(k) { return marked[k]; }).length;
    var unattempted = questions.length - answered;

    var summaryEl = document.getElementById('submit-summary');
    if (summaryEl) {
        summaryEl.innerHTML =
            '<div class="summary-item"><div class="label">Total Questions</div><div class="value">' + questions.length + '</div></div>' +
            '<div class="summary-item"><div class="label text-success">Answered</div><div class="value text-success">' + answered + '</div></div>' +
            '<div class="summary-item"><div class="label text-danger">Unattempted</div><div class="value text-danger">' + unattempted + '</div></div>' +
            '<div class="summary-item"><div class="label text-warning">Marked</div><div class="value text-warning">' + markedCount + '</div></div>';
    }
    document.getElementById('confirm-submit-modal').classList.add('show');
}

// ---- SUBMIT ----
function submitTest() {
    if (timerObj) timerObj.stop();
    var timeTaken  = Math.floor((Date.now() - startTime) / 1000);
    var token = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = token ? token.content : '';

    document.getElementById('confirm-submit-modal').classList.remove('show');
    document.getElementById('auto-submit-modal').classList.add('show');
    var titleEl = document.getElementById('auto-submit-title');
    if (titleEl) titleEl.textContent = 'Submitting...';
    var msgEl = document.getElementById('auto-submit-msg');
    if (msgEl) msgEl.textContent = 'Please wait while we calculate your score.';
    var countEl = document.getElementById('auto-submit-countdown');
    if (countEl) countEl.textContent = '';

    fetch('/api/submit-test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ test_id: testId, answers: answers, time_taken: timeTaken })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else if (data.error) {
            showExamToast('Error: ' + data.error, 'danger');
            document.getElementById('auto-submit-modal').classList.remove('show');
            autoSubmitting = false;
        }
    })
    .catch(function() {
        showExamToast('Submission failed. Please try again.', 'danger');
        document.getElementById('auto-submit-modal').classList.remove('show');
        autoSubmitting = false;
    });
}

// ---- TOAST ----
function showExamToast(msg, type) {
    var c   = document.getElementById('exam-toast-container');
    if (!c) return;
    var div = document.createElement('div');
    div.className = 'toast-item toast-' + (type || 'success');
    div.textContent = msg;
    c.appendChild(div);
    setTimeout(function() { if (div.parentNode) div.parentNode.removeChild(div); }, 4000);
}

function escapeText(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

// ---- COUNTDOWN TIMER CLASS (inline) ----
function CountdownTimer(totalSeconds, onTick, onComplete) {
    this.remaining  = totalSeconds;
    this.onTick     = onTick     || function() {};
    this.onComplete = onComplete || function() {};
    this._interval  = null;
    this._running   = false;
}
CountdownTimer.prototype.start = function() {
    if (this._running) return;
    this._running = true;
    var self = this;
    self.onTick(self.remaining);
    self._interval = setInterval(function() {
        self.remaining--;
        if (self.remaining <= 0) {
            self.remaining = 0;
            self.stop();
            self.onTick(0);
            self.onComplete();
        } else {
            self.onTick(self.remaining);
        }
    }, 1000);
};
CountdownTimer.prototype.stop = function() {
    if (this._interval) { clearInterval(this._interval); this._interval = null; }
    this._running = false;
};

// ---- FULLSCREEN HELPERS (inline) ----
function enterFullscreen(elem) {
    elem = elem || document.documentElement;
    if (elem.requestFullscreen)            return elem.requestFullscreen();
    if (elem.webkitRequestFullscreen)      return elem.webkitRequestFullscreen();
    if (elem.mozRequestFullScreen)         return elem.mozRequestFullScreen();
    if (elem.msRequestFullscreen)          return elem.msRequestFullscreen();
}
function exitFullscreen() {
    if (document.exitFullscreen)           return document.exitFullscreen();
    if (document.webkitExitFullscreen)     return document.webkitExitFullscreen();
    if (document.mozCancelFullScreen)      return document.mozCancelFullScreen();
    if (document.msExitFullscreen)         return document.msExitFullscreen();
}
</script>
</body>
</html>
