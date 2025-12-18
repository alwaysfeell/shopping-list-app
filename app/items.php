<?php
// app/items.php
require_once __DIR__ . '/validators.php';

function get_categories(PDO $pdo): array {
    return $pdo->query("SELECT id, name FROM categories ORDER BY id")->fetchAll();
}

function get_items(PDO $pdo, int $userId, ?int $categoryId): array {
    if ($categoryId) {
        $st = $pdo->prepare("SELECT i.*, c.name AS category_name
                             FROM items i JOIN categories c ON c.id=i.category_id
                             WHERE i.user_id=? AND i.category_id=?
                             ORDER BY i.created_at DESC, i.id DESC");
        $st->execute([$userId, $categoryId]);
    } else {
        $st = $pdo->prepare("SELECT i.*, c.name AS category_name
                             FROM items i JOIN categories c ON c.id=i.category_id
                             WHERE i.user_id=?
                             ORDER BY i.created_at DESC, i.id DESC");
        $st->execute([$userId]);
    }
    return $st->fetchAll();
}

function get_item(PDO $pdo, int $userId, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM items WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
    $r = $st->fetch();
    return $r ?: null;
}

function sum_unpurchased(PDO $pdo, int $userId): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(price),0) AS s FROM items WHERE user_id=? AND is_purchased=0");
    $st->execute([$userId]);
    return (float)$st->fetch()['s'];
}

function create_item(PDO $pdo, int $userId, string $name, string $price, int $categoryId): array {
    $errors = [];
    if ($e = validate_item_name($name)) $errors[] = $e;
    if ($e = validate_price($price)) $errors[] = $e;
    if ($errors) return ['ok'=>false,'errors'=>$errors];

    $p = round((float)normalize_price($price), 2);

    $st = $pdo->prepare("INSERT INTO items (user_id, category_id, name, price, is_purchased, created_at)
                         VALUES (?, ?, ?, ?, 0, NOW())");
    $st->execute([$userId, $categoryId, trim($name), $p]);
    return ['ok'=>true];
}

function update_item(PDO $pdo, int $userId, int $id, string $name, string $price, int $categoryId): array {
    $errors = [];
    if ($e = validate_item_name($name)) $errors[] = $e;
    if ($e = validate_price($price)) $errors[] = $e;
    if ($errors) return ['ok'=>false,'errors'=>$errors];

    $p = round((float)normalize_price($price), 2);

    $st = $pdo->prepare("UPDATE items SET name=?, price=?, category_id=? WHERE id=? AND user_id=?");
    $st->execute([trim($name), $p, $categoryId, $id, $userId]);
    return ['ok'=>true];
}

function delete_item(PDO $pdo, int $userId, int $id): void {
    $st = $pdo->prepare("DELETE FROM items WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
}

function toggle_purchased(PDO $pdo, int $userId, int $id): void {
    $st = $pdo->prepare("UPDATE items SET is_purchased = 1 - is_purchased WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
}

function category_id_by_name(PDO $pdo, string $name): ?int {
    $st = $pdo->prepare("SELECT id FROM categories WHERE name=?");
    $st->execute([$name]);
    $r = $st->fetch();
    return $r ? (int)$r['id'] : null;
}
