<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

/**
 * Placeholder photography until real recipe/event photos exist.
 * Swap this for a real image_url the moment one exists in the row —
 * every template below already prefers the real column when present.
 * Uses Lorem Picsum (picsum.photos), a free placeholder image service —
 * not FoodFusion content, just a stand-in so the layout can be judged
 * with real photography instead of empty boxes.
 */
function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

/* ---------- Fresh from the Kitchen: two newest recipes + one community post ---------- */
$recentRecipes = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT r.recipe_id, r.recipe_name, r.description, r.prep_time, r.image_url, c.cuisine_name
             FROM RECIPE r
             JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
             ORDER BY r.created_at DESC
             LIMIT 2"
        );
        $recentRecipes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $recentRecipes = [];
    }
}
if (empty($recentRecipes)) {
    $recentRecipes = [
        ['recipe_id' => 1, 'recipe_name' => 'Chicken Tagine with Preserved Lemon', 'description' => 'Slow-simmered chicken with olives, preserved lemon, and warm spices — the dish that started it all for most home cooks here.', 'prep_time' => 25, 'cuisine_name' => 'Moroccan', 'image_url' => null],
        ['recipe_id' => 2, 'recipe_name' => 'Msemen with Honey and Argan Oil', 'description' => 'Flaky square pancakes, folded and pan-fried, best eaten warm with honey and a drizzle of argan oil.', 'prep_time' => 40, 'cuisine_name' => 'Moroccan', 'image_url' => null],
    ];
}

$spotlightPost = null;
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT p.post_id, p.post_type, p.post_title, p.post_content, u.first_name
             FROM COMMUNITY_POST p
             JOIN USERS u ON p.user_id = u.user_id
             ORDER BY p.created_at DESC
             LIMIT 1"
        );
        $spotlightPost = $stmt->fetch();
    } catch (PDOException $e) {
        $spotlightPost = null;
    }
}
if (!$spotlightPost) {
    $spotlightPost = ['post_id' => null, 'post_type' => 'Culinary Experience', 'post_title' => 'The Friday Couscous Ritual', 'post_content' => 'Every Friday my grandmother steamed couscous three times by hand. This week I finally learned why it matters.', 'first_name' => 'Amina'];
}

/* ---------- Popular recipes carousel ---------- */
$popularRecipes = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT r.recipe_id, r.recipe_name, r.prep_time, r.image_url, c.cuisine_name, d.difficulty_name
             FROM RECIPE r
             JOIN CUISINE_TYPE c ON r.cuisine_id = c.cuisine_id
             JOIN DIFFICULTY d ON r.difficulty_id = d.difficulty_id
             ORDER BY r.created_at DESC
             LIMIT 8"
        );
        $popularRecipes = $stmt->fetchAll();
    } catch (PDOException $e) {
        $popularRecipes = [];
    }
}
if (empty($popularRecipes)) {
    $popularRecipes = [
        ['recipe_id' => 1, 'recipe_name' => 'Chicken Tagine with Preserved Lemon', 'prep_time' => 25, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Medium', 'image_url' => null],
        ['recipe_id' => 2, 'recipe_name' => 'Msemen with Honey and Argan Oil', 'prep_time' => 40, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Easy', 'image_url' => null],
        ['recipe_id' => 3, 'recipe_name' => 'Harira Soup', 'prep_time' => 20, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Easy', 'image_url' => null],
        ['recipe_id' => 4, 'recipe_name' => 'Lamb Mechoui', 'prep_time' => 30, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Hard', 'image_url' => null],
        ['recipe_id' => 5, 'recipe_name' => 'Zaalouk Salad', 'prep_time' => 15, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Easy', 'image_url' => null],
        ['recipe_id' => 6, 'recipe_name' => 'Seffa with Almonds', 'prep_time' => 35, 'cuisine_name' => 'Moroccan', 'difficulty_name' => 'Medium', 'image_url' => null],
    ];
}

/* ---------- Add a new event (logged-in members) ---------- */
$eventError   = null;
$eventSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_event') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $eventError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eventError = 'Log in to add a cooking event.';
    } else {
        $eTitle    = trim($_POST['event_title'] ?? '');
        $eDesc     = trim($_POST['description'] ?? '');
        $eDate     = trim($_POST['event_date'] ?? '');
        $eLocation = trim($_POST['event_location'] ?? '');

        $uploadError = null;
        $eImage = handle_image_upload($_FILES['image_file'] ?? null, $uploadError);

        $dateOk = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $eDate) && strtotime($eDate) !== false;

        if ($uploadError) {
            $eventError = $uploadError;
        } elseif ($eImage === null) {
            $eventError = 'Please add a photo for the event.';
        } elseif ($eTitle === '' || mb_strlen($eTitle) > 150) {
            $eventError = 'Give the event a title under 150 characters.';
        } elseif ($eDesc === '') {
            $eventError = 'Please describe the event.';
        } elseif (!$dateOk) {
            $eventError = 'Please choose a valid event date.';
        } elseif (strtotime($eDate) < strtotime(date('Y-m-d'))) {
            $eventError = 'The event date cannot be in the past.';
        } elseif ($eLocation === '' || mb_strlen($eLocation) > 150) {
            $eventError = 'Please give the event a location.';
        } elseif ($pdo) {
            try {
                $pdo->prepare(
                    'INSERT INTO EVENT (event_title, description, event_date, event_location, image_url)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$eTitle, $eDesc, $eDate, $eLocation, $eImage]);
                $newEventId = (int) $pdo->lastInsertId();

                // Record who created this event, so "edit the event I added" is
                // something the app can actually check ownership for later.
                $organizerRole = $pdo->query("SELECT role_id FROM EVENT_ROLE WHERE role_name = 'Organizer'")->fetchColumn();
                if ($organizerRole) {
                    $pdo->prepare('INSERT INTO EVENT_PARTICIPANT (event_id, user_id, role_id) VALUES (?, ?, ?)')
                        ->execute([$newEventId, $_SESSION['user_id'], $organizerRole]);
                }

                header('Location: index.php?event_added=1#events');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion create_event: ' . $e->getMessage());
                $eventError = 'Something went wrong saving that event. Please try again.';
            }
        }
    }
}

/* ---------- Edit an event (organizer only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_event') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['event_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $eventError = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eventError = 'Log in to manage events.';
    } elseif ($pdo && $targetId > 0) {
        try {
            // Ownership check: the person must be recorded as this event's
            // Organizer in EVENT_PARTICIPANT. Events created before this
            // feature existed have no organizer on file, so nobody can edit
            // them yet — see the note in seed_event_roles.sql.
            $orgCheck = $pdo->prepare(
                "SELECT 1 FROM EVENT_PARTICIPANT ep
                 JOIN EVENT_ROLE er ON ep.role_id = er.role_id
                 WHERE ep.event_id = ? AND ep.user_id = ? AND er.role_name = 'Organizer'"
            );
            $orgCheck->execute([$targetId, $_SESSION['user_id']]);
            $isOrganizer = (bool) $orgCheck->fetch();

            if (!$isOrganizer) {
                $eventError = 'You can only edit events you organized.';
            } else {
                $eTitle    = trim($_POST['event_title'] ?? '');
                $eDesc     = trim($_POST['description'] ?? '');
                $eDate     = trim($_POST['event_date'] ?? '');
                $eLocation = trim($_POST['event_location'] ?? '');

                $uploadError = null;
                $newImage = handle_image_upload($_FILES['image_file'] ?? null, $uploadError);

                $dateOk = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $eDate) && strtotime($eDate) !== false;

                if ($uploadError) {
                    $eventError = $uploadError;
                } elseif ($eTitle === '' || mb_strlen($eTitle) > 150) {
                    $eventError = 'Give the event a title under 150 characters.';
                } elseif ($eDesc === '') {
                    $eventError = 'Please describe the event.';
                } elseif (!$dateOk) {
                    $eventError = 'Please choose a valid event date.';
                } elseif ($eLocation === '' || mb_strlen($eLocation) > 150) {
                    $eventError = 'Please give the event a location.';
                } else {
                    $imgStmt = $pdo->prepare('SELECT image_url FROM EVENT WHERE event_id = ?');
                    $imgStmt->execute([$targetId]);
                    $oldImage = $imgStmt->fetchColumn();
                    $finalImage = $newImage !== null ? $newImage : $oldImage;

                    $pdo->prepare(
                        'UPDATE EVENT SET event_title=?, description=?, event_date=?, event_location=?, image_url=?, updated_at=NOW()
                         WHERE event_id=?'
                    )->execute([$eTitle, $eDesc, $eDate, $eLocation, $finalImage, $targetId]);

                    if ($newImage !== null && !empty($oldImage)) {
                        delete_uploaded_file($oldImage);
                    }

                    header('Location: index.php?event_updated=1#events');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_event: ' . $e->getMessage());
            $eventError = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Join / leave an event (Participant role) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_participation') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $targetId = (int) ($_POST['event_id'] ?? 0);

    if (hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken) && !empty($_SESSION['user_id']) && $pdo && $targetId > 0) {
        try {
            $existingStmt = $pdo->prepare('SELECT event_participant_id, role_id FROM EVENT_PARTICIPANT WHERE event_id = ? AND user_id = ?');
            $existingStmt->execute([$targetId, $_SESSION['user_id']]);
            $existing = $existingStmt->fetch();

            $organizerRoleId = $pdo->query("SELECT role_id FROM EVENT_ROLE WHERE role_name = 'Organizer'")->fetchColumn();

            if ($existing && (int) $existing['role_id'] === (int) $organizerRoleId) {
                // The organizer is already "in" their own event — nothing to toggle.
            } elseif ($existing) {
                // Already joined as a Participant — leaving.
                $pdo->prepare('DELETE FROM EVENT_PARTICIPANT WHERE event_participant_id = ?')->execute([$existing['event_participant_id']]);
            } else {
                $participantRoleId = $pdo->query("SELECT role_id FROM EVENT_ROLE WHERE role_name = 'Participant'")->fetchColumn();
                if ($participantRoleId) {
                    $pdo->prepare('INSERT INTO EVENT_PARTICIPANT (event_id, user_id, role_id) VALUES (?, ?, ?)')
                        ->execute([$targetId, $_SESSION['user_id'], $participantRoleId]);
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion toggle_participation: ' . $e->getMessage());
        }
    }
    header('Location: index.php#events');
    exit;
}

if (isset($_GET['event_added'])) {
    $eventSuccess = 'Your event has been added to the calendar.';
}
if (isset($_GET['event_updated'])) {
    $eventSuccess = 'Your event has been updated.';
}

/* ---------- Upcoming events ---------- */
$events = [];
if ($pdo) {
    try {
        // Was capped at 6, which is why events further out (e.g. next month)
        // could disappear from view once 6 sooner ones existed — raised so a
        // fuller run of upcoming events actually shows.
        $stmt = $pdo->query(
            "SELECT event_id, event_title, description, event_date, event_location, image_url
             FROM EVENT
             WHERE event_date >= CURDATE()
             ORDER BY event_date ASC
             LIMIT 16"
        );
        $events = $stmt->fetchAll();
    } catch (PDOException $e) {
        $events = [];
    }
}

// Which of these events did the current member organize? Used to decide
// whether they see an Edit option for a given event.
$myOrganizedEventIds = [];
if ($pdo && !empty($_SESSION['user_id'])) {
    try {
        $orgStmt = $pdo->prepare(
            "SELECT ep.event_id FROM EVENT_PARTICIPANT ep
             JOIN EVENT_ROLE er ON ep.role_id = er.role_id
             WHERE ep.user_id = ? AND er.role_name = 'Organizer'"
        );
        $orgStmt->execute([$_SESSION['user_id']]);
        $myOrganizedEventIds = array_column($orgStmt->fetchAll(), 'event_id');
    } catch (PDOException $e) {
        $myOrganizedEventIds = [];
    }
}

// Which events has the current member already joined as a Participant?
$myJoinedEventIds = [];
if ($pdo && !empty($_SESSION['user_id'])) {
    try {
        $joinStmt = $pdo->prepare(
            "SELECT ep.event_id FROM EVENT_PARTICIPANT ep
             JOIN EVENT_ROLE er ON ep.role_id = er.role_id
             WHERE ep.user_id = ? AND er.role_name = 'Participant'"
        );
        $joinStmt->execute([$_SESSION['user_id']]);
        $myJoinedEventIds = array_column($joinStmt->fetchAll(), 'event_id');
    } catch (PDOException $e) {
        $myJoinedEventIds = [];
    }
}

// Organizer name + total headcount for every event, so the card/modal can
// show "Organized by X · N going" without a separate query per event.
$eventPeople = [];
if ($pdo) {
    try {
        $peopleStmt = $pdo->query(
            "SELECT ep.event_id, er.role_name, u.first_name, u.last_name
             FROM EVENT_PARTICIPANT ep
             JOIN EVENT_ROLE er ON ep.role_id = er.role_id
             JOIN USERS u ON ep.user_id = u.user_id"
        );
        foreach ($peopleStmt->fetchAll() as $row) {
            $eventPeople[$row['event_id']]['count'] = ($eventPeople[$row['event_id']]['count'] ?? 0) + 1;
            if ($row['role_name'] === 'Organizer') {
                $eventPeople[$row['event_id']]['organizer'] = $row['first_name'] . ' ' . $row['last_name'];
            }
        }
    } catch (PDOException $e) {
        $eventPeople = [];
    }
}

if (empty($events)) {
    $events = [
        ['event_id' => 1, 'event_title' => 'Artisan Sourdough Masterclass', 'description' => 'Details for this event will be published soon.', 'event_date' => date('Y-m-d', strtotime('+4 days')), 'event_location' => 'Online', 'image_url' => null],
        ['event_id' => 2, 'event_title' => 'Wood-Fired Vegetables Workshop', 'description' => 'Details for this event will be published soon.', 'event_date' => date('Y-m-d', strtotime('+9 days')), 'event_location' => 'Community Kitchen, Rabat', 'image_url' => null],
        ['event_id' => 3, 'event_title' => 'Pasta Fresca Workshop', 'description' => 'Details for this event will be published soon.', 'event_date' => date('Y-m-d', strtotime('+16 days')), 'event_location' => 'Marrakech Medina', 'image_url' => null],
        ['event_id' => 4, 'event_title' => 'Spice Market Tour', 'description' => 'Details for this event will be published soon.', 'event_date' => date('Y-m-d', strtotime('+23 days')), 'event_location' => 'Fes Souk', 'image_url' => null],
    ];
}

require_once 'includes/header.php';
?>

<section class="hero-carousel">

  <div class="hero-slide grad-1 active">
    <div class="wrap hero-slide-inner">
      <div>
        <span class="eyebrow">Cook. Share. Belong.</span>
        <h1>Where Culinary Creativity Meets Community.</h1>
        <p class="lede">Discover a world of flavor rooted in Moroccan tradition, share your favourite home-cooked meals, and connect with food enthusiasts who believe every dish tells a story.</p>
        <div class="hero-actions">
          <button type="button" class="btn btn-primary" data-open-modal>Start Cooking</button>
          <a href="recipes.php" class="btn btn-outline">Explore Recipes</a>
        </div>
      </div>
      <div class="hero-visual-slide">
        <img src="assets/img/share.jpg" alt="Moroccan tagine and spices" loading="lazy">
      </div>
    </div>
  </div>

  <div class="hero-slide grad-2">
    <div class="wrap hero-slide-inner">
      <div>
        <span class="eyebrow">2,300+ Members</span>
        <h1>Join a Community That Shares Its Table.</h1>
        <p class="lede">Post your own recipes, swap kitchen tips, and follow along as members cook their way through the same dishes — no recipe too simple, no cook too new.</p>
        <div class="hero-actions">
          <button type="button" class="btn btn-primary" data-open-modal>Join Us — it's free</button>
          <a href="community.php" class="btn btn-outline">Visit the Cookbook</a>
        </div>
      </div>
      <div class="hero-visual-slide">
        <img src="assets/img/cm.jpg" alt="Fresh herbs and ingredients for a shared meal" loading="lazy">
      </div>
    </div>
  </div>

  <div class="hero-slide grad-3">
    <div class="wrap hero-slide-inner">
      <div>
        <span class="eyebrow">Live &amp; Online</span>
        <h1>Learn Hands-On at a Cooking Event.</h1>
        <p class="lede">From sourdough masterclasses to spice market tours, book a seat at an upcoming FoodFusion event and cook alongside people who love food as much as you do.</p>
        <div class="hero-actions">
          <a href="#events" class="btn btn-primary">See Upcoming Events</a>
          <a href="recipes.php" class="btn btn-outline">Explore Recipes</a>
        </div>
      </div>
      <div class="hero-visual-slide">
        <img src="assets/img/k.jpg" alt="Hands-on cooking class in progress" loading="lazy">
      </div>
    </div>
  </div>

  <button type="button" class="hero-arrow prev" id="heroPrev" aria-label="Previous slide">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
  </button>
  <button type="button" class="hero-arrow next" id="heroNext" aria-label="Next slide">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
  </button>
  <div class="hero-dots">
    <button type="button" class="hero-dot active" aria-label="Slide 1"></button>
    <button type="button" class="hero-dot" aria-label="Slide 2"></button>
    <button type="button" class="hero-dot" aria-label="Slide 3"></button>
  </div>
</section>

<div class="divider-zellige" aria-hidden="true"></div>

<!-- FoodFusion mission — required by the brief: a brief introduction to
     FoodFusion's mission on the landing page. -->
<section class="section mission-section" id="mission">
  <div class="wrap">
    <div class="mission-layout">
      <div class="mission-intro">
        <span class="eyebrow">Our mission</span>
        <h2>Keeping home cooking, and the stories behind it, from being lost</h2>
        <p class="mission-statement">
          FoodFusion exists to promote home cooking and culinary creativity by giving food
          enthusiasts one place to find recipes, learn the techniques behind them, and share
          what they cook with people who care about it.
        </p>
        <p class="mission-body">
          A recipe tells you what to do. It rarely tells you why the couscous is steamed three
          times instead of once, or why your grandmother added that extra pinch of saffron.
          We built FoodFusion for that second half &mdash; the context, the tips and the stories
          that turn a set of instructions into something worth cooking again.
        </p>
        <div class="mission-actions">
          <a href="recipes.php" class="btn btn-primary">Explore the recipes</a>
          <a href="about.php" class="btn btn-ghost">More about us &rarr;</a>
        </div>
      </div>

      <ul class="mission-values">
        <li>
          <span class="mission-value-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>
          </span>
          <div>
            <strong>Authenticity</strong>
            <p>Recipes come from real kitchens and real cooks, not generic reformulations.</p>
          </div>
        </li>
        <li>
          <span class="mission-value-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.6-3.4 3.2-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><circle cx="17.5" cy="9" r="2.4"/><path d="M15.8 14.6c2.6.2 4.6 2.1 5.1 5"/></svg>
          </span>
          <div>
            <strong>Community</strong>
            <p>The best tip in the comments usually beats the one in the recipe itself.</p>
          </div>
        </li>
        <li>
          <span class="mission-value-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20V10M12 10L6 6M12 10l6-4M4 6l8-4 8 4"/></svg>
          </span>
          <div>
            <strong>Learning</strong>
            <p>Every cook started as a beginner &mdash; nothing here assumes otherwise.</p>
          </div>
        </li>
        <li>
          <span class="mission-value-icon">
            <svg viewBox="0 0 24 24" width="18" height="18" style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
          </span>
          <div>
            <strong>Accessibility</strong>
            <p>Free to browse, free to join, free to share &mdash; no paywall on a family recipe.</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow">Fresh from the kitchen</span>
        <h2>Latest recipes and community highlights</h2>
      </div>
      <a href="recipes.php" class="btn btn-ghost">Browse all recipes →</a>
    </div>

    <div class="kitchen-grid">
      <?php
        $feature = $recentRecipes[0];
        $featureImg = !empty($feature['image_url']) ? $feature['image_url'] : placeholder_img('recipe-' . $feature['recipe_id'], 900, 700);
      ?>
      <a href="recipe-detail.php?id=<?php echo (int) $feature['recipe_id']; ?>" class="kitchen-feature">
        <div class="visual-bg"><img src="<?php echo htmlspecialchars($featureImg); ?>" alt="<?php echo htmlspecialchars($feature['recipe_name']); ?>" loading="lazy"></div>
        <div class="scrim"></div>
        <div class="kitchen-feature-body">
          <div class="kitchen-badges">
            <span class="badge badge-solid">Featured Recipe</span>
            <span class="badge"><?php echo (int) $feature['prep_time']; ?> min</span>
          </div>
          <h3><?php echo htmlspecialchars($feature['recipe_name']); ?></h3>
          <p><?php echo htmlspecialchars($feature['description']); ?></p>
          <span class="news-link" style="color: var(--saffron); font-weight: 600; display: inline-block; margin-top: 14px;">View Recipe &rarr;</span>
        </div>
      </a>

      <div class="kitchen-side">
        <?php if (isset($recentRecipes[1])): $second = $recentRecipes[1]; ?>
        <a href="recipe-detail.php?id=<?php echo (int) $second['recipe_id']; ?>" class="kitchen-card">
          <span class="badge" style="align-self: flex-start; background: rgba(25,25,25,0.08); color: var(--zellige-deep); border-color: rgba(25,25,25,0.15);">Just Added</span>
          <h3><?php echo htmlspecialchars($second['recipe_name']); ?></h3>
          <p><?php echo htmlspecialchars($second['description']); ?></p>
          <div class="author-row">
            <span class="cuisine-avatar" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3v8a2 2 0 002 2h0a2 2 0 002-2V3"/><path d="M9 13v8"/><path d="M17 3c-1.5 2-2 4-2 6s.5 3 2 3v9"/></svg>
            </span>
            <span class="author-name"><?php echo htmlspecialchars($second['cuisine_name']); ?> cuisine</span>
          </div>
        </a>
        <?php endif; ?>

        <?php
          $spotTab = ['Recipe' => 'recipe', 'Cooking Tip' => 'tip', 'Culinary Experience' => 'experience'][$spotlightPost['post_type'] ?? ''] ?? 'experience';
          $spotHref = !empty($spotlightPost['post_id'])
              ? 'post-detail.php?id=' . (int) $spotlightPost['post_id']
              : 'community.php?tab=' . $spotTab;
        ?>
        <a href="<?php echo htmlspecialchars($spotHref); ?>" class="kitchen-card dark">
          <span class="quote-mark">"</span>
          <h3><?php echo htmlspecialchars($spotlightPost['post_title']); ?></h3>
          <p><?php echo htmlspecialchars($spotlightPost['post_content']); ?></p>
          <div class="author-row">
            <span class="author-avatar"><?php echo htmlspecialchars(strtoupper(substr($spotlightPost['first_name'], 0, 2))); ?></span>
            <span class="author-name"><?php echo htmlspecialchars($spotlightPost['first_name']); ?></span>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background: var(--plaster-deep);">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow">Popular this month</span>
        <h2>Recipes members keep coming back to</h2>
      </div>
    </div>

    <div class="carousel">
      <div class="recipe-carousel-track" id="recipeTrack">
        <?php foreach ($popularRecipes as $recipe):
          $img = !empty($recipe['image_url']) ? $recipe['image_url'] : placeholder_img('recipe-' . $recipe['recipe_id'], 400, 400);
        ?>
          <article class="recipe-card-h">
            <a href="recipe-detail.php?id=<?php echo (int) $recipe['recipe_id']; ?>" class="recipe-card-h-link">
              <div class="recipe-card-h-photo">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" loading="lazy">
                <span class="recipe-cuisine-chip"><?php echo htmlspecialchars($recipe['cuisine_name']); ?></span>
              </div>
              <div class="recipe-card-h-body">
                <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                <div class="recipe-meta-row">
                  <span><?php echo (int) $recipe['prep_time']; ?> min</span>
                  <span><?php echo htmlspecialchars($recipe['difficulty_name']); ?></span>
                </div>
                <span class="recipe-card-h-cta">View recipe &rarr;</span>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="carousel-controls">
        <button type="button" class="carousel-btn" id="recipeCarouselPrev" aria-label="Previous recipes">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
        <button type="button" class="carousel-btn" id="recipeCarouselNext" aria-label="Next recipes">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </button>
      </div>
    </div>
  </div>
</section>

<section class="section section-dark" id="events">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow">Don't miss out</span>
        <h2>Upcoming cooking events</h2>
      </div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-primary" id="addEventToggle" aria-expanded="false" aria-controls="addEventPanel">
          <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Add new event
        </button>
      <?php else: ?>
        <button type="button" class="btn btn-primary" data-open-modal="loginModal" title="Log in to add an event">
          <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Add new event
        </button>
      <?php endif; ?>
    </div>

    <?php if ($eventSuccess): ?>
      <p class="event-form-note success"><?php echo htmlspecialchars($eventSuccess); ?></p>
    <?php endif; ?>
    <?php if ($eventError): ?>
      <p class="event-form-note error"><?php echo htmlspecialchars($eventError); ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-event-panel<?php echo $eventError ? ' open' : ''; ?>" id="addEventPanel">
        <h3 class="form-section-title" id="eventFormHeading" style="margin-top:0;">Add an event</h3>
        <form method="POST" action="index.php#events" enctype="multipart/form-data" id="eventForm">
          <input type="hidden" name="action" value="create_event" id="eventFormAction">
          <input type="hidden" name="event_id" value="" id="eventFormEventId">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field-row">
            <div class="field">
              <label for="event_title">Event title</label>
              <input type="text" id="event_title" name="event_title" required maxlength="150" placeholder="e.g. Tagine Workshop">
            </div>
            <div class="field">
              <label for="event_date">Date</label>
              <input type="date" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          <div class="field">
            <label for="event_location">Location</label>
            <input type="text" id="event_location" name="event_location" required maxlength="150" placeholder="e.g. Community Kitchen, Rabat">
          </div>
          <div class="field">
            <label for="event_description">Description</label>
            <textarea id="event_description" name="description" required placeholder="What happens at this event?"></textarea>
          </div>
          <div class="field">
            <label for="event_image" id="eventImageLabel">Photo</label>
            <input type="file" id="event_image" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <span class="field-hint" id="eventImageHint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary" id="eventFormSubmit">Add event</button>
            <button type="button" class="btn btn-ghost" id="cancelEventForm">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="carousel">
      <div class="carousel-track" id="eventsTrack">
        <?php foreach ($events as $event):
          $ts = strtotime($event['event_date']);
          $eImg = !empty($event['image_url']) ? $event['image_url'] : placeholder_img('event-' . $event['event_id'], 500, 350);
        ?>
          <article class="event-card-v2">
            <div class="event-photo">
              <img src="<?php echo htmlspecialchars($eImg); ?>" alt="<?php echo htmlspecialchars($event['event_title']); ?>" loading="lazy">
              <span class="event-date-chip">
                <span class="day"><?php echo htmlspecialchars(date('d', $ts)); ?></span>
                <span class="mon"><?php echo htmlspecialchars(date('M', $ts)); ?></span>
              </span>
            </div>
            <div class="event-card-v2-body">
              <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
              <span class="event-loc"><?php echo htmlspecialchars($event['event_location']); ?></span>
              <?php
                $peopleInfo = $eventPeople[$event['event_id']] ?? [];
                $goingCount = $peopleInfo['count'] ?? 0;
                $organizerName = $peopleInfo['organizer'] ?? '';
                $isOrganizer = in_array((int) $event['event_id'], $myOrganizedEventIds, true);
                $hasJoined = in_array((int) $event['event_id'], $myJoinedEventIds, true);
              ?>
              <?php if ($goingCount > 0): ?>
                <span class="event-going-count"><?php echo (int) $goingCount; ?> going</span>
              <?php endif; ?>
              <button type="button" class="btn btn-outline event-detail-btn"
                      style="border-color: rgba(25,25,25,0.3); color: var(--zellige-deep);"
                      data-event-id="<?php echo (int) $event['event_id']; ?>"
                      data-event-title="<?php echo htmlspecialchars($event['event_title']); ?>"
                      data-event-date="<?php echo htmlspecialchars(date('l j F Y', $ts)); ?>"
                      data-event-date-raw="<?php echo htmlspecialchars(date('Y-m-d', $ts)); ?>"
                      data-event-location="<?php echo htmlspecialchars($event['event_location']); ?>"
                      data-event-description="<?php echo htmlspecialchars($event['description'] ?? ''); ?>"
                      data-event-image="<?php echo htmlspecialchars($eImg); ?>"
                      data-can-edit="<?php echo $isOrganizer ? '1' : '0'; ?>"
                      data-organizer-name="<?php echo htmlspecialchars($organizerName); ?>"
                      data-going-count="<?php echo (int) $goingCount; ?>"
                      data-has-joined="<?php echo $hasJoined ? '1' : '0'; ?>"
                      data-is-organizer="<?php echo $isOrganizer ? '1' : '0'; ?>"
                      data-logged-in="<?php echo empty($_SESSION['user_id']) ? '0' : '1'; ?>">
                View details
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="carousel-controls">
        <button type="button" class="carousel-btn" id="carouselPrev" aria-label="Previous events">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
        <button type="button" class="carousel-btn" id="carouselNext" aria-label="Next events">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </button>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="eyebrow">Why FoodFusion</span>
        <h2>Everything a home cook needs, in one table</h2>
      </div>
    </div>

    <div class="pillars">
      <div class="pillar">
        <span class="pillar-icon c1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.3-4.3"/></svg></span>
        <h3>Discover</h3>
        <p>Search recipes by cuisine, dietary need, or difficulty — from quick weeknight dinners to weekend tagines.</p>
      </div>
      <div class="pillar">
        <span class="pillar-icon c2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg></span>
        <h3>Share</h3>
        <p>Post your own recipes, cooking tips, and kitchen wins to the community cookbook — no recipe too simple.</p>
      </div>
      <div class="pillar">
        <span class="pillar-icon c3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.6-3.4 3.2-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><circle cx="17.5" cy="9" r="2.4"/><path d="M15.8 14.6c2.6.2 4.6 2.1 5.1 5"/></svg></span>
        <h3>Connect</h3>
        <p>Comment, favourite, and follow along as other members cook their way through the same recipes.</p>
      </div>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap">
    <h2>Ready to cook something worth sharing?</h2>
    <p>Join thousands of home cooks swapping recipes, tips, and Friday dinner ideas.</p>
    <button type="button" class="btn btn-primary" data-open-modal>Join Us — it's free</button>
  </div>
</section>

<!-- Event details modal -->
<div class="modal-overlay" id="eventModal" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
  <div class="modal event-modal">
    <button type="button" class="modal-close" data-close-modal aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <img id="eventModalImage" class="event-modal-image" src="" alt="">
    <h2 id="eventModalTitle"></h2>
    <div class="event-modal-meta">
      <span id="eventModalDate"></span>
      <span id="eventModalLocation"></span>
    </div>
    <p id="eventModalDescription" class="event-modal-description"></p>

    <p id="eventModalPeople" class="event-modal-people"></p>

    <form method="POST" action="index.php#events" id="eventJoinForm" style="display:none;">
      <input type="hidden" name="action" value="toggle_participation">
      <input type="hidden" name="event_id" id="eventJoinEventId" value="">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      <button type="submit" id="eventJoinBtn" class="btn btn-primary" style="width:100%; margin-top:6px;">Join this event</button>
    </form>
    <a href="#" data-open-modal="loginModal" id="eventJoinLoginPrompt" class="btn btn-primary" style="display:none; width:100%; margin-top:6px; justify-content:center;">Log in to join this event</a>

    <button type="button" id="eventModalEditBtn" class="btn btn-outline" style="display:none; margin-top:10px; width:100%; justify-content:center;">
      <svg viewBox="0 0 24 24" width="15" height="15" style="width:15px;height:15px;flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
      Edit this event
    </button>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>