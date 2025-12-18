<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/items.php';

require_auth();
$user = auth_user();
$userId = (int)$user['id'];

$id = (int)($_GET['id'] ?? 0);
$item = get_item($pdo, $userId, $id);
if (!$item) {
    http_response_code(404);
    $title = "Не знайдено";
    include __DIR__ . '/_header.php';
    echo "<h1>Товар не знайдено (404)</h1>";
    echo "<p><a href='app.php'>Назад</a></p>";
    include __DIR__ . '/_footer.php';
    exit;
}

$categories = get_categories($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $res = update_item($pdo, $userId, $id, $_POST['name'] ?? '', $_POST['price'] ?? '', $categoryId);
    if ($res['ok']) {
        flash_set('success', 'Зміни збережено.');
        redirect('app.php');
    } else {
        $errors = $res['errors'];
    }
}

$title = "Редагування";
include __DIR__ . '/_header.php';
?>
<h1>Редагування товару</h1>

<?php if ($errors): ?>
  <div class="alert alert--danger">
    <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <label>Назва
    <input name="name" maxlength="100" value="<?= e($_POST['name'] ?? $item['name']) ?>" required>
  </label>
  <label>Ціна
    <input name="price" value="<?= e($_POST['price'] ?? $item['price']) ?>" required>
  </label>
  <label>Категорія
    <select name="category_id" required>
      <?php $cur = (int)($_POST['category_id'] ?? $item['category_id']); ?>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cur===(int)$c['id']?'selected':'' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn--primary" type="submit">Зберегти</button>
  <a class="btn btn--ghost" href="app.php">Скасувати</a>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
