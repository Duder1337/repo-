<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/karty.php';
require_once __DIR__ . '/druzyna_funkcje.php';

function aktywne(string $sciezka): string {
    return ($_SERVER['SCRIPT_NAME'] === BASE_URL . '/' . $sciezka) ? ' aktywne' : '';
}
$wAdminie    = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$wDruzynach  = strpos(basename($_SERVER['SCRIPT_NAME']), 'druzyn') === 0;

// Kolor sekcji: klasa s-... na znaczniku body (patrz assets/style.css)
$plik = basename($_SERVER['SCRIPT_NAME'], '.php');
if ($wAdminie)                                                  $sek = 'admin';
elseif (in_array($plik, ['turniej', 'turnieje'], true))         $sek = 'turnieje';
elseif (strpos($plik, 'druzyn') === 0 || $plik === 'zaproszenia') $sek = 'druzyny';
elseif ($plik === 'ranking')                                    $sek = 'ranking';
elseif ($plik === 'liga')                                       $sek = 'liga';
elseif ($plik === 'ogloszenia')                                 $sek = 'ogloszenia';
elseif (strpos($plik, 'zawodnik') === 0)                        $sek = 'zawodnicy';
elseif ($plik === 'index')                                      $sek = 'start';
else                                                            $sek = 'konto';

// Powiadomienia: liczba oczekujących zaproszeń do drużyn
$mojProfil = zalogowany() ? mojZawodnik() : null;
$mojeZaproszenia = 0;
if ($mojProfil) {
    try {
        $stZ = db()->prepare("SELECT COUNT(*) FROM zaproszenie WHERE id_zawodnika = ? AND status = 'oczekujące'");
        $stZ->execute([$mojProfil['id_zawodnika']]);
        $mojeZaproszenia = (int)$stZ->fetchColumn();
    } catch (PDOException $ex) { $mojeZaproszenia = 0; }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(isset($tytul) ? $tytul . ' · Nexora' : 'Nexora') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Exo+2:wght@600;700;800&family=Figtree:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body class="s-<?= e($sek) ?>">
<div class="uklad">
  <aside class="boczne">
    <a class="logo" href="<?= BASE_URL ?>/index.php">🎯 <span>NEXORA<small>CS2</small></span></a>
    <nav>
      <p class="sekcja">Rywalizacja</p>
      <a class="poz<?= aktywne('turnieje.php') ?>" href="<?= BASE_URL ?>/turnieje.php">🏆 <span>Turnieje</span></a>
      <a class="poz<?= $wDruzynach ? ' aktywne' : '' ?>" href="<?= BASE_URL ?>/druzyny.php">👥 <span>Drużyny</span></a>
      <a class="poz<?= aktywne('ranking.php') ?>" href="<?= BASE_URL ?>/ranking.php">📊 <span>Ranking</span></a>
      <a class="poz<?= aktywne('liga.php') ?>" href="<?= BASE_URL ?>/liga.php">🥇 <span>Liga</span></a>

      <p class="sekcja">Społeczność</p>
      <a class="poz<?= aktywne('ogloszenia.php') ?>" href="<?= BASE_URL ?>/ogloszenia.php">📢 <span>Ogłoszenia</span></a>
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
          <?php if ($mojProfil): ?>
            <a href="<?= BASE_URL ?>/zaproszenia.php">✉️ Zaproszenia<?php if ($mojeZaproszenia): ?> <span class="licznik"><?= $mojeZaproszenia ?></span><?php endif; ?></a>
            <a href="<?= BASE_URL ?>/zawodnik.php?id=<?= (int)$mojProfil['id_zawodnika'] ?>">Mój profil</a>
          <?php endif; ?>
          <a class="nick" href="<?= BASE_URL ?>/konto.php">👤 <?= e(uzytkownik()['nick']) ?></a>
          <a href="<?= BASE_URL ?>/logout.php">Wyloguj</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/register.php">Rejestracja</a>
          <a class="przycisk maly" href="<?= BASE_URL ?>/login.php">Logowanie</a>
        <?php endif; ?>
      </div>
    </header>
    <main class="kontener">
    <?php if ($mojeZaproszenia && basename($_SERVER['SCRIPT_NAME']) !== 'zaproszenia.php'):
        $n = $mojeZaproszenia;
        $slowo = $n === 1 ? 'nowe zaproszenie' : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) ? 'nowe zaproszenia' : 'nowych zaproszeń'); ?>
      <div class="powiadomienie">✉️ Masz <?= $n ?> <?= $slowo ?> do drużyny.
        <a href="<?= BASE_URL ?>/zaproszenia.php">Zobacz</a></div>
    <?php endif; ?>
