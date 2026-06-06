<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
require_once ROOT . '/includes/admin_middleware.php';

$admin_page_title = 'Edit Test';
$active_menu = 'tests';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin/tests/list.php');
    exit;
}

$test = $pdo->prepare("SELECT * FROM mock_tests WHERE id = ? LIMIT 1");
$test->execute([$id]);
$test = $test->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: /admin/tests/list.php');
    exit;
}

$existing_questions = [];
if (!empty($test['questions_json'])) {
    $decoded = json_decode($test['questions_json'], true);
    if (is_array($decoded)) {
        $existing_questions = $decoded;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        die('CSRF token mismatch.');
    }

    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $subject     = trim($_POST['subject'] ?? '');
    $duration    = (int)($_POST['duration_minutes'] ?? 60);
    $neg_marking = (float)($_POST['negative_marking'] ?? 0);
    $access_type = in_array($_POST['access_type'] ?? 'free', ['free','premium']) ? $_POST['access_type'] : 'free';
    $is_active   = !empty($_POST['is_active']) ? 1 : 0;

    if (empty($title)) $errors[] = 'Title is required.';

    $questions_raw = $_POST['questions'] ?? [];
    $questions = [];
    foreach ($questions_raw as $q) {
        $qtext = trim($q['question'] ?? '');
        if (empty($qtext)) continue;
        $options = array_values(array_map('trim', $q['options'] ?? []));
        $correct = strtoupper(trim($q['correct'] ?? 'A'));
        $explanation = trim($q['explanation'] ?? '');
        $q_subject = trim($q['subject'] ?? '');
        if (count($options) === 4) {
            $entry = [
                'question'    => $qtext,
                'options'     => $options,
                'correct'     => $correct,
                'explanation' => $explanation,
            ];
            if ($q_subject !== '') {
                $entry['subject'] = $q_subject;
            }
            $questions[] = $entry;
        }
    }
    $total_questions = count($questions);

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE mock_tests SET
             title=?, category=?, total_questions=?, duration_minutes=?,
             negative_marking=?, access_type=?, questions_json=?, is_active=?
             WHERE id=?"
        );
        $stmt->execute([
            $title, $category, $total_questions, $duration,
            $neg_marking, $access_type, json_encode($questions), $is_active, $id
        ]);

        header('Location: /admin/tests/list.php?success=1');
        exit;
    }
}

require_once ROOT . '/includes/admin_header.php';
require_once ROOT . '/includes/admin_sidebar.php';
?>
<div class="admin-content">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Test</h2>
    <a href="/admin/tests/list.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i>Back to List
    </a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="post" id="test-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white fw-semibold">Test Details</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control"
                   value="<?= htmlspecialchars($test['title']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Category</label>
            <input type="text" name="category" class="form-control" list="category-list"
                   value="<?= htmlspecialchars($test['category'] ?? '') ?>"
                   placeholder="e.g. UP Police, SSC">
            <datalist id="category-list">
              <?php foreach (get_exam_categories() as $cat): ?>
              <option value="<?= htmlspecialchars($cat) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label">Duration (minutes)</label>
            <input type="number" name="duration_minutes" class="form-control" min="1"
                   value="<?= (int)$test['duration_minutes'] ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Negative Marking</label>
            <select name="negative_marking" class="form-select">
              <?php $cur_neg = (float)$test['negative_marking']; ?>
              <option value="0" <?= $cur_neg==0?'selected':'' ?>>None (0)</option>
              <option value="0.25" <?= $cur_neg==0.25?'selected':'' ?>>-0.25</option>
              <option value="0.33" <?= $cur_neg==0.33?'selected':'' ?>>-0.33</option>
              <option value="0.5" <?= $cur_neg==0.5?'selected':'' ?>>-0.50</option>
              <option value="1" <?= $cur_neg==1?'selected':'' ?>>-1.00</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Access Type</label>
            <select name="access_type" class="form-select">
              <option value="free" <?= $test['access_type']==='free'?'selected':'' ?>>Free</option>
              <option value="premium" <?= $test['access_type']==='premium'?'selected':'' ?>>Premium</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="test-active"
                     <?= $test['is_active']?'checked':'' ?>>
              <label class="form-check-label" for="test-active">Active</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Question Builder -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Questions <span class="badge bg-primary ms-1" id="q-count">0</span></span>
        <button type="button" id="add-question-btn" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-plus"></i> Add Question
        </button>
      </div>
      <div class="card-body">
        <div id="questions-container"></div>
        <p class="text-muted text-center" id="no-questions-msg" style="display:none">No questions yet.</p>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Update Test
      </button>
      <a href="/admin/tests/list.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>

</div>
</div>
<?php
$existing_json = json_encode($existing_questions);
$extra_js = '<script>
var qIndex = 0;
var existingQuestions = ' . $existing_json . ';

function updateQCount() {
    var count = document.querySelectorAll(".question-item").length;
    document.getElementById("q-count").textContent = count;
    var msg = document.getElementById("no-questions-msg");
    if (msg) msg.style.display = count > 0 ? "none" : "block";
}

function escHtml(s) {
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

function addQuestion(data) {
    var idx = qIndex++;
    var div = document.createElement("div");
    div.className = "question-item card mb-3";
    div.dataset.index = idx;
    var q = data || {};
    var opts = q.options || ["", "", "", ""];
    var correct = q.correct || "A";
    var subject = q.subject || "";
    var letters = ["A","B","C","D"];
    var optHtml = "";
    for (var i = 0; i < 4; i++) {
        optHtml += "<div class=\"col-md-6\"><div class=\"input-group mb-2\">"
            + "<span class=\"input-group-text\">" + letters[i] + "</span>"
            + "<input type=\"text\" name=\"questions[" + idx + "][options][" + i + "]\" class=\"form-control\" "
            + "placeholder=\"Option " + letters[i] + "\" value=\"" + escHtml(opts[i] || "") + "\">"
            + "</div></div>";
    }
    var selOpts = "";
    ["A","B","C","D"].forEach(function(l) {
        selOpts += "<option value=\"" + l + "\"" + (correct === l ? " selected" : "") + ">" + l + "</option>";
    });
    div.innerHTML = "<div class=\"card-header d-flex justify-content-between align-items-center\">"
        + "<span class=\"fw-semibold\">Question " + (document.querySelectorAll(".question-item").length + 1) + "</span>"
        + "<button type=\"button\" class=\"btn btn-sm btn-outline-danger remove-q\"><i class=\"fas fa-trash\"></i> Remove</button>"
        + "</div>"
        + "<div class=\"card-body\">"
        + "<textarea name=\"questions[" + idx + "][question]\" class=\"form-control mb-3\" rows=\"2\" placeholder=\"Question text\">"
        + escHtml(q.question || "")
        + "</textarea>"
        + "<div class=\"row\">" + optHtml + "</div>"
        + "<div class=\"row align-items-center mt-2\">"
        + "<div class=\"col-md-4\">"
        + "<label class=\"form-label small mb-1\">Correct Answer</label>"
        + "<select name=\"questions[" + idx + "][correct]\" class=\"form-select form-select-sm\">" + selOpts + "</select>"
        + "</div>"
        + "<div class=\"col-md-4\">"
        + "<label class=\"form-label small mb-1\">Subject / Section</label>"
        + "<input type=\"text\" name=\"questions[" + idx + "][subject]\" class=\"form-control form-control-sm\" "
        + "placeholder=\"e.g. Reasoning\" value=\"" + escHtml(subject) + "\">"
        + "</div>"
        + "<div class=\"col-md-4\">"
        + "<label class=\"form-label small mb-1\">Explanation (optional)</label>"
        + "<input type=\"text\" name=\"questions[" + idx + "][explanation]\" class=\"form-control form-control-sm\" "
        + "value=\"" + escHtml(q.explanation || "") + "\">"
        + "</div>"
        + "</div>"
        + "</div>";
    div.querySelector(".remove-q").addEventListener("click", function() {
        div.remove();
        updateQCount();
    });
    document.getElementById("questions-container").appendChild(div);
    updateQCount();
}

existingQuestions.forEach(function(q) { addQuestion(q); });

document.getElementById("add-question-btn").addEventListener("click", function() {
    addQuestion(null);
});
</script>';
?>
<?php require_once ROOT . '/includes/admin_footer.php'; ?>
