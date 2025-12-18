<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/items.php';

require_auth();
$userId = (int)auth_user()['id'];
$fmt = strtolower($_GET['fmt'] ?? 'json');
$items = get_items($pdo, $userId, null);

// map to export structure
$out = [];
foreach ($items as $it) {
    $out[] = [
        'name' => $it['name'],
        'price' => (float)$it['price'],
        'category' => $it['category_name'],
        'is_purchased' => (bool)$it['is_purchased'],
    ];
}

if ($fmt === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shopping_list.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['name','price','category','is_purchased']);
    foreach ($out as $row) {
        fputcsv($fh, [$row['name'], number_format($row['price'],2,'.',''), $row['category'], $row['is_purchased'] ? 'true' : 'false']);
    }
    fclose($fh);
    exit;
}

// default json
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="shopping_list.json"');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
