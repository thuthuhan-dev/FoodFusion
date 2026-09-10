<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Legal</span>
    <h1>Privacy Policy</h1>
    <p>Last updated: <?php echo date('F Y'); ?></p>
  </div>
</section>

<div class="wrap legal-wrap">

  <p class="lede">This page explains what information FoodFusion collects, why, and how it's used. It's written in plain language rather than dense legal text, because you should actually be able to read it.</p>

  <h2>Who this covers</h2>
  <p>This policy covers FoodFusion (this website). It applies to visitors browsing the site and to members who register an account.</p>

  <h2>What we collect</h2>
  <p>We only collect what the site actually needs to work:</p>
  <ul>
    <li><strong>Account details</strong> — first name, last name, and email address, provided when you register.</li>
    <li><strong>Password</strong> — never stored as plain text. It's run through a one-way hashing algorithm (bcrypt) before it ever reaches the database, so we cannot see or recover your actual password.</li>
    <li><strong>Content you create</strong> — recipes, community posts, comments, and any photos you choose to upload.</li>
    <li><strong>Interactions</strong> — which recipes you've saved as favourites, which posts you've liked or reposted.</li>
    <li><strong>Session information</strong> — login timestamps, IP address, and browser/device string, recorded to support the account lockout security feature and to help detect suspicious activity.</li>
    <li><strong>Contact form submissions</strong> — name, email, and message content, if you use the Contact Us form.</li>
  </ul>
  <p>We do not collect payment information, government ID numbers, or any data beyond what's listed above.</p>

  <h2>How we use it</h2>
  <ul>
    <li>To create and secure your account, including the failed-login lockout protection.</li>
    <li>To display your recipes, posts, and comments to other members, attributed to your name.</li>
    <li>To let you save favourites and pick up where you left off.</li>
    <li>To respond to enquiries submitted through the Contact Us form.</li>
  </ul>
  <p>We do not sell, rent, or share your personal information with third parties for marketing purposes.</p>

  <h2>Uploaded photos</h2>
  <p>If you upload a photo to a recipe, community post, or event, the file itself is validated before it's saved — we check that it's genuinely an image, not just that it looks like one by its filename — and stored under a randomly generated filename rather than the name of the file on your device.</p>

  <h2>Your choices</h2>
  <ul>
    <li>You can edit or remove your own community posts and comments at any time from <a href="account.php">My Account</a>.</li>
    <li>You can unsave a favourite recipe at any time.</li>
    <li>You can request that your account and associated data be deleted by contacting us through the <a href="contact.php">Contact Us</a> page.</li>
  </ul>

  <h2>Cookies</h2>
  <p>FoodFusion uses a small number of cookies to keep you logged in and to keep the site secure. See our <a href="cookie-policy.php">Cookie Policy</a> for the full detail.</p>

  <h2>Security</h2>
  <p>Passwords are hashed, not stored in reversible form. All database queries use parameterised statements to prevent SQL injection, and all user-submitted content is escaped before being displayed, to prevent malicious code from running in another visitor's browser. Accounts lock automatically after repeated failed login attempts.</p>

  <h2>Contact</h2>
  <p>Questions about this policy, or about your data, can be sent through our <a href="contact.php">Contact Us</a> page.</p>

</div>

<div style="height: 60px;"></div>

<?php require_once 'includes/footer.php'; ?>