<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
wymagajLogowania();

$z = mojZawodnik();
if (!$z) exit('To konto nie ma profilu zawodnika, więc nie może należeć do drużyny.');

// Zawodnik z drużyną trafia prosto na jej profil
$d = druzynaZawodnika((int)$z['id_zawodnika']);
if ($d) { header('Location: ' . BASE_URL . '/druzyna.php?id=' . (int)$d['id_druzyny']); exit; }

$tytul = 'Moja drużyna';
require 'includes/header.php';
?>
<h1>Moja drużyna</h1>
<div class="dr-box dr-pusta">
  <p><b>Nie należysz jeszcze do żadnej drużyny.</b></p>
  <p class="przygaszony">Załóż własną i zaproś graczy albo poczekaj na zaproszenie od kapitana innej drużyny.</p>
  <div class="przyciski-rzad">
    <a class="przycisk" href="<?= BASE_URL ?>/druzyna_form.php">＋ Utwórz drużynę</a>
    <a class="przycisk drugi" href="<?= BASE_URL ?>/zaproszenia.php">✉️ Zaproszenia<?= $mojeZaproszenia ? ' (' . (int)$mojeZaproszenia . ')' : '' ?></a>
    <a class="przycisk drugi" href="<?= BASE_URL ?>/druzyny.php">Przeglądaj drużyny</a>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
