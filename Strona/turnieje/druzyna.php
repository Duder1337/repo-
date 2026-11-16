<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM druzyna WHERE id_druzyny = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Nie znaleziono drużyny.'); }

$komunikat = '';
$blad = '';

// Zwykły członek może sam opuścić drużynę (kapitan musi najpierw przekazać funkcję)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcja'] ?? '') === 'opusc') {
    wymagajLogowania();
    csrfSprawdz();
    $mojaD = mojaDruzyna();
    if (!$mojaD || (int)$mojaD['id_druzyny'] !== $id) {
        $blad = 'Nie należysz do tej drużyny.';
    } elseif ($mojaD['rola'] === 'kapitan') {
        $blad = 'Kapitan nie może opuścić drużyny. Przekaż kapitana innemu graczowi lub rozwiąż drużynę.';
    } elseif (druzynaZablokowana($id)) {
        $blad = 'Nie można opuścić drużyny uczestniczącej w trwających rozgrywkach.';
    } else {
        db()->prepare('DELETE FROM sklad WHERE id_druzyny = ? AND id_zawodnika = ?')->execute([$id, mojZawodnik()['id_zawodnika']]);
        $komunikat = 'Opuściłeś drużynę.';
    }
}

$grupy = ['kapitan' => [], 'gracz' => [], 'rezerwowy' => [], 'trener' => []];
foreach (pobierzSklad($id) as $s) $grupy[$s['rola']][] = $s;

$moja     = mozeZarzadzac($id);          // kapitan lub administrator
$mojaD    = mojaDruzyna();
$jestCzlonkiem = $mojaD && (int)$mojaD['id_druzyny'] === $id && $mojaD['rola'] !== 'kapitan';

$st = db()->prepare('SELECT punkty, wygrane, przegrane FROM ranking WHERE id_druzyny = ?');
$st->execute([$id]);
$r = $st->fetch() ?: ['punkty' => 0, 'wygrane' => 0, 'przegrane' => 0];

$st = db()->prepare("SELECT t.id_turnieju, t.nazwa, t.status FROM zgloszenie z
                     JOIN turniej t ON t.id_turnieju = z.id_turnieju
                     WHERE z.id_druzyny = ? AND z.status = 'przyjęte' AND t.status <> 'szkic'
                     ORDER BY t.data_startu DESC");
$st->execute([$id]);
$turnieje = $st->fetchAll();

$naglowki = ['kapitan' => 'Kapitan', 'gracz' => 'Gracze', 'rezerwowy' => 'Rezerwowi', 'trener' => 'Trener'];
$tytul = $d['nazwa'];
require 'includes/header.php';
?>
<section class="baner-turnieju" style="--h:<?= (int)(crc32($d['nazwa']) % 360) ?>">
  <small>Drużyna CS2</small>
  <h1><?= e($d['nazwa']) ?></h1>
</section>

<div class="info-kafle">
  <div class="stat"><small>Punkty</small><b><?= (int)$r['punkty'] ?></b></div>
  <div class="stat"><small>Wygrane</small><b><?= (int)$r['wygrane'] ?></b></div>
  <div class="stat"><small>Przegrane</small><b><?= (int)$r['przegrane'] ?></b></div>
</div>

<?php if ($komunikat): ?><p class="ok"><?= e($komunikat) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<?php if ($moja): ?>
  <p><a class="przycisk" href="druzyna_form.php?id=<?= (int)$id ?>">Zarządzaj drużyną</a></p>
<?php elseif ($jestCzlonkiem): ?>
  <form method="post" class="inline">
    <?= csrfPole() ?>
    <button name="akcja" value="opusc" class="drugi" onclick="return confirm('Opuścić drużynę?')">Opuść drużynę</button>
  </form>
<?php endif; ?>

<h2>Skład</h2>
<div class="sklad-siatka">
  <?php foreach ($grupy as $rola => $zawodnicy): ?>
    <div class="sklad-grupa">
      <h3><?= e($naglowki[$rola]) ?></h3>
      <?php if (!$zawodnicy): ?><p class="przygaszony">–</p><?php endif; ?>
      <?php foreach ($zawodnicy as $z): ?>
        <div class="sklad-gracz"><a href="zawodnik.php?id=<?= (int)$z['id_zawodnika'] ?>"><?= e($z['nick']) ?></a></div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<h2>Turnieje</h2>
<?php if (!$turnieje): ?>
  <p class="przygaszony">Drużyna nie bierze udziału w żadnym turnieju.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Turniej</th><th>Status turnieju</th></tr>
    <?php foreach ($turnieje as $t): ?>
      <tr>
        <td><a href="turniej.php?id=<?= (int)$t['id_turnieju'] ?>"><?= e($t['nazwa']) ?></a></td>
        <td><?= e(etykietaStatusu($t['status'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<p><a href="druzyny.php">&larr; Wróć do listy drużyn</a></p>
<?php require 'includes/footer.php'; ?>
