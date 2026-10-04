<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

$formErrors = ['card' => null, 'tutorial' => null, 'hack' => null, 'tip' => null];
$formOpen   = ['card' => false, 'tutorial' => false, 'hack' => false, 'tip' => false];
$resourceAddedLabels = ['card' => 'Recipe card', 'tutorial' => 'Tutorial', 'hack' => 'Kitchen hack', 'tip' => 'Cooking tip'];
$resourceSuccess = isset($_GET['added'], $resourceAddedLabels[$_GET['added']])
    ? $resourceAddedLabels[$_GET['added']] . ' added.'
    : null;

// Edit forms: keyed the same way as $formErrors, but the value is the
// specific item's id being edited (or null), since a list page can have
// many editable items open at once — only the one just submitted reopens
// with its error on redisplay.
$editErrors = ['card' => null, 'tutorial' => null, 'hack' => null, 'tip' => null];
$editOpenId = ['card' => null, 'tutorial' => null, 'hack' => null, 'tip' => null];
if (isset($_GET['card_updated'])) $resourceSuccess = 'Recipe card updated.';
if (isset($_GET['tutorial_updated'])) $resourceSuccess = 'Tutorial updated.';
if (isset($_GET['hack_updated'])) $resourceSuccess = 'Kitchen hack updated.';
if (isset($_GET['tip_updated'])) $resourceSuccess = 'Cooking tip updated.';

/* ---------- Add: Recipe Card ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_recipe_card') {
    $formOpen['card'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $formErrors['card'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $formErrors['card'] = 'Log in to add a recipe card.';
    } elseif ($pdo) {
        $recipeId = (int) ($_POST['recipe_id'] ?? 0);
        $cardTitle = trim($_POST['card_title'] ?? '');
        $docError = null;
        $fileUrl = handle_document_upload($_FILES['file_upload'] ?? null, $docError);

        if ($docError) {
            $formErrors['card'] = $docError;
        } elseif ($recipeId <= 0) {
            $formErrors['card'] = 'Please choose which recipe this card is for.';
        } elseif ($cardTitle === '' || mb_strlen($cardTitle) > 150) {
            $formErrors['card'] = 'Give the card a title under 150 characters.';
        } elseif ($fileUrl === null) {
            $formErrors['card'] = 'Please attach a PDF file.';
        } else {
            try {
                $pdo->prepare('INSERT INTO RECIPE_CARD (recipe_id, user_id, card_title, file_url) VALUES (?, ?, ?, ?)')
                    ->execute([$recipeId, $_SESSION['user_id'], $cardTitle, $fileUrl]);
                header('Location: resources.php?added=card#cards');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion add_recipe_card: ' . $e->getMessage());
                $formErrors['card'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Add: Cooking Tutorial ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tutorial') {
    $formOpen['tutorial'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $formErrors['tutorial'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $formErrors['tutorial'] = 'Log in to add a tutorial.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $typedUrl = trim($_POST['video_url'] ?? '');
        $duration = (int) ($_POST['duration_minutes'] ?? 0);

        // A video file is optional — if one is uploaded it takes priority
        // over a typed link, same as the pasted-URL fallback already used
        // for a Cooking Tip / Culinary Experience post's image.
        $videoError = null;
        $uploadedVideo = handle_video_upload($_FILES['video_file'] ?? null, $videoError);
        $videoUrl = $uploadedVideo !== null
            ? $uploadedVideo
            : (($typedUrl !== '' && filter_var($typedUrl, FILTER_VALIDATE_URL)) ? $typedUrl : '');

        if ($videoError) {
            $formErrors['tutorial'] = $videoError;
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $formErrors['tutorial'] = 'Give the tutorial a title under 150 characters.';
        } elseif ($videoUrl === '') {
            $formErrors['tutorial'] = 'Please provide a video link or upload a video file.';
        } elseif ($duration <= 0) {
            $formErrors['tutorial'] = 'Please give the video length in minutes.';
        } else {
            try {
                $pdo->prepare('INSERT INTO COOKING_TUTORIAL (user_id, title, description, video_url, duration_minutes) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $desc !== '' ? $desc : null, $videoUrl, $duration]);
                header('Location: resources.php?added=tutorial#tutorials');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion add_tutorial: ' . $e->getMessage());
                $formErrors['tutorial'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Add: Kitchen Hack ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_hack') {
    $formOpen['hack'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $formErrors['hack'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $formErrors['hack'] = 'Log in to add a kitchen hack.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $items = array_values(array_filter(array_map('trim', $_POST['item'] ?? []), fn($s) => $s !== ''));
        $imgError = null;
        $imageUrl = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

        if ($imgError) {
            $formErrors['hack'] = $imgError;
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $formErrors['hack'] = 'Give the hack a title under 150 characters.';
        } elseif (count($items) === 0) {
            $formErrors['hack'] = 'Add at least one step.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO KITCHEN_HACK (user_id, title, image_url) VALUES (?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $imageUrl]);
                $hackId = (int) $pdo->lastInsertId();
                $itemStmt = $pdo->prepare('INSERT INTO KITCHEN_HACK_ITEM (hack_id, item_number, item_text) VALUES (?, ?, ?)');
                foreach ($items as $i => $text) {
                    $itemStmt->execute([$hackId, $i + 1, $text]);
                }
                $pdo->commit();
                header('Location: resources.php?added=hack#hacks');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('FoodFusion add_hack: ' . $e->getMessage());
                $formErrors['hack'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Add: Cooking Tip ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tip') {
    $formOpen['tip'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $formErrors['tip'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $formErrors['tip'] = 'Log in to add a cooking tip.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $items = array_values(array_filter(array_map('trim', $_POST['item'] ?? []), fn($s) => $s !== ''));
        $imgError = null;
        $imageUrl = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

        if ($imgError) {
            $formErrors['tip'] = $imgError;
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $formErrors['tip'] = 'Give the tip a title under 150 characters.';
        } elseif (count($items) === 0) {
            $formErrors['tip'] = 'Add at least one point.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO COOKING_TIP (user_id, title, image_url) VALUES (?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $imageUrl]);
                $tipId = (int) $pdo->lastInsertId();
                $itemStmt = $pdo->prepare('INSERT INTO COOKING_TIP_ITEM (tip_id, item_number, item_text) VALUES (?, ?, ?)');
                foreach ($items as $i => $text) {
                    $itemStmt->execute([$tipId, $i + 1, $text]);
                }
                $pdo->commit();
                header('Location: resources.php?added=tip#tips');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('FoodFusion add_tip: ' . $e->getMessage());
                $formErrors['tip'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Edit: Recipe Card (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_recipe_card') {
    $targetId = (int) ($_POST['card_id'] ?? 0);
    $editOpenId['card'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $editErrors['card'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $editErrors['card'] = 'Log in to manage your recipe cards.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, file_url FROM RECIPE_CARD WHERE recipe_card_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingCard = $ownerStmt->fetch();

            if (!$existingCard || (int) $existingCard['user_id'] !== (int) $_SESSION['user_id']) {
                $editErrors['card'] = 'You can only edit recipe cards you added yourself.';
            } else {
                $newRecipeId = (int) ($_POST['recipe_id'] ?? 0);
                $newCardTitle = trim($_POST['card_title'] ?? '');
                $docError = null;
                // A new PDF is optional here — if none was chosen, the current
                // file_url is kept untouched, same as photo replacement on Edit Recipe.
                $newFile = handle_document_upload($_FILES['file_upload'] ?? null, $docError);

                if ($docError) {
                    $editErrors['card'] = $docError;
                } elseif ($newRecipeId <= 0) {
                    $editErrors['card'] = 'Please choose which recipe this card is for.';
                } elseif ($newCardTitle === '' || mb_strlen($newCardTitle) > 150) {
                    $editErrors['card'] = 'Give the card a title under 150 characters.';
                } else {
                    $finalFile = $newFile !== null ? $newFile : $existingCard['file_url'];
                    $pdo->prepare(
                        'UPDATE RECIPE_CARD SET recipe_id = ?, card_title = ?, file_url = ?, updated_at = NOW()
                         WHERE recipe_card_id = ? AND user_id = ?'
                    )->execute([$newRecipeId, $newCardTitle, $finalFile, $targetId, $_SESSION['user_id']]);

                    // Old file is only deleted after the update succeeds, and only
                    // when it was actually replaced with a new one.
                    if ($newFile !== null && !empty($existingCard['file_url'])) {
                        delete_uploaded_file($existingCard['file_url']);
                    }

                    header('Location: resources.php?card_updated=1#cards');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_recipe_card: ' . $e->getMessage());
            $editErrors['card'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Edit: Cooking Tutorial (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_tutorial') {
    $targetId = (int) ($_POST['tutorial_id'] ?? 0);
    $editOpenId['tutorial'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $editErrors['tutorial'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $editErrors['tutorial'] = 'Log in to manage your tutorials.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, video_url FROM COOKING_TUTORIAL WHERE tutorial_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingTutorial = $ownerStmt->fetch();

            if (!$existingTutorial || (int) $existingTutorial['user_id'] !== (int) $_SESSION['user_id']) {
                $editErrors['tutorial'] = 'You can only edit tutorials you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newDesc = trim($_POST['description'] ?? '');
                $typedUrl = trim($_POST['video_url'] ?? '');
                $newDuration = (int) ($_POST['duration_minutes'] ?? 0);

                // A new video file is optional on edit — if none was chosen,
                // fall back to a changed link, or keep the current video_url
                // untouched, same as photo replacement on Edit Recipe.
                $videoError = null;
                $uploadedVideo = handle_video_upload($_FILES['video_file'] ?? null, $videoError);
                if ($uploadedVideo !== null) {
                    $newVideoUrl = $uploadedVideo;
                } elseif ($typedUrl !== '' && filter_var($typedUrl, FILTER_VALIDATE_URL)) {
                    $newVideoUrl = $typedUrl;
                } else {
                    $newVideoUrl = $existingTutorial['video_url'];
                }

                if ($videoError) {
                    $editErrors['tutorial'] = $videoError;
                } elseif ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $editErrors['tutorial'] = 'Give the tutorial a title under 150 characters.';
                } elseif ($newVideoUrl === '') {
                    $editErrors['tutorial'] = 'Please provide a video link or upload a video file.';
                } elseif ($newDuration <= 0) {
                    $editErrors['tutorial'] = 'Please give the video length in minutes.';
                } else {
                    $pdo->prepare(
                        'UPDATE COOKING_TUTORIAL SET title = ?, description = ?, video_url = ?, duration_minutes = ?, updated_at = NOW()
                         WHERE tutorial_id = ? AND user_id = ?'
                    )->execute([$newTitle, $newDesc !== '' ? $newDesc : null, $newVideoUrl, $newDuration, $targetId, $_SESSION['user_id']]);

                    // Old file is only deleted after the update succeeds, and
                    // only when the stored value actually changed away from it.
                    if (is_uploaded_video($existingTutorial['video_url']) && $existingTutorial['video_url'] !== $newVideoUrl) {
                        delete_uploaded_file($existingTutorial['video_url']);
                    }

                    header('Location: resources.php?tutorial_updated=1#tutorials');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_tutorial: ' . $e->getMessage());
            $editErrors['tutorial'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Edit: Kitchen Hack (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_hack') {
    $targetId = (int) ($_POST['hack_id'] ?? 0);
    $editOpenId['hack'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $editErrors['hack'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $editErrors['hack'] = 'Log in to manage your kitchen hacks.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, image_url FROM KITCHEN_HACK WHERE hack_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingHack = $ownerStmt->fetch();

            if (!$existingHack || (int) $existingHack['user_id'] !== (int) $_SESSION['user_id']) {
                $editErrors['hack'] = 'You can only edit kitchen hacks you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newItems = array_values(array_filter(array_map('trim', $_POST['item'] ?? []), fn($s) => $s !== ''));
                $imgError = null;
                $newImage = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

                if ($imgError) {
                    $editErrors['hack'] = $imgError;
                } elseif ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $editErrors['hack'] = 'Give the hack a title under 150 characters.';
                } elseif (count($newItems) === 0) {
                    $editErrors['hack'] = 'Add at least one step.';
                } else {
                    $pdo->beginTransaction();

                    $finalImage = $newImage !== null ? $newImage : $existingHack['image_url'];
                    $pdo->prepare(
                        'UPDATE KITCHEN_HACK SET title = ?, image_url = ?, updated_at = NOW() WHERE hack_id = ? AND user_id = ?'
                    )->execute([$newTitle, $finalImage, $targetId, $_SESSION['user_id']]);

                    // Items are replaced wholesale — same approach as RECIPE_STEP on
                    // Edit Recipe — rather than diffing old and new rows.
                    $pdo->prepare('DELETE FROM KITCHEN_HACK_ITEM WHERE hack_id = ?')->execute([$targetId]);
                    $itemStmt = $pdo->prepare('INSERT INTO KITCHEN_HACK_ITEM (hack_id, item_number, item_text) VALUES (?, ?, ?)');
                    foreach ($newItems as $i => $text) {
                        $itemStmt->execute([$targetId, $i + 1, $text]);
                    }

                    $pdo->commit();

                    if ($newImage !== null && !empty($existingHack['image_url'])) {
                        delete_uploaded_file($existingHack['image_url']);
                    }

                    header('Location: resources.php?hack_updated=1#hacks');
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FoodFusion edit_hack: ' . $e->getMessage());
            $editErrors['hack'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Edit: Cooking Tip (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_tip') {
    $targetId = (int) ($_POST['tip_id'] ?? 0);
    $editOpenId['tip'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $editErrors['tip'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $editErrors['tip'] = 'Log in to manage your cooking tips.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, image_url FROM COOKING_TIP WHERE tip_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingTip = $ownerStmt->fetch();

            if (!$existingTip || (int) $existingTip['user_id'] !== (int) $_SESSION['user_id']) {
                $editErrors['tip'] = 'You can only edit cooking tips you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newItems = array_values(array_filter(array_map('trim', $_POST['item'] ?? []), fn($s) => $s !== ''));
                $imgError = null;
                $newImage = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

                if ($imgError) {
                    $editErrors['tip'] = $imgError;
                } elseif ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $editErrors['tip'] = 'Give the tip a title under 150 characters.';
                } elseif (count($newItems) === 0) {
                    $editErrors['tip'] = 'Add at least one point.';
                } else {
                    $pdo->beginTransaction();

                    $finalImage = $newImage !== null ? $newImage : $existingTip['image_url'];
                    $pdo->prepare(
                        'UPDATE COOKING_TIP SET title = ?, image_url = ?, updated_at = NOW() WHERE tip_id = ? AND user_id = ?'
                    )->execute([$newTitle, $finalImage, $targetId, $_SESSION['user_id']]);

                    $pdo->prepare('DELETE FROM COOKING_TIP_ITEM WHERE tip_id = ?')->execute([$targetId]);
                    $itemStmt = $pdo->prepare('INSERT INTO COOKING_TIP_ITEM (tip_id, item_number, item_text) VALUES (?, ?, ?)');
                    foreach ($newItems as $i => $text) {
                        $itemStmt->execute([$targetId, $i + 1, $text]);
                    }

                    $pdo->commit();

                    if ($newImage !== null && !empty($existingTip['image_url'])) {
                        delete_uploaded_file($existingTip['image_url']);
                    }

                    header('Location: resources.php?tip_updated=1#tips');
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('FoodFusion edit_tip: ' . $e->getMessage());
            $editErrors['tip'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Recipe list, for the "add a recipe card" dropdown ---------- */
$allRecipesForCards = [];
if ($pdo) {
    try {
        $allRecipesForCards = $pdo->query('SELECT recipe_id, recipe_name FROM RECIPE ORDER BY recipe_name')->fetchAll();
    } catch (PDOException $e) {
        $allRecipesForCards = [];
    }
}

/* ---------- Recipe Cards ---------- */
$recipeCards = [];
if ($pdo) {
    try {
        $recipeCards = $pdo->query(
            "SELECT rc.recipe_card_id AS card_id, rc.user_id, rc.card_title, rc.file_url, rc.uploaded_at, r.recipe_name, r.image_url, r.recipe_id
             FROM RECIPE_CARD rc
             JOIN RECIPE r ON rc.recipe_id = r.recipe_id
             ORDER BY rc.uploaded_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $recipeCards = [];
    }
}

/* ---------- Cooking Tutorials ---------- */
$tutorials = [];
if ($pdo) {
    try {
        $tutorials = $pdo->query(
            "SELECT ct.tutorial_id, ct.user_id, ct.title, ct.description, ct.video_url, ct.duration_minutes, u.first_name, u.last_name
             FROM COOKING_TUTORIAL ct
             JOIN USERS u ON ct.user_id = u.user_id
             ORDER BY ct.uploaded_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $tutorials = [];
    }
}

/* ---------- Kitchen Hacks (header + items) ---------- */
$hacks = [];
if ($pdo) {
    try {
        $hacks = $pdo->query(
            "SELECT h.hack_id, h.user_id, h.title, h.image_url, u.first_name, u.last_name
             FROM KITCHEN_HACK h
             JOIN USERS u ON h.user_id = u.user_id
             ORDER BY h.created_at DESC"
        )->fetchAll();
        if ($hacks) {
            $ids = array_column($hacks, 'hack_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemStmt = $pdo->prepare(
                "SELECT hack_id, item_text FROM KITCHEN_HACK_ITEM WHERE hack_id IN ($placeholders) ORDER BY hack_id, item_number"
            );
            $itemStmt->execute($ids);
            $itemsByHack = [];
            foreach ($itemStmt->fetchAll() as $row) {
                $itemsByHack[$row['hack_id']][] = $row['item_text'];
            }
            foreach ($hacks as &$h) {
                $h['items'] = $itemsByHack[$h['hack_id']] ?? [];
            }
            unset($h);
        }
    } catch (PDOException $e) {
        $hacks = [];
    }
}

/* ---------- Cooking Tips (header + items) ---------- */
$tips = [];
if ($pdo) {
    try {
        $tips = $pdo->query(
            "SELECT t.tip_id, t.user_id, t.title, t.image_url, u.first_name, u.last_name
             FROM COOKING_TIP t
             JOIN USERS u ON t.user_id = u.user_id
             ORDER BY t.created_at DESC"
        )->fetchAll();
        if ($tips) {
            $ids = array_column($tips, 'tip_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemStmt = $pdo->prepare(
                "SELECT tip_id, item_text FROM COOKING_TIP_ITEM WHERE tip_id IN ($placeholders) ORDER BY tip_id, item_number"
            );
            $itemStmt->execute($ids);
            $itemsByTip = [];
            foreach ($itemStmt->fetchAll() as $row) {
                $itemsByTip[$row['tip_id']][] = $row['item_text'];
            }
            foreach ($tips as &$t) {
                $t['items'] = $itemsByTip[$t['tip_id']] ?? [];
            }
            unset($t);
        }
    } catch (PDOException $e) {
        $tips = [];
    }
}

/* ---------- Fallback sample content when there's no database yet ---------- */
if (!$pdo) {
    $recipeCards = [
        ['card_id' => 1, 'user_id' => 0, 'card_title' => 'Chicken Tagine — Printable Recipe Card', 'file_url' => '#', 'recipe_name' => 'Chicken Tagine with Preserved Lemon', 'image_url' => null, 'recipe_id' => 1],
        ['card_id' => 2, 'user_id' => 0, 'card_title' => 'Harira Soup — Printable Recipe Card', 'file_url' => '#', 'recipe_name' => 'Harira Soup', 'image_url' => null, 'recipe_id' => 3],
    ];
    $tutorials = [
        ['tutorial_id' => 1, 'user_id' => 0, 'title' => 'How to Fold Msemen Like a Pro', 'description' => 'A close-up walkthrough of the folding technique behind flaky msemen.', 'video_url' => '#', 'duration_minutes' => 8, 'first_name' => 'Youssef', 'last_name' => 'El Amrani'],
        ['tutorial_id' => 2, 'user_id' => 0, 'title' => 'Knife Skills for Moroccan Salads', 'description' => 'Dicing and prep techniques used across Zaalouk and other cooked salads.', 'video_url' => '#', 'duration_minutes' => 12, 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $hacks = [
        ['hack_id' => 1, 'user_id' => 0, 'title' => 'Keep Fresh Herbs Longer', 'image_url' => null, 'first_name' => 'Amina', 'last_name' => 'Idrissi', 'items' => ['Trim the stems before storing, like flowers.', 'Stand herbs upright in a jar with an inch of water.', 'Cover loosely with a plastic bag and refrigerate.']],
    ];
    $tips = [
        ['tip_id' => 1, 'user_id' => 0, 'title' => 'Getting the Most Flavor from Ras el Hanout', 'image_url' => null, 'first_name' => 'Sara', 'last_name' => 'Benali', 'items' => ['Toast the spice blend briefly in a dry pan before using.', 'Add it in two stages — half at the start, half near the end.', 'Store in an airtight container away from light.']],
    ];
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Culinary Resources</span>
    <h1>Recipe cards, tutorials, hacks, and tips</h1>
    <p>Everything that isn't quite a recipe on its own, but makes every recipe easier — downloadable, watchable, and quick to scan.</p>
  </div>
</section>

<div class="wrap">
  <?php if ($resourceSuccess): ?>
    <p class="auth-success"><?php echo htmlspecialchars($resourceSuccess); ?></p>
  <?php endif; ?>

  <nav class="resource-jump-nav">
    <a href="#cards">Recipe Cards</a>
    <a href="#tutorials">Cooking Tutorials</a>
    <a href="#hacks">Kitchen Hacks</a>
    <a href="#tips">Cooking Tips</a>
  </nav>

  <section class="resource-section" id="cards">
    <div class="section-head">
      <div><span class="eyebrow">Print &amp; save</span><h2>Recipe Cards</h2></div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="cardAddPanel">+ Add a recipe card</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a recipe card</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $formOpen['card'] ? ' open' : ''; ?>" id="cardAddPanel">
        <?php if ($formErrors['card']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($formErrors['card']); ?></p>
        <?php endif; ?>
        <form method="POST" action="resources.php#cards" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_recipe_card">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="card_recipe">Which recipe is this card for?</label>
            <select id="card_recipe" name="recipe_id" required>
              <option value="">Choose a recipe…</option>
              <?php foreach ($allRecipesForCards as $r): ?>
                <option value="<?php echo (int) $r['recipe_id']; ?>"><?php echo htmlspecialchars($r['recipe_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="card_title">Card title</label>
            <input type="text" id="card_title" name="card_title" required maxlength="150" placeholder="e.g. Chicken Tagine — Printable Recipe Card">
          </div>
          <div class="field">
            <label for="card_file">PDF file</label>
            <input type="file" id="card_file" name="file_upload" accept="application/pdf" required>
            <span class="field-hint">PDF only &middot; max 5 MB</span>
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add card</button>
            <button type="button" class="btn btn-ghost" data-close-panel="cardAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (empty($recipeCards)): ?>
      <p class="empty-note">No recipe cards have been uploaded yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($recipeCards as $card):
          $img = !empty($card['image_url']) ? $card['image_url'] : placeholder_img('recipe-' . $card['recipe_id'], 120, 120);
          $isCardOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $card['user_id'];
        ?>
          <div class="download-card">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['recipe_name']); ?>">
            <div class="download-card-body">
              <h3><?php echo htmlspecialchars($card['card_title']); ?></h3>
              <span>For <?php echo htmlspecialchars($card['recipe_name']); ?></span>
            </div>
            <?php if ($isCardOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editCardPanel-<?php echo (int) $card['card_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($card['card_title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
            <?php
              // The download attribute tells the browser to SAVE the file instead
              // of opening it in a tab (browsers show PDFs inline by default).
              // Giving it a value also means the user gets a readable filename
              // rather than the random name the file is stored under.
              $cardDownloadName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $card['card_title']))) . '.pdf';
            ?>
            <a href="<?php echo htmlspecialchars($card['file_url']); ?>"
               download="<?php echo htmlspecialchars($cardDownloadName); ?>"
               class="dl-icon" aria-label="Download <?php echo htmlspecialchars($card['card_title']); ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:17px;height:17px;"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            </a>
          </div>
          <?php if ($isCardOwner):
            $cardOpen = $editOpenId['card'] === (int) $card['card_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $cardOpen ? ' open' : ''; ?>" id="editCardPanel-<?php echo (int) $card['card_id']; ?>">
              <?php if ($cardOpen && $editErrors['card']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($editErrors['card']); ?></p>
              <?php endif; ?>
              <form method="POST" action="resources.php#cards" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_recipe_card">
                <input type="hidden" name="card_id" value="<?php echo (int) $card['card_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_card_recipe_<?php echo (int) $card['card_id']; ?>">Which recipe is this card for?</label>
                  <select id="edit_card_recipe_<?php echo (int) $card['card_id']; ?>" name="recipe_id" required>
                    <option value="">Choose a recipe…</option>
                    <?php foreach ($allRecipesForCards as $r): ?>
                      <option value="<?php echo (int) $r['recipe_id']; ?>" <?php echo (int) $r['recipe_id'] === (int) $card['recipe_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['recipe_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="edit_card_title_<?php echo (int) $card['card_id']; ?>">Card title</label>
                  <input type="text" id="edit_card_title_<?php echo (int) $card['card_id']; ?>" name="card_title" required maxlength="150" value="<?php echo htmlspecialchars($card['card_title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_card_file_<?php echo (int) $card['card_id']; ?>">PDF file</label>
                  <input type="file" id="edit_card_file_<?php echo (int) $card['card_id']; ?>" name="file_upload" accept="application/pdf">
                  <span class="field-hint">Leave empty to keep the current file. PDF only &middot; max 5 MB</span>
                </div>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editCardPanel-<?php echo (int) $card['card_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="tutorials">
    <div class="section-head">
      <div><span class="eyebrow">Watch &amp; learn</span><h2>Cooking Tutorials</h2></div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="tutorialAddPanel">+ Add a tutorial</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a tutorial</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $formOpen['tutorial'] ? ' open' : ''; ?>" id="tutorialAddPanel">
        <?php if ($formErrors['tutorial']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($formErrors['tutorial']); ?></p>
        <?php endif; ?>
        <form method="POST" action="resources.php#tutorials" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_tutorial">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="tut_title">Title</label>
            <input type="text" id="tut_title" name="title" required maxlength="150" placeholder="e.g. How to Fold Msemen Like a Pro">
          </div>
          <div class="field">
            <label for="tut_description">Description</label>
            <textarea id="tut_description" name="description" placeholder="What does this tutorial cover?"></textarea>
          </div>
          <div class="field">
            <label for="tut_video">Video link</label>
            <input type="url" id="tut_video" name="video_url" placeholder="https://youtube.com/...">
          </div>
          <div class="field">
            <label for="tut_video_file">Or upload a video file</label>
            <input type="file" id="tut_video_file" name="video_file" accept="video/mp4,video/webm">
            <span class="field-hint">MP4 or WEBM &middot; max 50 MB &middot; leave the link above empty if you upload one here</span>
          </div>
          <div class="field">
            <label for="tut_duration">Length (minutes)</label>
            <input type="number" id="tut_duration" name="duration_minutes" required min="1" max="600">
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add tutorial</button>
            <button type="button" class="btn btn-ghost" data-close-panel="tutorialAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (empty($tutorials)): ?>
      <p class="empty-note">No tutorials have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($tutorials as $t):
          $isTutorialOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $t['user_id'];
          $tutIsUploadedVideo = is_uploaded_video($t['video_url']);
        ?>
          <div class="video-card-wrap">
            <a href="<?php echo htmlspecialchars($t['video_url']); ?>" class="video-card" style="color: inherit;">
              <?php $vthumb = video_thumbnail($t['video_url']); ?>
              <div class="video-card-thumb">
                <?php if ($vthumb): ?>
                  <img class="video-thumb-img" src="<?php echo htmlspecialchars($vthumb); ?>" alt="" loading="lazy">
                <?php endif; ?>
                <span class="play-btn">
                  <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M8 5v14l11-7z"/></svg>
                </span>
                <span class="duration-badge"><?php echo (int) $t['duration_minutes']; ?> min</span>
              </div>
              <div class="video-card-body">
                <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                <p><?php echo htmlspecialchars($t['description']); ?></p>
                <span class="uploader">by <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></span>
              </div>
            </a>
            <?php if ($tutIsUploadedVideo):
              // download attribute: save the file instead of opening it in a
              // tab, and give the user a readable filename rather than the
              // random name the file is stored under.
              $tutExt = pathinfo($t['video_url'], PATHINFO_EXTENSION);
              $tutDownloadName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $t['title']))) . '.' . $tutExt;
            ?>
              <a href="<?php echo htmlspecialchars($t['video_url']); ?>"
                 download="<?php echo htmlspecialchars($tutDownloadName); ?>"
                 class="dl-icon" aria-label="Download <?php echo htmlspecialchars($t['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:17px;height:17px;"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
              </a>
            <?php endif; ?>
            <?php if ($isTutorialOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editTutorialPanel-<?php echo (int) $t['tutorial_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($t['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
          </div>
          <?php if ($isTutorialOwner):
            $tutorialOpen = $editOpenId['tutorial'] === (int) $t['tutorial_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $tutorialOpen ? ' open' : ''; ?>" id="editTutorialPanel-<?php echo (int) $t['tutorial_id']; ?>">
              <?php if ($tutorialOpen && $editErrors['tutorial']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($editErrors['tutorial']); ?></p>
              <?php endif; ?>
              <?php $tutHasUploadedVideo = is_uploaded_video($t['video_url']); ?>
              <form method="POST" action="resources.php#tutorials" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_tutorial">
                <input type="hidden" name="tutorial_id" value="<?php echo (int) $t['tutorial_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_tut_title_<?php echo (int) $t['tutorial_id']; ?>">Title</label>
                  <input type="text" id="edit_tut_title_<?php echo (int) $t['tutorial_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($t['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_tut_description_<?php echo (int) $t['tutorial_id']; ?>">Description</label>
                  <textarea id="edit_tut_description_<?php echo (int) $t['tutorial_id']; ?>" name="description"><?php echo htmlspecialchars($t['description']); ?></textarea>
                </div>
                <div class="field">
                  <label for="edit_tut_video_<?php echo (int) $t['tutorial_id']; ?>">Video link</label>
                  <input type="url" id="edit_tut_video_<?php echo (int) $t['tutorial_id']; ?>" name="video_url" value="<?php echo $tutHasUploadedVideo ? '' : htmlspecialchars($t['video_url']); ?>">
                </div>
                <div class="field">
                  <label for="edit_tut_video_file_<?php echo (int) $t['tutorial_id']; ?>">Or upload a new video file</label>
                  <input type="file" id="edit_tut_video_file_<?php echo (int) $t['tutorial_id']; ?>" name="video_file" accept="video/mp4,video/webm">
                  <span class="field-hint"><?php echo $tutHasUploadedVideo ? 'Currently using an uploaded video file. ' : ''; ?>Leave both empty to keep the current video. MP4 or WEBM &middot; max 50 MB</span>
                </div>
                <div class="field">
                  <label for="edit_tut_duration_<?php echo (int) $t['tutorial_id']; ?>">Length (minutes)</label>
                  <input type="number" id="edit_tut_duration_<?php echo (int) $t['tutorial_id']; ?>" name="duration_minutes" required min="1" max="600" value="<?php echo (int) $t['duration_minutes']; ?>">
                </div>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editTutorialPanel-<?php echo (int) $t['tutorial_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="hacks">
    <div class="section-head">
      <div><span class="eyebrow">Quick wins</span><h2>Kitchen Hacks</h2></div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="hackAddPanel">+ Add a hack</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a hack</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $formOpen['hack'] ? ' open' : ''; ?>" id="hackAddPanel">
        <?php if ($formErrors['hack']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($formErrors['hack']); ?></p>
        <?php endif; ?>
        <form method="POST" action="resources.php#hacks" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_hack">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="hack_title">Title</label>
            <input type="text" id="hack_title" name="title" required maxlength="150" placeholder="e.g. Keep Fresh Herbs Longer">
          </div>
          <div class="field">
            <label for="hack_image">Photo (optional)</label>
            <input type="file" id="hack_image" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
            <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
          </div>
          <div class="field" id="hackItems">
            <label>Steps</label>
            <div class="step-input-row"><input type="text" name="item[]" placeholder="Step 1"></div>
          </div>
          <button type="button" class="btn btn-ghost" data-add-step="hackItems" style="margin-bottom:16px;">+ Add another step</button>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add hack</button>
            <button type="button" class="btn btn-ghost" data-close-panel="hackAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (empty($hacks)): ?>
      <p class="empty-note">No kitchen hacks have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($hacks as $hack):
          $isHackOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $hack['user_id'];
        ?>
          <div class="mini-list-card">
            <?php if ($isHackOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editHackPanel-<?php echo (int) $hack['hack_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($hack['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
            <?php if (!empty($hack['image_url'])): ?>
              <img src="<?php echo htmlspecialchars($hack['image_url']); ?>" alt="<?php echo htmlspecialchars($hack['title']); ?>">
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($hack['title']); ?></h3>
            <ol>
              <?php foreach ($hack['items'] as $item): ?>
                <li><?php echo htmlspecialchars($item); ?></li>
              <?php endforeach; ?>
            </ol>
            <div class="author-row">
              <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($hack['first_name'], 0, 1) . substr($hack['last_name'], 0, 1))); ?></span>
              <span class="author-name"><?php echo htmlspecialchars($hack['first_name'] . ' ' . $hack['last_name']); ?></span>
            </div>
          </div>
          <?php if ($isHackOwner):
            $hackOpen = $editOpenId['hack'] === (int) $hack['hack_id'];
            $hackPanelId = 'editHackItems-' . (int) $hack['hack_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $hackOpen ? ' open' : ''; ?>" id="editHackPanel-<?php echo (int) $hack['hack_id']; ?>">
              <?php if ($hackOpen && $editErrors['hack']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($editErrors['hack']); ?></p>
              <?php endif; ?>
              <form method="POST" action="resources.php#hacks" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_hack">
                <input type="hidden" name="hack_id" value="<?php echo (int) $hack['hack_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_hack_title_<?php echo (int) $hack['hack_id']; ?>">Title</label>
                  <input type="text" id="edit_hack_title_<?php echo (int) $hack['hack_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($hack['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_hack_image_<?php echo (int) $hack['hack_id']; ?>">Photo (optional)</label>
                  <input type="file" id="edit_hack_image_<?php echo (int) $hack['hack_id']; ?>" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                  <span class="field-hint">Leave empty to keep the current photo. JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
                </div>
                <div class="field" id="<?php echo $hackPanelId; ?>">
                  <label>Steps</label>
                  <?php if (empty($hack['items'])): ?>
                    <div class="step-input-row"><input type="text" name="item[]" maxlength="500" placeholder="Step 1"></div>
                  <?php else: ?>
                    <?php foreach ($hack['items'] as $item): ?>
                      <div class="step-input-row"><input type="text" name="item[]" maxlength="500" value="<?php echo htmlspecialchars($item); ?>"></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn btn-ghost" data-add-step="<?php echo $hackPanelId; ?>" style="margin-bottom:16px;">+ Add another step</button>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editHackPanel-<?php echo (int) $hack['hack_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="tips">
    <div class="section-head">
      <div><span class="eyebrow">Worth knowing</span><h2>Cooking Tips</h2></div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="tipAddPanel">+ Add a tip</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a tip</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $formOpen['tip'] ? ' open' : ''; ?>" id="tipAddPanel">
        <?php if ($formErrors['tip']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($formErrors['tip']); ?></p>
        <?php endif; ?>
        <form method="POST" action="resources.php#tips" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_tip">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="tip_title2">Title</label>
            <input type="text" id="tip_title2" name="title" required maxlength="150" placeholder="e.g. Getting the Most Flavor from Ras el Hanout">
          </div>
          <div class="field">
            <label for="tip_image2">Photo (optional)</label>
            <input type="file" id="tip_image2" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
            <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
          </div>
          <div class="field" id="tipItems">
            <label>Points</label>
            <div class="step-input-row"><input type="text" name="item[]" placeholder="Point 1"></div>
          </div>
          <button type="button" class="btn btn-ghost" data-add-step="tipItems" style="margin-bottom:16px;">+ Add another point</button>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add tip</button>
            <button type="button" class="btn btn-ghost" data-close-panel="tipAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (empty($tips)): ?>
      <p class="empty-note">No cooking tips have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($tips as $tip):
          $isTipOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $tip['user_id'];
        ?>
          <div class="mini-list-card">
            <?php if ($isTipOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editTipPanel-<?php echo (int) $tip['tip_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($tip['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
            <?php if (!empty($tip['image_url'])): ?>
              <img src="<?php echo htmlspecialchars($tip['image_url']); ?>" alt="<?php echo htmlspecialchars($tip['title']); ?>">
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($tip['title']); ?></h3>
            <ol>
              <?php foreach ($tip['items'] as $item): ?>
                <li><?php echo htmlspecialchars($item); ?></li>
              <?php endforeach; ?>
            </ol>
            <div class="author-row">
              <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($tip['first_name'], 0, 1) . substr($tip['last_name'], 0, 1))); ?></span>
              <span class="author-name"><?php echo htmlspecialchars($tip['first_name'] . ' ' . $tip['last_name']); ?></span>
            </div>
          </div>
          <?php if ($isTipOwner):
            $tipOpen = $editOpenId['tip'] === (int) $tip['tip_id'];
            $tipPanelId = 'editTipItems-' . (int) $tip['tip_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $tipOpen ? ' open' : ''; ?>" id="editTipPanel-<?php echo (int) $tip['tip_id']; ?>">
              <?php if ($tipOpen && $editErrors['tip']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($editErrors['tip']); ?></p>
              <?php endif; ?>
              <form method="POST" action="resources.php#tips" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_tip">
                <input type="hidden" name="tip_id" value="<?php echo (int) $tip['tip_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_tip_title_<?php echo (int) $tip['tip_id']; ?>">Title</label>
                  <input type="text" id="edit_tip_title_<?php echo (int) $tip['tip_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($tip['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_tip_image_<?php echo (int) $tip['tip_id']; ?>">Photo (optional)</label>
                  <input type="file" id="edit_tip_image_<?php echo (int) $tip['tip_id']; ?>" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                  <span class="field-hint">Leave empty to keep the current photo. JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
                </div>
                <div class="field" id="<?php echo $tipPanelId; ?>">
                  <label>Points</label>
                  <?php if (empty($tip['items'])): ?>
                    <div class="step-input-row"><input type="text" name="item[]" maxlength="500" placeholder="Point 1"></div>
                  <?php else: ?>
                    <?php foreach ($tip['items'] as $item): ?>
                      <div class="step-input-row"><input type="text" name="item[]" maxlength="500" value="<?php echo htmlspecialchars($item); ?>"></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn btn-ghost" data-add-step="<?php echo $tipPanelId; ?>" style="margin-bottom:16px;">+ Add another point</button>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editTipPanel-<?php echo (int) $tip['tip_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div style="height: 40px;"></div>

<?php require_once 'includes/footer.php'; ?>