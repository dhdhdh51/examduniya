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

// Merged exam syllabi (built-in + admin-added) for suggestions & full paper
$syllabi_json = json_encode(get_all_syllabi());

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-robot me-2"></i>AI Question Generator</h2>
    <div class="d-flex gap-2">
      <a href="/admin/tests/syllabus.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-book-open me-1"></i>Syllabus Manager
      </a>
      <a href="/admin/tests/list.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Tests
      </a>
    </div>
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
              <input type="text" name="exam_type" id="exam-type" class="form-control"
                     list="exam-type-list" value="SSC"
                     placeholder="e.g. UP Police, UPSSSC, SSC">
              <datalist id="exam-type-list">
                <?php foreach (get_exam_categories() as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <div class="form-text">Type any exam (UP Police, UPSSSC...) or pick a suggestion.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Topic / Subject</label>
              <input type="text" name="topic" id="topic" class="form-control"
                     list="topic-suggestions"
                     placeholder="e.g. Indian History, Coding-Decoding">
              <datalist id="topic-suggestions"></datalist>
              <div class="form-text">Pick a topic from the syllabus suggestions, or type your own.</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Assign to Subject / Section</label>
              <input type="text" name="subject" id="subject" class="form-control"
                     placeholder="e.g. General Knowledge" list="subject-suggestions">
              <datalist id="subject-suggestions"></datalist>
              <div class="form-text">Questions get grouped under this subject as a section tab. Auto-filled from syllabus when you pick a topic.</div>
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
            <div class="mb-3 p-2 rounded bg-light border">
              <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" id="full-paper">
                <label class="form-check-label fw-semibold" for="full-paper">Generate full paper from syllabus</label>
              </div>
              <div class="form-text mb-2">Generates questions for <em>every subject</em> of the selected exam and auto-assigns each question to its subject.</div>
              <label class="form-label small mb-1">Questions per subject</label>
              <input type="number" id="per-subject" class="form-control form-control-sm" value="5" min="1" max="20">
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
var examSyllabi = ' . $syllabi_json . ';

// Dynamic syllabus suggestions: when exam type changes, populate topic + subject datalists.
(function() {
    var examInput   = document.getElementById("exam-type");
    var topicInput  = document.getElementById("topic");
    var subjectInput= document.getElementById("subject");
    var topicList   = document.getElementById("topic-suggestions");
    var subjectList = document.getElementById("subject-suggestions");

    function updateSuggestions() {
        var exam = (examInput ? examInput.value.trim() : "");
        topicList.innerHTML = "";
        subjectList.innerHTML = "";
        var data = examSyllabi[exam];
        if (!data) {
            // Try partial match (e.g. "UP Police Constable" matches "UP Police")
            for (var key in examSyllabi) {
                if (exam.toLowerCase().indexOf(key.toLowerCase()) !== -1 || key.toLowerCase().indexOf(exam.toLowerCase()) !== -1) {
                    data = examSyllabi[key]; break;
                }
            }
        }
        if (!data) return;
        // Subjects (numbered sections) go to subject datalist
        for (var subj in data) {
            var opt = document.createElement("option");
            opt.value = subj;
            subjectList.appendChild(opt);
            // Topics go to topic datalist
            var topics = data[subj];
            for (var i = 0; i < topics.length; i++) {
                var topt = document.createElement("option");
                topt.value = topics[i];
                topicList.appendChild(topt);
            }
        }
    }

    // Auto-fill subject when a topic is picked (find which subject it belongs to)
    function autoFillSubject() {
        var topic = (topicInput ? topicInput.value.trim() : "");
        if (!topic) return;
        var exam = (examInput ? examInput.value.trim() : "");
        var data = examSyllabi[exam];
        if (!data) {
            for (var key in examSyllabi) {
                if (exam.toLowerCase().indexOf(key.toLowerCase()) !== -1 || key.toLowerCase().indexOf(exam.toLowerCase()) !== -1) {
                    data = examSyllabi[key]; break;
                }
            }
        }
        if (!data) return;
        for (var subj in data) {
            var topics = data[subj];
            for (var i = 0; i < topics.length; i++) {
                if (topics[i].toLowerCase() === topic.toLowerCase()) {
                    if (subjectInput) subjectInput.value = subj;
                    return;
                }
            }
        }
    }

    if (examInput) {
        examInput.addEventListener("change", updateSuggestions);
        examInput.addEventListener("input", updateSuggestions);
        updateSuggestions(); // run once on load
    }
    if (topicInput) {
        topicInput.addEventListener("change", autoFillSubject);
        topicInput.addEventListener("input", function() {
            setTimeout(autoFillSubject, 100); // wait for datalist selection to settle
        });
    }
})();

document.getElementById("generate-btn").addEventListener("click", function() {
    if (document.getElementById("full-paper") && document.getElementById("full-paper").checked) {
        generateFullPaper();
        return;
    }
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

function resolveExamData(exam) {
    var data = examSyllabi[exam];
    if (!data) {
        for (var key in examSyllabi) {
            if (exam.toLowerCase().indexOf(key.toLowerCase()) !== -1 || key.toLowerCase().indexOf(exam.toLowerCase()) !== -1) { data = examSyllabi[key]; break; }
        }
    }
    return data;
}
function generateFullPaper() {
    var examType = document.getElementById("exam-type").value.trim();
    var perSubject = parseInt(document.getElementById("per-subject").value, 10) || 5;
    var difficulty = document.getElementById("difficulty").value;
    var language = document.getElementById("language").value;
    var providerEl = document.getElementById("ai-provider");
    var provider = providerEl ? providerEl.value : "";
    var targetTest = document.getElementById("target-test").value;
    var data = resolveExamData(examType);
    if (!data) { alert("No syllabus found for this exam. Add it in the Syllabus Manager first."); return; }
    var subjects = Object.keys(data);
    if (!subjects.length) { alert("This syllabus has no subjects."); return; }

    document.getElementById("generate-status").style.display = "block";
    document.getElementById("generate-spinner").style.display = "inline-block";
    document.getElementById("questions-result").style.display = "none";
    document.getElementById("generate-error").style.display = "none";
    generatedQuestions = [];

    var idx = 0;
    function nextSubject() {
        if (idx >= subjects.length) {
            document.getElementById("generate-status").style.display = "none";
            renderQuestions(generatedQuestions);
            document.getElementById("questions-result").style.display = "block";
            document.getElementById("result-count").textContent = generatedQuestions.length;
            if (!generatedQuestions.length) {
                document.getElementById("generate-error").style.display = "block";
                document.getElementById("generate-error").textContent = "No questions were generated. Check your AI provider settings.";
            }
            return;
        }
        var subj = subjects[idx];
        var topics = data[subj] || [];
        var topicHint = topics.length ? topics.slice(0, 8).join(", ") : subj;
        document.getElementById("generate-status-msg").textContent = "Generating " + subj + " (" + (idx+1) + "/" + subjects.length + ")...";
        var body = new URLSearchParams({
            csrf_token: csrfToken, provider: provider, exam_type: examType,
            topic: topicHint, subject: subj, target_test_id: targetTest || "",
            num_questions: perSubject, difficulty: difficulty, language: language
        });
        fetch("/admin/ajax/generate-questions.php", { method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body: body.toString() })
          .then(function(r){ return r.json(); })
          .then(function(res){
            if (res && res.success && res.questions) {
                res.questions.forEach(function(q){ if (!q.subject) { q.subject = subj; } generatedQuestions.push(q); });
            }
            idx++; nextSubject();
          })
          .catch(function(){ idx++; nextSubject(); });
    }
    nextSubject();
}
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
