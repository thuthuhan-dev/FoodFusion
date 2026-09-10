<?php
require_once 'includes/db.php';

$errors = [];
$success = false;
$old = ['name' => '', 'email' => '', 'message_type' => 'Enquiry', 'subject' => '', 'message' => ''];

// Prefill name/email for a logged-in visitor
if (!empty($_SESSION['user_id']) && $pdo) {
    try {
        $stmt = $pdo->prepare('SELECT first_name, last_name, email FROM USERS WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        if ($me = $stmt->fetch()) {
            $old['name'] = $me['first_name'] . ' ' . $me['last_name'];
            $old['email'] = $me['email'];
        }
    } catch (PDOException $e) {
        // Not fatal — the form just won't be prefilled.
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $errors[] = 'Your session expired before this form was submitted. Please try again.';
    }

    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['message_type'] = $_POST['message_type'] ?? 'Enquiry';
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    $validTypes = ['Enquiry', 'Recipe Request', 'Feedback'];

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Please enter your name.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($old['message_type'], $validTypes, true)) {
        $errors[] = 'Please choose a valid message type.';
    }
    if ($old['subject'] === '' || mb_strlen($old['subject']) > 150) {
        $errors[] = 'Please enter a subject under 150 characters.';
    }
    if ($old['message'] === '') {
        $errors[] = 'Please enter a message.';
    }

    if (empty($errors) && !$pdo) {
        $errors[] = 'The database is not connected right now, so this message cannot be saved. Check includes/db.php.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                'INSERT INTO CONTACT_MESSAGE (user_id, message_type, name, email, subject, message)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $_SESSION['user_id'] ?? null,
                $old['message_type'],
                $old['name'],
                $old['email'],
                $old['subject'],
                $old['message'],
            ]);
            $success = true;
            $old['subject'] = '';
            $old['message'] = '';
        } catch (PDOException $e) {
            $errors[] = 'Something went wrong sending that. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="wrap">
    <span class="eyebrow">Contact Us</span>
    <h1>Enquiries, recipe requests, or feedback</h1>
    <p>Whatever it is, someone actually reads these — not a ticket queue that disappears into the void.</p>
  </div>
</section>

<div class="wrap contact-grid">
  <div class="contact-form-card">
    <?php if ($success): ?>
      <div class="success-banner">
        <p>Message sent — thank you. We'll get back to you soon.</p>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div style="background: rgba(177,74,42,0.08); border: 1px solid rgba(177,74,42,0.3); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
        <?php foreach ($errors as $error): ?>
          <p style="color: var(--paprika); font-size: 0.88rem; margin: 0 0 4px;">• <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="contact.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      <div class="field-row">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($old['name']); ?>">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($old['email']); ?>">
        </div>
      </div>
      <div class="field">
        <label for="message_type">This is a</label>
        <select id="message_type" name="message_type">
          <?php foreach (['Enquiry', 'Recipe Request', 'Feedback'] as $type): ?>
            <option value="<?php echo $type; ?>" <?php echo $old['message_type'] === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" required maxlength="150" value="<?php echo htmlspecialchars($old['subject']); ?>">
      </div>
      <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" required style="min-height: 140px;"><?php echo htmlspecialchars($old['message']); ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Send message</button>
    </form>
  </div>

  <aside>
    <div class="sidebar-card">
      <h3>Other ways to reach us</h3>
      <div class="contact-info-item">
        <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:18px;height:18px;"><path d="M4 4h16v16H4z"/><path d="M4 6l8 6 8-6"/></svg></span>
        <div>
          <h4>Email</h4>
          <p>hello@foodfusion.com</p>
        </div>
      </div>
      <div class="contact-info-item">
        <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:18px;height:18px;"><path d="M12 21s-6-5.5-6-10a6 6 0 0112 0c0 4.5-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/></svg></span>
        <div>
          <h4>Based in</h4>
          <p>Rabat, Morocco</p>
        </div>
      </div>
      <div class="contact-info-item">
        <span class="icon-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:18px;height:18px;"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span>
        <div>
          <h4>Response time</h4>
          <p>Usually within 2 business days</p>
        </div>
      </div>
    </div>
  </aside>
</div>

<?php require_once 'includes/footer.php'; ?>