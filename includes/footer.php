<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.php" class="brand"><span class="brand-mark"><svg viewBox="0 0 64 64" width="30" height="30" aria-hidden="true" focusable="false"><rect width="64" height="64" rx="16" fill="#1F5A3D"/><g stroke="#E8C46A" stroke-width="2.4" stroke-linecap="round" fill="none" opacity="0.9"><path d="M25 15c-1.8-2 1.8-3.6 0-5.6"/><path d="M32 12.6c-1.8-2 1.8-3.6 0-5.6"/><path d="M39 15c-1.8-2 1.8-3.6 0-5.6"/></g><circle cx="32" cy="21" r="2.7" fill="#E8C46A"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40 Z" fill="#FFFDF8"/><path d="M15 40 Q19.5 30.5 29.8 23.4 Q32 21.9 34.2 23.4 Q44.5 30.5 49 40" fill="none" stroke="#E8C46A" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/><path d="M22.5 35h19" stroke="#1F5A3D" stroke-width="2.2" stroke-linecap="round" opacity="0.35"/><rect x="12" y="40.3" width="40" height="7.4" rx="2.6" fill="#E8C46A"/><path d="M18 47.6h28" stroke="#1F5A3D" stroke-width="2" stroke-linecap="round" opacity="0.25"/></svg></span>FoodFusion</a>
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
          <li><a href="about.php">About Us</a></li>
          <li><a href="recipes.php">Recipe Collection</a></li>
          <li><a href="community.php">Community Cookbook</a></li>
        </ul>
      </div>

      <div>
        <h4>Resources</h4>
        <ul>
          <li><a href="resources.php">Culinary Resources</a></li>
          <li><a href="education.php">Educational Resources</a></li>
          <li><a href="contact.php">Contact Us</a></li>
        </ul>
      </div>

      <div>
        <h4>Legal</h4>
        <ul>
          <li><a href="privacy-policy.php">Privacy Policy</a></li>
          <li><a href="cookie-policy.php">Cookie Policy</a></li>
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

<script src="assets/js/main.js"></script>
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