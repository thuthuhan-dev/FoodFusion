<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.php" class="brand">FoodFusion</a>
        <p>A home for home cooks — Moroccan-rooted recipes, culinary tips, and a table big enough for everyone.</p>
        <div class="social-row">
          <a href="https://instagram.com" aria-label="FoodFusion on Instagram" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/></svg>
          </a>
          <a href="https://facebook.com" aria-label="FoodFusion on Facebook" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 9h3V6h-3c-2 0-3.5 1.5-3.5 3.5V12H8v3h2.5v6h3v-6H16l.5-3h-3V10c0-.6.4-1 1-1z"/></svg>
          </a>
          <a href="https://youtube.com" aria-label="FoodFusion on YouTube" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 9.5l4 2.5-4 2.5z"/></svg>
          </a>
          <a href="https://pinterest.com" aria-label="FoodFusion on Pinterest" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M9.5 18c1-3.5 1.6-6 2-8m0 0c.4-2 2-3 3.5-2.5 1.6.5 2 2.3 1.4 4-.5 1.5-1.9 2.5-3.4 2-0.8-.3-1.2-1-1-2M11.5 10c0-.5.4-1.3 1.3-1.3"/></svg>
          </a>
        </div>
      </div>

      <div>
        <h4>Explore</h4>
        <ul>
          <li><a href="recipes.php">Recipe Collection</a></li>
          <li><a href="community.php">Community Cookbook</a></li>
          <li><a href="resources.php">Culinary Resources</a></li>
          <li><a href="education.php">Educational Resources</a></li>
        </ul>
      </div>

      <div>
        <h4>About</h4>
        <ul>
          <li><a href="about.php">About Us</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="index.php#events">Events</a></li>
        </ul>
      </div>

      <div>
        <h4>Legal</h4>
        <ul>
          <li><a href="privacy-policy.php">Privacy Policy</a></li>
          <li><a href="cookie-policy.php">Cookie settings</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> FoodFusion. All rights reserved.</span>
      <span><a href="privacy-policy.php">Privacy Policy</a> &nbsp;·&nbsp; <a href="cookie-policy.php">Cookie Policy</a></span>
    </div>
  </div>
</footer>

<!-- Cookie consent -->
<div class="cookie-panel" id="cookieBar" role="dialog" aria-live="polite" aria-label="Cookie notice">
  <div class="cookie-panel-head">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1"/><circle cx="14" cy="9" r="1"/><circle cx="10" cy="15" r="1"/><circle cx="15" cy="14" r="1"/></svg>
    We use cookies
  </div>
  <p>To enhance your culinary journey, we use cookies to personalize content and analyze traffic. Read our <a href="cookie-policy.php">Cookie Policy</a>.</p>
  <div class="cookie-panel-actions">
    <button type="button" class="btn btn-ghost" id="cookieDecline">Decline</button>
    <button type="button" class="btn btn-primary" id="cookieAccept">Accept All</button>
  </div>
</div>

<script src="assets/js/main.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>"></script>
<?php if ($authOpenModal === 'login' || $authOpenModal === 'join'): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.openModalById) {
      window.openModalById('<?php echo $authOpenModal === 'join' ? 'joinModal' : 'loginModal'; ?>');
    }
  });
</script>
<?php endif; ?>
</body>
</html>