<?php
require_once 'includes/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php?auth=login');
    exit;
}

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

$userId = (int) $_SESSION['user_id'];
$user = null;
$favorites = [];
$myRecipes = [];
$myPosts = [];
$myOrganizing = [];
$myAttending = [];

if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT user_id, first_name, last_name, email, role, created_at FROM USERS WHERE user_id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $favStmt = $pdo->prepare(
            "SELECT r.recipe_id, r.recipe_name, r.description, r.prep_time, r.cook_time, r.image_url,
                    c.cuisine_name, cat.category_name, d.difficulty_name, f.created_at AS saved_at
             FROM RECIPE_FAVORITE f
             JOIN RECIPE r ON f.recipe_id = r.recipe_id
             JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
             JOIN RECIPE_CATEGORY cat ON r.category_id = cat.category_id
             JOIN DIFFICULTY d ON r.difficulty_id = d.difficulty_id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC"
        );
        $favStmt->execute([$userId]);
        $favorites = $favStmt->fetchAll();

        $recipeStmt = $pdo->prepare(
            "SELECT r.recipe_id, r.recipe_name, r.description, r.prep_time, r.image_url,
                    c.cuisine_name, d.difficulty_name, r.created_at
             FROM RECIPE r
             JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
             JOIN DIFFICULTY d ON r.difficulty_id = d.difficulty_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC"
        );
        $recipeStmt->execute([$userId]);
        $myRecipes = $recipeStmt->fetchAll();

        $postStmt = $pdo->prepare(
            "SELECT post_id, post_type, post_title, post_content, image_url, recipe_id, created_at
             FROM COMMUNITY_POST
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $postStmt->execute([$userId]);
        $myPosts = $postStmt->fetchAll();

        $organizingStmt = $pdo->prepare(
            "SELECT e.event_id, e.event_title, e.event_date, e.event_location, e.image_url
             FROM EVENT e
             JOIN EVENT_PARTICIPANT ep ON e.event_id = ep.event_id
             JOIN EVENT_ROLE er ON ep.role_id = er.role_id
             WHERE ep.user_id = ? AND er.role_name = 'Organizer'
             ORDER BY e.event_date ASC"
        );
        $organizingStmt->execute([$userId]);
        $myOrganizing = $organizingStmt->fetchAll();

        $attendingStmt = $pdo->prepare(
            "SELECT e.event_id, e.event_title, e.event_date, e.event_location, e.image_url
             FROM EVENT e
             JOIN EVENT_PARTICIPANT ep ON e.event_id = ep.event_id
             JOIN EVENT_ROLE er ON ep.role_id = er.role_id
             WHERE ep.user_id = ? AND er.role_name = 'Participant'
             ORDER BY e.event_date ASC"
        );
        $attendingStmt->execute([$userId]);
        $myAttending = $attendingStmt->fetchAll();

    } catch (PDOException $e) {
        error_log('FoodFusion account.php: ' . $e->getMessage());
    }
}

if (!$user) {
    // Session points at a user that no longer exists — treat as logged out.
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?auth=login');
    exit;
}

$typeTabs = ['Recipe' => 'recipe', 'Cooking Tip' => 'tip', 'Culinary Experience' => 'experience'];

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">My Account</span>
    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
    <p>Your saved recipes, the recipes you've added, and everything you've posted to the community cookbook.</p>
  </div>
</section>

<div class="wrap account-wrap">

  <div class="account-profile-card">
    <span class="account-avatar-lg"><?php echo htmlspecialchars(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))); ?></span>
    <div class="account-profile-info">
      <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
      <p><?php echo htmlspecialchars($user['email']); ?></p>
      <span class="account-meta">
        <?php echo htmlspecialchars($user['role']); ?> &middot;
        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
      </span>
    </div>
    <a href="logout.php" class="btn btn-ghost">Log out</a>
  </div>

  <nav class="account-tabs" aria-label="Account sections">
    <a href="#favorites" class="account-tab-btn">Favorites <span class="tab-count">(<?php echo count($favorites); ?>)</span></a>
    <a href="#my-recipes" class="account-tab-btn">My Recipes <span class="tab-count">(<?php echo count($myRecipes); ?>)</span></a>
    <a href="#my-posts" class="account-tab-btn">My Posts <span class="tab-count">(<?php echo count($myPosts); ?>)</span></a>
    <a href="#my-events" class="account-tab-btn">My Events <span class="tab-count">(<?php echo count($myOrganizing) + count($myAttending); ?>)</span></a>
  </nav>

  <section class="account-section" id="favorites">
    <div class="section-head">
      <div><span class="eyebrow">Saved</span><h2>My Favorites</h2></div>
      <a href="recipes.php" class="btn btn-ghost">Browse recipes &rarr;</a>
    </div>
    <?php if (empty($favorites)): ?>
      <p class="empty-note">No saved recipes yet — hit Save on any recipe in the collection.</p>
    <?php else: ?>
      <div class="recipe-grid">
        <?php foreach ($favorites as $recipe):
          $img = !empty($recipe['image_url']) ? $recipe['image_url'] : placeholder_img('recipe-' . $recipe['recipe_id'], 500, 400);
        ?>
          <article class="recipe-grid-card">
            <div class="recipe-grid-photo">
              <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" loading="lazy">
              <span class="badge"><?php echo htmlspecialchars($recipe['difficulty_name']); ?></span>
            </div>
            <div class="recipe-grid-body">
              <div class="recipe-grid-meta">
                <span><?php echo htmlspecialchars($recipe['cuisine_name']); ?></span>
                <span><?php echo htmlspecialchars($recipe['category_name']); ?></span>
                <span><?php echo (int) $recipe['prep_time'] + (int) $recipe['cook_time']; ?> min total</span>
              </div>
              <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
              <p><?php echo htmlspecialchars($recipe['description']); ?></p>
              <a href="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>" class="btn btn-ghost">View recipe &rarr;</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="account-section" id="my-recipes">
    <div class="section-head">
      <div><span class="eyebrow">Contributed</span><h2>My Recipes</h2></div>
    </div>
    <?php if (empty($myRecipes)): ?>
      <p class="empty-note">You haven't added a recipe yet — <a href="recipes.php">add one</a> to the collection.</p>
    <?php else: ?>
      <div class="recipe-grid">
        <?php foreach ($myRecipes as $recipe):
          $img = !empty($recipe['image_url']) ? $recipe['image_url'] : placeholder_img('recipe-' . $recipe['recipe_id'], 500, 400);
        ?>
          <article class="recipe-grid-card">
            <div class="recipe-grid-photo">
              <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" loading="lazy">
              <span class="badge"><?php echo htmlspecialchars($recipe['difficulty_name']); ?></span>
            </div>
            <div class="recipe-grid-body">
              <div class="recipe-grid-meta">
                <span><?php echo htmlspecialchars($recipe['cuisine_name']); ?></span>
                <span><?php echo time_ago($recipe['created_at']); ?></span>
              </div>
              <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
              <p><?php echo htmlspecialchars($recipe['description']); ?></p>
              <a href="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>" class="btn btn-ghost">View recipe &rarr;</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="account-section" id="my-posts">
    <div class="section-head">
      <div><span class="eyebrow">Community Cookbook</span><h2>My Posts</h2></div>
      <a href="community.php" class="btn btn-ghost">Go to Community Cookbook &rarr;</a>
    </div>
    <?php if (empty($myPosts)): ?>
      <p class="empty-note">You haven't posted anything yet.</p>
    <?php else: ?>
      <div class="my-post-list">
        <?php foreach ($myPosts as $post): $tab = $typeTabs[$post['post_type']] ?? 'recipe'; ?>
          <article class="my-post-row">
            <div class="my-post-row-body">
              <span class="post-type-badge"><?php echo htmlspecialchars($post['post_type']); ?></span>
              <h3><a href="post-detail.php?id=<?php echo (int) $post['post_id']; ?>"><?php echo htmlspecialchars($post['post_title']); ?></a></h3>
              <p><?php echo htmlspecialchars(mb_strimwidth($post['post_content'], 0, 140, '…')); ?></p>
              <span class="post-time"><?php echo time_ago($post['created_at']); ?></span>
            </div>
            <form method="POST" action="community.php" data-confirm="Delete this post? This can't be undone.">
              <input type="hidden" name="action" value="delete_post">
              <input type="hidden" name="post_id" value="<?php echo (int) $post['post_id']; ?>">
              <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
              <input type="hidden" name="return_to" value="account.php#my-posts">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
              <button type="submit" class="post-delete-btn my-post-delete">
                <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m2 0v13a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z"/></svg>
                Delete
              </button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="account-section" id="my-events">
    <div class="section-head">
      <div><span class="eyebrow">Cooking events</span><h2>My Events</h2></div>
      <a href="index.php#events" class="btn btn-ghost">See all events &rarr;</a>
    </div>

    <?php if (!empty($myOrganizing)): ?>
      <h3 class="form-section-title" style="margin-top:0;">Organizing</h3>
      <div class="my-event-list">
        <?php foreach ($myOrganizing as $ev): ?>
          <a href="index.php#events" class="my-event-row">
            <div>
              <span class="post-type-badge">Organizer</span>
              <h4><?php echo htmlspecialchars($ev['event_title']); ?></h4>
              <p><?php echo htmlspecialchars(date('j F Y', strtotime($ev['event_date']))); ?> &middot; <?php echo htmlspecialchars($ev['event_location']); ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($myAttending)): ?>
      <h3 class="form-section-title">Attending</h3>
      <div class="my-event-list">
        <?php foreach ($myAttending as $ev): ?>
          <a href="index.php#events" class="my-event-row">
            <div>
              <span class="post-type-badge type-tip">Going</span>
              <h4><?php echo htmlspecialchars($ev['event_title']); ?></h4>
              <p><?php echo htmlspecialchars(date('j F Y', strtotime($ev['event_date']))); ?> &middot; <?php echo htmlspecialchars($ev['event_location']); ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($myOrganizing) && empty($myAttending)): ?>
      <p class="empty-note">You're not organizing or attending any events yet — <a href="index.php#events">browse upcoming events</a>.</p>
    <?php endif; ?>
  </section>

</div>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>