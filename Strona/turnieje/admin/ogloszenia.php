<?php
require_once __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');

$ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['akcja'] ?? '') === 'usun' && $id) {
        $st = db()->prepare('DELETE FROM ogloszenie WHERE id_ogloszenia = ?');
        $st->execute([$id]);
        $ok = $st->rowCount() ? 'Ogłoszenie zostało usunięte.' : 'Nie znaleziono ogłoszenia.';
    }
}

$ogloszenia = db()->query(
    "SELECT o.id_ogloszenia, o.tytul, o.data_publikacji, u.nick AS autor, t.nazwa AS turniej, l.nazwa AS liga
     FROM ogloszenie o
     JOIN uzytkownik u ON u.id_uzytkownika = o.id_autora
     LEFT JOIN turniej t ON t.id_turnieju = o.id_turnieju
     LEFT JOIN liga l ON l.id_ligi = o.id_ligi
     ORDER BY o.data_publikacji DESC, o.id_ogloszenia DESC"
)->fetchAll();

$tytul = 'Zarządzaj ogłoszeniami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj ogłoszeniami</h1>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<p><a class="przycisk" href="ogloszenie_form.php">+ Dodaj ogłoszenie</a></p>

<?php if (!$ogloszenia): ?>
  <p class="przygaszony">Brak ogłoszeń.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Tytuł</th><th>Data</th><th>Autor</th><th>Dotyczy</th><th>Akcje</th></tr>
  <?php foreach ($ogloszenia as $o): ?>
    <tr>
      <td><?= e($o['tytul']) ?></td>
      <td><?= e(date('d.m.Y H:i', strtotime($o['data_publikacji']))) ?></td>
      <td><?= e($o['autor']) ?></td>
      <td><?= e($o['turniej'] ?? $o['liga'] ?? '–') ?></td>
      <td class="akcje">
        <a href="ogloszenie_form.php?id=<?= (int)$o['id_ogloszenia'] ?>">Edytuj</a>
        <form method="post" class="inline">
          <?= csrfPole() ?>
          <input type="hidden" name="id" value="<?= (int)$o['id_ogloszenia'] ?>">
          <button name="akcja" value="usun" class="drugi" onclick="return confirm('Usunąć ogłoszenie?')">Usuń</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<p><a href="index.php">&larr; Wróć do panelu</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
