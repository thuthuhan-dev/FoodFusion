<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

/* ---------- Read filters from the query string ---------- */
$filterCuisine  = isset($_GET['cuisine']) && $_GET['cuisine'] !== '' ? (int) $_GET['cuisine'] : null;
$filterCategory = isset($_GET['category']) && $_GET['category'] !== '' ? (int) $_GET['category'] : null;
$filterDifficulty = isset($_GET['difficulty']) && $_GET['difficulty'] !== '' ? (int) $_GET['difficulty'] : null;
$filterDietary  = isset($_GET['dietary']) && $_GET['dietary'] !== '' ? (int) $_GET['dietary'] : null;
$searchTerm     = isset($_GET['q']) ? trim($_GET['q']) : '';
$activeFilterCount = (int) ($filterCuisine !== null) + (int) ($filterCategory !== null) + (int) ($filterDifficulty !== null) + (int) ($filterDietary !== null);

$page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = 9;
$cardMessage = null;
if (isset($_GET['recipe_deleted'])) {
    $cardMessage = 'Recipe deleted.';
}

/* ---------- Save (favourite) a recipe straight from the collection ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_favorite') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postedId = (int) ($_POST['recipe_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $cardMessage = 'Your session expired &mdash; please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $cardMessage = 'Log in to save recipes to your favourites.';
    } elseif ($pdo && $postedId > 0) {
        try {
            $check = $pdo->prepare('SELECT favorite_id FROM RECIPE_FAVORITE WHERE user_id = ? AND recipe_id = ?');
            $check->execute([$_SESSION['user_id'], $postedId]);
            $existing = $check->fetch();
            if ($existing) {
                $pdo->prepare('DELETE FROM RECIPE_FAVORITE WHERE favorite_id = ?')->execute([$existing['favorite_id']]);
            } else {
                $pdo->prepare('INSERT INTO RECIPE_FAVORITE (user_id, recipe_id) VALUES (?, ?)')->execute([$_SESSION['user_id'], $postedId]);
            }
            header('Location: ' . ($_POST['return_to'] ?? 'recipes.php'));
            exit;
        } catch (PDOException $e) {
            $cardMessage = 'Something went wrong updating your favourites.';
        }
    }
}

/* ---------- Share a recipe to the Community Cookbook ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share_recipe') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $postedId = (int) ($_POST['recipe_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $cardMessage = 'Your session expired &mdash; please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $cardMessage = 'Log in to share recipes to the Community Cookbook.';
    } elseif ($pdo && $postedId > 0) {
        try {
            $nameStmt = $pdo->prepare('SELECT recipe_name FROM RECIPE WHERE recipe_id = ?');
            $nameStmt->execute([$postedId]);
            $sharedName = $nameStmt->fetchColumn();

            if ($sharedName === false) {
                $cardMessage = 'That recipe no longer exists.';
            } else {
                $dupe = $pdo->prepare(
                    "SELECT post_id FROM COMMUNITY_POST WHERE user_id = ? AND recipe_id = ? AND post_type = 'Recipe'"
                );
                $dupe->execute([$_SESSION['user_id'], $postedId]);
                if ($dupe->fetch()) {
                    $cardMessage = 'You have already shared \u201c' . htmlspecialchars($sharedName) . '\u201d to the Community Cookbook.';
                } else {
                    $pdo->prepare(
                        "INSERT INTO COMMUNITY_POST (user_id, recipe_id, post_type, post_title, post_content)
                         VALUES (?, ?, 'Recipe', ?, ?)"
                    )->execute([$_SESSION['user_id'], $postedId, $sharedName, 'Shared a recipe worth trying.']);
                    header('Location: community.php?tab=recipe');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $cardMessage = 'Something went wrong sharing that recipe. Please try again.';
        }
    }
}

/* ---------- Add a new recipe ---------- */
$recipeFormError = null;
$recipeFormOpen  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_recipe') {
    $recipeFormOpen = true;
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $recipeFormError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $recipeFormError = 'Log in to add a recipe.';
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

        // Ingredient rows: each row is either an existing ingredient (a numeric
        // id) or a brand-new one the user typed in (id = "__new__" plus a name).
        // Resolution of new ingredient names into real ingredient_ids happens
        // inside the transaction below, since it needs $pdo and must roll back
        // cleanly with everything else if anything later fails.
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

        $uploadError = null;
        $rImage = handle_image_upload($_FILES['image_file'] ?? null, $uploadError);

        if ($uploadError) {
            $recipeFormError = $uploadError;
        } elseif ($rImage === null) {
            $recipeFormError = 'Please add a photo of the recipe.';
        } elseif ($rName === '' || mb_strlen($rName) > 150) {
            $recipeFormError = 'Give the recipe a name under 150 characters.';
        } elseif ($rCuisine <= 0 || $rCategory <= 0 || $rDiff <= 0) {
            $recipeFormError = 'Please choose a cuisine, category and difficulty.';
        } elseif ($rPrep < 0 || $rCook < 0) {
            $recipeFormError = 'Preparation and cooking times cannot be negative.';
        } elseif ($rServings <= 0) {
            $recipeFormError = 'Servings must be at least 1.';
        } elseif (count($ingredientRows) === 0) {
            $recipeFormError = 'Add at least one ingredient, with a quantity and a unit.';
        } elseif (count($stepRows) === 0) {
            $recipeFormError = 'Add at least one method step.';
        } elseif ($pdo) {
            try {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'INSERT INTO RECIPE
                       (user_id, cuisine_id, category_id, difficulty_id, recipe_name,
                        description, prep_time, cook_time, servings, image_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $_SESSION['user_id'], $rCuisine, $rCategory, $rDiff, $rName,
                    $rDesc !== '' ? $rDesc : null, $rPrep, $rCook, $rServings, $rImage,
                ]);
                $newRecipeId = (int) $pdo->lastInsertId();

                $ingStmt = $pdo->prepare(
                    'INSERT INTO RECIPE_INGREDIENT (recipe_id, ingredient_id, unit_id, quantity)
                     VALUES (?, ?, ?, ?)'
                );
                $findIngStmt = $pdo->prepare('SELECT ingredient_id FROM INGREDIENT WHERE LOWER(ingredient_name) = LOWER(?)');
                $newIngStmt  = $pdo->prepare('INSERT INTO INGREDIENT (ingredient_name) VALUES (?)');

                $seenIngredients = [];
                foreach ($ingredientRows as $row) {
                    if ($row['type'] === 'new') {
                        // Re-use an existing ingredient with the same name (case-insensitive)
                        // rather than creating a near-duplicate like "Onion" and "onion".
                        $findIngStmt->execute([$row['name']]);
                        $existingId = $findIngStmt->fetchColumn();
                        if ($existingId) {
                            $resolvedId = (int) $existingId;
                        } else {
                            $newIngStmt->execute([$row['name']]);
                            $resolvedId = (int) $pdo->lastInsertId();
                        }
                    } else {
                        $resolvedId = $row['ingredient_id'];
                    }

                    // UNIQUE(recipe_id, ingredient_id) — skip a repeated ingredient
                    // (including two "new" rows that turned out to resolve to the
                    // same existing ingredient) rather than letting the insert fail.
                    if (isset($seenIngredients[$resolvedId])) continue;
                    $seenIngredients[$resolvedId] = true;

                    $ingStmt->execute([$newRecipeId, $resolvedId, $row['unit_id'], $row['quantity']]);
                }

                $stepStmt = $pdo->prepare(
                    'INSERT INTO RECIPE_STEP (recipe_id, step_number, instruction) VALUES (?, ?, ?)'
                );
                foreach ($stepRows as $i => $instruction) {
                    $stepStmt->execute([$newRecipeId, $i + 1, $instruction]);
                }

                if ($rDietary) {
                    $dietStmt = $pdo->prepare(
                        'INSERT IGNORE INTO RECIPE_DIETARY (recipe_id, dietary_id) VALUES (?, ?)'
                    );
                    foreach (array_unique($rDietary) as $dId) {
                        if ($dId > 0) $dietStmt->execute([$newRecipeId, $dId]);
                    }
                }

                // A new recipe also appears in the Community Cookbook as a Recipe post.
                $pdo->prepare(
                    "INSERT INTO COMMUNITY_POST (user_id, recipe_id, post_type, post_title, post_content)
                     VALUES (?, ?, 'Recipe', ?, ?)"
                )->execute([
                    $_SESSION['user_id'],
                    $newRecipeId,
                    $rName,
                    $rDesc !== '' ? $rDesc : 'Added a new recipe to the collection.',
                ]);

                $pdo->commit();
                header('Location: recipe-detail.php?id=' . $newRecipeId);
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $recipeFormError = 'Something went wrong saving that recipe. Please try again.';
            }
        }
    }
}

/* ---------- Filter option lists for the dropdowns ---------- */
$cuisines = $categories = $difficulties = $dietaryOptions = [];
$allIngredients = $allUnits = [];
if ($pdo) {
    try {
        $cuisines       = $pdo->query('SELECT cuisine_id, cuisine_name FROM CUISINE_TYPE ORDER BY cuisine_name')->fetchAll();
        $categories     = $pdo->query('SELECT category_id, category_name FROM RECIPE_CATEGORY ORDER BY category_name')->fetchAll();
        $difficulties   = $pdo->query('SELECT difficulty_id, difficulty_name FROM DIFFICULTY ORDER BY difficulty_id')->fetchAll();
        $dietaryOptions = $pdo->query('SELECT dietary_id, dietary_name FROM DIETARY_PREFERENCE ORDER BY dietary_name')->fetchAll();
        $allIngredients = $pdo->query('SELECT ingredient_id, ingredient_name FROM INGREDIENT ORDER BY ingredient_name')->fetchAll();
        $allUnits       = $pdo->query('SELECT unit_id, unit_name, unit_symbol FROM UNIT ORDER BY unit_name')->fetchAll();
    } catch (PDOException $e) {
        $cuisines = $categories = $difficulties = $dietaryOptions = [];
        $allIngredients = $allUnits = [];
    }
}

/* ---------- Build the filtered recipe query ---------- */
$recipes = [];
$totalRecipes = 0;

if ($pdo) {
    try {
        $where = [];
        $params = [];

        if ($filterCuisine) { $where[] = 'r.cuisine_id = :cuisine'; $params[':cuisine'] = $filterCuisine; }
        if ($filterCategory) { $where[] = 'r.category_id = :category'; $params[':category'] = $filterCategory; }
        if ($filterDifficulty) { $where[] = 'r.difficulty_id = :difficulty'; $params[':difficulty'] = $filterDifficulty; }
        if ($filterDietary) {
            $where[] = 'EXISTS (SELECT 1 FROM RECIPE_DIETARY rd WHERE rd.recipe_id = r.recipe_id AND rd.dietary_id = :dietary)';
            $params[':dietary'] = $filterDietary;
        }
        if ($searchTerm !== '') {
            $where[] = 'r.recipe_name LIKE :search';
            $params[':search'] = '%' . $searchTerm . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total count for pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM RECIPE r $whereSql");
        $countStmt->execute($params);
        $totalRecipes = (int) $countStmt->fetchColumn();

        // Page of results
        $offset = ($page - 1) * $perPage;
        $listSql = "
            SELECT r.recipe_id, r.recipe_name, r.description, r.prep_time, r.cook_time, r.image_url,
                   c.cuisine_name, cat.category_name, d.difficulty_name
            FROM RECIPE r
            JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
            JOIN RECIPE_CATEGORY cat ON r.category_id = cat.category_id
            JOIN DIFFICULTY d ON r.difficulty_id = d.difficulty_id
            $whereSql
            ORDER BY r.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $listStmt = $pdo->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $recipes = $listStmt->fetchAll();

        // Dietary tags for the recipes on this page, fetched in one extra query
        if ($recipes) {
            $ids = array_column($recipes, 'recipe_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $tagStmt = $pdo->prepare(
                "SELECT rd.recipe_id, dp.dietary_name
                 FROM RECIPE_DIETARY rd
                 JOIN DIETARY_PREFERENCE dp ON rd.dietary_id = dp.dietary_id
                 WHERE rd.recipe_id IN ($placeholders)"
            );
            $tagStmt->execute($ids);
            $tagsByRecipe = [];
            foreach ($tagStmt->fetchAll() as $row) {
                $tagsByRecipe[$row['recipe_id']][] = $row['dietary_name'];
            }
            foreach ($recipes as &$r) {
                $r['dietary_tags'] = $tagsByRecipe[$r['recipe_id']] ?? [];
            }
            unset($r);
        }
    } catch (PDOException $e) {
        $recipes = [];
        $totalRecipes = 0;
    }
}

// Fallback sample data when there's no database connection yet — filters are ignored here,
// this is only so the page is still viewable during development.
if (!$pdo) {
    $recipes = [
        ['recipe_id' => 1, 'recipe_name' => 'Chicken Tagine with Preserved Lemon', 'description' => 'Slow-simmered chicken with olives, preserved lemon, and warm spices.', 'prep_time' => 25, 'cook_time' => 60, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Main Course', 'difficulty_name' => 'Medium', 'dietary_tags' => ['Halal']],
        ['recipe_id' => 2, 'recipe_name' => 'Msemen with Honey and Argan Oil', 'description' => 'Flaky square pancakes, folded and pan-fried, best eaten warm.', 'prep_time' => 40, 'cook_time' => 20, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Breakfast', 'difficulty_name' => 'Easy', 'dietary_tags' => []],
        ['recipe_id' => 3, 'recipe_name' => 'Harira Soup', 'description' => 'A hearty tomato and lentil soup, traditionally the first thing eaten to break the fast.', 'prep_time' => 20, 'cook_time' => 45, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Soup', 'difficulty_name' => 'Easy', 'dietary_tags' => ['Halal']],
        ['recipe_id' => 4, 'recipe_name' => 'Lamb Mechoui', 'description' => 'Whole-roasted lamb shoulder rubbed with ras el hanout and slow-cooked until tender.', 'prep_time' => 30, 'cook_time' => 180, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Main Course', 'difficulty_name' => 'Hard', 'dietary_tags' => []],
        ['recipe_id' => 5, 'recipe_name' => 'Zaalouk Salad', 'description' => 'A smoky, garlicky cooked eggplant and tomato salad, served warm with fresh bread.', 'prep_time' => 15, 'cook_time' => 20, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Salad', 'difficulty_name' => 'Easy', 'dietary_tags' => ['Vegan', 'Gluten-Free']],
        ['recipe_id' => 6, 'recipe_name' => 'Seffa with Almonds', 'description' => 'Steamed couscous tossed with butter, orange blossom water, and toasted almonds.', 'prep_time' => 35, 'cook_time' => 30, 'image_url' => null, 'cuisine_name' => 'Moroccan', 'category_name' => 'Dessert', 'difficulty_name' => 'Medium', 'dietary_tags' => ['Vegetarian']],
    ];
    $totalRecipes = count($recipes);
}

$totalPages = max(1, (int) ceil($totalRecipes / $perPage));

/* ---------- Which of these recipes has the current user already saved / shared? ---------- */
$myFavoriteIds = [];
$mySharedIds = [];
if ($pdo && !empty($_SESSION['user_id']) && !empty($recipes)) {
    try {
        $ids = array_values(array_unique(array_column($recipes, 'recipe_id')));
        $ph = implode(',', array_fill(0, count($ids), '?'));

        $favStmt = $pdo->prepare("SELECT recipe_id FROM RECIPE_FAVORITE WHERE user_id = ? AND recipe_id IN ($ph)");
        $favStmt->execute(array_merge([$_SESSION['user_id']], $ids));
        $myFavoriteIds = array_column($favStmt->fetchAll(), 'recipe_id');

        $shrStmt = $pdo->prepare(
            "SELECT recipe_id FROM COMMUNITY_POST
             WHERE user_id = ? AND post_type = 'Recipe' AND recipe_id IN ($ph)"
        );
        $shrStmt->execute(array_merge([$_SESSION['user_id']], $ids));
        $mySharedIds = array_column($shrStmt->fetchAll(), 'recipe_id');
    } catch (PDOException $e) {
        $myFavoriteIds = $mySharedIds = [];
    }
}

$returnTo = 'recipes.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

// Helper to rebuild the query string while changing one param (used for pagination links)
function filter_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
    }
    return 'recipes.php' . ($params ? ('?' . http_build_query($params)) : '');
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Recipe Collection</span>
    <h1>Recipes from a Moroccan kitchen, and beyond</h1>
    <p>Browse by cuisine, dietary need, or difficulty — from quick weeknight dinners to weekend project cooking.</p>
  </div>
</section>

<div class="wrap">

  <div class="add-recipe-bar">
    <div>
      <h2>Have a recipe to share?</h2>
      <p>Add it to the collection — it will also appear in the Community Cookbook.</p>
    </div>
    <?php if (!empty($_SESSION['user_id'])): ?>
      <button type="button" class="btn btn-primary" id="addRecipeToggle" aria-expanded="false" aria-controls="addRecipePanel">
        <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Add new recipe
      </button>
    <?php else: ?>
      <button type="button" class="btn btn-primary" data-open-modal="loginModal" title="Log in to add a recipe">
        <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Add new recipe
      </button>
    <?php endif; ?>
  </div>

  <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="add-recipe-panel<?php echo $recipeFormOpen ? ' open' : ''; ?>" id="addRecipePanel">
      <?php if ($recipeFormError): ?>
        <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($recipeFormError); ?></p>
      <?php endif; ?>

      <form method="POST" action="recipes.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_recipe">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <h3 class="form-section-title">The basics</h3>
        <div class="field">
          <label for="recipe_name">Recipe name</label>
          <input type="text" id="recipe_name" name="recipe_name" required maxlength="150"
                 placeholder="e.g. Chicken Tagine with Preserved Lemon"
                 value="<?php echo htmlspecialchars($_POST['recipe_name'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="recipe_description">Short description</label>
          <textarea id="recipe_description" name="description" placeholder="What makes this dish worth cooking?"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-grid-3">
          <div class="field">
            <label for="new_cuisine">Cuisine</label>
            <select id="new_cuisine" name="cuisine_id" required>
              <option value="">Choose…</option>
              <?php foreach ($cuisines as $c): ?>
                <option value="<?php echo (int) $c['cuisine_id']; ?>" <?php echo (int) ($_POST['cuisine_id'] ?? 0) === (int) $c['cuisine_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['cuisine_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="new_category">Category</label>
            <select id="new_category" name="category_id" required>
              <option value="">Choose…</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo (int) $c['category_id']; ?>" <?php echo (int) ($_POST['category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($c['category_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="new_difficulty">Difficulty</label>
            <select id="new_difficulty" name="difficulty_id" required>
              <option value="">Choose…</option>
              <?php foreach ($difficulties as $df): ?>
                <option value="<?php echo (int) $df['difficulty_id']; ?>" <?php echo (int) ($_POST['difficulty_id'] ?? 0) === (int) $df['difficulty_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($df['difficulty_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-3">
          <div class="field">
            <label for="prep_time">Prep time (minutes)</label>
            <input type="number" id="prep_time" name="prep_time" required min="0" max="1440"
                   value="<?php echo htmlspecialchars($_POST['prep_time'] ?? '15'); ?>">
          </div>
          <div class="field">
            <label for="cook_time">Cook time (minutes)</label>
            <input type="number" id="cook_time" name="cook_time" required min="0" max="1440"
                   value="<?php echo htmlspecialchars($_POST['cook_time'] ?? '30'); ?>">
          </div>
          <div class="field">
            <label for="servings">Servings</label>
            <input type="number" id="servings" name="servings" required min="1" max="100"
                   value="<?php echo htmlspecialchars($_POST['servings'] ?? '4'); ?>">
          </div>
        </div>

        <div class="field">
          <label>Dietary tags (optional)</label>
          <div class="checkbox-row">
            <?php foreach ($dietaryOptions as $dt): ?>
              <label class="checkbox-chip">
                <input type="checkbox" name="dietary[]" value="<?php echo (int) $dt['dietary_id']; ?>"
                  <?php echo in_array((string) $dt['dietary_id'], $_POST['dietary'] ?? [], true) ? 'checked' : ''; ?>>
                <span><?php echo htmlspecialchars($dt['dietary_name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="recipe_photo">Photo</label>
          <input type="file" id="recipe_photo" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp" required>
          <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
        </div>

        <h3 class="form-section-title">Ingredients</h3>
        <p class="form-hint-block">Pick each ingredient from the list, then set how much of it the recipe needs.</p>

        <div id="ingredientRows">
          <div class="ingredient-row">
            <div class="ingredient-pick">
              <select name="ingredient_id[]" class="ingredient-select">
                <option value="">Choose an ingredient…</option>
                <?php foreach ($allIngredients as $ing): ?>
                  <option value="<?php echo (int) $ing['ingredient_id']; ?>"><?php echo htmlspecialchars($ing['ingredient_name']); ?></option>
                <?php endforeach; ?>
                <option value="__new__">+ Add a new ingredient…</option>
              </select>
              <input type="text" name="new_ingredient_name[]" class="ingredient-new-name" placeholder="Ingredient name" maxlength="150" style="display:none;">
            </div>
            <input type="number" name="quantity[]" step="0.01" min="0" placeholder="Qty">
            <select name="unit_id[]">
              <option value="">Unit</option>
              <?php foreach ($allUnits as $u): ?>
                <option value="<?php echo (int) $u['unit_id']; ?>"><?php echo htmlspecialchars($u['unit_name'] . ' (' . $u['unit_symbol'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="row-remove" aria-label="Remove ingredient">&times;</button>
          </div>
        </div>
        <button type="button" class="btn btn-ghost" id="addIngredientRow">+ Add another ingredient</button>

        <h3 class="form-section-title">Method</h3>
        <div id="methodRows">
          <div class="step-row">
            <span class="step-num">1</span>
            <input type="text" name="step[]" placeholder="Describe the first step">
            <button type="button" class="row-remove" aria-label="Remove step">&times;</button>
          </div>
        </div>
        <button type="button" class="btn btn-ghost" id="addMethodRow">+ Add another step</button>

        <div class="add-recipe-actions">
          <button type="submit" class="btn btn-primary">Publish recipe</button>
          <button type="button" class="btn btn-ghost" id="cancelAddRecipe">Cancel</button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php
    $activeFilterChips = [];
    $chipQuery = $_GET;
    if ($filterCuisine) {
        foreach ($cuisines as $c) {
            if ((int) $c['cuisine_id'] === $filterCuisine) {
                $q = $chipQuery; unset($q['cuisine']);
                $activeFilterChips[] = ['label' => $c['cuisine_name'], 'href' => 'recipes.php' . ($q ? '?' . http_build_query($q) : '')];
                break;
            }
        }
    }
    if ($filterCategory) {
        foreach ($categories as $c) {
            if ((int) $c['category_id'] === $filterCategory) {
                $q = $chipQuery; unset($q['category']);
                $activeFilterChips[] = ['label' => $c['category_name'], 'href' => 'recipes.php' . ($q ? '?' . http_build_query($q) : '')];
                break;
            }
        }
    }
    if ($filterDifficulty) {
        foreach ($difficulties as $d) {
            if ((int) $d['difficulty_id'] === $filterDifficulty) {
                $q = $chipQuery; unset($q['difficulty']);
                $activeFilterChips[] = ['label' => $d['difficulty_name'], 'href' => 'recipes.php' . ($q ? '?' . http_build_query($q) : '')];
                break;
            }
        }
    }
    if ($filterDietary) {
        foreach ($dietaryOptions as $dt) {
            if ((int) $dt['dietary_id'] === $filterDietary) {
                $q = $chipQuery; unset($q['dietary']);
                $activeFilterChips[] = ['label' => $dt['dietary_name'], 'href' => 'recipes.php' . ($q ? '?' . http_build_query($q) : '')];
                break;
            }
        }
    }
  ?>

  <form class="filter-bar" method="GET" action="recipes.php">
    <div class="filter-bar-top">
      <div class="filter-field filter-field-search">
        <label for="q">Search</label>
        <input type="text" id="q" name="q" placeholder="Search recipes…" value="<?php echo htmlspecialchars($searchTerm); ?>">
      </div>
      <button type="button" class="filter-toggle-btn" data-toggle-panel="filterPanel" aria-expanded="false" aria-controls="filterPanel">
        Filters
        <?php if ($activeFilterCount > 0): ?><span class="filter-active-count"><?php echo $activeFilterCount; ?></span><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <button type="submit" class="btn btn-primary">Apply</button>
    </div>

    <?php if ($activeFilterChips): ?>
      <div class="filter-chips">
        <?php foreach ($activeFilterChips as $chip): ?>
          <a href="<?php echo htmlspecialchars($chip['href']); ?>" class="filter-chip"><?php echo htmlspecialchars($chip['label']); ?> <span class="filter-chip-x" aria-hidden="true">&times;</span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="filter-panel" id="filterPanel">
      <div class="filter-field">
        <label for="cuisine">Cuisine</label>
        <select id="cuisine" name="cuisine">
          <option value="">All cuisines</option>
          <?php foreach ($cuisines as $c): ?>
            <option value="<?php echo $c['cuisine_id']; ?>" <?php echo $filterCuisine === (int) $c['cuisine_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($c['cuisine_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="category">Category</label>
        <select id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['category_id']; ?>" <?php echo $filterCategory === (int) $c['category_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($c['category_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="difficulty">Difficulty</label>
        <select id="difficulty" name="difficulty">
          <option value="">Any difficulty</option>
          <?php foreach ($difficulties as $d): ?>
            <option value="<?php echo $d['difficulty_id']; ?>" <?php echo $filterDifficulty === (int) $d['difficulty_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d['difficulty_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="dietary">Dietary</label>
        <select id="dietary" name="dietary">
          <option value="">Any dietary need</option>
          <?php foreach ($dietaryOptions as $dt): ?>
            <option value="<?php echo $dt['dietary_id']; ?>" <?php echo $filterDietary === (int) $dt['dietary_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($dt['dietary_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary filter-panel-apply">Apply filters</button>
    </div>

    <?php if ($filterCuisine || $filterCategory || $filterDifficulty || $filterDietary || $searchTerm !== ''): ?>
      <div class="filter-clear"><a href="recipes.php">Clear all filters</a></div>
    <?php endif; ?>
  </form>

  <?php if ($cardMessage): ?>
    <div class="login-prompt" style="margin: 0 0 20px; text-align: left;"><?php echo $cardMessage; ?></div>
  <?php endif; ?>

  <div class="results-row">
    <span><?php echo (int) $totalRecipes; ?> recipe<?php echo $totalRecipes == 1 ? '' : 's'; ?> found</span>
  </div>

  <?php if (empty($recipes)): ?>
    <div class="no-results">
      <h3>No recipes match those filters</h3>
      <p>Try clearing a filter or searching for something else.</p>
    </div>
  <?php else: ?>
    <div class="recipe-grid">
      <?php foreach ($recipes as $recipe):
        $img = !empty($recipe['image_url']) ? $recipe['image_url'] : placeholder_img('recipe-' . $recipe['recipe_id'], 500, 400);
      ?>
        <article class="recipe-grid-card">
          <div class="recipe-grid-photo">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" loading="lazy">
            <span class="badge"><?php echo htmlspecialchars($recipe['difficulty_name']); ?></span>
          </div>
          <div class="recipe-grid-body">
            <span class="recipe-grid-meta"><?php echo (int) $recipe['prep_time'] + (int) $recipe['cook_time']; ?> min total</span>
            <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
            <p><?php echo htmlspecialchars($recipe['description']); ?></p>
            <a href="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>" class="btn btn-ghost">View recipe &rarr;</a>

            <?php
              $isSaved  = in_array($recipe['recipe_id'], $myFavoriteIds);
              $isShared = in_array($recipe['recipe_id'], $mySharedIds);
            ?>
            <div class="card-actions">
              <form method="POST" action="recipes.php">
                <input type="hidden" name="action" value="toggle_favorite">
                <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="card-action-btn <?php echo $isSaved ? 'active' : ''; ?>"
                        <?php echo empty($_SESSION['user_id']) ? 'disabled title="Log in to save recipes"' : ''; ?>>
                  <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12a1 1 0 011 1v16l-7-4-7 4V4a1 1 0 011-1z"/></svg>
                  <?php echo $isSaved ? 'Saved' : 'Save'; ?>
                </button>
              </form>

              <?php if ($isShared): ?>
                <a href="community.php?tab=recipe" class="card-action-btn active">
                  <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6L9 17l-5-5"/></svg>
                  Shared
                </a>
              <?php else: ?>
                <form method="POST" action="recipes.php">
                  <input type="hidden" name="action" value="share_recipe">
                  <input type="hidden" name="recipe_id" value="<?php echo (int) $recipe['recipe_id']; ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                  <button type="submit" class="card-action-btn"
                          <?php echo empty($_SESSION['user_id']) ? 'disabled title="Log in to share recipes"' : ''; ?>>
                    <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                    Share
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Recipe results pages">
        <?php if ($page > 1): ?>
          <a href="<?php echo htmlspecialchars(filter_url(['page' => $page - 1])); ?>" aria-label="Previous page">‹</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="current"><?php echo $p; ?></span>
          <?php elseif ($p == 1 || $p == $totalPages || abs($p - $page) <= 1): ?>
            <a href="<?php echo htmlspecialchars(filter_url(['page' => $p])); ?>"><?php echo $p; ?></a>
          <?php elseif (abs($p - $page) == 2): ?>
            <span class="ellipsis">…</span>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?php echo htmlspecialchars(filter_url(['page' => $page + 1])); ?>" aria-label="Next page">›</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
</div>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>