<?php
require_once __DIR__ . '/../app/helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$flash = flash_get_all();
?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Shopping List') ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="container topbar__inner">
    <div class="brand">🛒 Shopping List</div>
    <nav class="nav">
      <?php if (!empty($_SESSION['user'])): ?>
        <span class="nav__user">@<?= e($_SESSION['user']['username']) ?></span>
        <a class="btn btn--ghost" href="app.php">Список</a>
        <a class="btn btn--ghost" href="import.php">Імпорт</a>
        <a class="btn btn--ghost" href="export.php?fmt=json">Експорт JSON</a>
        <a class="btn btn--ghost" href="export.php?fmt=csv">Експорт CSV</a>
        <a class="btn btn--danger" href="logout.php">Вийти</a>
      <?php else: ?>
        <a class="btn btn--ghost" href="login.php">Логін</a>
        <a class="btn btn--ghost" href="register.php">Реєстрація</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container">
  <?php foreach ($flash as $f): ?>
    <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
