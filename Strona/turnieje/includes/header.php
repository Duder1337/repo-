<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($tytul ?? 'Turnieje e-sportowe') ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<header class="pasek">
  <a class="logo" href="<?= BASE_URL ?>/index.php">🎮 Turnieje E-sport</a>
  <nav>
    <a href="<?= BASE_URL ?>/turnieje.php">Turnieje</a>
    <a href="<?= BASE_URL ?>/ranking.php">Ranking</a>
    <?php if (maRole('kapitan')): ?>
      <a href="<?= BASE_URL ?>/kapitan/druzyny.php">Moje drużyny</a>
    <?php endif; ?>
    <?php if (maRole('administrator')): ?>
      <a href="<?= BASE_URL ?>/admin/index.php">Panel admina</a>
    <?php endif; ?>
    <?php if (zalogowany()): ?>
      <a href="<?= BASE_URL ?>/logout.php">Wyloguj (<?= e(uzytkownik()['nick']) ?>)</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php">Logowanie</a>
      <a href="<?= BASE_URL ?>/register.php">Rejestracja</a>
    <?php endif; ?>
  </nav>
</header>
<main class="kontener">
