<?php
require_once 'includes/db.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

// Trims trailing zeros off a decimal quantity: 800.00 -> "800", 1.50 -> "1.5"
function format_qty($value) {
    $trimmed = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    return $trimmed === '' ? '0' : $trimmed;
}

$recipeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$recipe = null;
$ingredients = [];
$steps = [];
$nutrition = null;
$dietaryTags = [];
$recipeCard = null;
$favoriteCount = 0;
$isFavorited = false;
$favoriteMessage = null;
$shareMessage = null;
$alreadyShared = false;

/* ---------- Handle sharing this recipe to the Community Cookbook ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share_recipe') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postedId = (int) ($_POST['recipe_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $shareMessage = 'Your session expired \u2014 please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $shareMessage = 'Log in to share recipes to the Community Cookbook.';
    } elseif ($pdo && $postedId > 0) {
        try {
            $nameStmt = $pdo->prepare('SELECT recipe_name FROM RECIPE WHERE recipe_id = ?');
            $nameStmt->execute([$postedId]);
            $sharedName = $nameStmt->fetchColumn();

            if ($sharedName === false) {
                $shareMessage = 'That recipe no longer exists.';
            } else {
                $dupe = $pdo->prepare(
                    "SELECT post_id FROM COMMUNITY_POST
                     WHERE user_id = ? AND recipe_id = ? AND post_type = 'Recipe'"
                );
                $dupe->execute([$_SESSION['user_id'], $postedId]);

                if ($dupe->fetch()) {
                    $shareMessage = 'You have already shared this recipe to the Community Cookbook.';
                } else {
                    $note = trim($_POST['share_note'] ?? '');
                    if ($note === '') {
                        $note = 'Shared a recipe worth trying.';
                    }
                    $pdo->prepare(
                        "INSERT INTO COMMUNITY_POST (user_id, recipe_id, post_type, post_title, post_content)
                         VALUES (?, ?, 'Recipe', ?, ?)"
                    )->execute([$_SESSION['user_id'], $postedId, $sharedName, $note]);

                    header('Location: community.php?tab=recipe');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $shareMessage = 'Something went wrong sharing that recipe. Please try again.';
        }
    }
}

/* ---------- Handle the favorite toggle (requires login) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $favoriteMessage = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $favoriteMessage = 'Log in to save recipes to your favorites.';
    } elseif ($pdo) {
        $postedId = (int) ($_POST['recipe_id'] ?? 0);
        try {
            $check = $pdo->prepare('SELECT favorite_id FROM RECIPE_FAVORITE WHERE user_id = ? AND recipe_id = ?');
            $check->execute([$_SESSION['user_id'], $postedId]);
            $existing = $check->fetch();

            if ($existing) {
                $pdo->prepare('DELETE FROM RECIPE_FAVORITE WHERE favorite_id = ?')->execute([$existing['favorite_id']]);
            } else {
                $pdo->prepare('INSERT INTO RECIPE_FAVORITE (user_id, recipe_id) VALUES (?, ?)')->execute([$_SESSION['user_id'], $postedId]);
            }
            header('Location: recipe-detail.php?id=' . $postedId);
            exit;
        } catch (PDOException $e) {
            $favoriteMessage = 'Something went wrong updating your favorites.';
        }
    }
}

/* ---------- Load the recipe ---------- */
if ($pdo && $recipeId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT r.*, u.first_name, u.last_name, c.cuisine_name, cat.category_name, d.difficulty_name
             FROM RECIPE r
             JOIN USERS u ON r.user_id = u.user_id
             JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
             JOIN RECIPE_CATEGORY cat ON r.category_id = cat.category_id
             JOIN DIFFICULTY d ON r.difficulty_id = d.difficulty_id
             WHERE r.recipe_id = ?"
        );
        $stmt->execute([$recipeId]);
        $recipe = $stmt->fetch();

        if ($recipe) {
            $dietStmt = $pdo->prepare(
                'SELECT dp.dietary_name FROM RECIPE_DIETARY rd
                 JOIN DIETARY_PREFERENCE dp ON rd.dietary_id = dp.dietary_id
                 WHERE rd.recipe_id = ?'
            );
            $dietStmt->execute([$recipeId]);
            $dietaryTags = array_column($dietStmt->fetchAll(), 'dietary_name');

            $ingStmt = $pdo->prepare(
                'SELECT ri.quantity, i.ingredient_name, un.unit_symbol
                 FROM RECIPE_INGREDIENT ri
                 JOIN INGREDIENT i ON ri.ingredient_id = i.ingredient_id
                 JOIN UNIT un ON ri.unit_id = un.unit_id
                 WHERE ri.recipe_id = ?
                 ORDER BY ri.recipe_ingredient_id'
            );
            $ingStmt->execute([$recipeId]);
            $ingredients = $ingStmt->fetchAll();

            $stepStmt = $pdo->prepare(
                'SELECT step_number, instruction FROM RECIPE_STEP WHERE recipe_id = ? ORDER BY step_number'
            );
            $stepStmt->execute([$recipeId]);
            $steps = $stepStmt->fetchAll();

            $nutStmt = $pdo->prepare('SELECT * FROM NUTRITION WHERE recipe_id = ?');
            $nutStmt->execute([$recipeId]);
            $nutrition = $nutStmt->fetch();

            $cardStmt = $pdo->prepare('SELECT card_title, file_url FROM RECIPE_CARD WHERE recipe_id = ? LIMIT 1');
            $cardStmt->execute([$recipeId]);
            $recipeCard = $cardStmt->fetch();

            $favCountStmt = $pdo->prepare('SELECT COUNT(*) FROM RECIPE_FAVORITE WHERE recipe_id = ?');
            $favCountStmt->execute([$recipeId]);
            $favoriteCount = (int) $favCountStmt->fetchColumn();

            if (!empty($_SESSION['user_id'])) {
                $mineStmt = $pdo->prepare('SELECT 1 FROM RECIPE_FAVORITE WHERE user_id = ? AND recipe_id = ?');
                $mineStmt->execute([$_SESSION['user_id'], $recipeId]);
                $isFavorited = (bool) $mineStmt->fetch();

                $sharedStmt = $pdo->prepare(
                    "SELECT 1 FROM COMMUNITY_POST WHERE user_id = ? AND recipe_id = ? AND post_type = 'Recipe'"
                );
                $sharedStmt->execute([$_SESSION['user_id'], $recipeId]);
                $alreadyShared = (bool) $sharedStmt->fetch();
            }
        }
    } catch (PDOException $e) {
        $recipe = null;
    }
}

require_once 'includes/header.php';
?>

<?php if (!$recipe): ?>

  <section class="section" style="text-align:center;">
    <div class="wrap">
      <span class="eyebrow" style="justify-content:center;">Recipe not found</span>
      <h1>We couldn't find that recipe</h1>
      <p class="lede" style="margin-inline:auto;">It may have been removed, or the link might be incorrect.</p>
      <a href="recipes.php" class="btn btn-primary">Browse the Recipe Collection</a>
    </div>
  </section>

<?php else:
  $img = !empty($recipe['image_url']) ? $recipe['image_url'] : placeholder_img('recipe-' . $recipe['recipe_id'], 800, 600);
  $totalTime = (int) $recipe['prep_time'] + (int) $recipe['cook_time'];
?>

  <div class="wrap">
    <a href="recipes.php" class="back-link">
      <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back to Recipe Collection
    </a>
  </div>

  <section class="section" style="padding-bottom: 0;">
    <div class="wrap recipe-detail-hero">
      <div class="recipe-detail-media">
        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
      </div>
      <div>
        <span class="eyebrow"><?php echo htmlspecialchars($recipe['cuisine_name']); ?> · <?php echo htmlspecialchars($recipe['category_name']); ?></span>
        <h1><?php echo htmlspecialchars($recipe['recipe_name']); ?></h1>
        <p class="lede"><?php echo htmlspecialchars($recipe['description']); ?></p>

        <div class="recipe-meta-pills">
          <span class="pill"><?php echo htmlspecialchars($recipe['difficulty_name']); ?></span>
          <?php foreach ($dietaryTags as $tag): ?>
            <span class="pill diet"><?php echo htmlspecialchars($tag); ?></span>
          <?php endforeach; ?>
        </div>

        <div class="recipe-stat-grid">
          <div><span class="stat-value"><?php echo (int) $recipe['prep_time']; ?> min</span><span class="stat-label">Prep</span></div>
          <div><span class="stat-value"><?php echo (int) $recipe['cook_time']; ?> min</span><span class="stat-label">Cook</span></div>
          <div><span class="stat-value"><?php echo (int) $recipe['servings']; ?></span><span class="stat-label">Servings</span></div>
        </div>

        <div class="recipe-author-row">
          <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($recipe['first_name'], 0, 1) . substr($recipe['last_name'], 0, 1))); ?></span>
          <span class="author-name">Recipe by <?php echo htmlspecialchars($recipe['first_name'] . ' ' . $recipe['last_name']); ?></span>
        </div>

        <?php if ($favoriteMessage): ?>
          <p class="form-note" style="color: var(--paprika); margin-top: 12px;"><?php echo htmlspecialchars($favoriteMessage); ?></p>
        <?php endif; ?>

        <form class="favorite-form" method="POST" action="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>">
          <input type="hidden" name="action" value="toggle_favorite">
          <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <button type="submit" class="btn-favorite <?php echo $isFavorited ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="<?php echo $isFavorited ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12a1 1 0 011 1v16l-7-4-7 4V4a1 1 0 011-1z"/></svg>
            <?php echo $isFavorited ? 'Saved' : 'Save recipe'; ?> · <?php echo $favoriteCount; ?>
          </button>
        </form>

        <?php if ($shareMessage): ?>
          <p class="form-note" style="color: var(--paprika); margin-top: 12px;"><?php echo htmlspecialchars($shareMessage); ?></p>
        <?php endif; ?>

        <div class="share-widget">
          <?php if (empty($_SESSION['user_id'])): ?>
            <button type="button" class="share-toggle-btn" disabled title="Log in to share recipes">
              <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
              Share to Community Cookbook
            </button>
            <p class="form-note" style="margin-top:8px;"><a href="login.php">Log in</a> to share this recipe.</p>
          <?php elseif ($alreadyShared): ?>
            <p class="form-note">
              You&rsquo;ve shared this recipe &mdash;
              <a href="community.php?tab=recipe">view it in the Community Cookbook</a>.
            </p>
          <?php else: ?>
            <button type="button" class="share-toggle-btn" id="shareToggleBtn">
              <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
              Share to Community Cookbook
            </button>
            <div class="share-panel" id="sharePanel">
              <form method="POST" action="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>">
                <input type="hidden" name="action" value="share_recipe">
                <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <label for="share_note" style="display:block; font-size:0.82rem; font-weight:600; margin-bottom:6px;">Add a note (optional)</label>
                <textarea id="share_note" name="share_note" placeholder="Why are you sharing this recipe?"></textarea>
                <button type="submit" class="btn btn-primary">Post to Cookbook</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top: 0;">
    <div class="wrap recipe-detail-body">
      <aside>
        <h2>Ingredients</h2>
        <?php if ($ingredients): ?>
          <ul class="ingredient-list">
            <?php foreach ($ingredients as $ing): ?>
              <li>
                <span><?php echo htmlspecialchars($ing['ingredient_name']); ?></span>
                <span class="qty"><?php echo format_qty($ing['quantity']); ?> <?php echo htmlspecialchars($ing['unit_symbol']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="lede">Ingredients haven't been added for this recipe yet.</p>
        <?php endif; ?>

        <?php if ($nutrition): ?>
          <div class="nutrition-card">
            <h3>Nutrition (per serving)</h3>
            <div class="nutrition-grid">
              <div><span class="n-value"><?php echo (int) $nutrition['calories']; ?></span><span class="n-label">Calories</span></div>
              <div><span class="n-value"><?php echo (int) $nutrition['protein']; ?>g</span><span class="n-label">Protein</span></div>
              <div><span class="n-value"><?php echo (int) $nutrition['fat']; ?>g</span><span class="n-label">Fat</span></div>
              <div><span class="n-value"><?php echo (int) $nutrition['carbohydrates']; ?>g</span><span class="n-label">Carbs</span></div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($recipeCard): ?>
          <div class="recipe-card-download">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            <span>Printable version available — <a href="<?php echo htmlspecialchars($recipeCard['file_url']); ?>"><?php echo htmlspecialchars($recipeCard['card_title']); ?></a></span>
          </div>
        <?php endif; ?>
      </aside>

      <div>
        <h2>Method</h2>
        <?php if ($steps): ?>
          <ol class="step-list">
            <?php foreach ($steps as $step): ?>
              <li><p><?php echo htmlspecialchars($step['instruction']); ?></p></li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="lede">Steps haven't been added for this recipe yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>