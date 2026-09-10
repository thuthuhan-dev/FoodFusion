<?php
require_once 'includes/db.php';

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $errors[] = 'Your session expired before this form was submitted. Please try again.';
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    $old['first_name'] = $firstName;
    $old['last_name']  = $lastName;
    $old['email']      = $email;

    if ($firstName === '' || mb_strlen($firstName) > 50) {
        $errors[] = 'First name is required and must be under 50 characters.';
    } elseif (!preg_match("/^[\p{L}\s'\-]+$/u", $firstName)) {
        $errors[] = 'First name may only contain letters, spaces, and hyphens.';
    }

    if ($lastName === '' || mb_strlen($lastName) > 50) {
        $errors[] = 'Last name is required and must be under 50 characters.';
    } elseif (!preg_match("/^[\p{L}\s'\-]+$/u", $lastName)) {
        $errors[] = 'Last name may only contain letters, spaces, and hyphens.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }

    if (empty($errors) && $pdo) {
        try {
            $check = $pdo->prepare('SELECT user_id FROM USERS WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'An account with that email already exists. Try logging in instead.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Something went wrong checking that email. Please try again.';
        }
    }

    if (!$pdo) {
        $errors[] = 'The database is not connected right now, so accounts cannot be created. Check includes/db.php.';
    }

    if (empty($errors)) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $pdo->prepare(
                'INSERT INTO USERS (first_name, last_name, email, password_hash, role, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$firstName, $lastName, $email, $passwordHash, 'Member', 'Active']);
            $newUserId = (int) $pdo->lastInsertId();

            // Log the new account straight in — no reason to make them retype
            // credentials they just entered thirty seconds ago.
            $logStmt = $pdo->prepare(
                'INSERT INTO SESSION_LOG (user_id, login_time, ip_address, device_info)
                 VALUES (?, NOW(), ?, ?)'
            );
            $logStmt->execute([
                $newUserId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id']        = $newUserId;
            $_SESSION['first_name']     = $firstName;
            $_SESSION['role']           = 'Member';
            $_SESSION['session_log_id'] = $pdo->lastInsertId();
            unset($_SESSION['csrf_token']);

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Something went wrong creating your account. Please try again.';
        }
    }
}

$authPageTitle = 'Create your account';
require_once 'includes/auth-header.php';
?>

<div class="auth-page">
  <div class="auth-brand-panel">
    <a href="index.php" class="auth-logo">
      <span class="brand-mark">FF</span> FoodFusion
    </a>
    <div class="auth-brand-content">
      <h2>Join a table that's always setting one more place.</h2>
      <p>Save your favourite recipes, share your own, and swap tips with home cooks who care as much as you do.</p>
    </div>
    <div class="auth-brand-footer">&copy; <?php echo date('Y'); ?> FoodFusion</div>
  </div>

  <div class="auth-form-panel">
    <div class="auth-form-card">
      <a href="index.php" class="auth-mobile-logo"><span class="brand-mark">FF</span> FoodFusion</a>

      <h1>Create your account</h1>
      <p class="lede">Free to join — takes less than a minute.</p>

      <?php if (!empty($errors)): ?>
        <div style="background: rgba(177,74,42,0.08); border: 1px solid rgba(177,74,42,0.3); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px;">
          <?php foreach ($errors as $error): ?>
            <p style="color: var(--paprika); font-size: 0.88rem; margin: 0 0 4px;">• <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form action="register.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        <div class="field-row">
          <div class="field">
            <label for="first_name">First name</label>
            <input type="text" id="first_name" name="first_name" required autocomplete="given-name" autofocus value="<?php echo htmlspecialchars($old['first_name']); ?>">
          </div>
          <div class="field">
            <label for="last_name">Last name</label>
            <input type="text" id="last_name" name="last_name" required autocomplete="family-name" value="<?php echo htmlspecialchars($old['last_name']); ?>">
          </div>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($old['email']); ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
          <p class="form-note" style="margin-top: 6px;">At least 8 characters, with one letter and one number.</p>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Create account</button>
        <p class="form-note">By joining, you agree to our <a href="privacy-policy.php">Privacy Policy</a> and <a href="cookie-policy.php">Cookie Policy</a>.</p>
      </form>

      <p class="modal-switch">Already a member? <a href="login.php">Log in</a></p>
      <a href="index.php" class="auth-back-link">&larr; Back to home</a>
    </div>
  </div>
</div>

<?php require_once 'includes/auth-footer.php'; ?>