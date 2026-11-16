<?php
require_once 'includes/auth.php';

$wiersze = db()->query(
    'SELECT d.nazwa, r.punkty, r.wygrane, r.przegrane
     FROM ranking r JOIN druzyna d ON d.id_druzyny = r.id_druzyny
     ORDER BY r.punkty DESC, r.wygrane DESC, d.nazwa ASC
     LIMIT 100'
)->fetchAll();
$medale = ['🥇', '🥈', '🥉'];

$tytul = 'Ranking';
require 'includes/header.php';
?>
<h1>Ranking drużyn</h1>
<?php if (!$wiersze): ?>
  <p class="przygaszony">Ranking jest jeszcze pusty.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Miejsce</th><th>Drużyna</th><th>Punkty</th><th>Wygrane</th><th>Przegrane</th></tr>
    <?php foreach ($wiersze as $i => $w): ?>
      <tr>
        <td><?= $medale[$i] ?? ($i + 1) ?></td><td><?= e($w['nazwa']) ?></td>
        <td><strong><?= (int)$w['punkty'] ?></strong></td><td><?= (int)$w['wygrane'] ?></td><td><?= (int)$w['przegrane'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
