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

// Edit forms: same shape as $eduErrors, but the value is the specific
// item's id being edited (or null) — a list page can have many editable
// items, only the one just submitted reopens with its error on redisplay.
$eduEditErrors = ['document' => null, 'video' => null, 'infographic' => null];
$eduEditOpenId = ['document' => null, 'video' => null, 'infographic' => null];
if (isset($_GET['document_updated'])) $eduSuccess = 'Document updated.';
if (isset($_GET['video_updated'])) $eduSuccess = 'Video updated.';
if (isset($_GET['infographic_updated'])) $eduSuccess = 'Infographic updated.';

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

/* ---------- Edit: Educational Document (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_edu_document') {
    $targetId = (int) ($_POST['document_id'] ?? 0);
    $eduEditOpenId['document'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduEditErrors['document'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduEditErrors['document'] = 'Log in to manage your documents.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, file_url FROM EDUCATIONAL_DOCUMENT WHERE edu_document_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingDoc = $ownerStmt->fetch();

            if (!$existingDoc || (int) $existingDoc['user_id'] !== (int) $_SESSION['user_id']) {
                $eduEditErrors['document'] = 'You can only edit documents you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newDesc = trim($_POST['description'] ?? '');
                $docError = null;
                // A new PDF is optional — if none was chosen, the current file
                // is kept untouched, same as photo replacement on Edit Recipe.
                $newFile = handle_document_upload($_FILES['file_upload'] ?? null, $docError);

                if ($docError) {
                    $eduEditErrors['document'] = $docError;
                } elseif ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $eduEditErrors['document'] = 'Give the document a title under 150 characters.';
                } else {
                    $finalFile = $newFile !== null ? $newFile : $existingDoc['file_url'];
                    $pdo->prepare(
                        'UPDATE EDUCATIONAL_DOCUMENT SET title = ?, description = ?, file_url = ?, updated_at = NOW()
                         WHERE edu_document_id = ? AND user_id = ?'
                    )->execute([$newTitle, $newDesc !== '' ? $newDesc : null, $finalFile, $targetId, $_SESSION['user_id']]);

                    if ($newFile !== null && !empty($existingDoc['file_url'])) {
                        delete_uploaded_file($existingDoc['file_url']);
                    }

                    header('Location: education.php?document_updated=1#documents');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_edu_document: ' . $e->getMessage());
            $eduEditErrors['document'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Edit: Educational Video (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_edu_video') {
    $targetId = (int) ($_POST['video_id'] ?? 0);
    $eduEditOpenId['video'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduEditErrors['video'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduEditErrors['video'] = 'Log in to manage your videos.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id FROM EDUCATIONAL_VIDEO WHERE edu_video_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingVideo = $ownerStmt->fetch();

            if (!$existingVideo || (int) $existingVideo['user_id'] !== (int) $_SESSION['user_id']) {
                $eduEditErrors['video'] = 'You can only edit videos you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newDesc = trim($_POST['description'] ?? '');
                $newVideoUrl = trim($_POST['video_url'] ?? '');

                if ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $eduEditErrors['video'] = 'Give the video a title under 150 characters.';
                } elseif ($newVideoUrl === '' || !filter_var($newVideoUrl, FILTER_VALIDATE_URL)) {
                    $eduEditErrors['video'] = 'Please provide a valid video link.';
                } else {
                    $pdo->prepare(
                        'UPDATE EDUCATIONAL_VIDEO SET title = ?, description = ?, video_url = ?, updated_at = NOW()
                         WHERE edu_video_id = ? AND user_id = ?'
                    )->execute([$newTitle, $newDesc !== '' ? $newDesc : null, $newVideoUrl, $targetId, $_SESSION['user_id']]);

                    header('Location: education.php?video_updated=1#videos');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_edu_video: ' . $e->getMessage());
            $eduEditErrors['video'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Edit: Infographic (owner only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_infographic') {
    $targetId = (int) ($_POST['infographic_id'] ?? 0);
    $eduEditOpenId['infographic'] = $targetId;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $eduEditErrors['infographic'] = 'Your session expired — please try again.';
    } elseif (empty($_SESSION['user_id'])) {
        $eduEditErrors['infographic'] = 'Log in to manage your infographics.';
    } elseif ($pdo && $targetId > 0) {
        try {
            $ownerStmt = $pdo->prepare('SELECT user_id, image_url FROM INFOGRAPHIC WHERE infographic_id = ?');
            $ownerStmt->execute([$targetId]);
            $existingInfo = $ownerStmt->fetch();

            if (!$existingInfo || (int) $existingInfo['user_id'] !== (int) $_SESSION['user_id']) {
                $eduEditErrors['infographic'] = 'You can only edit infographics you added yourself.';
            } else {
                $newTitle = trim($_POST['title'] ?? '');
                $newDesc = trim($_POST['description'] ?? '');
                $imgError = null;
                $newImage = handle_image_upload($_FILES['image_file'] ?? null, $imgError);

                if ($imgError) {
                    $eduEditErrors['infographic'] = $imgError;
                } elseif ($newTitle === '' || mb_strlen($newTitle) > 150) {
                    $eduEditErrors['infographic'] = 'Give the infographic a title under 150 characters.';
                } else {
                    $finalImage = $newImage !== null ? $newImage : $existingInfo['image_url'];
                    $pdo->prepare(
                        'UPDATE INFOGRAPHIC SET title = ?, description = ?, image_url = ?, updated_at = NOW()
                         WHERE infographic_id = ? AND user_id = ?'
                    )->execute([$newTitle, $newDesc !== '' ? $newDesc : null, $finalImage, $targetId, $_SESSION['user_id']]);

                    if ($newImage !== null && !empty($existingInfo['image_url'])) {
                        delete_uploaded_file($existingInfo['image_url']);
                    }

                    header('Location: education.php?infographic_updated=1#infographics');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('FoodFusion edit_infographic: ' . $e->getMessage());
            $eduEditErrors['infographic'] = 'Something went wrong saving your changes. Please try again.';
        }
    }
}

/* ---------- Educational Documents ---------- */
$documents = [];
if ($pdo) {
    try {
        $documents = $pdo->query(
            "SELECT d.edu_document_id AS document_id, d.user_id, d.title, d.description, d.file_url, u.first_name, u.last_name
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
            "SELECT v.edu_video_id AS video_id, v.user_id, v.title, v.description, v.video_url, u.first_name, u.last_name
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
            "SELECT infographic_id, user_id, title, description, image_url
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
        ['document_id' => 1, 'user_id' => 0, 'title' => 'Understanding Moroccan Spice Blends', 'description' => 'A short guide to the spice families behind ras el hanout, chermoula, and harissa.', 'file_url' => '#', 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $eduVideos = [
        ['video_id' => 1, 'user_id' => 0, 'title' => 'The History of the Tagine', 'description' => 'Where the tagine comes from, and why its shape is built for slow cooking.', 'video_url' => '#', 'first_name' => 'FoodFusion', 'last_name' => 'Admin'],
    ];
    $infographics = [
        ['infographic_id' => 1, 'user_id' => 0, 'title' => 'A Visual Guide to Moroccan Mint Tea', 'description' => 'The pour, the glass, and the ritual behind atay — illustrated step by step.', 'image_url' => null],
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
        <?php foreach ($documents as $doc):
          $isDocOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $doc['user_id'];
        ?>
          <div class="doc-card">
            <?php if ($isDocOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editDocPanel-<?php echo (int) $doc['document_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($doc['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
            <span class="doc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/></svg>
            </span>
            <div class="doc-card-body">
              <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
              <p><?php echo htmlspecialchars($doc['description']); ?></p>
              <?php
                // download attribute: save the file instead of opening it in a tab,
                // and give the user a readable filename.
                $docDownloadName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 _-]/', '', $doc['title']))) . '.pdf';
              ?>
              <a href="<?php echo htmlspecialchars($doc['file_url']); ?>"
                 download="<?php echo htmlspecialchars($docDownloadName); ?>"
                 class="dl-link">
                Download
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
              </a>
            </div>
          </div>
          <?php if ($isDocOwner):
            $docOpen = $eduEditOpenId['document'] === (int) $doc['document_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $docOpen ? ' open' : ''; ?>" id="editDocPanel-<?php echo (int) $doc['document_id']; ?>">
              <?php if ($docOpen && $eduEditErrors['document']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduEditErrors['document']); ?></p>
              <?php endif; ?>
              <form method="POST" action="education.php#documents" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_edu_document">
                <input type="hidden" name="document_id" value="<?php echo (int) $doc['document_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_doc_title_<?php echo (int) $doc['document_id']; ?>">Title</label>
                  <input type="text" id="edit_doc_title_<?php echo (int) $doc['document_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($doc['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_doc_description_<?php echo (int) $doc['document_id']; ?>">Description</label>
                  <textarea id="edit_doc_description_<?php echo (int) $doc['document_id']; ?>" name="description"><?php echo htmlspecialchars($doc['description']); ?></textarea>
                </div>
                <div class="field">
                  <label for="edit_doc_file_<?php echo (int) $doc['document_id']; ?>">PDF file</label>
                  <input type="file" id="edit_doc_file_<?php echo (int) $doc['document_id']; ?>" name="file_upload" accept="application/pdf">
                  <span class="field-hint">Leave empty to keep the current file. PDF only &middot; max 5 MB</span>
                </div>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editDocPanel-<?php echo (int) $doc['document_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
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
        <?php foreach ($eduVideos as $v):
          $isVideoOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $v['user_id'];
        ?>
          <div class="video-card-wrap">
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
            <?php if ($isVideoOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editVideoPanel-<?php echo (int) $v['video_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($v['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
          </div>
          <?php if ($isVideoOwner):
            $videoOpen = $eduEditOpenId['video'] === (int) $v['video_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $videoOpen ? ' open' : ''; ?>" id="editVideoPanel-<?php echo (int) $v['video_id']; ?>">
              <?php if ($videoOpen && $eduEditErrors['video']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduEditErrors['video']); ?></p>
              <?php endif; ?>
              <form method="POST" action="education.php#videos">
                <input type="hidden" name="action" value="edit_edu_video">
                <input type="hidden" name="video_id" value="<?php echo (int) $v['video_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_vid_title_<?php echo (int) $v['video_id']; ?>">Title</label>
                  <input type="text" id="edit_vid_title_<?php echo (int) $v['video_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($v['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_vid_description_<?php echo (int) $v['video_id']; ?>">Description</label>
                  <textarea id="edit_vid_description_<?php echo (int) $v['video_id']; ?>" name="description"><?php echo htmlspecialchars($v['description']); ?></textarea>
                </div>
                <div class="field">
                  <label for="edit_vid_url_<?php echo (int) $v['video_id']; ?>">Video link</label>
                  <input type="url" id="edit_vid_url_<?php echo (int) $v['video_id']; ?>" name="video_url" required value="<?php echo htmlspecialchars($v['video_url']); ?>">
                </div>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editVideoPanel-<?php echo (int) $v['video_id']; ?>">Cancel</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
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
          $isInfoOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $info['user_id'];
        ?>
          <div class="infographic-card">
            <button type="button" class="infographic-trigger" aria-label="Enlarge <?php echo htmlspecialchars($info['title']); ?>">
              <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($info['title']); ?>" loading="lazy">
              <span class="infographic-zoom-badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M11 8v6M8 11h6"/></svg>
              </span>
            </button>
            <?php if ($isInfoOwner): ?>
              <button type="button" class="edit-icon-btn" data-toggle-panel="editInfoPanel-<?php echo (int) $info['infographic_id']; ?>" aria-label="Edit <?php echo htmlspecialchars($info['title']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              </button>
            <?php endif; ?>
            <div class="infographic-card-body">
              <h3><?php echo htmlspecialchars($info['title']); ?></h3>
              <p><?php echo htmlspecialchars($info['description']); ?></p>
            </div>
          </div>
          <?php if ($isInfoOwner):
            $infoOpen = $eduEditOpenId['infographic'] === (int) $info['infographic_id'];
          ?>
            <div class="add-recipe-panel resource-edit-panel<?php echo $infoOpen ? ' open' : ''; ?>" id="editInfoPanel-<?php echo (int) $info['infographic_id']; ?>">
              <?php if ($infoOpen && $eduEditErrors['infographic']): ?>
                <p class="form-note" style="color: var(--paprika); margin-bottom: 14px;"><?php echo htmlspecialchars($eduEditErrors['infographic']); ?></p>
              <?php endif; ?>
              <form method="POST" action="education.php#infographics" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_infographic">
                <input type="hidden" name="infographic_id" value="<?php echo (int) $info['infographic_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="field">
                  <label for="edit_info_title_<?php echo (int) $info['infographic_id']; ?>">Title</label>
                  <input type="text" id="edit_info_title_<?php echo (int) $info['infographic_id']; ?>" name="title" required maxlength="150" value="<?php echo htmlspecialchars($info['title']); ?>">
                </div>
                <div class="field">
                  <label for="edit_info_description_<?php echo (int) $info['infographic_id']; ?>">Description</label>
                  <textarea id="edit_info_description_<?php echo (int) $info['infographic_id']; ?>" name="description"><?php echo htmlspecialchars($info['description']); ?></textarea>
                </div>
                <div class="field">
                  <label for="edit_info_image_<?php echo (int) $info['infographic_id']; ?>">Image</label>
                  <input type="file" id="edit_info_image_<?php echo (int) $info['infographic_id']; ?>" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                  <span class="field-hint">Leave empty to keep the current image. JPG, PNG, GIF or WEBP &middot; max 3 MB</span>
                </div>
                <div class="add-recipe-actions">
                  <button type="submit" class="btn btn-primary">Save changes</button>
                  <button type="button" class="btn btn-ghost" data-close-panel="editInfoPanel-<?php echo (int) $info['infographic_id']; ?>">Cancel</button>
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

<div class="modal-overlay" id="infographicModal" role="dialog" aria-modal="true" aria-labelledby="infographicModalTitle">
  <div class="modal lightbox-modal">
    <button type="button" class="modal-close" data-close-modal aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <img id="infographicModalImage" class="lightbox-image" src="" alt="">
    <div class="lightbox-caption">
      <h2 id="infographicModalTitle"></h2>
      <p id="infographicModalDescription"></p>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>