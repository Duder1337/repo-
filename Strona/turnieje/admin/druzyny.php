<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/druzyna_funkcje.php';
wymagajRoli('administrator');

$ok = '';
$blad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['akcja'] ?? '') === 'usun' && $id) {
        $st = db()->prepare("SELECT 1 FROM mecz WHERE (id_druzyny_a = ? OR id_druzyny_b = ?) AND status = 'rozegrany' LIMIT 1");
        $st->execute([$id, $id]);
        if ($st->fetchColumn()) {
            $blad = 'Nie można usunąć drużyny z rozegranymi meczami.';
        } elseif (druzynaZablokowana($id)) {
            $blad = 'Nie można usunąć drużyny uczestniczącej w trwającym turnieju.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM zgloszenie WHERE id_druzyny = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM druzyna WHERE id_druzyny = ?')->execute([$id]);
                $pdo->commit();
                $ok = 'Drużyna została usunięta.';
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $blad = 'Nie udało się usunąć drużyny.';
            }
        }
    }
}

$druzyny = db()->query(
    "SELECT d.id_druzyny, d.nazwa, u.nick AS wlasciciel,
            (SELECT z.nick FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
              WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan' LIMIT 1) AS kapitan,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny) AS liczba,
            (SELECT COUNT(*) FROM zgloszenie g WHERE g.id_druzyny = d.id_druzyny AND g.status = 'przyjęte') AS turnieje
     FROM druzyna d JOIN uzytkownik u ON u.id_uzytkownika = d.id_wlasciciela
     ORDER BY d.nazwa"
)->fetchAll();

$tytul = 'Zarządzaj drużynami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj drużynami</h1>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<?php if (!$druzyny): ?>
  <p class="przygaszony">Brak zarejestrowanych drużyn.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Drużyna</th><th>Właściciel</th><th>Kapitan</th><th>Skład</th><th>Turnieje</th><th>Akcje</th></tr>
  <?php foreach ($druzyny as $d): ?>
    <tr>
      <td><a href="../druzyna.php?id=<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></a></td>
      <td><?= e($d['wlasciciel']) ?></td>
      <td><?= e($d['kapitan'] ?? '–') ?></td>
      <td><?= (int)$d['liczba'] ?> os.</td>
      <td><?= (int)$d['turnieje'] ?></td>
      <td class="akcje">
        <a href="../druzyna_form.php?id=<?= (int)$d['id_druzyny'] ?>">Edytuj skład</a>
        <form method="post" class="inline">
          <?= csrfPole() ?>
          <input type="hidden" name="id" value="<?= (int)$d['id_druzyny'] ?>">
          <button name="akcja" value="usun" class="drugi" onclick="return confirm('Usunąć drużynę?')">Usuń</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<p><a href="index.php">&larr; Wróć do panelu</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
