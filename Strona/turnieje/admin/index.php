<?php
require_once __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');

$pdo = db();
$liczby = [
    'Turnieje'          => (int)$pdo->query('SELECT COUNT(*) FROM turniej')->fetchColumn(),
    'Zapisy otwarte'    => (int)$pdo->query("SELECT COUNT(*) FROM turniej WHERE status = 'otwarty'")->fetchColumn(),
    'Drużyny'           => (int)$pdo->query('SELECT COUNT(*) FROM druzyna')->fetchColumn(),
    'Konta użytkowników'=> (int)$pdo->query('SELECT COUNT(*) FROM uzytkownik')->fetchColumn(),
];

$tytul = 'Panel administratora';
require __DIR__ . '/../includes/header.php';
?>
<h1>Panel administratora</h1>

<div class="info-kafle">
  <?php foreach ($liczby as $nazwa => $n): ?>
    <div class="stat"><small><?= e($nazwa) ?></small><b><?= $n ?></b></div>
  <?php endforeach; ?>
</div>

<div class="siatka-kart">
  <a class="karta" href="turnieje.php">
    <div class="karta-tresc"><h3>🏆 Zarządzaj turniejami</h3>
      <small>Dodawanie, edycja, publikowanie i anulowanie turniejów CS2</small></div>
  </a>
  <a class="karta" href="druzyny.php">
    <div class="karta-tresc"><h3>👥 Zarządzaj drużynami</h3>
      <small>Przegląd drużyn i usuwanie drużyn</small></div>
  </a>
  <a class="karta" href="zaproszenia.php">
    <div class="karta-tresc"><h3>✉️ Zarządzaj zaproszeniami</h3>
      <small>Wysyłanie, akceptowanie i anulowanie zaproszeń do drużyn (faza testowa)</small></div>
  </a>
  <a class="karta" href="ogloszenia.php">
    <div class="karta-tresc"><h3>📢 Zarządzaj ogłoszeniami</h3>
      <small>Publikowanie, edycja i usuwanie ogłoszeń</small></div>
  </a>
  <a class="karta" href="liga.php">
    <div class="karta-tresc"><h3>🥇 NEXORA PRO LEAGUE</h3>
      <small>Zgłoszenia, start ligi i wpisywanie wyników</small></div>
  </a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
