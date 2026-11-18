<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/karty.php';
require_once __DIR__ . '/druzyna_funkcje.php';
require_once __DIR__ . '/zawodnik_funkcje.php';
require_once __DIR__ . '/powiadomienia.php';

function aktywne(string $sciezka): string {
    return ($_SERVER['SCRIPT_NAME'] === BASE_URL . '/' . $sciezka) ? ' aktywne' : '';
}
$wAdminie    = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$wDruzynach  = strpos(basename($_SERVER['SCRIPT_NAME']), 'druzyn') === 0;

// Kolor sekcji: klasa s-... na znaczniku body (patrz assets/style.css)
$plik = basename($_SERVER['SCRIPT_NAME'], '.php');
if ($wAdminie)                                                  $sek = 'admin';
elseif (in_array($plik, ['turniej', 'turnieje'], true))         $sek = 'turnieje';
elseif (strpos($plik, 'druzyn') === 0 || in_array($plik, ['zaproszenia', 'moja_druzyna'], true)) $sek = 'druzyny';
elseif ($plik === 'ranking')                                    $sek = 'ranking';
elseif ($plik === 'liga')                                       $sek = 'liga';
elseif ($plik === 'ogloszenia')                                 $sek = 'ogloszenia';
elseif (strpos($plik, 'zawodnik') === 0)                        $sek = 'zawodnicy';
elseif ($plik === 'index')                                      $sek = 'start';
else                                                            $sek = 'konto';

// Powiadomienia: liczba oczekujących zaproszeń do drużyn
$mojProfil = zalogowany() ? mojZawodnik() : null;

// Zakładka „Moja drużyna”: link prowadzi na profil własnej drużyny (albo na stronę z propozycją założenia)
$mojaDr = $mojProfil ? druzynaZawodnika((int)$mojProfil['id_zawodnika']) : null;
$mojaLink = $mojaDr ? BASE_URL . '/druzyna.php?id=' . (int)$mojaDr['id_druzyny'] : BASE_URL . '/moja_druzyna.php';
$naMojejD = $plik === 'moja_druzyna'
    || ($mojaDr && in_array($plik, ['druzyna', 'druzyna_form'], true) && (int)($_GET['id'] ?? 0) === (int)$mojaDr['id_druzyny']);
if ($naMojejD) $wDruzynach = false;
$zapLista = $mojProfil ? oczekujaceZaproszeniaZawodnika((int)$mojProfil['id_zawodnika']) : [];
$mojeZaproszenia = count($zapLista);
$noweZap = $mojProfil ? noweZaproszeniaDoPowiadomienia((int)$mojProfil['id_zawodnika'], $zapLista) : [];
$naStronieZap = basename($_SERVER['SCRIPT_NAME']) === 'zaproszenia.php';
$awatarGora = zalogowany() ? awatarHtml($mojProfil ?: ['nick' => uzytkownik()['nick']]) : '';
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
      <?php if ($mojProfil): ?>
        <a class="poz<?= $naMojejD ? ' aktywne' : '' ?>" href="<?= e($mojaLink) ?>">⭐ <span>Moja drużyna</span></a>
      <?php endif; ?>
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
          <details class="pm" id="profil-menu">
            <summary class="pm-btn" aria-label="Menu profilu">
              <span class="pm-awatar"><?= $awatarGora ?><?php if ($mojeZaproszenia): ?><span class="pm-kropka" title="Oczekujące zaproszenia"><?= $mojeZaproszenia ?></span><?php endif; ?></span>
              <span class="pm-nick"><?= e(uzytkownik()['nick']) ?></span>
              <span class="pm-strzalka" aria-hidden="true">▾</span>
            </summary>
            <div class="pm-menu">
              <div class="pm-naglowek">
                <?= str_replace('class="awatar ', 'class="awatar sredni ', $awatarGora) ?>
                <div><b><?= e(uzytkownik()['nick']) ?></b><small><?= maRole('administrator') ? 'Administrator' : 'Zawodnik' ?></small></div>
              </div>
              <?php if ($mojProfil): ?>
                <a href="<?= BASE_URL ?>/zawodnik.php?id=<?= (int)$mojProfil['id_zawodnika'] ?>">👤 <span>Mój profil</span></a>
                <a href="<?= e($mojaLink) ?>">⭐ <span><?= $mojaDr ? 'Moja drużyna' : 'Drużyna' ?></span></a>
                <a href="<?= BASE_URL ?>/zaproszenia.php">✉️ <span>Zaproszenia</span>
                  <span class="licznik pm-licznik"<?= $mojeZaproszenia ? '' : ' hidden' ?>><?= $mojeZaproszenia ?></span></a>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/konto.php">⚙️ <span>Ustawienia konta</span></a>
              <?php if (maRole('administrator')): ?>
                <a href="<?= BASE_URL ?>/admin/index.php">🛡️ <span>Panel admina</span></a>
              <?php endif; ?>
              <hr>
              <a class="pm-wyloguj" href="<?= BASE_URL ?>/logout.php">🚪 <span>Wyloguj się</span></a>
            </div>
          </details>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/register.php">Rejestracja</a>
          <a class="przycisk maly" href="<?= BASE_URL ?>/login.php">Logowanie</a>
        <?php endif; ?>
      </div>
    </header>

    <!-- Wyskakujące powiadomienia (nowe zaproszenia do drużyn) -->
    <div class="powiad-stos" id="powiad-stos" aria-live="polite"
         data-endpoint="<?= $mojProfil ? BASE_URL . '/zaproszenia_status.php' : '' ?>"
         data-zaproszenia="<?= BASE_URL ?>/zaproszenia.php"
         data-bez-toastow="<?= $naStronieZap ? '1' : '0' ?>">
      <?php if ($noweZap && !$naStronieZap): [$tytulP, $opisP] = trescPowiadomieniaZaproszen($noweZap); ?>
        <div class="toast" role="status">
          <span class="toast-ikona">✉️</span>
          <div class="toast-tresc"><b><?= e($tytulP) ?></b><span><?= e($opisP) ?></span></div>
          <a class="toast-link" href="<?= BASE_URL ?>/zaproszenia.php">Zobacz</a>
          <button type="button" class="toast-x" aria-label="Zamknij">&times;</button>
        </div>
      <?php endif; ?>
    </div>

    <main class="kontener">
