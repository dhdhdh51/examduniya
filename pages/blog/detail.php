<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
maintenance_mode_check();

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: /blog/');
    exit;
}

// Fetch blog post
$stmt = $pdo->prepare("SELECT b.*, u.name as author_name, u.avatar as author_avatar FROM blogs b LEFT JOIN users u ON b.author_id = u.id WHERE b.slug = ? AND b.is_published = 1 LIMIT 1");
$stmt->execute([$slug]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    http_response_code(404);
    include ROOT . '/404.php';
    exit;
}

// Increment views
$pdo->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?")->execute([$blog['id']]);

// Handle comment POST
$comment_success = false;
$comment_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'comment') {
    if (!isset($_SESSION['user_id'])) {
        $comment_error = 'You must be logged in to comment.';
    } elseif (!csrf_verify()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        $comment_text = trim($_POST['comment'] ?? '');
        if (mb_strlen($comment_text) < 3) {
            $comment_error = 'Comment is too short.';
        } elseif (mb_strlen($comment_text) > 2000) {
            $comment_error = 'Comment is too long (max 2000 characters).';
        } else {
            try {
                $ins = $pdo->prepare("INSERT INTO comments (blog_id, user_id, content, is_approved) VALUES (?, ?, ?, 0)");
                $ins->execute([$blog['id'], $_SESSION['user_id'], $comment_text]);
                $comment_success = true;
            } catch (Exception $e) {
                $comment_error = 'Failed to save comment. Please try again.';
            }
        }
    }
}

// Fetch approved comments
$c_stmt = $pdo->prepare("SELECT c.id, c.content, c.created_at, u.name, u.avatar FROM comments c JOIN users u ON c.user_id = u.id WHERE c.blog_id = ? AND c.is_approved = 1 ORDER BY c.created_at ASC");
$c_stmt->execute([$blog['id']]);
$comments = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

// Related posts (same category, 3)
$rel_stmt = $pdo->prepare("SELECT id, title, slug, featured_image, category, created_at FROM blogs WHERE is_published=1 AND category = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
$rel_stmt->execute([$blog['category'], $blog['id']]);
$related_posts = $rel_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = $blog['title'];
require_once ROOT . '/includes/seo.php';
require_once ROOT . '/includes/schema.php';

$site_url   = rtrim((get_setting('canonical_domain') ?: get_setting('site_url')) ?: 'https://examduniya.in', '/');
$clean_path = blog_url($blog['slug']);
$page_url   = $site_url . $clean_path;
$wa_text    = urlencode($blog['title'] . ' - ' . $page_url);
$wa_url     = 'https://wa.me/?text=' . $wa_text;

$tags = array_filter(array_map('trim', explode(',', $blog['tags'] ?? '')));

$seo_title = !empty($blog['seo_title']) ? $blog['seo_title'] : seo_blog_title($blog['title']);
$seo_desc  = !empty($blog['meta_description'])
    ? $blog['meta_description']
    : seo_clamp_description($blog['excerpt'] ?: $blog['content']);
$published = !empty($blog['published_at'])
    ? date('c', strtotime($blog['published_at']))
    : (!empty($blog['created_at']) ? date('c', strtotime($blog['created_at'])) : null);
$modified  = !empty($blog['updated_at']) ? date('c', strtotime($blog['updated_at'])) : $published;
$author_slug = !empty($blog['author_name']) ? slug($blog['author_name']) : '';

// Estimated read time from the article body.
$word_count = str_word_count(strip_tags((string)$blog['content']));
$read_time  = max(1, (int)round($word_count / 200));

seo_set([
    'title'          => $seo_title,
    'description'    => $seo_desc,
    'canonical'      => !empty($blog['canonical_url']) ? $blog['canonical_url'] : $clean_path,
    'robots'         => 'index,follow',
    'og_type'        => 'article',
    'og_image'       => !empty($blog['featured_image']) ? '/uploads/blogs/' . $blog['featured_image'] : '',
    'published_time' => $published,
    'modified_time'  => $modified,
]);

require_once ROOT . '/includes/header.php';
require_once ROOT . '/includes/navbar.php';

schema_breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog/'],
    ['name' => $blog['title'], 'url' => $clean_path],
]);
schema_article([
    'type'          => 'BlogPosting',
    'headline'      => $blog['title'],
    'url'           => $clean_path,
    'description'   => $seo_desc,
    'image'         => !empty($blog['featured_image']) ? '/uploads/blogs/' . $blog['featured_image'] : '',
    'datePublished' => $published,
    'dateModified'  => $modified,
    'authorName'    => $blog['author_name'] ?? '',
    'authorUrl'     => $author_slug ? author_url($author_slug) : '',
]);
?>

<div class="container my-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/"><i class="fa-solid fa-house me-1"></i>Home</a></li>
            <li class="breadcrumb-item"><a href="/blog/">Blog</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars(mb_substr($blog['title'], 0, 50)) ?><?= mb_strlen($blog['title']) > 50 ? '...' : '' ?></li>
        </ol>
    </nav>

    <div class="row g-4">

        <!-- Main Article -->
        <div class="col-lg-8">
            <article>
                <!-- Cover Image -->
                <?php if ($blog['featured_image']): ?>
                    <img src="/uploads/blogs/<?= htmlspecialchars($blog['featured_image']) ?>"
                         alt="<?= htmlspecialchars($blog['title']) ?>"
                         class="w-100 rounded-3 mb-4"
                         style="max-height:400px;object-fit:cover;">
                <?php endif; ?>

                <!-- Title & Meta -->
                <h1 class="fw-bold mb-3"><?= htmlspecialchars($blog['title']) ?></h1>
                <div class="d-flex flex-wrap gap-3 align-items-center mb-3 text-muted small">
                    <?php if ($blog['author_name']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($blog['author_avatar']): ?>
                                <img src="<?= htmlspecialchars(get_avatar_url($blog['author_avatar'])) ?>"
                                     class="rounded-circle" width="28" height="28" alt="author">
                            <?php else: ?>
                                <i class="fa-solid fa-circle-user fa-lg"></i>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars(author_url($author_slug)) ?>" class="text-decoration-none"><?= htmlspecialchars($blog['author_name']) ?></a>
                        </div>
                    <?php endif; ?>
                    <div><i class="fa-solid fa-calendar me-1"></i><?= htmlspecialchars(format_date($blog['created_at'])) ?></div>
                    <?php if (!empty($blog['updated_at']) && date('Y-m-d', strtotime($blog['updated_at'])) !== date('Y-m-d', strtotime($blog['created_at']))): ?>
                        <div><i class="fa-solid fa-pen me-1"></i>Updated <?= htmlspecialchars(format_date($blog['updated_at'])) ?></div>
                    <?php endif; ?>
                    <div><i class="fa-solid fa-clock me-1"></i><?= (int)$read_time ?> min read</div>
                    <div><i class="fa-solid fa-eye me-1"></i><?= number_format((int)$blog['views']) ?> views</div>
                    <?php if ($blog['category']): ?>
                        <div>
                            <a href="/pages/blog/listing.php?category=<?= urlencode($blog['category']) ?>"
                               class="blog-category"><?= htmlspecialchars($blog['category']) ?></a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                    <div class="mb-4 d-flex flex-wrap gap-1">
                        <?php foreach ($tags as $tag): ?>
                            <span class="badge bg-primary-light text-primary fw-normal" style="font-size:0.8rem;">
                                <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars($tag) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Social Share -->
                <div class="d-flex gap-2 mb-4">
                    <a href="<?= htmlspecialchars($wa_url) ?>"
                       class="btn btn-success btn-sm"
                       target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp me-1"></i> Share on WhatsApp
                    </a>
                    <button class="btn btn-outline-secondary btn-sm"
                            onclick="copyLink('<?= htmlspecialchars(addslashes($page_url)) ?>')">
                        <i class="fa-solid fa-copy me-1"></i> Copy Link
                    </button>
                </div>

                <hr>

                <!-- Full Article Content (admin-entered trusted HTML) -->
                <div class="prose mt-4">
                    <?= $blog['content'] ?>
                </div>

            </article>

            <hr class="my-5">

            <!-- Comments Section -->
            <section id="comments">
                <h4 class="fw-bold mb-4">
                    <i class="fa-solid fa-comments text-primary me-2"></i>
                    Comments (<?= count($comments) ?>)
                </h4>

                <?php if ($comment_success): ?>
                    <div class="alert alert-success auto-dismiss">
                        <i class="fa-solid fa-circle-check me-2"></i>
                        Your comment is awaiting moderation. Thank you!
                    </div>
                <?php endif; ?>
                <?php if ($comment_error): ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-circle-xmark me-2"></i>
                        <?= htmlspecialchars($comment_error) ?>
                    </div>
                <?php endif; ?>

                <!-- Comment Form -->
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="card no-lift mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Leave a Comment</h6>
                            <form method="POST" action="/pages/blog/detail.php?slug=<?= urlencode($blog['slug']) ?>&action=comment">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <div class="mb-3">
                                    <textarea name="comment" class="form-control" rows="4"
                                              placeholder="Write your comment here..."
                                              maxlength="2000" required><?= isset($_POST['comment']) && $comment_error ? htmlspecialchars($_POST['comment']) : '' ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-paper-plane me-2"></i>Post Comment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        <a href="/auth/login.php?redirect=<?= urlencode($page_url) ?>">Login</a> to leave a comment.
                    </div>
                <?php endif; ?>

                <!-- Approved Comments -->
                <?php if (empty($comments)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-comment-slash fa-3x mb-3 opacity-50"></i>
                        <p>No comments yet. Be the first to comment!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($comments as $comment): ?>
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <?php if ($comment['avatar']): ?>
                                        <img src="<?= htmlspecialchars(get_avatar_url($comment['avatar'])) ?>"
                                             class="rounded-circle" width="40" height="40" alt="avatar">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center"
                                             style="width:40px;height:40px;">
                                            <i class="fa-solid fa-user text-primary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="bg-white border rounded-3 p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="small"><?= htmlspecialchars($comment['name']) ?></strong>
                                            <span class="text-muted" style="font-size:0.75rem;">
                                                <?= htmlspecialchars(format_date($comment['created_at'], 'd M Y, h:i A')) ?>
                                            </span>
                                        </div>
                                        <p class="mb-0 small"><?= htmlspecialchars($comment['content']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </section>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">

            <!-- Related Posts -->
            <?php if (!empty($related_posts)): ?>
                <div class="card no-lift mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fa-solid fa-newspaper text-primary me-2"></i>Related Articles</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($related_posts as $rp): ?>
                                <a href="<?= htmlspecialchars(blog_url($rp['slug'])) ?>"
                                   class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex gap-3 align-items-start">
                                        <?php if ($rp['featured_image']): ?>
                                            <img src="/uploads/blogs/<?= htmlspecialchars($rp['featured_image']) ?>"
                                                 alt="" class="rounded"
                                                 style="width:60px;height:50px;object-fit:cover;flex-shrink:0;">
                                        <?php else: ?>
                                            <div class="rounded bg-primary-light d-flex align-items-center justify-content-center"
                                                 style="width:60px;height:50px;flex-shrink:0;">
                                                <i class="fa-solid fa-newspaper text-primary opacity-50"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold small" style="line-height:1.4;">
                                                <?= htmlspecialchars(mb_substr($rp['title'], 0, 55)) ?><?= mb_strlen($rp['title']) > 55 ? '...' : '' ?>
                                            </div>
                                            <div class="text-muted mt-1" style="font-size:0.75rem;">
                                                <i class="fa-solid fa-calendar me-1"></i><?= htmlspecialchars(format_date($rp['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Share Card -->
            <div class="card no-lift">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-share-nodes text-primary me-2"></i>Share This Article</h6>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars($wa_url) ?>"
                       class="btn btn-success btn-sm flex-fill"
                       target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp me-1"></i> WhatsApp
                    </a>
                    <button class="btn btn-outline-secondary btn-sm flex-fill"
                            onclick="copyLink('<?= htmlspecialchars(addslashes($page_url)) ?>')">
                        <i class="fa-solid fa-copy me-1"></i> Copy Link
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function copyLink(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showToast('Link copied to clipboard!', 'success');
        }).catch(function() { fallbackCopy(url); });
    } else {
        fallbackCopy(url);
    }
}
function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('Link copied!', 'success'); }
    catch(e) { showToast('Could not copy link', 'danger'); }
    document.body.removeChild(ta);
}
</script>

<?php require_once ROOT . '/includes/footer.php'; ?>
