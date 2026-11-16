<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/karty.php';

function aktywne(string $sciezka): string {
    return ($_SERVER['SCRIPT_NAME'] === BASE_URL . '/' . $sciezka) ? ' aktywne' : '';
}
$wAdminie    = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$wDruzynach  = strpos(basename($_SERVER['SCRIPT_NAME']), 'druzyn') === 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($tytul ?? 'Turnieje CS2') ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="uklad">
  <aside class="boczne">
    <a class="logo" href="<?= BASE_URL ?>/index.php">🎯 <span>TURNIEJE<small>CS2</small></span></a>
    <nav>
      <p class="sekcja">Rywalizacja</p>
      <a class="poz<?= aktywne('turnieje.php') ?>" href="<?= BASE_URL ?>/turnieje.php">🏆 <span>Turnieje</span></a>
      <a class="poz<?= $wDruzynach ? ' aktywne' : '' ?>" href="<?= BASE_URL ?>/druzyny.php">👥 <span>Drużyny</span></a>
      <a class="poz<?= aktywne('ranking.php') ?>" href="<?= BASE_URL ?>/ranking.php">📊 <span>Ranking</span></a>

      <p class="sekcja">Społeczność</p>
      <a class="poz<?= aktywne('zawodnicy.php') ?>" href="<?= BASE_URL ?>/zawodnicy.php">🎮 <span>Zawodnicy</span></a>

      <?php if (maRole('administrator')): ?>
        <p class="sekcja">Administracja</p>
        <a class="poz<?= $wAdminie ? ' aktywne' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">⚙️ <span>Panel admina</span></a>
      <?php endif; ?>
    </nav>
  </aside>

  <div class="glowna">
    <header class="gora">
      <span class="sciezka"><a href="<?= BASE_URL ?>/index.php">🏠</a> &rsaquo; <?= e(mb_strtoupper($tytul ?? 'Strona główna')) ?></span>
      <div class="konto">
        <?php if (zalogowany()): ?>
          <a class="nick" href="<?= BASE_URL ?>/konto.php">👤 <?= e(uzytkownik()['nick']) ?></a>
          <a href="<?= BASE_URL ?>/logout.php">Wyloguj</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/register.php">Rejestracja</a>
          <a class="przycisk maly" href="<?= BASE_URL ?>/login.php">Logowanie</a>
        <?php endif; ?>
      </div>
    </header>
    <main class="kontener">
