<?php
require_once __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');

$komunikat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $id = (int)($_POST['id'] ?? 0);
    $akcja = $_POST['akcja'] ?? '';
    if ($akcja === 'opublikuj') {
        $st = db()->prepare("UPDATE turniej SET status = 'otwarty' WHERE id_turnieju = ? AND status = 'szkic'");
        $st->execute([$id]);
        $komunikat = $st->rowCount() ? 'Turniej opublikowany.' : 'Nie można opublikować tego turnieju.';
    } elseif ($akcja === 'anuluj') {
        $st = db()->prepare("UPDATE turniej SET status = 'anulowany' WHERE id_turnieju = ? AND status IN ('szkic','otwarty')");
        $st->execute([$id]);
        $komunikat = $st->rowCount() ? 'Turniej anulowany.' : 'Tego turnieju nie można anulować.';
    }
}

$turnieje = db()->query(
    "SELECT t.*, (SELECT COUNT(*) FROM zgloszenie z
                  WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zgloszone
     FROM turniej t ORDER BY t.data_startu DESC"
)->fetchAll();

$tytul = 'Zarządzaj turniejami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj turniejami</h1>
<?php if ($komunikat): ?><p class="ok"><?= e($komunikat) ?></p><?php endif; ?>
<p><a class="przycisk" href="turniej_form.php">+ Dodaj turniej</a></p>

<?php if (!$turnieje): ?>
  <p>Brak turniejów.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Nazwa</th><th>Start</th><th>Status</th><th>Drużyny</th><th>Akcje</th></tr>
  <?php foreach ($turnieje as $t): ?>
    <tr>
      <td><a href="../turniej.php?id=<?= (int)$t['id_turnieju'] ?>"><?= e($t['nazwa']) ?></a></td>
      
      <td><?= e($t['data_startu']) ?></td>
      <td><?= e($t['status']) ?></td>
      <td><?= (int)$t['zgloszone'] ?> / <?= (int)$t['limit_druzyn'] ?></td>
      <td class="akcje">
        <?php if (in_array($t['status'], ['szkic', 'otwarty'], true)): ?>
          <a href="turniej_form.php?id=<?= (int)$t['id_turnieju'] ?>">Edytuj</a>
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id" value="<?= (int)$t['id_turnieju'] ?>">
            <?php if ($t['status'] === 'szkic'): ?>
              <button name="akcja" value="opublikuj">Opublikuj</button>
            <?php endif; ?>
            <button name="akcja" value="anuluj" class="drugi" onclick="return confirm('Anulować turniej?')">Anuluj</button>
          </form>
        <?php else: ?>–<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
