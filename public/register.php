<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/validators.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

$title = "Реєстрація";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($e = validate_username($username)) $errors[] = $e;
    if ($e = validate_password($password)) $errors[] = $e;
    if ($password !== $confirm) $errors[] = "Паролі не співпадають.";

    if (!$errors) {
        // check exists
        $st = $pdo->prepare("SELECT id FROM users WHERE username=?");
        $st->execute([trim($username)]);
        if ($st->fetch()) {
            $errors[] = "Такий username вже існує.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $st = $pdo->prepare("INSERT INTO users (username, password_hash, twofa_enabled, twofa_code, failed_attempts, lock_until, created_at)
                                 VALUES (?, ?, 0, NULL, 0, NULL, NOW())");
            $st->execute([trim($username), $hash]);
            flash_set('success', 'Акаунт створено. Тепер можна увійти.');
            redirect('login.php');
        }
    }
}

include __DIR__ . '/_header.php';
?>
<h1>Реєстрація</h1>

<?php if ($errors): ?>
  <div class="alert alert--danger">
    <ul>
      <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>Username (3–20, латиниця/цифри/_)
    <input name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
  </label>
  <label>Password (мін. 8)
    <input type="password" name="password" required>
  </label>
  <label>Confirm password
    <input type="password" name="confirm" required>
  </label>
  <button class="btn btn--primary" type="submit">Register</button>
</form>

<?php include __DIR__ . '/_footer.php'; ?>