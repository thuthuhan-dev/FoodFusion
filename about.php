<?php
require_once 'includes/db.php';

$team = [
    ['name' => 'Nadia El Fassi', 'role' => 'Founder', 'bio' => 'Started FoodFusion after moving abroad and missing her mother\'s kitchen.', 'image' => 'assets/img/p1.webp'],
    ['name' => 'Karim Ouazzani', 'role' => 'Head of Recipes', 'bio' => 'Tests every recipe at least three times before it goes on the site.', 'image' => 'assets/img/p2.webp'],
    ['name' => 'Salma Bennis', 'role' => 'Community Manager', 'bio' => 'Reads every single post in the Community Cookbook — really, every one.', 'image' => 'assets/img/p3.webp'],
];

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">About Us</span>
    <h1>A table built by people who missed home</h1>
    <p>FoodFusion started as a way to keep Moroccan recipes from getting lost between kitchens and generations — it grew into a place for anyone who believes food is worth sharing properly.</p>
  </div>
</section>

<div class="wrap about-split">
  <div class="media">
    <img src="assets/img/kitchen.webp" alt="A home kitchen with fresh ingredients">
  </div>
  <div>
    <span class="eyebrow">Our philosophy</span>
    <h2>Recipes are only half the story</h2>
    <p class="lede">A recipe tells you what to do. It rarely tells you why your grandmother added an extra pinch of saffron, or why the couscous gets steamed three times instead of one. FoodFusion exists for that second half — the context, the tips, the stories that turn a set of instructions into something worth cooking again.</p>
    <p class="lede">We're not trying to be the biggest recipe site out there. We'd rather be the one where a Msemen recipe comes with the folding technique explained by someone who learned it from their own family, not a stock photo.</p>
  </div>
</div>

<div class="wrap" style="padding-bottom: 60px;">
  <div class="section-head">
    <div><span class="eyebrow">What we value</span><h2>The principles behind the platform</h2></div>
  </div>
  <div class="value-grid">
    <div class="value-card">
      <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg></span>
      <h3>Authenticity</h3>
      <p>Recipes come from real kitchens and real cooks, not generic reformulations.</p>
    </div>
    <div class="value-card">
      <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.6-3.4 3.2-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><circle cx="17.5" cy="9" r="2.4"/><path d="M15.8 14.6c2.6.2 4.6 2.1 5.1 5"/></svg></span>
      <h3>Community</h3>
      <p>The best tips in the comments usually beat the ones in the recipe itself.</p>
    </div>
    <div class="value-card">
      <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><path d="M12 20V10M12 10L6 6M12 10l6-4M4 6l8-4 8 4"/></svg></span>
      <h3>Learning</h3>
      <p>Every cook started as a beginner — nothing here assumes otherwise.</p>
    </div>
    <div class="value-card">
      <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:20px;height:20px;"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></span>
      <h3>Accessibility</h3>
      <p>Free to browse, free to join, free to share — no paywall on a family recipe.</p>
    </div>
  </div>
</div>

<div class="wrap" style="padding-bottom: 70px;">
  <div class="section-head">
    <div><span class="eyebrow">Meet the team</span><h2>The people behind FoodFusion</h2></div>
  </div>
  <div class="team-grid">
    <?php foreach ($team as $i => $person): ?>
      <div class="team-card">
        <div class="photo-circle">
          <img src="<?php echo htmlspecialchars($person['image']); ?>" alt="<?php echo htmlspecialchars($person['name']); ?>">
        </div>
        <h3><?php echo htmlspecialchars($person['name']); ?></h3>
        <span class="role"><?php echo htmlspecialchars($person['role']); ?></span>
        <p class="bio"><?php echo htmlspecialchars($person['bio']); ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- <section class="cta-band">
  <div class="wrap">
    <h2>Come cook with us</h2>
    <p>Join a community that actually reads the comments before changing the recipe.</p>
    <button type="button" class="btn btn-primary" data-open-modal>Join Us — it's free</button>
  </div>
</section> -->

<?php require_once 'includes/footer.php'; ?>