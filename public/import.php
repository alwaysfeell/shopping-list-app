<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/items.php';
require_once __DIR__ . '/../app/validators.php';

require_auth();
$userId = (int)auth_user()['id'];

$title = "Імпорт";
$errors = [];
$okCount = 0;

function parse_bool($v): bool {
    if (is_bool($v)) return $v;
    $s = strtolower(trim((string)$v));
    return in_array($s, ['1','true','yes','y'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Файл не завантажено.";
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $name = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $content = file_get_contents($tmp);

        $records = null;

        if ($ext === 'json') {
            $records = json_decode($content, true);
            if (!is_array($records)) $errors[] = "Помилка формату JSON.";
        } elseif ($ext === 'csv') {
            $records = [];
            $fh = fopen($tmp, 'r');
            $header = fgetcsv($fh);
            if (!$header) $errors[] = "Порожній CSV.";
            else {
                while (($row = fgetcsv($fh)) !== false) {
                    $assoc = [];
                    for ($i=0; $i<count($header); $i++) {
                        $assoc[$header[$i]] = $row[$i] ?? null;
                    }
                    $records[] = $assoc;
                }
            }
            if ($fh) fclose($fh);
        } else {
            $errors[] = "Підтримка лише .json або .csv";
        }

        if (!$errors && is_array($records)) {
            foreach ($records as $r) {
                $itemName = (string)($r['name'] ?? '');
                $itemPrice = (string)($r['price'] ?? '');
                $catName = (string)($r['category'] ?? '');
                $purchased = parse_bool($r['is_purchased'] ?? false);

                if (($e = validate_item_name($itemName)) || ($e2 = validate_price($itemPrice))) {
                    // пропускаємо невалідний запис
                    continue;
                }
                $catId = category_id_by_name($pdo, $catName);
                if (!$catId) continue;

                $p = round((float)normalize_price($itemPrice), 2);

                $st = $pdo->prepare("INSERT INTO items (user_id, category_id, name, price, is_purchased, created_at)
                                     VALUES (?, ?, ?, ?, ?, NOW())");
                $st->execute([$userId, $catId, trim($itemName), $p, $purchased ? 1 : 0]);
                $okCount++;
            }
        }
    }

    if ($errors) flash_set('danger', implode(' ', $errors));
    else flash_set('success', "Імпорт завершено. Додано записів: $okCount");
    redirect('app.php');
}

include __DIR__ . '/_header.php';
?>
<h1>Імпорт JSON/CSV</h1>

<div class="card">
  <p class="muted">Формат JSON: масив об'єктів з полями <code>name</code>, <code>price</code>, <code>category</code>, <code>is_purchased</code>.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="file" name="file" accept=".json,.csv" required>
    <button class="btn btn--primary" type="submit">Імпортувати</button>
  </form>

  <details class="mt">
    <summary>Приклад валідного JSON</summary>
    <pre>[
  {"name":"Рис 1кг","price":62.40,"category":"Продукти","is_purchased":false},
  {"name":"Шкарпетки чорні","price":99.99,"category":"Одяг","is_purchased":true},
  {"name":"Лампочка LED E27","price":120.00,"category":"Інше","is_purchased":false}
]</pre>
  </details>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
