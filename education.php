<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

/* ---------- Educational Documents ---------- */
$documents = [];
if ($pdo) {
    try {
        $documents = $pdo->query(
            "SELECT d.title, d.description, d.file_url, u.first_name, u.last_name
             FROM EDUCATIONAL_DOCUMENT d
             JOIN USERS u ON d.user_id = u.user_id
             ORDER BY d.uploaded_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $documents = [];
    }
}

/* ---------- Educational Videos ---------- */
$eduVideos = [];
if ($pdo) {
    try {
        $eduVideos = $pdo->query(
            "SELECT v.title, v.description, v.video_url, u.first_name, u.last_name
             FROM EDUCATIONAL_VIDEO v
             JOIN USERS u ON v.user_id = u.user_id
             ORDER BY v.uploaded_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $eduVideos = [];
    }
}

/* ---------- Infographics ---------- */
$infographics = [];
if ($pdo) {
    try {
        $infographics = $pdo->query(
            "SELECT infographic_id, title, description, image_url
             FROM INFOGRAPHIC
             ORDER BY uploaded_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        $infographics = [];
    }
}

/* ---------- Fallback sample content ---------- */
if (!$pdo) {
    $documents = [
        ['title' => 'Understanding Moroccan Spice Blends', 'description' => 'A short guide to the spice families behind ras el hanout, chermoula, and harissa.', 'file_url' => '#', 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $eduVideos = [
        ['title' => 'The History of the Tagine', 'description' => 'Where the tagine comes from, and why its shape is built for slow cooking.', 'video_url' => '#', 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $infographics = [
        ['infographic_id' => 1, 'title' => 'A Visual Guide to Moroccan Mint Tea', 'description' => 'The pour, the glass, and the ritual behind atay — illustrated step by step.', 'image_url' => null],
    ];
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Educational Resources</span>
    <h1>Learn the why behind the how</h1>
    <p>Guides, short videos, and visual references on the ingredients, history, and technique behind the recipes.</p>
  </div>
</section>

<div class="wrap">
  <nav class="resource-jump-nav">
    <a href="#documents">Documents</a>
    <a href="#videos">Videos</a>
    <a href="#infographics">Infographics</a>
  </nav>

  <section class="resource-section" id="documents">
    <div class="section-head">
      <div><span class="eyebrow">Read</span><h2>Documents</h2></div>
    </div>
    <?php if (empty($documents)): ?>
      <p class="empty-note">No documents have been uploaded yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($documents as $doc): ?>
          <div class="doc-card">
            <span class="doc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/></svg>
            </span>
            <div class="doc-card-body">
              <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
              <p><?php echo htmlspecialchars($doc['description']); ?></p>
              <a href="<?php echo htmlspecialchars($doc['file_url']); ?>" class="dl-link">
                Download
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="videos">
    <div class="section-head">
      <div><span class="eyebrow">Watch</span><h2>Videos</h2></div>
    </div>
    <?php if (empty($eduVideos)): ?>
      <p class="empty-note">No videos have been posted yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($eduVideos as $v): ?>
          <a href="<?php echo htmlspecialchars($v['video_url']); ?>" class="video-card" style="color: inherit;">
            <?php $vthumb = video_thumbnail($v['video_url']); ?>
            <div class="video-card-thumb">
              <?php if ($vthumb): ?>
                <img class="video-thumb-img" src="<?php echo htmlspecialchars($vthumb); ?>" alt="" loading="lazy">
              <?php endif; ?>
              <span class="play-btn">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;"><path d="M8 5v14l11-7z"/></svg>
              </span>
            </div>
            <div class="video-card-body">
              <h3><?php echo htmlspecialchars($v['title']); ?></h3>
              <p><?php echo htmlspecialchars($v['description']); ?></p>
              <span class="uploader">by <?php echo htmlspecialchars($v['first_name'] . ' ' . $v['last_name']); ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="resource-section" id="infographics">
    <div class="section-head">
      <div><span class="eyebrow">Save &amp; share</span><h2>Infographics</h2></div>
    </div>
    <?php if (empty($infographics)): ?>
      <p class="empty-note">No infographics have been uploaded yet.</p>
    <?php else: ?>
      <div class="card-grid-3">
        <?php foreach ($infographics as $info):
          $img = !empty($info['image_url']) ? $info['image_url'] : placeholder_img('infographic-' . $info['infographic_id'], 500, 400);
        ?>
          <div class="infographic-card">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($info['title']); ?>" loading="lazy">
            <div class="infographic-card-body">
              <h3><?php echo htmlspecialchars($info['title']); ?></h3>
              <p><?php echo htmlspecialchars($info['description']); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div style="height: 40px;"></div>

<?php require_once 'includes/footer.php'; ?>