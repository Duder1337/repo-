<?php
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT t.*, d.nazwa AS zwyciezca FROM turniej t
                     LEFT JOIN druzyna d ON d.id_druzyny = t.id_zwyciezcy
                     WHERE t.id_turnieju = ?');
$st->execute([$id]);
$t = $st->fetch();

// Szkice widzi tylko administrator
if (!$t || ($t['status'] === 'szkic' && !maRole('administrator'))) {
    http_response_code(404);
    exit('Nie znaleziono turnieju.');
}

$st = db()->prepare("SELECT d.nazwa, z.data_zgloszenia FROM zgloszenie z
                     JOIN druzyna d ON d.id_druzyny = z.id_druzyny
                     WHERE z.id_turnieju = ? AND z.status = 'przyjęte'
                     ORDER BY z.data_zgloszenia, d.nazwa");
$st->execute([$id]);
$druzyny = $st->fetchAll();
$wolne = max(0, (int)$t['limit_druzyn'] - count($druzyny));
$h = crc32($t['nazwa']) % 360;

$tytul = $t['nazwa'];
require 'includes/header.php';
?>
<section class="baner-turnieju" style="--h:<?= (int)$h ?>">
  <span class="plakietka st-<?= e(klasaStatusu($t['status'])) ?>"><?= e(etykietaStatusu($t['status'])) ?></span>
  <small><?= e($t['dyscyplina']) ?></small>
  <h1><?= e($t['nazwa']) ?></h1>
  <?php if ($t['zwyciezca']): ?><p class="zwyciezca">🏆 Zwycięzca: <strong><?= e($t['zwyciezca']) ?></strong></p><?php endif; ?>
</section>

<div class="info-kafle">
  <div class="stat"><small>Data startu</small><b><?= e($t['data_startu']) ?></b></div>
  <div class="stat"><small>Zapisanych drużyn</small><b><?= count($druzyny) ?> / <?= (int)$t['limit_druzyn'] ?></b></div>
  <div class="stat"><small>Wolne miejsca</small><b><?= $wolne ?></b></div>
  <div class="stat"><small>Format meczu</small><b><?= e($t['format_meczu']) ?></b></div>
</div>

<?php if ($t['status'] === 'otwarty'): ?>
  <p class="przygaszony"><em>Zgłaszanie drużyn pojawi się w kolejnym kroku.</em></p>
<?php elseif (in_array($t['status'], ['w trakcie', 'zakończony'], true)): ?>
  <p class="przygaszony"><em>Drabinka turniejowa pojawi się w kolejnym kroku.</em></p>
<?php endif; ?>

<h2>Zgłoszone drużyny</h2>
<?php if (!$druzyny): ?>
  <p class="przygaszony">Brak zgłoszonych drużyn.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>#</th><th>Drużyna</th><th>Data zgłoszenia</th></tr>
    <?php foreach ($druzyny as $i => $d): ?>
      <tr><td><?= $i + 1 ?></td><td><?= e($d['nazwa']) ?></td><td><?= e($d['data_zgloszenia']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<p><a href="turnieje.php">&larr; Wróć do listy turniejów</a></p>
<?php require 'includes/footer.php'; ?>
