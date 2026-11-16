<?php
require_once 'includes/auth.php';

$statusy = ['otwarty', 'w trakcie', 'zakończony', 'anulowany'];
$status  = $_GET['status'] ?? '';
$sort    = (($_GET['sort'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$strona  = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 12;

// Publicznie widać tylko turnieje opublikowane (status inny niż szkic)
$where = ["t.status <> 'szkic'"];
$param = [];
if (in_array($status, $statusy, true)) { $where[] = 't.status = ?'; $param[] = $status; }
$whereSql = implode(' AND ', $where);

$st = db()->prepare("SELECT COUNT(*) FROM turniej t WHERE $whereSql");
$st->execute($param);
$razem  = (int)$st->fetchColumn();
$stron  = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$st = db()->prepare(
    "SELECT t.*, (SELECT COUNT(*) FROM zgloszenie z
                  WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zgloszone
     FROM turniej t WHERE $whereSql
     ORDER BY t.data_startu $sort
     LIMIT $naStrone OFFSET $offset"
);
$st->execute($param);
$turnieje = $st->fetchAll();

function linkStrony(int $nr): string {
    return '?' . http_build_query(array_merge($_GET, ['strona' => $nr]));
}

$tytul = 'Turnieje';
require 'includes/header.php';
?>
<h1>Turnieje CS2</h1>

<form method="get" class="filtry">
  <label>Status
    <select name="status">
      <option value="">Wszystkie</option>
      <?php foreach ($statusy as $s): ?>
        <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e(etykietaStatusu($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Sortuj po dacie startu
    <select name="sort">
      <option value="asc" <?= $sort === 'ASC' ? 'selected' : '' ?>>od najbliższej</option>
      <option value="desc" <?= $sort === 'DESC' ? 'selected' : '' ?>>od najdalszej</option>
    </select>
  </label>
  <button type="submit">Filtruj</button>
</form>

<?php if (!$turnieje): ?>
  <p class="przygaszony">Brak turniejów spełniających kryteria.</p>
<?php else: ?>
  <div class="siatka-kart">
    <?php foreach ($turnieje as $t) kartaTurnieju($t); ?>
  </div>

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
