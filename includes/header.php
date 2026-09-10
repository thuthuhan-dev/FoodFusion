<?php
require_once __DIR__ . '/db.php';

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
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>

<header class="site-header">
  <div class="wrap nav">
    <a href="index.php" class="brand">
      <span class="brand-mark"><svg viewBox="0 0 64 64" width="30" height="30" aria-hidden="true" focusable="false"><rect width="64" height="64" rx="16" fill="#1F5A3D"/><g stroke="#E8C46A" stroke-width="2.4" stroke-linecap="round" fill="none" opacity="0.9"><path d="M25 15c-1.8-2 1.8-3.6 0-5.6"/><path d="M32 12.6c-1.8-2 1.8-3.6 0-5.6"/><path d="M39 15c-1.8-2 1.8-3.6 0-5.6"/></g><circle cx="32" cy="21" r="2.7" fill="#E8C46A"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40 Z" fill="#FFFDF8"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40" fill="none" stroke="#E8C46A" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/><path d="M22.5 35h19" stroke="#1F5A3D" stroke-width="2.2" stroke-linecap="round" opacity="0.35"/><rect x="12" y="40.3" width="40" height="7.4" rx="2.6" fill="#E8C46A"/><path d="M18 47.6h28" stroke="#1F5A3D" stroke-width="2" stroke-linecap="round" opacity="0.25"/></svg></span>
      FoodFusion
    </a>

    <nav aria-label="Primary">
      <ul class="nav-links" id="navLinks">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="recipes.php">Recipe Collection</a></li>
        <li><a href="community.php">Community Cookbook</a></li>
        <li class="nav-dropdown">
          <button type="button" class="nav-dropdown-trigger">
            Resources
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="nav-dropdown-menu">
            <a href="resources.php">Culinary Resources</a>
            <a href="education.php">Educational Resources</a>
          </div>
        </li>
        <li><a href="contact.php">Contact Us</a></li>
        <li class="mobile-nav-cta">
          <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="account.php" class="btn btn-outline">My Account</a>
            <a href="logout.php" class="btn btn-outline">Log out</a>
          <?php else: ?>
            <a href="login.php" class="btn btn-outline" data-open-modal="loginModal">Log in</a>
          <?php endif; ?>
        </li>
      </ul>
    </nav>

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
            <a href="account.php#favorites">My Favorites</a>
            <a href="logout.php">Log out</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="btn-ghost" style="color: rgba(255,253,248,0.85); background: none; border: none;" data-open-modal="loginModal">Log in</a>
      <?php endif; ?>
      <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="navLinks">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- Join Us modal -->
<div class="modal-overlay" id="joinModal" role="dialog" aria-modal="true" aria-labelledby="joinModalTitle">
  <div class="modal">
    <button type="button" class="modal-close" data-close-modal aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="modal-brand">
      <span class="modal-brand-mark">
        <svg viewBox="0 0 64 64" width="40" height="40" aria-hidden="true"><rect width="64" height="64" rx="16" fill="#1F5A3D"/><g stroke="#E8C46A" stroke-width="2.4" stroke-linecap="round" fill="none" opacity="0.9"><path d="M25 15c-1.8-2 1.8-3.6 0-5.6"/><path d="M32 12.6c-1.8-2 1.8-3.6 0-5.6"/><path d="M39 15c-1.8-2 1.8-3.6 0-5.6"/></g><circle cx="32" cy="21" r="2.7" fill="#E8C46A"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40 Z" fill="#FFFDF8"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40" fill="none" stroke="#E8C46A" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/><rect x="12" y="40.3" width="40" height="7.4" rx="2.6" fill="#E8C46A"/></svg>
      </span>
      <h2 id="joinModalTitle">Join FoodFusion</h2>
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
      <span class="modal-brand-mark">
        <svg viewBox="0 0 64 64" width="40" height="40" aria-hidden="true"><rect width="64" height="64" rx="16" fill="#1F5A3D"/><g stroke="#E8C46A" stroke-width="2.4" stroke-linecap="round" fill="none" opacity="0.9"><path d="M25 15c-1.8-2 1.8-3.6 0-5.6"/><path d="M32 12.6c-1.8-2 1.8-3.6 0-5.6"/><path d="M39 15c-1.8-2 1.8-3.6 0-5.6"/></g><circle cx="32" cy="21" r="2.7" fill="#E8C46A"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40 Z" fill="#FFFDF8"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40" fill="none" stroke="#E8C46A" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/><rect x="12" y="40.3" width="40" height="7.4" rx="2.6" fill="#E8C46A"/></svg>
      </span>
      <h2 id="loginModalTitle">Welcome back</h2>
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