<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'AI Question Generator';
$active_menu = 'tests';

// Load existing tests for "add to test" dropdown
$tests = $pdo->query("SELECT id, title FROM mock_tests WHERE is_active=1 ORDER BY title ASC")
             ->fetchAll(PDO::FETCH_ASSOC);

// Load enabled AI providers for the provider selector
$ai_providers = [];
try {
    $ai_providers = $pdo->query("SELECT provider_key, name, is_default FROM ai_providers WHERE enabled = 1 ORDER BY is_default DESC, sort_order ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $ai_providers = [];
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-robot me-2"></i>AI Question Generator</h2>
    <a href="/admin/tests/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to Tests
    </a>
  </div>

  <div class="row g-4">
    <!-- Generator form -->
    <div class="col-md-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">
          <i class="fas fa-sliders me-2"></i>Configuration
        </div>
        <div class="card-body">
          <form id="generate-form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">AI Provider</label>
              <?php if (empty($ai_providers)): ?>
                <div class="alert alert-warning py-2 mb-0 small">
                  No AI provider enabled. <a href="/admin/ai/">Configure providers</a>.
                </div>
              <?php else: ?>
                <select name="provider" id="ai-provider" class="form-select">
                  <?php foreach ($ai_providers as $ap): ?>
                    <option value="<?= htmlspecialchars($ap['provider_key']) ?>">
                      <?= htmlspecialchars($ap['name']) ?><?= $ap['is_default'] ? ' (default)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Manage keys/models in <a href="/admin/ai/">AI Providers</a>.</div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Exam Type</label>
              <select name="exam_type" id="exam-type" class="form-select">
                <option value="SSC">SSC (Combined Graduate Level)</option>
                <option value="UPSC">UPSC (Civil Services)</option>
                <option value="Railway">Railway (RRB NTPC)</option>
                <option value="Banking">Banking (IBPS/SBI)</option>
                <option value="Defence">Defence (CDS/NDA)</option>
                <option value="StatePSC">State PSC</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Topic / Subject</label>
              <input type="text" name="topic" id="topic" class="form-control"
                     placeholder="e.g. Indian History, Quantitative Aptitude">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Assign to Subject / Section</label>
              <input type="text" name="subject" id="subject" class="form-control"
                     placeholder="e.g. General Awareness" list="subject-suggestions">
              <datalist id="subject-suggestions">
                <option value="General Awareness">
                <option value="Quantitative Aptitude">
                <option value="Reasoning">
                <option value="English">
                <option value="General Science">
                <option value="Current Affairs">
                <option value="Computer Knowledge">
              </datalist>
              <div class="form-text">Questions get grouped under this subject as a section tab in the test. Leave blank to use the Topic.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Number of Questions</label>
              <select name="num_questions" id="num-questions" class="form-select">
                <option value="5">5 Questions</option>
                <option value="10" selected>10 Questions</option>
                <option value="15">15 Questions</option>
                <option value="20">20 Questions</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Difficulty</label>
              <select name="difficulty" id="difficulty" class="form-select">
                <option value="easy">Easy</option>
                <option value="medium" selected>Medium</option>
                <option value="hard">Hard</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Language</label>
              <select name="language" id="language" class="form-select">
                <option value="English">English</option>
                <option value="Hindi">Hindi</option>
              </select>
            </div>
            <button type="button" id="generate-btn" class="btn btn-success w-100">
              <i class="fas fa-wand-magic-sparkles me-1"></i>Generate with AI
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Results section -->
    <div class="col-md-8">
      <!-- Status message -->
      <div id="generate-status" class="mb-3" style="display:none">
        <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
          <div class="spinner-border spinner-border-sm" id="generate-spinner"></div>
          <span id="generate-status-msg">Generating questions...</span>
        </div>
      </div>

      <div id="questions-result" style="display:none">
        <div class="card shadow-sm border-0 mb-3">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Generated Questions <span class="badge bg-success ms-1" id="result-count">0</span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-results">
              <i class="fas fa-times me-1"></i>Clear
            </button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered mb-0" id="questions-table">
                <thead class="table-light">
                  <tr>
                    <th style="width:40px">#</th>
                    <th>Question</th>
                    <th style="width:120px">Subject</th>
                    <th style="width:70px">Correct</th>
                    <th style="width:100px">Explanation</th>
                  </tr>
                </thead>
                <tbody id="questions-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Save to Test -->
        <div class="card shadow-sm border-0">
          <div class="card-header bg-white fw-semibold">
            <i class="fas fa-floppy-disk me-2"></i>Save Questions to Test
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Select Existing Test</label>
                <select id="target-test" class="form-select">
                  <option value="">-- Create New Test --</option>
                  <?php foreach ($tests as $t): ?>
                  <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="button" id="save-to-test-btn" class="btn btn-primary w-100">
                  <i class="fas fa-save me-1"></i>Save to Test
                </button>
              </div>
              <div id="new-test-name-wrap" class="col-12">
                <label class="form-label">New Test Title (if creating new)</label>
                <input type="text" id="new-test-name" class="form-control" placeholder="Test title...">
              </div>
            </div>
            <div id="save-result" class="mt-3"></div>
          </div>
        </div>
      </div>

      <div id="generate-error" class="alert alert-danger" style="display:none"></div>
    </div>
  </div>

</div>
</div>

<?php
$csrf = csrf_token();
$extra_js = '<script>
var generatedQuestions = [];
var csrfToken = ' . json_encode($csrf) . ';

document.getElementById("generate-btn").addEventListener("click", function() {
    var examType = document.getElementById("exam-type").value;
    var topic = document.getElementById("topic").value.trim();
    var subject = document.getElementById("subject").value.trim();
    var numQ = document.getElementById("num-questions").value;
    var difficulty = document.getElementById("difficulty").value;
    var language = document.getElementById("language").value;
    var providerEl = document.getElementById("ai-provider");
    var provider = providerEl ? providerEl.value : "";
    var targetTest = document.getElementById("target-test").value;

    if (!topic) {
        alert("Please enter a topic.");
        return;
    }

    document.getElementById("generate-status").style.display = "block";
    document.getElementById("generate-spinner").style.display = "inline-block";
    document.getElementById("generate-status-msg").textContent = "Generating " + numQ + " questions with AI...";
    document.getElementById("questions-result").style.display = "none";
    document.getElementById("generate-error").style.display = "none";

    var body = new URLSearchParams({
        csrf_token: csrfToken,
        provider: provider,
        exam_type: examType,
        topic: topic,
        subject: subject,
        target_test_id: targetTest || "",
        num_questions: numQ,
        difficulty: difficulty,
        language: language
    });

    fetch("/admin/ajax/generate-questions.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: body.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById("generate-status").style.display = "none";
        if (!res.success) {
            document.getElementById("generate-error").style.display = "block";
            document.getElementById("generate-error").textContent = "Error: " + (res.error || "Unknown error");
            return;
        }
        generatedQuestions = res.questions;
        renderQuestions(res.questions);
        document.getElementById("questions-result").style.display = "block";
        document.getElementById("result-count").textContent = res.questions.length;
        if (res.duplicates_removed > 0) {
            showToast(res.duplicates_removed + " duplicate question(s) were removed automatically.", "info", 5000);
        }
        if (res.questions.length === 0) {
            document.getElementById("generate-error").style.display = "block";
            document.getElementById("generate-error").textContent = "All generated questions were duplicates of existing ones. Try a different topic or more questions.";
        }
    })
    .catch(function(e) {
        document.getElementById("generate-status").style.display = "none";
        document.getElementById("generate-error").style.display = "block";
        document.getElementById("generate-error").textContent = "Request failed: " + e.message;
    });
});

function escHtml(s) {
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

function renderQuestions(questions) {
    var tbody = document.getElementById("questions-tbody");
    tbody.innerHTML = "";
    questions.forEach(function(q, i) {
        var opts = (q.options || []).map(function(o) { return escHtml(o); }).join("<br>");
        var tr = document.createElement("tr");
        tr.innerHTML = "<td class=\"fw-semibold\">" + (i+1) + "</td>"
            + "<td><div class=\"fw-semibold mb-1\">" + escHtml(q.question || "") + "</div>"
            + "<small class=\"text-muted\">" + opts + "</small></td>"
            + "<td><span class=\"badge bg-light text-dark border\">" + escHtml(q.subject || "General") + "</span></td>"
            + "<td><span class=\"badge bg-success\">" + escHtml(q.correct || "A") + "</span></td>"
            + "<td><small>" + escHtml(q.explanation || "") + "</small></td>";
        tbody.appendChild(tr);
    });
}

document.getElementById("clear-results").addEventListener("click", function() {
    generatedQuestions = [];
    document.getElementById("questions-result").style.display = "none";
});

document.getElementById("target-test").addEventListener("change", function() {
    document.getElementById("new-test-name-wrap").style.display = this.value ? "none" : "block";
});

document.getElementById("save-to-test-btn").addEventListener("click", function() {
    if (!generatedQuestions.length) {
        alert("No questions to save.");
        return;
    }
    var targetTest = document.getElementById("target-test").value;
    var newTestName = document.getElementById("new-test-name").value.trim();
    var resultDiv = document.getElementById("save-result");
    resultDiv.innerHTML = "<span class=\"spinner-border spinner-border-sm\"></span> Saving...";

    var body = new URLSearchParams({
        csrf_token: csrfToken,
        target_test_id: targetTest,
        new_test_name: newTestName,
        questions_json: JSON.stringify(generatedQuestions)
    });

    fetch("/admin/ajax/save-generated-questions.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: body.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            var extra = (res.duplicates_removed > 0)
                ? " (" + res.duplicates_removed + " duplicate(s) skipped)" : "";
            resultDiv.innerHTML = "<div class=\"alert alert-success mb-0\">Saved! "
                + (res.total_questions ? res.total_questions + " questions in test." : "")
                + extra
                + (res.test_id ? " <a href=\"/admin/tests/edit.php?id=" + res.test_id + "\">Edit Test</a>" : "")
                + "</div>";
        } else {
            resultDiv.innerHTML = "<div class=\"alert alert-danger mb-0\">" + escHtml(res.error || "Error") + "</div>";
        }
    })
    .catch(function(e) {
        resultDiv.innerHTML = "<div class=\"alert alert-danger mb-0\">Request failed: " + e.message + "</div>";
    });
});
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
