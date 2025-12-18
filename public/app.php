<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/items.php';

require_auth();
$user = auth_user();
$userId = (int)$user['id'];

$categoryFilter = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
$categories = get_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $res = create_item($pdo, $userId, $_POST['name'] ?? '', $_POST['price'] ?? '', $categoryId);
        if ($res['ok']) flash_set('success', 'Товар додано.');
        else flash_set('danger', implode(' ', $res['errors']));
        redirect('app.php' . ($categoryFilter ? '?category='.$categoryFilter : ''));
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        toggle_purchased($pdo, $userId, $id);
        flash_set('success', 'Статус оновлено.');
        redirect('app.php' . ($categoryFilter ? '?category='.$categoryFilter : ''));
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        delete_item($pdo, $userId, $id);
        flash_set('success', 'Товар видалено.');
        redirect('app.php' . ($categoryFilter ? '?category='.$categoryFilter : ''));
    }
}

$items = get_items($pdo, $userId, $categoryFilter);
$sum = sum_unpurchased($pdo, $userId);

$title = "Список покупок";
include __DIR__ . '/_header.php';
?>
<h1>Список покупок</h1>

<section class="grid">
  <div class="card">
    <h2>Додати товар</h2>
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <label>Назва
        <input name="name" maxlength="100" placeholder="Напр. Молоко 2л" required>
      </label>
      <label>Ціна (0–9999.99)
        <input name="price" inputmode="decimal" placeholder="45.50" required>
      </label>
      <label>Категорія
        <select name="category_id" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn--primary" type="submit">Зберегти</button>
    </form>
    <p class="muted">Сума (тільки НЕ придбані): <b><?= number_format($sum, 2, '.', '') ?> грн</b></p>
  </div>

  <div class="card">
    <h2>Фільтр</h2>
    <form method="get" class="form form--row">
      <label>Категорія
        <select name="category" onchange="this.form.submit()">
          <option value="">Усі</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $categoryFilter===(int)$c['id']?'selected':'' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <noscript><button class="btn" type="submit">Застосувати</button></noscript>
    </form>

    <?php if (!$items): ?>
      <div class="empty">Немає товарів у цій категорії 🙃</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($items as $it): ?>
          <div class="item <?= (int)$it['is_purchased']===1?'item--purchased':'' ?>">
            <div class="item__main">
              <div class="item__title"><?= e($it['name']) ?></div>
              <div class="item__meta">
                <span class="tag"><?= e($it['category_name']) ?></span>
                <span class="price"><?= number_format((float)$it['price'], 2, '.', '') ?> грн</span>
              </div>
            </div>
            <div class="item__actions">
              <form method="post" class="inline">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <label class="checkbox">
                  <input type="checkbox" <?= (int)$it['is_purchased']===1?'checked':'' ?> onchange="this.form.submit()">
                  Придбано
                </label>
              </form>

              <a class="btn btn--ghost" href="item_edit.php?id=<?= (int)$it['id'] ?>">Редагувати</a>

              <form method="post" class="inline" onsubmit="return confirm('Точно видалити?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn--danger" type="submit">Видалити</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/_footer.php'; ?>