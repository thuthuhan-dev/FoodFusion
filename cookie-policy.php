<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Legal</span>
    <h1>Cookie Policy</h1>
    <p>Last updated: <?php echo date('F Y'); ?></p>
  </div>
</section>

<div class="wrap legal-wrap">

  <p class="lede">This page explains, honestly and specifically, what FoodFusion stores in your browser and why — not a generic template.</p>

  <h2>The one cookie we set</h2>
  <table class="legal-table">
    <thead>
      <tr><th>Name</th><th>Purpose</th><th>Type</th><th>Expires</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><code>PHPSESSID</code></td>
        <td>Keeps you logged in as you move between pages, and protects forms against cross-site request forgery (CSRF).</td>
        <td>Strictly necessary</td>
        <td>When you close your browser, or when you log out</td>
      </tr>
    </tbody>
  </table>
  <p>This cookie is essential — the site cannot keep you logged in without it, and it cannot be switched off while still using an account.</p>

  <h2>The cookie notice preference</h2>
  <p>When you accept or decline the cookie notice banner, that choice is remembered using your browser's local storage rather than a separate tracking cookie. It serves the same purpose — so the banner doesn't reappear on every visit — without adding another cookie beyond the one described above.</p>

  <h2>What we don't use</h2>
  <ul>
    <li>No third-party advertising or tracking cookies.</li>
    <li>No cross-site analytics cookies.</li>
    <li>No cookies that follow you to other websites.</li>
  </ul>

  <h2>Managing cookies in your browser</h2>
  <p>Most browsers let you view, delete, or block cookies through their settings. Blocking the essential session cookie will prevent you from staying logged in, since that's the only thing it's used for.</p>

  <h2>Questions</h2>
  <p>If anything here is unclear, reach out through our <a href="contact.php">Contact Us</a> page. See also our <a href="privacy-policy.php">Privacy Policy</a> for how we handle your account data more broadly.</p>

</div>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>