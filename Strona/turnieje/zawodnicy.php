<?php
require_once 'includes/auth.php';
require_once 'includes/zawodnik_funkcje.php';

$q       = trim($_GET['q'] ?? '');
$strona  = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 20;

$where = '';
$param = [];
if ($q !== '') {
    $where = 'WHERE w.nick LIKE ?';
    $param[] = '%' . addcslashes($q, '%_\\') . '%';
}

$st = db()->prepare("SELECT COUNT(*) FROM zawodnik w $where");
$st->execute($param);
$razem  = (int)$st->fetchColumn();
$stron  = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$st = db()->prepare(
    "SELECT w.id_zawodnika, w.nick, w.imie, w.nazwisko, w.zdjecie, d.id_druzyny, d.nazwa AS druzyna
     FROM zawodnik w
     LEFT JOIN sklad s ON s.id_zawodnika = w.id_zawodnika
     LEFT JOIN druzyna d ON d.id_druzyny = s.id_druzyny
     $where ORDER BY w.nick LIMIT $naStrone OFFSET $offset"
);
$st->execute($param);
$zawodnicy = $st->fetchAll();

function linkStrony(int $nr): string {
    return '?' . http_build_query(array_merge($_GET, ['strona' => $nr]));
}

$tytul = 'Zawodnicy';
require 'includes/header.php';
?>
<h1>Zawodnicy</h1>
<form method="get" class="filtry">
  <label>Szukaj po nicku <input type="text" name="q" value="<?= e($q) ?>" class="pole"></label>
  <button type="submit">Szukaj</button>
</form>

<?php if (!$zawodnicy): ?>
  <p class="przygaszony">Brak zawodników spełniających kryteria.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Nick</th><th>Drużyna</th><?php if (zalogowany()): ?><th>Imię</th><th>Nazwisko</th><?php endif; ?></tr>
    <?php foreach ($zawodnicy as $z): ?>
      <tr>
        <td><?= awatarHtml($z) ?> <a href="zawodnik.php?id=<?= (int)$z['id_zawodnika'] ?>"><?= e($z['nick']) ?></a></td>
        <td>
          <?php if ($z['druzyna']): ?><a href="druzyna.php?id=<?= (int)$z['id_druzyny'] ?>"><?= e($z['druzyna']) ?></a>
          <?php else: ?><span class="przygaszony">brak</span><?php endif; ?>
        </td>
        <?php if (zalogowany()): ?><td><?= e($z['imie']) ?></td><td><?= e($z['nazwisko']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (!zalogowany()): ?><p class="przygaszony">Imiona i nazwiska są widoczne po zalogowaniu.</p><?php endif; ?>

  <?php if ($stron > 1): ?>
  <p class="strony">
    <?php for ($i = 1; $i <= $stron; $i++): ?>
      <?php if ($i === $strona): ?><strong><?= $i ?></strong>
      <?php else: ?><a href="<?= e(linkStrony($i)) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
