<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

/* ---------- Recipe Cards ---------- */
$recipeCards = [];
if ($pdo) {
    try {
        $recipeCards = $pdo->query(
            "SELECT rc.card_title, rc.file_url, rc.uploaded_at, r.recipe_name, r.image_url, r.recipe_id
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
            "SELECT ct.title, ct.description, ct.video_url, ct.duration_minutes, u.first_name, u.last_name
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
            "SELECT h.hack_id, h.title, h.image_url, u.first_name, u.last_name
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
            "SELECT t.tip_id, t.title, t.image_url, u.first_name, u.last_name
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
        ['card_title' => 'Chicken Tagine — Printable Recipe Card', 'file_url' => '#', 'recipe_name' => 'Chicken Tagine with Preserved Lemon', 'image_url' => null, 'recipe_id' => 1],
        ['card_title' => 'Harira Soup — Printable Recipe Card', 'file_url' => '#', 'recipe_name' => 'Harira Soup', 'image_url' => null, 'recipe_id' => 3],
    ];
    $tutorials = [
        ['title' => 'How to Fold Msemen Like a Pro', 'description' => 'A close-up walkthrough of the folding technique behind flaky msemen.', 'video_url' => '#', 'duration_minutes' => 8, 'first_name' => 'Youssef', 'last_name' => 'El Amrani'],
        ['title' => 'Knife Skills for Moroccan Salads', 'description' => 'Dicing and prep techniques used across Zaalouk and other cooked salads.', 'video_url' => '#', 'duration_minutes' => 12, 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $hacks = [
        ['title' => 'Keep Fresh Herbs Longer', 'image_url' => null, 'first_name' => 'Amina', 'last_name' => 'Idrissi', 'items' => ['Trim the stems before storing, like flowers.', 'Stand herbs upright in a jar with an inch of water.', 'Cover loosely with a plastic bag and refrigerate.']],
    ];
    $tips = [
        ['title' => 'Getting the Most Flavor from Ras el Hanout', 'image_url' => null, 'first_name' => 'Sara', 'last_name' => 'Benali', 'items' => ['Toast the spice blend briefly in a dry pan before using.', 'Add it in two stages — half at the start, half near the end.', 'Store in an airtight container away from light.']],
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
  <nav class="resource-jump-nav">
    <a href="#cards">Recipe Cards</a>
    <a href="#tutorials">Cooking Tutorials</a>
    <a href="#hacks">Kitchen Hacks</a>
    <a href="#tips">Cooking Tips</a>
  </nav>

  <section class="resource-section" id="cards">
    <div class="section-head">
      <div><span class="eyebrow">Print &amp; save</span><h2>Recipe Cards</h2></div>
    </div>
    <?php if (empty($recipeCards)): ?>
      <p class="empty-note">No recipe cards have been uploaded yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($recipeCards as $card):
          $img = !empty($card['image_url']) ? $card['image_url'] : placeholder_img('recipe-' . $card['recipe_id'], 120, 120);
        ?>
          <div class="download-card">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['recipe_name']); ?>">
            <div class="download-card-body">
              <h3><?php echo htmlspecialchars($card['card_title']); ?></h3>
              <span>For <?php echo htmlspecialchars($card['recipe_name']); ?></span>
            </div>
            <a href="<?php echo htmlspecialchars($card['file_url']); ?>" class="dl-icon" aria-label="Download">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:17px;height:17px;"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="tutorials">
    <div class="section-head">
      <div><span class="eyebrow">Watch &amp; learn</span><h2>Cooking Tutorials</h2></div>
    </div>
    <?php if (empty($tutorials)): ?>
      <p class="empty-note">No tutorials have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($tutorials as $t): ?>
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
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="hacks">
    <div class="section-head">
      <div><span class="eyebrow">Quick wins</span><h2>Kitchen Hacks</h2></div>
    </div>
    <?php if (empty($hacks)): ?>
      <p class="empty-note">No kitchen hacks have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($hacks as $hack): ?>
          <div class="mini-list-card">
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
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="tips">
    <div class="section-head">
      <div><span class="eyebrow">Worth knowing</span><h2>Cooking Tips</h2></div>
    </div>
    <?php if (empty($tips)): ?>
      <p class="empty-note">No cooking tips have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($tips as $tip): ?>
          <div class="mini-list-card">
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
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div style="height: 40px;"></div>

<?php require_once 'includes/footer.php'; ?>