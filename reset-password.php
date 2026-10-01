<?php
require_once 'includes/db.php';

// The one Reset Password form lives in the "Forgot your password?" modal in
// includes/header.php. This file just processes the submission — see
// includes/header.php and includes/footer.php for the modal reopen logic.
//
// This project has no email-sending capability, so instead of mailing a
// reset link, a request resets the account straight to a fixed default
// password below. The account then has that default password, hashed with
// password_hash() like any other — it is never stored in plain text.
const DEFAULT_RESET_PASSWORD = 'Reset123!';

function fail_reset($message, $email = '') {
    $_SESSION['reset_error'] = $message;
    $_SESSION['reset_old'] = ['email' => $email];
    header('Location: index.php?auth=reset');
    exit;
}

// Whether or not the email belongs to an account, the response is this
// exact same wording — revealing that distinction is how login forms leak
// which emails are registered, so the two cases must be indistinguishable.
function finish_reset($email) {
    $_SESSION['reset_message'] = 'If an account exists for that email, its password has been reset to the default "'
        . DEFAULT_RESET_PASSWORD . '". Log in with it, then change your password.';
    $_SESSION['reset_old'] = ['email' => $email];
    header('Location: index.php?auth=reset');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?auth=reset');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    fail_reset('Your session expired before this form was submitted. Please try again.');
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    fail_reset('Please enter a valid email address.', $email);
}

if (!$pdo) {
    fail_reset('The database is not connected right now, so passwords cannot be reset. Check includes/db.php.', $email);
}

try {
    // Hashed unconditionally, before the lookup even runs, so a nonexistent
    // email doesn't get a faster response than a real one — the same
    // timing-attack defense as login.php's dummy password_verify() call on
    // its "no such user" path.
    $newHash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('SELECT user_id FROM USERS WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Resetting the password also clears any lockout — otherwise a
        // locked-out member would reset to the default and still be unable
        // to log in with it.
        $pdo->prepare(
            'UPDATE USERS SET password_hash = ?, failed_attempts = 0, lockout_until = NULL, status = ? WHERE user_id = ?'
        )->execute([$newHash, 'Active', $user['user_id']]);
    }

    finish_reset($email);

} catch (PDOException $e) {
    error_log('FoodFusion reset-password: ' . $e->getMessage());
    fail_reset('Something went wrong resetting that password. Please try again.', $email);
}
