<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = null;
$comments = [];
$steps = [];
$likeCount = 0;
$isLiked = false;
$commentError = null;

/* ---------- Post a comment ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['post_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $commentError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $commentError = 'Log in to leave a comment.';
    } else {
        $text = trim($_POST['comment_text'] ?? '');
        if ($text === '') {
            $commentError = 'Write something before posting your comment.';
        } elseif (mb_strlen($text) > 2000) {
            $commentError = 'That comment is too long — keep it under 2000 characters.';
        } elseif ($pdo && $targetId > 0) {
            try {
                $pdo->prepare(
                    'INSERT INTO POST_COMMENT (post_id, user_id, comment_text) VALUES (?, ?, ?)'
                )->execute([$targetId, $_SESSION['user_id'], $text]);
                header('Location: post-detail.php?id=' . $targetId . '#comments');
                exit;
            } catch (PDOException $e) {
                $commentError = 'Something went wrong posting that comment. Please try again.';
            }
        }
    }
}

/* ---------- Delete your own comment ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_comment') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['post_id'] ?? 0);
    $commentId = (int) ($_POST['comment_id'] ?? 0);

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo && $commentId > 0) {
        try {
            // Ownership is checked in the WHERE clause, not just in the UI.
            $pdo->prepare('DELETE FROM POST_COMMENT WHERE comment_id = ? AND user_id = ?')
                ->execute([$commentId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log('FoodFusion delete_comment: ' . $e->getMessage());
        }
    }
    header('Location: post-detail.php?id=' . $targetId . '#comments');
    exit;
}

/* ---------- Delete the post itself (author only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_post') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['post_id'] ?? 0);
    $returnTab = $_POST['tab'] ?? 'recipe';

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo && $targetId > 0) {
        try {
            $pdo->prepare('DELETE FROM COMMUNITY_POST WHERE post_id = ? AND user_id = ?')
                ->execute([$targetId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log('FoodFusion delete_post (post-detail): ' . $e->getMessage());
        }
    }
    header('Location: community.php?tab=' . urlencode($returnTab));
    exit;
}

/* ---------- Load the post ---------- */
if ($pdo && $postId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT p.post_id, p.post_title, p.post_content, p.post_type, p.image_url,
                    p.recipe_id, p.created_at, p.user_id AS author_id,
                    u.first_name, u.last_name,
                    r.recipe_name, r.image_url AS recipe_image
             FROM COMMUNITY_POST p
             JOIN USERS u ON p.user_id = u.user_id
             LEFT JOIN RECIPE r ON p.recipe_id = r.recipe_id
             WHERE p.post_id = ?"
        );
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if ($post) {
            $stepStmt = $pdo->prepare(
                'SELECT instruction FROM COMMUNITY_POST_STEP WHERE post_id = ? ORDER BY step_number'
            );
            $stepStmt->execute([$postId]);
            $steps = array_column($stepStmt->fetchAll(), 'instruction');

            $commentStmt = $pdo->prepare(
                'SELECT c.comment_id, c.user_id, c.comment_text, c.created_at, u.first_name, u.last_name
                 FROM POST_COMMENT c
                 JOIN USERS u ON c.user_id = u.user_id
                 WHERE c.post_id = ?
                 ORDER BY c.created_at ASC'
            );
            $commentStmt->execute([$postId]);
            $comments = $commentStmt->fetchAll();

            $likeStmt = $pdo->prepare('SELECT COUNT(*) FROM POST_LIKE WHERE post_id = ?');
            $likeStmt->execute([$postId]);
            $likeCount = (int) $likeStmt->fetchColumn();

            if (!empty($_SESSION['user_id'])) {
                $mineStmt = $pdo->prepare('SELECT 1 FROM POST_LIKE WHERE post_id = ? AND user_id = ?');
                $mineStmt->execute([$postId, $_SESSION['user_id']]);
                $isLiked = (bool) $mineStmt->fetch();
            }
        }
    } catch (PDOException $e) {
        $post = null;
    }
}

$typeTabs = ['Recipe' => 'recipe', 'Cooking Tip' => 'tip', 'Culinary Experience' => 'experience'];
$typeClasses = ['Recipe' => 'type-recipe', 'Cooking Tip' => 'type-tip', 'Culinary Experience' => 'type-experience'];

require_once 'includes/header.php';
?>

<?php if (!$post): ?>

  <section class="section" style="text-align:center;">
    <div class="wrap">
      <span class="eyebrow" style="justify-content:center;">Post not found</span>
      <h1>We couldn't find that post</h1>
      <p class="lede" style="margin-inline:auto;">It may have been removed, or the link might be incorrect.</p>
      <a href="community.php" class="btn btn-primary">Back to the Community Cookbook</a>
    </div>
  </section>

<?php else:
  $backTab = $typeTabs[$post['post_type']] ?? 'recipe';
  $typeClass = $typeClasses[$post['post_type']] ?? '';
?>

  <div class="wrap">
    <a href="community.php?tab=<?php echo htmlspecialchars($backTab); ?>" class="back-link">
      <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back to Community Cookbook
    </a>
  </div>

  <div class="wrap post-detail-wrap">

    <article class="post-card <?php echo $typeClass; ?>">
      <span class="post-type-badge"><?php echo htmlspecialchars($post['post_type']); ?></span>

      <div class="post-header">
        <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1))); ?></span>
        <div>
          <span class="author-name"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
          <span class="post-time"><?php echo time_ago($post['created_at']); ?></span>
        </div>
      </div>

      <h1 class="post-detail-title"><?php echo htmlspecialchars($post['post_title']); ?></h1>

      <?php if ($post['post_type'] === 'Recipe' && !empty($post['recipe_name'])):
        $thumb = !empty($post['recipe_image']) ? $post['recipe_image'] : placeholder_img('recipe-' . $post['recipe_id'], 500, 400);
      ?>
        <a href="recipe-detail.php?id=<?php echo (int) $post['recipe_id']; ?>" class="linked-recipe-banner">
          <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($post['recipe_name']); ?>" loading="lazy">
          <div>
            <span class="linked-recipe-label">Linked recipe</span>
            <strong><?php echo htmlspecialchars($post['recipe_name']); ?></strong>
            <span class="linked-recipe-cta">View the full recipe &rarr;</span>
          </div>
        </a>
      <?php elseif (!empty($post['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="" class="post-detail-image" loading="lazy">
      <?php endif; ?>

      <p class="post-body"><?php echo nl2br(htmlspecialchars($post['post_content'])); ?></p>

      <?php if ($steps): ?>
        <h2 class="post-detail-subhead">Steps</h2>
        <ol class="step-list">
          <?php foreach ($steps as $step): ?>
            <li><p><?php echo htmlspecialchars($step); ?></p></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <div class="post-actions">
        <form method="POST" action="community.php" style="display:inline;" data-ajax-action="toggle_like">
          <input type="hidden" name="action" value="toggle_like">
          <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
          <input type="hidden" name="tab" value="<?php echo htmlspecialchars($backTab); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <button type="submit" class="post-like-btn <?php echo $isLiked ? 'active' : ''; ?>" <?php echo empty($_SESSION['user_id']) ? 'disabled title="Log in to like"' : ''; ?>>
            <svg viewBox="0 0 24 24" fill="<?php echo $isLiked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-4.5-9.5-8.5C.5 8.5 2.5 5 6 5c2 0 3.5 1.2 4 2.2C10.5 6.2 12 5 14 5c3.5 0 5.5 3.5 3.5 7.5C15 16.5 12 21 12 21z"/></svg>
            <span class="action-count"><?php echo $likeCount; ?></span>
          </button>
        </form>
        <span class="post-comment-link" style="cursor:default;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
          <?php echo count($comments); ?> comment<?php echo count($comments) === 1 ? '' : 's'; ?>
        </span>

        <?php if (!empty($_SESSION['user_id']) && (int) $post['author_id'] === (int) $_SESSION['user_id']): ?>
          <form method="POST" action="post-detail.php" style="display:inline; margin-left:auto;" data-confirm="Delete this post? This can't be undone.">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($backTab); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="post-comment-link post-delete-btn">
              <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m2 0v13a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z"/></svg>
              Delete post
            </button>
          </form>
        <?php endif; ?>
      </div>
    </article>

    <section class="comments-section" id="comments">
      <h2 class="post-detail-subhead">
        <?php echo count($comments); ?> comment<?php echo count($comments) === 1 ? '' : 's'; ?>
      </h2>

      <?php if ($commentError): ?>
        <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($commentError); ?></p>
      <?php endif; ?>

      <?php if (!empty($_SESSION['user_id'])): ?>
        <form method="POST" action="post-detail.php?id=<?php echo (int) $post['post_id']; ?>" class="comment-form">
          <input type="hidden" name="action" value="add_comment">
          <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="comment_text">Add a comment</label>
            <textarea id="comment_text" name="comment_text" required maxlength="2000" placeholder="Share your thoughts, or ask a question…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Post comment</button>
        </form>
      <?php else: ?>
        <div class="login-prompt" style="text-align:left; margin-bottom: 22px;">
          <a href="#" data-open-modal="loginModal">Log in</a> or
          <a href="#" data-open-modal="joinModal">create an account</a> to join the conversation.
        </div>
      <?php endif; ?>

      <?php if (empty($comments)): ?>
        <p class="empty-note">No comments yet — be the first to say something.</p>
      <?php else: ?>
        <div class="comment-list">
          <?php foreach ($comments as $c): ?>
            <div class="comment-item">
              <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1))); ?></span>
              <div class="comment-body">
                <div class="comment-meta">
                  <span class="author-name"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></span>
                  <span class="post-time"><?php echo time_ago($c['created_at']); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($c['comment_text'])); ?></p>
                <?php if (!empty($_SESSION['user_id']) && (int) $c['user_id'] === (int) $_SESSION['user_id']): ?>
                  <form method="POST" action="post-detail.php?id=<?php echo (int) $post['post_id']; ?>" class="comment-delete-form" data-confirm="Delete this comment?">
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
                    <input type="hidden" name="comment_id" value="<?php echo (int) $c['comment_id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="comment-delete-btn">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

  </div>

<?php endif; ?>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>