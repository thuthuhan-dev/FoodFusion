<?php
require_once __DIR__ . '/db.php';

// A session can end up with user_id set but the other login fields
// missing — e.g. a stale/leftover session from before a code change, or
// one that never went through login.php's normal (atomic) sign-in path.
// Treat that as not logged in rather than rendering with missing fields.
if (!empty($_SESSION['user_id']) && empty($_SESSION['first_name'])) {
    unset($_SESSION['user_id'], $_SESSION['first_name'], $_SESSION['role'], $_SESSION['session_log_id']);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Flash state from login.php / register.php: they no longer render their own
// page — they redirect back here and the Login / Join modal reopens with any
// error shown, so there is exactly one login form and one join form in the
// whole site, not two.
$authOpenModal = $_GET['auth'] ?? null;
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
$authOld = $_SESSION['auth_old'] ?? [];
unset($_SESSION['auth_old']);

$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FoodFusion — Moroccan-Inspired Cooking Community</title>
<meta name="description" content="FoodFusion is a home for home cooks: Moroccan-inspired recipes, culinary tips, and a community that shares its table.">
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>

<header class="site-header">
  <div class="wrap nav">
    <a href="index.php" class="brand">FoodFusion</a>

    <div class="nav-top-right">
      <form class="site-search" action="recipes.php" method="GET">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.3-4.3"/></svg>
        <input type="text" name="q" placeholder="Search recipes, ingredients…" aria-label="Search recipes">
      </form>

      <div class="nav-actions">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <div class="user-menu">
            <button type="button" class="user-chip" aria-haspopup="true" aria-expanded="false">
              <span class="user-avatar-sm"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['first_name'], 0, 1))); ?></span>
              Hi, <?php echo htmlspecialchars($_SESSION['first_name']); ?>
            </button>
            <div class="user-dropdown">
              <a href="account.php">My Account</a>
              <a href="logout.php">Log out</a>
            </div>
          </div>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline nav-login-btn" data-open-modal="loginModal">Log in</a>
        <?php endif; ?>
        <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="navLinks">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </div>

  <nav class="nav-row" aria-label="Primary">
    <div class="wrap">
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
        <li><a href="about.php" class="<?php echo $currentPage === 'about.php' ? 'active' : ''; ?>">About Us</a></li>
        <li><a href="recipes.php" class="<?php echo $currentPage === 'recipes.php' ? 'active' : ''; ?>">Recipe Collection</a></li>
        <li><a href="community.php" class="<?php echo $currentPage === 'community.php' ? 'active' : ''; ?>">Community Cookbook</a></li>
        <li><a href="resources.php" class="<?php echo $currentPage === 'resources.php' ? 'active' : ''; ?>">Culinary Resources</a></li>
        <li><a href="education.php" class="<?php echo $currentPage === 'education.php' ? 'active' : ''; ?>">Educational Resources</a></li>
        <li><a href="contact.php" class="<?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact Us</a></li>
        <li class="mobile-nav-cta">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="account.php" class="mobile-nav-primary">My Account</a>
            <a href="logout.php" class="mobile-nav-secondary">Log out</a>
          <?php else: ?>
            <a href="login.php" class="mobile-nav-primary" data-open-modal="loginModal">Log in</a>
          <?php endif; ?>
        </li>
      </ul>
    </div>
  </nav>
</header>

<!-- Join Us modal -->
<div class="modal-overlay" id="joinModal" role="dialog" aria-modal="true" aria-labelledby="joinModalTitle">
  <div class="modal">
    <button type="button" class="modal-close" data-close-modal aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="modal-brand">
      <span class="modal-eyebrow">Homepage only</span>
      <h2 id="joinModalTitle">Join Us</h2>
      <p class="modal-sub">Save favourites, share recipes, and join the community cookbook.</p>
    </div>

    <form action="register.php" method="POST" id="joinForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <?php if ($authError && $authOpenModal === 'join'): ?>
        <div class="auth-error"><?php echo htmlspecialchars($authError); ?></div>
      <?php endif; ?>
      <div class="field-row">
        <div class="field">
          <label for="first_name">First name</label>
          <input type="text" id="first_name" name="first_name" required autocomplete="given-name" value="<?php echo htmlspecialchars($authOld['first_name'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input type="text" id="last_name" name="last_name" required autocomplete="family-name" value="<?php echo htmlspecialchars($authOld['last_name'] ?? ''); ?>">
        </div>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($authOld['email'] ?? ''); ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
          <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password">
            <svg viewBox="0 0 24 24" width="17" height="17" style="width:17px;height:17px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1.5 12S5 5.5 12 5.5 22.5 12 22.5 12 19 18.5 12 18.5 1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <span class="field-hint">At least 8 characters, with one letter and one number.</span>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create account</button>
      <p class="form-note">By joining, you agree to our <a href="privacy-policy.php">Privacy Policy</a> and <a href="cookie-policy.php">Cookie Policy</a>.</p>
    </form>

    <p class="modal-switch">Already a member? <a href="#" data-open-modal="loginModal">Log in</a></p>
  </div>
</div>

<!-- Log in modal -->
<div class="modal-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
  <div class="modal">
    <button type="button" class="modal-close" data-close-modal aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="modal-brand">
      <h2 id="loginModalTitle">Log in</h2>
      <p class="modal-sub">Log in to pick up where you left off.</p>
    </div>

    <form action="login.php" method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <?php if (isset($_GET['registered']) && $authOpenModal === 'login'): ?>
        <div class="auth-success">Account created — log in below to get started.</div>
      <?php endif; ?>
      <?php if ($authError && $authOpenModal === 'login'): ?>
        <div class="auth-error"><?php echo htmlspecialchars($authError); ?></div>
      <?php endif; ?>
      <div class="field">
        <label for="login_email">Email</label>
        <input type="email" id="login_email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($authOld['email'] ?? ''); ?>">
      </div>
      <div class="field">
        <label for="login_password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="login_password" name="password" required autocomplete="current-password">
          <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password">
            <svg viewBox="0 0 24 24" width="17" height="17" style="width:17px;height:17px;" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1.5 12S5 5.5 12 5.5 22.5 12 22.5 12 19 18.5 12 18.5 1.5 12 1.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="modal-switch">New to FoodFusion? <a href="#" data-open-modal="joinModal">Create an account</a></p>
  </div>
</div>