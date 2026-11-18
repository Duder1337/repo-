<?php
require_once 'includes/auth.php';
require_once 'includes/turnieje_funkcje.php';
usunStareAnulowane();

$pdo = db();
$otwarte   = (int)$pdo->query("SELECT COUNT(*) FROM turniej WHERE status = 'otwarty'")->fetchColumn();
$wTrakcie  = (int)$pdo->query("SELECT COUNT(*) FROM turniej WHERE status = 'w trakcie'")->fetchColumn();
$druzyny   = (int)$pdo->query('SELECT COUNT(*) FROM druzyna')->fetchColumn();
$zawodnicy = (int)$pdo->query('SELECT COUNT(*) FROM zawodnik')->fetchColumn();

$turnieje = $pdo->query(
    "SELECT t.*, (SELECT COUNT(*) FROM zgloszenie z
                  WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zgloszone
     FROM turniej t
     WHERE t.status IN ('otwarty','w trakcie')
     ORDER BY t.data_startu ASC LIMIT 4"
)->fetchAll();

$tytul = 'Strona główna';
require 'includes/header.php';
?>
<section class="hero">
  <div class="hero-tekst">
    <span class="hero-nad">NEXORA · COUNTER-STRIKE 2 · SEZON <?= date('Y') ?></span>
    <h1>Walcz o puchar.<br>Zbuduj drużynę.</h1>
    <p>Załóż konto, zbuduj skład 5 vs 5 z rezerwowymi i trenerem, zgłoś się do turnieju CS2 i śledź drabinkę oraz ranking.</p>
    <div class="hero-przyciski">
      <a class="przycisk" href="<?= BASE_URL ?>/turnieje.php">Przeglądaj turnieje</a>
      <?php if (!zalogowany()): ?>
        <a class="przycisk drugi" href="<?= BASE_URL ?>/register.php">Załóż konto</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-statystyki">
    <div class="stat"><small>Zapisy otwarte</small><b><?= $otwarte ?></b></div>
    <div class="stat"><small>Turnieje w trakcie</small><b><?= $wTrakcie ?></b></div>
    <div class="stat"><small>Drużyny</small><b><?= $druzyny ?></b></div>
    <div class="stat"><small>Zawodnicy</small><b><?= $zawodnicy ?></b></div>
  </div>
</section>

<div class="naglowek-sekcji">
  <h2>Wydarzenia</h2>
  <a href="<?= BASE_URL ?>/turnieje.php">Zobacz wszystkie &rarr;</a>
</div>
<?php if (!$turnieje): ?>
  <p class="przygaszony">Brak aktywnych turniejów. Zajrzyj tu wkrótce!</p>
<?php else: ?>
  <div class="siatka-kart">
    <?php foreach ($turnieje as $t) kartaTurnieju($t); ?>
  </div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
