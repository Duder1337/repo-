<?php
require_once 'includes/auth.php';

$strona   = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 10;
$razem  = (int)db()->query('SELECT COUNT(*) FROM ogloszenie')->fetchColumn();
$stron  = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$ogloszenia = db()->query(
    "SELECT o.*, u.nick AS autor, t.nazwa AS turniej, l.nazwa AS liga
     FROM ogloszenie o
     JOIN uzytkownik u ON u.id_uzytkownika = o.id_autora
     LEFT JOIN turniej t ON t.id_turnieju = o.id_turnieju AND t.status <> 'szkic'
     LEFT JOIN liga l ON l.id_ligi = o.id_ligi
     ORDER BY o.data_publikacji DESC, o.id_ogloszenia DESC
     LIMIT $naStrone OFFSET $offset"
)->fetchAll();

$tytul = 'Ogłoszenia';
require 'includes/header.php';
?>
<h1>Ogłoszenia</h1>
<p class="przygaszony">Ważne informacje o platformie, ligach i turniejach.</p>

<?php if (!$ogloszenia): ?>
  <p class="przygaszony">Nie ma jeszcze żadnych ogłoszeń.</p>
<?php else: ?>
  <?php foreach ($ogloszenia as $o): ?>
    <article class="ogloszenie">
      <h3><?= e($o['tytul']) ?></h3>
      <div class="ogloszenie-meta">
        <span><?= e(date('d.m.Y H:i', strtotime($o['data_publikacji']))) ?></span>
        <span>· <?= e($o['autor']) ?></span>
        <?php if ($o['turniej']): ?>
          <a class="powiazanie" href="<?= BASE_URL ?>/turniej.php?id=<?= (int)$o['id_turnieju'] ?>">🏆 <?= e($o['turniej']) ?></a>
        <?php elseif ($o['liga']): ?>
          <a class="powiazanie" href="<?= BASE_URL ?>/liga.php">🥇 <?= e($o['liga']) ?></a>
        <?php endif; ?>
      </div>
      <div class="ogloszenie-tresc"><?= nl2br(e($o['tresc'])) ?></div>
    </article>
  <?php endforeach; ?>

  <?php if ($stron > 1): ?>
  <p class="strony">
    <?php for ($i = 1; $i <= $stron; $i++): ?>
      <?php if ($i === $strona): ?><strong><?= $i ?></strong>
      <?php else: ?><a href="?strona=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
