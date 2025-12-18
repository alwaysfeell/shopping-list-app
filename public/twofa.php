<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

$title = "2FA";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$pendingId) redirect('login.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $code = trim($_POST['code'] ?? '');

    $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([(int)$pendingId]);
    $user = $st->fetch();

    if (!$user) {
        $errors[] = "Користувача не знайдено.";
    } else {
        $expected = $user['twofa_code'] ?: '123456';
        if (hash_equals($expected, $code)) {
            unset($_SESSION['pending_2fa_user_id']);
            auth_login($user);
            flash_set('success', '2FA OK. Вхід виконано.');
            redirect('app.php');
        } else {
            $errors[] = "Неправильний 2FA код.";
        }
    }
}

include __DIR__ . '/_header.php';
?>
<h1>Двофакторна перевірка</h1>

<?php if ($errors): ?>
  <div class="alert alert--danger">
    <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>2FA code
    <input name="code" inputmode="numeric" placeholder="123456" required>
  </label>
  <button class="btn btn--primary" type="submit">Підтвердити</button>
</form>

<p class="muted">Для seed-акаунта <b>twofa_user</b> код: <b>123456</b></p>

<?php include __DIR__ . '/_footer.php'; ?>