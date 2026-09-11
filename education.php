<?php
require_once 'includes/db.php';
require_once 'includes/upload.php';

function placeholder_img($seed, $w = 600, $h = 600) {
    return "https://picsum.photos/seed/" . urlencode($seed) . "/$w/$h";
}

$eduErrors = ['document' => null, 'video' => null, 'infographic' => null];
$eduOpen   = ['document' => false, 'video' => false, 'infographic' => false];
$eduAddedLabels = ['document' => 'Document', 'video' => 'Video', 'infographic' => 'Infographic'];
$eduSuccess = isset($_GET['added'], $eduAddedLabels[$_GET['added']])
    ? $eduAddedLabels[$_GET['added']] . ' added.'
    : null;

/* ---------- Add: Educational Document ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_edu_document') {
    $eduOpen['document'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduErrors['document'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduErrors['document'] = 'Log in to add a document.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $docError = null;
        $fileUrl = handle_document_upload($_FILES['file_upload'] ?? null, $docError);

        if ($docError) {
            $eduErrors['document'] = $docError;
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $eduErrors['document'] = 'Give the document a title under 150 characters.';
        } elseif ($fileUrl === null) {
            $eduErrors['document'] = 'Please attach a PDF file.';
        } else {
            try {
                $pdo->prepare('INSERT INTO EDUCATIONAL_DOCUMENT (user_id, title, description, file_url) VALUES (?, ?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $desc !== '' ? $desc : null, $fileUrl]);
                header('Location: education.php?added=document#documents');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion add_edu_document: ' . $e->getMessage());
                $eduErrors['document'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Add: Educational Video ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_edu_video') {
    $eduOpen['video'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduErrors['video'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduErrors['video'] = 'Log in to add a video.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');

        if ($title === '' || mb_strlen($title) > 150) {
            $eduErrors['video'] = 'Give the video a title under 150 characters.';
        } elseif ($videoUrl === '' || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            $eduErrors['video'] = 'Please provide a valid video link.';
        } else {
            try {
                $pdo->prepare('INSERT INTO EDUCATIONAL_VIDEO (user_id, title, description, video_url) VALUES (?, ?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $desc !== '' ? $desc : null, $videoUrl]);
                header('Location: education.php?added=video#videos');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion add_edu_video: ' . $e->getMessage());
                $eduErrors['video'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
}

/* ---------- Add: Infographic ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_infographic') {
    $eduOpen['infographic'] = true;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduErrors['infographic'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduErrors['infographic'] = 'Log in to add an infographic.';
    } elseif ($pdo) {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $imgError = null;
        $imageUrl = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

        if ($imgError) {
            $eduErrors['infographic'] = $imgError;
        } elseif ($title === '' || mb_strlen($title) > 150) {
            $eduErrors['infographic'] = 'Give the infographic a title under 150 characters.';
        } elseif ($imageUrl === null) {
            $eduErrors['infographic'] = 'Please add the infographic image.';
        } else {
            try {
                $pdo->prepare('INSERT INTO INFOGRAPHIC (user_id, title, description, image_url) VALUES (?, ?, ?, ?)')
                    ->execute([$_SESSION['user_id'], $title, $desc !== '' ? $desc : null, $imageUrl]);
                header('Location: education.php?added=infographic#infographics');
                exit;
            } catch (PDOException $e) {
                error_log('FoodFusion add_infographic: ' . $e->getMessage());
                $eduErrors['infographic'] = 'Something went wrong saving that. Please try again.';
            }
        }
    }
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
  <?php if ($eduSuccess): ?>
    <p class="auth-success"><?php echo htmlspecialchars($eduSuccess); ?></p>
  <?php endif; ?>

  <nav class="resource-jump-nav">
    <a href="#documents">Documents</a>
    <a href="#videos">Videos</a>
    <a href="#infographics">Infographics</a>
  </nav>

  <section class="resource-section" id="documents">
    <div class="section-head">
      <div><span class="eyebrow">Read</span><h2>Documents</h2></div>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="docAddPanel">+ Add a document</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a document</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $eduOpen['document'] ? ' open' : ''; ?>" id="docAddPanel">
        <?php if ($eduErrors['document']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduErrors['document']); ?></p>
        <?php endif; ?>
        <form method="POST" action="education.php#documents" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_edu_document">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="doc_title">Title</label>
            <input type="text" id="doc_title" name="title" required maxlength="150" placeholder="e.g. Understanding Moroccan Spice Blends">
          </div>
          <div class="field">
            <label for="doc_description">Description</label>
            <textarea id="doc_description" name="description" placeholder="What does this document cover?"></textarea>
          </div>
          <div class="field">
            <label for="doc_file">PDF file</label>
            <input type="file" id="doc_file" name="file_upload" accept="application/pdf" required>
            <span class="field-hint">PDF only &middot; max 5 MB</span>
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add document</button>
            <button type="button" class="btn btn-ghost" data-close-panel="docAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

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
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="videoAddPanel">+ Add a video</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add a video</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $eduOpen['video'] ? ' open' : ''; ?>" id="videoAddPanel">
        <?php if ($eduErrors['video']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduErrors['video']); ?></p>
        <?php endif; ?>
        <form method="POST" action="education.php#videos">
          <input type="hidden" name="action" value="add_edu_video">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="vid_title">Title</label>
            <input type="text" id="vid_title" name="title" required maxlength="150" placeholder="e.g. The History of the Tagine">
          </div>
          <div class="field">
            <label for="vid_description">Description</label>
            <textarea id="vid_description" name="description" placeholder="What does this video cover?"></textarea>
          </div>
          <div class="field">
            <label for="vid_url">Video link</label>
            <input type="url" id="vid_url" name="video_url" required placeholder="https://youtube.com/...">
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add video</button>
            <button type="button" class="btn btn-ghost" data-close-panel="videoAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

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
      <?php if (!empty($_SESSION['user_id'])): ?>
        <button type="button" class="btn btn-outline resource-add-toggle" data-toggle-panel="infoAddPanel">+ Add an infographic</button>
      <?php else: ?>
        <button type="button" class="btn btn-outline" data-open-modal="loginModal">+ Add an infographic</button>
      <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="add-recipe-panel<?php echo $eduOpen['infographic'] ? ' open' : ''; ?>" id="infoAddPanel">
        <?php if ($eduErrors['infographic']): ?>
          <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduErrors['infographic']); ?></p>
        <?php endif; ?>
        <form method="POST" action="education.php#infographics" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_infographic">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="field">
            <label for="info_title">Title</label>
            <input type="text" id="info_title" name="title" required maxlength="150" placeholder="e.g. A Visual Guide to Moroccan Mint Tea">
          </div>
          <div class="field">
            <label for="info_description">Description</label>
            <textarea id="info_description" name="description" placeholder="What does this infographic show?"></textarea>
          </div>
          <div class="field">
            <label for="info_image">Image</label>
            <input type="file" id="info_image" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <span class="field-hint">JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
          </div>
          <div class="add-recipe-actions">
            <button type="submit" class="btn btn-primary">Add infographic</button>
            <button type="button" class="btn btn-ghost" data-close-panel="infoAddPanel">Cancel</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

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