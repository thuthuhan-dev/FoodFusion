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

$validTabs = ['recipe' => 'Recipe', 'tip' => 'Cooking Tip', 'experience' => 'Culinary Experience'];
$activeTab = isset($_GET['tab']) && isset($validTabs[$_GET['tab']]) ? $_GET['tab'] : 'recipe';
$activePostType = $validTabs[$activeTab];

$page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = 6;
$formError = null;
$dbNotice = null;

/* ---------- Handle new Cooking Tip / Culinary Experience post ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_post') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postType = ($_POST['post_type'] ?? '') === 'Culinary Experience' ? 'Culinary Experience' : 'Cooking Tip';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $formError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $formError = 'Log in to post to the community cookbook.';
    } else {
        $title = trim($_POST['post_title'] ?? '');
        $content = trim($_POST['post_content'] ?? '');
        $steps = array_values(array_filter(array_map('trim', $_POST['step'] ?? []), fn($s) => $s !== ''));

        // Image comes from the user's device. Falls back to a pasted URL if given.
        $uploadError = null;
        $imageUrl = handle_image_upload($_FILES['image_file'] ?? null, $uploadError);
        if ($uploadError) {
            $formError = $uploadError;
        } elseif ($imageUrl === null) {
            $typedUrl = trim($_POST['image_url'] ?? '');
            $imageUrl = ($typedUrl !== '' && filter_var($typedUrl, FILTER_VALIDATE_URL)) ? $typedUrl : '';
        }

        if ($formError !== null) {
            // An upload problem was already reported above — fall through and redisplay.
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $formError = 'Give your post a title under 150 characters.';
        } elseif ($content === '') {
            $formError = 'Your post needs some content before you can share it.';
        } elseif ($pdo) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO COMMUNITY_POST (user_id, post_type, post_title, post_content, image_url)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$_SESSION['user_id'], $postType, $title, $content, $imageUrl !== '' ? $imageUrl : null]);
                $newPostId = $pdo->lastInsertId();

                if ($steps) {
                    $stepStmt = $pdo->prepare(
                        'INSERT INTO COMMUNITY_POST_STEP (post_id, step_number, instruction) VALUES (?, ?, ?)'
                    );
                    foreach ($steps as $i => $stepText) {
                        $stepStmt->execute([$newPostId, $i + 1, $stepText]);
                    }
                }
                $pdo->commit();
                $redirectTab = $postType === 'Culinary Experience' ? 'experience' : 'tip';
                header('Location: community.php?tab=' . $redirectTab);
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $formError = 'Something went wrong posting that. Please try again.';
            }
        }
    }
}

/* ---------- Handle like toggle ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_like') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $returnTab = $_POST['tab'] ?? 'recipe';
    $returnPage = (int) ($_POST['page'] ?? 1);

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo) {
        try {
            $check = $pdo->prepare('SELECT like_id FROM POST_LIKE WHERE post_id = ? AND user_id = ?');
            $check->execute([$postId, $_SESSION['user_id']]);
            $existing = $check->fetch();
            if ($existing) {
                $pdo->prepare('DELETE FROM POST_LIKE WHERE like_id = ?')->execute([$existing['like_id']]);
            } else {
                $pdo->prepare('INSERT INTO POST_LIKE (post_id, user_id) VALUES (?, ?)')->execute([$postId, $_SESSION['user_id']]);
            }
        } catch (PDOException $e) {
            // Silently ignore — the feed will just show the like count as it was.
        }
    }
    header('Location: community.php?tab=' . urlencode($returnTab) . '&page=' . $returnPage);
    exit;
}

/* ---------- Handle repost / un-repost (share someone else's post) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_repost') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $returnTab = $_POST['tab'] ?? 'recipe';
    $returnPage = (int) ($_POST['page'] ?? 1);
    $shareComment = trim($_POST['share_comment'] ?? '');
    if (mb_strlen($shareComment) > 500) {
        $shareComment = mb_substr($shareComment, 0, 500);
    }

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo) {
        try {
            $check = $pdo->prepare('SELECT share_id FROM POST_SHARE WHERE post_id = ? AND shared_by_user_id = ?');
            $check->execute([$postId, $_SESSION['user_id']]);
            $existing = $check->fetch();
            if ($existing) {
                $pdo->prepare('DELETE FROM POST_SHARE WHERE share_id = ?')->execute([$existing['share_id']]);
            } else {
                $pdo->prepare('INSERT INTO POST_SHARE (post_id, shared_by_user_id, share_comment) VALUES (?, ?, ?)')
                    ->execute([$postId, $_SESSION['user_id'], $shareComment !== '' ? $shareComment : null]);
            }
        } catch (PDOException $e) {
            // Silently ignore.
        }
    }
    header('Location: community.php?tab=' . urlencode($returnTab) . '&page=' . $returnPage);
    exit;
}

/* ---------- Delete a post you wrote ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_post') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postId = (int) ($_POST['post_id'] ?? 0);
    $returnTab = $_POST['tab'] ?? 'recipe';
    $returnTo = trim($_POST['return_to'] ?? '');

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo && $postId > 0) {
        try {
            // Look up the image BEFORE deleting the row — once the row is gone,
            // there's no way to know which file belonged to it.
            $imgStmt = $pdo->prepare('SELECT image_url FROM COMMUNITY_POST WHERE post_id = ? AND user_id = ?');
            $imgStmt->execute([$postId, $_SESSION['user_id']]);
            $imageToDelete = $imgStmt->fetchColumn();

            // The WHERE clause checks ownership directly in the query — this is
            // the actual protection, not just hiding the button in the UI. If the
            // post_id belongs to someone else, this deletes zero rows.
            $del = $pdo->prepare('DELETE FROM COMMUNITY_POST WHERE post_id = ? AND user_id = ?');
            $del->execute([$postId, $_SESSION['user_id']]);
            // ON DELETE CASCADE on COMMUNITY_POST_STEP, POST_LIKE, POST_COMMENT
            // and POST_SHARE takes care of everything attached to the post.

            if ($del->rowCount() > 0 && $imageToDelete) {
                delete_uploaded_file($imageToDelete);
            }
        } catch (PDOException $e) {
            error_log('FoodFusion delete_post: ' . $e->getMessage());
        }
    }

    if ($returnTo !== '' && (str_starts_with($returnTo, 'account.php') || str_starts_with($returnTo, 'community.php'))) {
        header('Location: ' . $returnTo);
    } else {
        header('Location: community.php?tab=' . urlencode($returnTab));
    }
    exit;
}

/* ---------- Tab counts (for the little numbers on each tab) ---------- */
$tabCounts = ['recipe' => 0, 'tip' => 0, 'experience' => 0];
if ($pdo) {
    try {
        foreach ($validTabs as $slug => $typeLabel) {
            $c1 = $pdo->prepare('SELECT COUNT(*) FROM COMMUNITY_POST WHERE post_type = ?');
            $c1->execute([$typeLabel]);
            $c2 = $pdo->prepare('SELECT COUNT(*) FROM POST_SHARE ps JOIN COMMUNITY_POST p ON ps.post_id = p.post_id WHERE p.post_type = ?');
            $c2->execute([$typeLabel]);
            $tabCounts[$slug] = (int) $c1->fetchColumn() + (int) $c2->fetchColumn();
        }
    } catch (PDOException $e) {
        // Never echo $e->getMessage() to the browser: raw driver errors leak
        // table and column names, which helps an attacker map the database.
        // The detail goes to the server log; the user sees a neutral message.
        error_log('FoodFusion community tab counts: ' . $e->getMessage());
        $dbNotice = 'The community cookbook is temporarily unavailable. Please try again shortly.';
    }
}

/* ---------- Load the feed for the active tab (original posts + reposts of that type) ---------- */
$posts = [];
$totalPosts = $tabCounts[$activeTab] ?? 0;

if ($pdo && !$dbNotice) {
    try {
        // LIMIT/OFFSET are cast to int above and below, so it's safe to inline them
        // directly rather than bind them — this avoids a named-parameter +
        // LIMIT/OFFSET + UNION compatibility issue some PDO/MySQL driver
        // combinations have (SQLSTATE[HY093]).
        $limitInt = (int) $perPage;
        $offsetInt = (int) (($page - 1) * $perPage);

        $feedStmt = $pdo->prepare(
            "(SELECT p.post_id, p.post_title, p.post_content, p.post_type, p.image_url, p.recipe_id,
                     p.created_at AS feed_time, p.created_at AS original_time, 0 AS is_repost,
                     u.user_id AS author_id, u.first_name, u.last_name,
                     r.recipe_name, r.image_url AS recipe_image, r.user_id AS recipe_owner_id,
                     NULL AS reshared_first, NULL AS reshared_last, NULL AS share_comment
              FROM COMMUNITY_POST p
              JOIN USERS u ON p.user_id = u.user_id
              LEFT JOIN RECIPE r ON p.recipe_id = r.recipe_id
              WHERE p.post_type = ?)
             UNION ALL
             (SELECT p.post_id, p.post_title, p.post_content, p.post_type, p.image_url, p.recipe_id,
                     ps.created_at AS feed_time, p.created_at AS original_time, 1 AS is_repost,
                     u.user_id AS author_id, u.first_name, u.last_name,
                     r.recipe_name, r.image_url AS recipe_image, r.user_id AS recipe_owner_id,
                     su.first_name AS reshared_first, su.last_name AS reshared_last, ps.share_comment
              FROM POST_SHARE ps
              JOIN COMMUNITY_POST p ON ps.post_id = p.post_id
              JOIN USERS u ON p.user_id = u.user_id
              JOIN USERS su ON ps.shared_by_user_id = su.user_id
              LEFT JOIN RECIPE r ON p.recipe_id = r.recipe_id
              WHERE p.post_type = ?)
             ORDER BY feed_time DESC
             LIMIT $limitInt OFFSET $offsetInt"
        );
        $feedStmt->execute([$activePostType, $activePostType]);
        $posts = $feedStmt->fetchAll();

        if ($posts) {
            // array_values() is essential here: array_unique() preserves the original
            // keys, so removing a duplicate (an original post + its repost share the
            // same post_id) leaves a gap like [0 => 1, 2 => 3]. PDO needs positional
            // parameters numbered 0,1,2… so a gap throws SQLSTATE[HY093].
            $ids = array_values(array_unique(array_column($posts, 'post_id')));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $likeStmt = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM POST_LIKE WHERE post_id IN ($placeholders) GROUP BY post_id");
            $likeStmt->execute($ids);
            $likeCounts = array_column($likeStmt->fetchAll(), 'c', 'post_id');

            $commentStmt = $pdo->prepare("SELECT post_id, COUNT(*) AS c FROM POST_COMMENT WHERE post_id IN ($placeholders) GROUP BY post_id");
            $commentStmt->execute($ids);
            $commentCounts = array_column($commentStmt->fetchAll(), 'c', 'post_id');

            $stepStmt = $pdo->prepare("SELECT post_id, step_number, instruction FROM COMMUNITY_POST_STEP WHERE post_id IN ($placeholders) ORDER BY post_id, step_number");
            $stepStmt->execute($ids);
            $stepsByPost = [];
            foreach ($stepStmt->fetchAll() as $row) {
                $stepsByPost[$row['post_id']][] = $row['instruction'];
            }

            $likedByMe = $repostedByMe = [];
            if (!empty($_SESSION['user_id'])) {
                $mineStmt = $pdo->prepare("SELECT post_id FROM POST_LIKE WHERE user_id = ? AND post_id IN ($placeholders)");
                $mineStmt->execute(array_merge([$_SESSION['user_id']], $ids));
                $likedByMe = array_column($mineStmt->fetchAll(), 'post_id');

                $repostStmt = $pdo->prepare("SELECT post_id FROM POST_SHARE WHERE shared_by_user_id = ? AND post_id IN ($placeholders)");
                $repostStmt->execute(array_merge([$_SESSION['user_id']], $ids));
                $repostedByMe = array_column($repostStmt->fetchAll(), 'post_id');
            }

            foreach ($posts as &$p) {
                $p['like_count'] = $likeCounts[$p['post_id']] ?? 0;
                $p['comment_count'] = $commentCounts[$p['post_id']] ?? 0;
                $p['steps'] = $stepsByPost[$p['post_id']] ?? [];
                $p['liked_by_me'] = in_array($p['post_id'], $likedByMe);
                $p['reposted_by_me'] = in_array($p['post_id'], $repostedByMe);
            }
            unset($p);
        }
    } catch (PDOException $e) {
        $posts = [];
        $totalPosts = 0;
        error_log('FoodFusion community feed: ' . $e->getMessage());
        $dbNotice = 'The community cookbook is temporarily unavailable. Please try again shortly.';
    }
}

$totalPages = max(1, (int) ceil($totalPosts / $perPage));

function tab_url($tab, $page = 1) {
    return 'community.php?tab=' . $tab . ($page > 1 ? '&page=' . $page : '');
}

$typeIcons = [
    'Recipe' => '<svg viewBox="0 0 24 24" width="12" height="12" style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
    'Cooking Tip' => '<svg viewBox="0 0 24 24" width="12" height="12" style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6M10 22h4M12 2a6 6 0 00-4 10.5c.6.6 1 1.4 1 2.5h6c0-1.1.4-1.9 1-2.5A6 6 0 0012 2z"/></svg>',
    'Culinary Experience' => '<svg viewBox="0 0 24 24" width="12" height="12" style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 5h7a3 3 0 013 3v11a2.5 2.5 0 00-2.5-2.5H2z"/><path d="M22 5h-7a3 3 0 00-3 3v11a2.5 2.5 0 012.5-2.5H22z"/></svg>',
];
$typeSlugClass = ['Recipe' => 'type-recipe', 'Cooking Tip' => 'type-tip', 'Culinary Experience' => 'type-experience'];

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Community Cookbook</span>
    <h1>Recipes, tips, and stories from the table</h1>
    <p>A collaborative space where members share favourite recipes, cooking tips, and culinary experiences with the FoodFusion community.</p>
  </div>
</section>

<div class="wrap community-wrap">

  <nav class="cookbook-tabs" aria-label="Community Cookbook categories">
    <a class="cookbook-tab-btn <?php echo $activeTab === 'recipe' ? 'active' : ''; ?>" href="<?php echo tab_url('recipe'); ?>">
      Recipes <span class="tab-count">(<?php echo $tabCounts['recipe']; ?>)</span>
    </a>
    <a class="cookbook-tab-btn <?php echo $activeTab === 'tip' ? 'active' : ''; ?>" href="<?php echo tab_url('tip'); ?>">
      Cooking Tips <span class="tab-count">(<?php echo $tabCounts['tip']; ?>)</span>
    </a>
    <a class="cookbook-tab-btn <?php echo $activeTab === 'experience' ? 'active' : ''; ?>" href="<?php echo tab_url('experience'); ?>">
      Culinary Experiences <span class="tab-count">(<?php echo $tabCounts['experience']; ?>)</span>
    </a>
  </nav>

  <?php if ($dbNotice): ?>
    <div class="login-prompt" style="border-color: var(--paprika); color: var(--paprika); margin-bottom: 24px;">
      <?php echo htmlspecialchars($dbNotice); ?>
    </div>
  <?php endif; ?>

  <div class="cookbook-layout">

    <!-- LEFT: what this tab is for — compose here, or a CTA -->
    <aside class="cookbook-sidebar">

      <?php if ($activeTab === 'recipe'): ?>
        <div class="sidebar-cta-card">
          <h2>Share a recipe</h2>
          <p>Recipes are posted from the Recipe Collection, not from here — add a new one or hit Share on an existing recipe and it'll appear in this feed.</p>
          <a href="recipes.php" class="btn btn-primary">Go to Recipe Collection</a>
        </div>

      <?php elseif ($activeTab === 'tip'): ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <div class="composer">
            <h2>Share a cooking tip</h2>
            <?php if ($formError): ?>
              <p class="form-note" style="color: var(--paprika); margin-bottom: 12px;"><?php echo htmlspecialchars($formError); ?></p>
            <?php endif; ?>
            <form method="POST" action="community.php?tab=tip" enctype="multipart/form-data">
              <input type="hidden" name="action" value="create_post">
              <input type="hidden" name="post_type" value="Cooking Tip">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
              <div class="field">
                <label for="tip_title">Title</label>
                <input type="text" id="tip_title" name="post_title" required maxlength="150" placeholder="e.g. Getting more from Ras el Hanout">
              </div>
              <div class="field">
                <label for="tip_content">Your tip</label>
                <textarea id="tip_content" name="post_content" required placeholder="Describe the tip…"></textarea>
              </div>
              <div class="field">
                <label for="tip_image">Photo (optional)</label>
                <input type="file" id="tip_image" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
              </div>
              <div class="field" id="tipSteps">
                <label>Steps (optional)</label>
                <div class="step-input-row"><input type="text" name="step[]" placeholder="Step 1"></div>
              </div>
              <button type="button" class="btn btn-ghost" data-add-step="tipSteps" style="margin-bottom:16px; width:100%;">+ Add another step</button>
              <button type="submit" class="btn btn-primary" style="width:100%;">Post Tip</button>
            </form>
          </div>
        <?php else: ?>
          <div class="login-prompt">
            <a href="login.php">Log in</a> or <a href="register.php">join FoodFusion</a> to share a cooking tip.
          </div>
        <?php endif; ?>

      <?php else: ?>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <div class="composer">
            <h2>Share a culinary experience</h2>
            <?php if ($formError): ?>
              <p class="form-note" style="color: var(--paprika); margin-bottom: 12px;"><?php echo htmlspecialchars($formError); ?></p>
            <?php endif; ?>
            <form method="POST" action="community.php?tab=experience" enctype="multipart/form-data">
              <input type="hidden" name="action" value="create_post">
              <input type="hidden" name="post_type" value="Culinary Experience">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
              <div class="field">
                <label for="exp_title">Title</label>
                <input type="text" id="exp_title" name="post_title" required maxlength="150" placeholder="e.g. Cooking couscous with my grandmother">
              </div>
              <div class="field">
                <label for="exp_content">Tell the story</label>
                <textarea id="exp_content" name="post_content" required placeholder="What happened, what you learned…"></textarea>
              </div>
              <div class="field">
                <label for="exp_image">Photo (optional)</label>
                <input type="file" id="exp_image" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
              </div>
              <div class="field" id="expSteps">
                <label>Moments (optional)</label>
                <div class="step-input-row"><input type="text" name="step[]" placeholder="Moment 1"></div>
              </div>
              <button type="button" class="btn btn-ghost" data-add-step="expSteps" style="margin-bottom:16px; width:100%;">+ Add another moment</button>
              <button type="submit" class="btn btn-primary" style="width:100%;">Post Experience</button>
            </form>
          </div>
        <?php else: ?>
          <div class="login-prompt">
            <a href="login.php">Log in</a> or <a href="register.php">join FoodFusion</a> to share a culinary experience.
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </aside>

    <!-- RIGHT: the feed for this tab -->
    <div>
      <div class="post-feed">
        <?php if (empty($posts) && !$dbNotice): ?>
          <p class="empty-note" style="grid-column: 1 / -1;">No posts here yet — be the first to share.</p>
        <?php endif; ?>

        <?php
          // Generic filler text the app itself writes when someone shares/adds a
          // recipe without typing anything — once the badge below already says
          // "Added a new recipe" / "Shared a recipe", repeating this adds nothing,
          // so it's the only content deliberately never shown, even though it's
          // sitting in the database like any real note would be.
          $genericRecipeText = ['Shared a recipe worth trying.', 'Added a new recipe to the collection.'];
        ?>
        <?php foreach ($posts as $postIndex => $post):
          $typeClass = $typeSlugClass[$post['post_type']] ?? '';
          $isRecipePost = $post['post_type'] === 'Recipe';
          $isOriginalAuthor = $isRecipePost && !empty($post['recipe_owner_id'])
                              && (int) $post['author_id'] === (int) $post['recipe_owner_id'];
          $recipeBadgeLabel = $isOriginalAuthor ? 'Added a new recipe' : 'Shared a recipe';
        ?>
          <article class="post-card <?php echo $typeClass; ?>">
            <?php if (!empty($post['is_repost'])): ?>
              <p class="post-time" style="margin-bottom: 10px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                <?php echo htmlspecialchars($post['reshared_first'] . ' ' . $post['reshared_last']); ?> reshared this
                &middot; <?php echo time_ago($post['feed_time']); ?>
              </p>
              <?php if (!empty($post['share_comment'])): ?>
                <div class="repost-thought">
                  <span class="repost-thought-avatar"><?php echo htmlspecialchars(strtoupper(substr($post['reshared_first'], 0, 1) . substr($post['reshared_last'], 0, 1))); ?></span>
                  <p><?php echo nl2br(htmlspecialchars($post['share_comment'])); ?></p>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <span class="post-type-badge"><?php echo $typeIcons[$post['post_type']] ?? ''; ?> <?php echo $isRecipePost ? htmlspecialchars($recipeBadgeLabel) : htmlspecialchars($post['post_type']); ?></span>

            <div class="post-header">
              <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1))); ?></span>
              <div>
                <span class="author-name"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                <span class="post-time">
                  <?php echo time_ago($post['original_time'] ?? $post['feed_time']); ?>
                  <?php if (!empty($post['is_repost'])): ?>
                    <span class="post-time-note">(original post)</span>
                  <?php endif; ?>
                </span>
              </div>
            </div>

            <h3><?php echo htmlspecialchars($post['post_title']); ?></h3>

            <?php if ($post['post_type'] === 'Recipe' && !empty($post['recipe_name'])):
              $thumb = !empty($post['recipe_image']) ? $post['recipe_image'] : placeholder_img('recipe-' . $post['recipe_id'], 200, 200);
              $showRecipeContent = !in_array(trim((string) $post['post_content']), $genericRecipeText, true);
            ?>
              <div class="recipe-post-card" style="border:none; padding:0; box-shadow:none; margin-bottom:14px;">
                <div class="thumb"><img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($post['recipe_name']); ?>"></div>
                <div class="recipe-post-body">
                  <a href="recipe-detail.php?id=<?php echo (int) $post['recipe_id']; ?>" class="shared-recipe-link">
                    <svg viewBox="0 0 24 24" width="14" height="14" style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                    <?php echo htmlspecialchars($post['recipe_name']); ?>
                  </a>
                  <?php if ($showRecipeContent): ?>
                    <p class="post-body"><?php echo nl2br(htmlspecialchars($post['post_content'])); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <?php if (!empty($post['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="" style="width:100%; max-height:320px; object-fit:cover; border-radius:12px; margin-bottom:14px;" loading="lazy">
              <?php endif; ?>
              <p class="post-body"><?php echo nl2br(htmlspecialchars($post['post_content'])); ?></p>
              <?php if (!empty($post['steps'])): ?>
                <ol class="step-list" style="margin-bottom:14px;">
                  <?php foreach ($post['steps'] as $step): ?>
                    <li><p><?php echo htmlspecialchars($step); ?></p></li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            <?php endif; ?>

            <div class="post-actions">
              <form method="POST" action="community.php" style="display:inline;" data-ajax-action="toggle_like">
                <input type="hidden" name="action" value="toggle_like">
                <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="post-like-btn <?php echo $post['liked_by_me'] ? 'active' : ''; ?>" <?php echo empty($_SESSION['user_id']) ? 'disabled title="Log in to like"' : ''; ?>>
                  <svg viewBox="0 0 24 24" fill="<?php echo $post['liked_by_me'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-4.5-9.5-8.5C.5 8.5 2.5 5 6 5c2 0 3.5 1.2 4 2.2C10.5 6.2 12 5 14 5c3.5 0 5.5 3.5 3.5 7.5C15 16.5 12 21 12 21z"/></svg>
                  <span class="action-count"><?php echo (int) $post['like_count']; ?></span>
                </button>
              </form>

              <a href="post-detail.php?id=<?php echo (int) $post['post_id']; ?>" class="post-comment-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                <?php echo (int) $post['comment_count']; ?> comment<?php echo $post['comment_count'] == 1 ? '' : 's'; ?>
              </a>

              <?php if (!empty($_SESSION['user_id']) && (int) $post['author_id'] !== (int) $_SESSION['user_id']): ?>
                <div class="repost-group <?php echo $post['reposted_by_me'] ? 'is-active' : ''; ?>" style="margin-left:auto;">
                  <form method="POST" action="community.php" data-ajax-action="toggle_repost" id="repostForm-<?php echo (int) $postIndex; ?>">
                    <input type="hidden" name="action" value="toggle_repost">
                    <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                    <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <button type="submit" class="repost-btn">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                      <span class="action-label"><?php echo $post['reposted_by_me'] ? 'Reposted' : 'Repost'; ?></span>
                    </button>
                  </form>
                  <?php if (!$post['reposted_by_me']): ?>
                    <button type="button" class="repost-caret" data-repost-note-toggle="repostNote-<?php echo (int) $postIndex; ?>" aria-label="Add your thoughts before reposting" title="Add your thoughts before reposting">
                      <svg viewBox="0 0 24 24" width="14" height="14" style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($_SESSION['user_id']) && (int) $post['author_id'] !== (int) $_SESSION['user_id'] && !$post['reposted_by_me']): ?>
                <div class="repost-note-field" id="repostNote-<?php echo (int) $postIndex; ?>" style="display:none;">
                  <label for="repostNoteInput-<?php echo (int) $postIndex; ?>">Add your thoughts <span>(optional — shown with your repost)</span></label>
                  <textarea id="repostNoteInput-<?php echo (int) $postIndex; ?>" form="repostForm-<?php echo (int) $postIndex; ?>" name="share_comment" maxlength="500"
                            placeholder="What made you want to share this?"></textarea>
                </div>
              <?php endif; ?>

              <?php if (empty($post['is_repost']) && !empty($_SESSION['user_id']) && (int) $post['author_id'] === (int) $_SESSION['user_id']): ?>
                <form method="POST" action="community.php" style="display:inline; margin-left:auto;" data-confirm="Delete this post? This can't be undone.">
                  <input type="hidden" name="action" value="delete_post">
                  <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
                  <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                  <button type="submit" class="post-comment-link post-delete-btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m2 0v13a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z"/></svg>
                    Delete
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Community post pages">
          <?php if ($page > 1): ?>
            <a href="<?php echo tab_url($activeTab, $page - 1); ?>">‹</a>
          <?php endif; ?>
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p == $page): ?>
              <span class="current"><?php echo $p; ?></span>
            <?php else: ?>
              <a href="<?php echo tab_url($activeTab, $p); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="<?php echo tab_url($activeTab, $page + 1); ?>">›</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>

  </div>
</div>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>