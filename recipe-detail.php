<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

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
$dietaryIds = [];
$recipeCard = null;
$favoriteCount = 0;
$isFavorited = false;
$favoriteMessage = null;
$recipeUpdated = isset($_GET['recipe_updated']);
$shareMessage = null;
$alreadyShared = false;
$deleteError = null;

/* ---------- Delete this recipe (author only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_recipe') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['recipe_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $deleteError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $deleteError = 'Log in to manage your recipes.';
    } elseif ($pdo && $targetId > 0) {
        try {
            // Ownership check happens in the WHERE clause below, not just in
            // the UI — this is what actually stops someone deleting a recipe
            // that isn't theirs.
            $ownerStmt = $pdo->prepare('SELECT user_id, image_url FROM RECIPE WHERE recipe_id = ?');
            $ownerStmt->execute([$targetId]);
            $recipeRow = $ownerStmt->fetch();

            if (!$recipeRow || (int) $recipeRow['user_id'] !== (int) $_SESSION['user_id']) {
                $deleteError = 'You can only delete recipes you added yourself.';
            } else {
                $pdo->beginTransaction();

                // Community posts point AT a recipe (ON DELETE SET NULL) — deleting
                // the recipe alone would leave a "Recipe" post with no recipe
                // attached. Remove the auto-created Recipe posts for it first, which
                // cascades their own steps/likes/comments/shares automatically.
                $pdo->prepare("DELETE FROM COMMUNITY_POST WHERE recipe_id = ? AND post_type = 'Recipe'")
                    ->execute([$targetId]);

                // Everything else (RECIPE_INGREDIENT, RECIPE_STEP, RECIPE_DIETARY,
                // NUTRITION, RECIPE_FAVORITE, RECIPE_CARD) cascades automatically.
                $del = $pdo->prepare('DELETE FROM RECIPE WHERE recipe_id = ? AND user_id = ?');
                $del->execute([$targetId, $_SESSION['user_id']]);

                $pdo->commit();

                if ($del->rowCount() > 0 && !empty($recipeRow['image_url'])) {
                    delete_uploaded_file($recipeRow['image_url']);
                }

                header('Location: recipes.php?recipe_deleted=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FoodFusion delete_recipe: ' . $e->getMessage());
            $deleteError = 'Something went wrong deleting that recipe. Please try again.';
        }
    }
}

/* ---------- Edit this recipe (author only) ---------- */
$editFormError = null;
$editFormOpen = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_recipe') {
    $editFormOpen = true;
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['recipe_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $editFormError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $editFormError = 'Log in to manage your recipes.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, image_url FROM RECIPE WHERE recipe_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingRecipe = $ownerStmt->fetch();

            if (!$existingRecipe || (int) $existingRecipe['user_id'] !== (int) $_SESSION['user_id']) {
                $editFormError = 'You can only edit recipes you added yourself.';
            } else {
                $rName     = trim($_POST['recipe_name'] ?? '');
                $rDesc     = trim($_POST['description'] ?? '');
                $rCuisine  = (int) ($_POST['cuisine_id'] ?? 0);
                $rCategory = (int) ($_POST['category_id'] ?? 0);
                $rDiff     = (int) ($_POST['difficulty_id'] ?? 0);
                $rPrep     = (int) ($_POST['prep_time'] ?? -1);
                $rCook     = (int) ($_POST['cook_time'] ?? -1);
                $rServings = (int) ($_POST['servings'] ?? 0);
                $rDietary  = array_map('intval', $_POST['dietary'] ?? []);

                $ingIds     = $_POST['ingredient_id'] ?? [];
                $ingNewName = $_POST['new_ingredient_name'] ?? [];
                $ingQty     = $_POST['quantity'] ?? [];
                $ingUnit    = $_POST['unit_id'] ?? [];
                $ingredientRows = [];
                foreach ($ingIds as $i => $rawId) {
                    $qty = (float) ($ingQty[$i] ?? 0);
                    $uId = (int) ($ingUnit[$i] ?? 0);
                    if ($qty <= 0 || $uId <= 0) continue;
                    if ($rawId === '__new__') {
                        $newName = trim($ingNewName[$i] ?? '');
                        if ($newName === '' || mb_strlen($newName) > 150) continue;
                        $ingredientRows[] = ['type' => 'new', 'name' => $newName, 'quantity' => $qty, 'unit_id' => $uId];
                    } else {
                        $id = (int) $rawId;
                        if ($id <= 0) continue;
                        $ingredientRows[] = ['type' => 'existing', 'ingredient_id' => $id, 'quantity' => $qty, 'unit_id' => $uId];
                    }
                }

                $stepRows = array_values(array_filter(array_map('trim', $_POST['step'] ?? []), fn($s) => $s !== ''));

                // Photo is optional on edit — keep the current one unless a new file is chosen.
                $uploadError = null;
                $newImage = handle_image_upload($_FILES['image_file'] ?? null, $uploadError);

                if ($uploadError) {
                    $editFormError = $uploadError;
                } elseif ($rName === '' || mb_strlen($rName) > 150) {
                    $editFormError = 'Give the recipe a name under 150 characters.';
                } elseif ($rCuisine <= 0 || $rCategory <= 0 || $rDiff <= 0) {
                    $editFormError = 'Please choose a cuisine, category and difficulty.';
                } elseif ($rPrep < 0 || $rCook < 0) {
                    $editFormError = 'Preparation and cooking times cannot be negative.';
                } elseif ($rServings <= 0) {
                    $editFormError = 'Servings must be at least 1.';
                } elseif (count($ingredientRows) === 0) {
                    $editFormError = 'Add at least one ingredient, with a quantity and a unit.';
                } elseif (count($stepRows) === 0) {
                    $editFormError = 'Add at least one method step.';
                } else {
                    $pdo->beginTransaction();

                    $finalImage = $newImage !== null ? $newImage : $existingRecipe['image_url'];

                    $pdo->prepare(
                        'UPDATE RECIPE SET cuisine_id=?, category_id=?, difficulty_id=?, recipe_name=?,
                         description=?, prep_time=?, cook_time=?, servings=?, image_url=?, updated_at=NOW()
                         WHERE recipe_id=? AND user_id=?'
                    )->execute([
                        $rCuisine, $rCategory, $rDiff, $rName, $rDesc !== '' ? $rDesc : null,
                        $rPrep, $rCook, $rServings, $finalImage, $targetId, $_SESSION['user_id'],
                    ]);

                    // Replace ingredients, steps and dietary tags wholesale — simpler
                    // and safer than trying to diff old vs new rows.
                    $pdo->prepare('DELETE FROM RECIPE_INGREDIENT WHERE recipe_id = ?')->execute([$targetId]);
                    $pdo->prepare('DELETE FROM RECIPE_STEP WHERE recipe_id = ?')->execute([$targetId]);
                    $pdo->prepare('DELETE FROM RECIPE_DIETARY WHERE recipe_id = ?')->execute([$targetId]);

                    $ingStmt = $pdo->prepare('INSERT INTO RECIPE_INGREDIENT (recipe_id, ingredient_id, unit_id, quantity) VALUES (?, ?, ?, ?)');
                    $findIngStmt = $pdo->prepare('SELECT ingredient_id FROM INGREDIENT WHERE LOWER(ingredient_name) = LOWER(?)');
                    $newIngStmt  = $pdo->prepare('INSERT INTO INGREDIENT (ingredient_name) VALUES (?)');
                    $seenIngredients = [];
                    foreach ($ingredientRows as $row) {
                        if ($row['type'] === 'new') {
                            $findIngStmt->execute([$row['name']]);
                            $existingId = $findIngStmt->fetchColumn();
                            $resolvedId = $existingId ? (int) $existingId : (function() use ($newIngStmt, $pdo, $row) {
                                $newIngStmt->execute([$row['name']]);
                                return (int) $pdo->lastInsertId();
                            })();
                        } else {
                            $resolvedId = $row['ingredient_id'];
                        }
                        if (isset($seenIngredients[$resolvedId])) continue;
                        $seenIngredients[$resolvedId] = true;
                        $ingStmt->execute([$targetId, $resolvedId, $row['unit_id'], $row['quantity']]);
                    }

                    $stepStmt = $pdo->prepare('INSERT INTO RECIPE_STEP (recipe_id, step_number, instruction) VALUES (?, ?, ?)');
                    foreach ($stepRows as $i => $instruction) {
                        $stepStmt->execute([$targetId, $i + 1, $instruction]);
                    }

                    if ($rDietary) {
                        $dietStmt = $pdo->prepare('INSERT IGNORE INTO RECIPE_DIETARY (recipe_id, dietary_id) VALUES (?, ?)');
                        foreach (array_unique($rDietary) as $dId) {
                            if ($dId > 0) $dietStmt->execute([$targetId, $dId]);
                        }
                    }

                    // The linked Community Cookbook post displays the recipe's name
                    // live via a join, but its OWN heading is a snapshot taken when
                    // it was created — keep that snapshot in sync too.
                    $pdo->prepare("UPDATE COMMUNITY_POST SET post_title = ? WHERE recipe_id = ? AND post_type = 'Recipe'")
                        ->execute([$rName, $targetId]);

                    $pdo->commit();

                    // Old photo is only deleted after the update succeeds, and only
                    // when it was actually replaced with a new one.
                    if ($newImage !== null && !empty($existingRecipe['image_url'])) {
                        delete_uploaded_file($existingRecipe['image_url']);
                    }

                    header('Location: recipe-detail.php?id=' . $targetId . '&recipe_updated=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FoodFusion edit_recipe: ' . $e->getMessage());
            $editFormError = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

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
                'SELECT dp.dietary_id, dp.dietary_name FROM RECIPE_DIETARY rd
                 JOIN DIETARY_PREFERENCE dp ON rd.dietary_id = dp.dietary_id
                 WHERE rd.recipe_id = ?'
            );
            $dietStmt->execute([$recipeId]);
            $dietaryRows = $dietStmt->fetchAll();
            $dietaryTags = array_column($dietaryRows, 'dietary_name');
            $dietaryIds  = array_column($dietaryRows, 'dietary_id');

            $ingStmt = $pdo->prepare(
                'SELECT ri.ingredient_id, ri.unit_id, ri.quantity, i.ingredient_name, un.unit_symbol
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

/* ---------- Data for the Edit Recipe form (only needed if this is your recipe) ---------- */
$cuisines = $categories = $difficulties = $dietaryOptions = $allIngredients = $allUnits = [];
if ($recipe && $pdo && !empty($_SESSION['user_id']) && (int) $recipe['user_id'] === (int) $_SESSION['user_id']) {
    try {
        $cuisines       = $pdo->query('SELECT cuisine_id, cuisine_name FROM CUISINE_TYPE ORDER BY cuisine_name')->fetchAll();
        $categories     = $pdo->query('SELECT category_id, category_name FROM RECIPE_CATEGORY ORDER BY category_name')->fetchAll();
        $difficulties   = $pdo->query('SELECT difficulty_id, difficulty_name FROM DIFFICULTY ORDER BY difficulty_id')->fetchAll();
        $dietaryOptions = $pdo->query('SELECT dietary_id, dietary_name FROM DIETARY_PREFERENCE ORDER BY dietary_name')->fetchAll();
        $allIngredients = $pdo->query('SELECT ingredient_id, ingredient_name FROM INGREDIENT ORDER BY ingredient_name')->fetchAll();
        $allUnits       = $pdo->query('SELECT unit_id, unit_name, unit_symbol FROM UNIT ORDER BY unit_name')->fetchAll();
    } catch (PDOException $e) {
        $cuisines = $categories = $difficulties = $dietaryOptions = $allIngredients = $allUnits = [];
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

        <?php if ($recipeUpdated): ?>
          <p class="auth-success" style="margin-top:14px;">Recipe updated.</p>
        <?php endif; ?>

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

        <?php if ($deleteError): ?>
          <p class="form-note" style="color: var(--paprika); margin-top: 12px;"><?php echo htmlspecialchars($deleteError); ?></p>
        <?php endif; ?>

        <?php if (!empty($_SESSION['user_id']) && (int) $recipe['user_id'] === (int) $_SESSION['user_id']): ?>
          <button type="button" class="btn btn-outline" id="editRecipeToggle" aria-expanded="<?php echo $editFormOpen ? 'true' : 'false'; ?>" aria-controls="editRecipePanel" style="margin-top:14px;">
            <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
            Edit this recipe
          </button>

          <div class="add-recipe-panel<?php echo $editFormOpen ? ' open' : ''; ?>" id="editRecipePanel" style="margin-top:14px;">
            <?php if ($editFormError): ?>
              <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($editFormError); ?></p>
            <?php endif; ?>

            <form method="POST" action="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>" enctype="multipart/form-data">
              <input type="hidden" name="action" value="edit_recipe">
              <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

              <h3 class="form-section-title">The basics</h3>
              <div class="field">
                <label for="edit_recipe_name">Recipe name</label>
                <input type="text" id="edit_recipe_name" name="recipe_name" required maxlength="150" value="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
              </div>
              <div class="field">
                <label for="edit_recipe_description">Short description</label>
                <textarea id="edit_recipe_description" name="description"><?php echo htmlspecialchars($recipe['description'] ?? ''); ?></textarea>
              </div>

              <div class="form-grid-3">
                <div class="field">
                  <label for="edit_cuisine">Cuisine</label>
                  <select id="edit_cuisine" name="cuisine_id" required>
                    <?php foreach ($cuisines as $c): ?>
                      <option value="<?php echo (int) $c['cuisine_id']; ?>" <?php echo (int) $recipe['cuisine_id'] === (int) $c['cuisine_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['cuisine_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="edit_category">Category</label>
                  <select id="edit_category" name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                      <option value="<?php echo (int) $c['category_id']; ?>" <?php echo (int) $recipe['category_id'] === (int) $c['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="edit_difficulty">Difficulty</label>
                  <select id="edit_difficulty" name="difficulty_id" required>
                    <?php foreach ($difficulties as $df): ?>
                      <option value="<?php echo (int) $df['difficulty_id']; ?>" <?php echo (int) $recipe['difficulty_id'] === (int) $df['difficulty_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($df['difficulty_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="form-grid-3">
                <div class="field">
                  <label for="edit_prep_time">Prep time (minutes)</label>
                  <input type="number" id="edit_prep_time" name="prep_time" required min="0" max="1440" value="<?php echo (int) $recipe['prep_time']; ?>">
                </div>
                <div class="field">
                  <label for="edit_cook_time">Cook time (minutes)</label>
                  <input type="number" id="edit_cook_time" name="cook_time" required min="0" max="1440" value="<?php echo (int) $recipe['cook_time']; ?>">
                </div>
                <div class="field">
                  <label for="edit_servings">Servings</label>
                  <input type="number" id="edit_servings" name="servings" required min="1" max="100" value="<?php echo (int) $recipe['servings']; ?>">
                </div>
              </div>

              <div class="field">
                <label>Dietary tags (optional)</label>
                <div class="checkbox-row">
                  <?php foreach ($dietaryOptions as $dt): ?>
                    <label class="checkbox-chip">
                      <input type="checkbox" name="dietary[]" value="<?php echo (int) $dt['dietary_id']; ?>" <?php echo in_array((int) $dt['dietary_id'], $dietaryIds, true) ? 'checked' : ''; ?>>
                      <span><?php echo htmlspecialchars($dt['dietary_name']); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="field">
                <label for="edit_recipe_photo">Photo</label>
                <input type="file" id="edit_recipe_photo" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                <span class="field-hint">Leave empty to keep the current photo. JPG, PNG, GIF or WEBP &middot; max 3 MB.</span>
              </div>

              <h3 class="form-section-title">Ingredients</h3>
              <div id="ingredientRows">
                <?php foreach ($ingredients as $i => $ing): ?>
                  <div class="ingredient-row">
                    <div class="ingredient-pick">
                      <select name="ingredient_id[]" class="ingredient-select">
                        <option value="">Choose an ingredient…</option>
                        <?php foreach ($allIngredients as $opt): ?>
                          <option value="<?php echo (int) $opt['ingredient_id']; ?>" <?php echo (int) $opt['ingredient_id'] === (int) $ing['ingredient_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt['ingredient_name']); ?></option>
                        <?php endforeach; ?>
                        <option value="__new__">+ Add a new ingredient…</option>
                      </select>
                      <input type="text" name="new_ingredient_name[]" class="ingredient-new-name" placeholder="Ingredient name" maxlength="150" style="display:none;">
                    </div>
                    <input type="number" name="quantity[]" step="0.01" min="0" placeholder="Qty" value="<?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $ing['quantity'], 2, '.', ''), '0'), '.')); ?>">
                    <select name="unit_id[]">
                      <option value="">Unit</option>
                      <?php foreach ($allUnits as $u): ?>
                        <option value="<?php echo (int) $u['unit_id']; ?>" <?php echo (int) $u['unit_id'] === (int) $ing['unit_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['unit_name'] . ' (' . $u['unit_symbol'] . ')'); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="row-remove" aria-label="Remove ingredient">&times;</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn btn-ghost" id="addIngredientRow">+ Add another ingredient</button>

              <h3 class="form-section-title">Method</h3>
              <div id="methodRows">
                <?php foreach ($steps as $i => $step): ?>
                  <div class="step-row">
                    <span class="step-num"><?php echo $i + 1; ?></span>
                    <input type="text" name="step[]" value="<?php echo htmlspecialchars($step['instruction']); ?>">
                    <button type="button" class="row-remove" aria-label="Remove step">&times;</button>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn btn-ghost" id="addMethodRow">+ Add another step</button>

              <div class="add-recipe-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <button type="button" class="btn btn-ghost" id="cancelEditRecipe">Cancel</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['user_id']) && (int) $recipe['user_id'] === (int) $_SESSION['user_id']): ?>
          <form method="POST" action="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>"
                data-confirm="Delete this recipe? This removes it from the collection and the homepage, along with its Community Cookbook post. This can't be undone."
                style="margin-top:10px;">
            <input type="hidden" name="action" value="delete_recipe">
            <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="delete-recipe-btn">
              <svg viewBox="0 0 24 24" width="16" height="16" style="width:16px;height:16px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m2 0v13a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z"/></svg>
              Delete this recipe
            </button>
          </form>
        <?php endif; ?>
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
          <?php $cardDownloadName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $recipeCard['card_title']))) . '.pdf'; ?>
          <div class="recipe-card-download">
            <span class="recipe-card-download-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
            </span>
            <div class="recipe-card-download-body">
              <span class="recipe-card-download-label">Printable version</span>
              <h4><?php echo htmlspecialchars($recipeCard['card_title']); ?></h4>
            </div>
            <a href="<?php echo htmlspecialchars($recipeCard['file_url']); ?>" download="<?php echo htmlspecialchars($cardDownloadName); ?>" class="btn btn-outline recipe-card-download-btn" aria-label="Download printable recipe card">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
              Download
            </a>
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