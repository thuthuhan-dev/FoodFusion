<?php
require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FoodFusion — Moroccan-Inspired Cooking Community</title>
<meta name="description" content="FoodFusion is a home for home cooks: Moroccan-inspired recipes, culinary tips, and a community that shares its table.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>

<header class="site-header">
  <div class="wrap nav">
    <a href="index.php" class="brand">
      <span class="brand-mark">FF</span>
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
            <a href="recipes.php">My Favorites</a>
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
    <h2 id="joinModalTitle">Join FoodFusion</h2>
    <p class="lede">Create an account to save favourites, share recipes, and join the community cookbook.</p>

    <form action="register.php" method="POST" id="joinForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <div class="field-row">
        <div class="field">
          <label for="first_name">First name</label>
          <input type="text" id="first_name" name="first_name" required autocomplete="given-name">
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input type="text" id="last_name" name="last_name" required autocomplete="family-name">
        </div>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary">Create account</button>
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
    <h2 id="loginModalTitle">Welcome back</h2>
    <p class="lede">Log in to save recipes, post to the community cookbook, and join the conversation.</p>

    <form action="login.php" method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
      <div class="field">
        <label for="login_email">Email</label>
        <input type="email" id="login_email" name="email" required autocomplete="email">
      </div>
      <div class="field">
        <label for="login_password">Password</label>
        <input type="password" id="login_password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary">Log in</button>
    </form>

    <p class="modal-switch">New to FoodFusion? <a href="#" data-open-modal="joinModal">Create an account</a></p>
  </div>
</div>