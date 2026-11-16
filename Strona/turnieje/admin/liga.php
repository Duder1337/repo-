<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/liga_funkcje.php';
require_once __DIR__ . '/../includes/druzyna_funkcje.php';
require_once __DIR__ . '/../includes/zawodnik_funkcje.php';
wymagajRoli('administrator');

$liga = pobierzLige();
if (!$liga) { exit('Brak ligi w bazie. Uruchom skrypt liga.sql.'); }
$idLigi = (int)$liga['id_ligi'];
$ok = '';
$blad = '';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';

    if ($akcja === 'start') {
        $ids = array_map('intval', array_column(druzynyLigi($idLigi), 'id_druzyny'));
        $niekompletne = [];
        foreach (druzynyLigi($idLigi) as $dr) {
            if (!skladKompletny((int)$dr['id_druzyny'])) $niekompletne[] = $dr['nazwa'];
        }
        if ($liga['status'] !== 'zapisy') {
            $blad = 'Liga już wystartowała.';
        } elseif (count($ids) < LIGA_MIN_DRUZYN) {
            $blad = 'Do startu potrzeba co najmniej ' . LIGA_MIN_DRUZYN . ' drużyn (zgłoszono ' . count($ids) . ').';
        } elseif ($niekompletne) {
            $blad = 'Drużyny bez pełnej piątki (kapitan + 4 graczy): ' . implode(', ', $niekompletne) . '. Usuń ich zgłoszenia lub poczekaj na uzupełnienie składów.';
        } elseif (count($ids) > (int)$liga['limit_druzyn']) {
            $blad = 'Zgłoszono więcej drużyn niż wynosi limit.';
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare("UPDATE liga SET status = 'w trakcie' WHERE id_ligi = ? AND status = 'zapisy'");
                $st->execute([$idLigi]);
                if (!$st->rowCount()) throw new RuntimeException('status');
                $ins = $pdo->prepare('INSERT INTO liga_mecz (id_ligi, id_druzyny_a, id_druzyny_b) VALUES (?, ?, ?)');
                foreach (losujTerminarz($ids) as $para) $ins->execute([$idLigi, $para[0], $para[1]]);
                $pdo->commit();
                $ok = 'Liga wystartowała. Wylosowano ' . (count($ids) * LIGA_MECZE_NA_DRUZYNE / 2) . ' meczów.';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $blad = 'Nie udało się wystartować ligi.';
            }
        }
    } elseif ($akcja === 'usun_zgloszenie') {
        if ($liga['status'] !== 'zapisy') {
            $blad = 'Zgłoszenia można usuwać tylko w czasie zapisów.';
        } else {
            $pdo->prepare('DELETE FROM liga_zgloszenie WHERE id_ligi = ? AND id_druzyny = ?')
                ->execute([$idLigi, (int)($_POST['id_druzyny'] ?? 0)]);
            $ok = 'Zgłoszenie zostało usunięte.';
        }
    } elseif ($akcja === 'wynik') {
        $idM = (int)($_POST['id_meczu'] ?? 0);
        $wa  = (string)($_POST['wynik_a'] ?? '');
        $wb  = (string)($_POST['wynik_b'] ?? '');
        $st = $pdo->prepare('SELECT * FROM liga_mecz WHERE id_meczu = ? AND id_ligi = ?');
        $st->execute([$idM, $idLigi]);
        $m = $st->fetch();
        if ($liga['status'] !== 'w trakcie') {
            $blad = 'Wyniki można wpisywać tylko, gdy liga jest w trakcie.';
        } elseif (!$m) {
            $blad = 'Nie znaleziono meczu.';
        } elseif (!ctype_digit($wa) || !ctype_digit($wb) || strlen($wa) > 2 || strlen($wb) > 2) {
            $blad = 'Wynik musi składać się z liczb całkowitych (0–99).';
        } elseif ((int)$wa === (int)$wb) {
            $blad = 'Remisy nie są możliwe (BO1) – podaj różne wyniki.';
        } else {
            $zw = ((int)$wa > (int)$wb) ? $m['id_druzyny_a'] : $m['id_druzyny_b'];
            $pdo->prepare("UPDATE liga_mecz SET wynik_a = ?, wynik_b = ?, id_zwyciezcy = ?, status = 'rozegrany' WHERE id_meczu = ?")
                ->execute([(int)$wa, (int)$wb, $zw, $idM]);
            zapiszUdzialLigi($idM); // zapamiętujemy, którzy zawodnicy grali (statystyki w profilach)
            $ok = 'Wynik meczu zapisany.';
        }
    } elseif ($akcja === 'zakoncz') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM liga_mecz WHERE id_ligi = ? AND status <> 'rozegrany'");
        $st->execute([$idLigi]);
        if ($liga['status'] !== 'w trakcie') {
            $blad = 'Liga nie jest w trakcie.';
        } elseif ((int)$st->fetchColumn() > 0) {
            $blad = 'Nie wszystkie mecze zostały rozegrane.';
        } else {
            $pdo->prepare("UPDATE liga SET status = 'zakończona' WHERE id_ligi = ?")->execute([$idLigi]);
            $ok = 'Liga została zakończona.';
        }
    }
    $liga = pobierzLige(); // odśwież status po akcji
}

$druzyny = druzynyLigi($idLigi);
$mecze = ($liga['status'] === 'zapisy') ? [] : meczeLigi($idLigi);
$rozegrane = count(array_filter($mecze, fn($m) => $m['status'] === 'rozegrany'));

$tytul = 'Zarządzaj ligą';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($liga['nazwa']) ?></h1>
<p class="przygaszony">Status: <strong><?= e(etykietaStatusuLigi($liga['status'])) ?></strong> ·
  drużyny: <?= count($druzyny) ?> / <?= (int)$liga['limit_druzyn'] ?> ·
  <a href="../liga.php">zobacz stronę ligi</a></p>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<?php if ($liga['status'] === 'zapisy'): ?>
  <h2>Zgłoszone drużyny</h2>
  <?php if (!$druzyny): ?>
    <p class="przygaszony">Brak zgłoszonych drużyn.</p>
  <?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>#</th><th>Drużyna</th><th>Akcje</th></tr>
    <?php foreach ($druzyny as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= e($d['nazwa']) ?></td>
        <td class="akcje">
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id_druzyny" value="<?= (int)$d['id_druzyny'] ?>">
            <button name="akcja" value="usun_zgloszenie" class="drugi" onclick="return confirm('Usunąć zgłoszenie?')">Usuń zgłoszenie</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>

  <form method="post" style="margin-top:18px">
    <?= csrfPole() ?>
    <button name="akcja" value="start" <?= count($druzyny) < LIGA_MIN_DRUZYN ? 'disabled' : '' ?>
            onclick="return confirm('Wystartować ligę? Po starcie nie można zmienić zgłoszeń ani terminarza.')">Wystartuj ligę i wylosuj mecze</button>
    <?php if (count($druzyny) < LIGA_MIN_DRUZYN): ?>
      <span class="przygaszony">&nbsp;Potrzeba min. <?= LIGA_MIN_DRUZYN ?> drużyn.</span>
    <?php endif; ?>
  </form>

<?php else: ?>
  <h2>Wyniki meczów (<?= $rozegrane ?> / <?= count($mecze) ?> rozegranych)</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna A</th><th>Drużyna B</th><th>Wynik</th><th>Akcja</th></tr>
    <?php foreach ($mecze as $m): $roz = ($m['status'] === 'rozegrany'); ?>
      <tr>
        <td><?= e($m['nazwa_a']) ?></td>
        <td><?= e($m['nazwa_b']) ?></td>
        <?php if ($liga['status'] === 'w trakcie'): ?>
          <td colspan="2">
            <form method="post" class="inline">
              <?= csrfPole() ?>
              <input type="hidden" name="id_meczu" value="<?= (int)$m['id_meczu'] ?>">
              <input class="wynik-in" type="number" name="wynik_a" min="0" max="99" required value="<?= $roz ? (int)$m['wynik_a'] : '' ?>">
              :
              <input class="wynik-in" type="number" name="wynik_b" min="0" max="99" required value="<?= $roz ? (int)$m['wynik_b'] : '' ?>">
              <button name="akcja" value="wynik"><?= $roz ? 'Popraw' : 'Zapisz' ?></button>
            </form>
          </td>
        <?php else: ?>
          <td class="mecz-wynik"><?= (int)$m['wynik_a'] ?> : <?= (int)$m['wynik_b'] ?></td><td>–</td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>

  <?php if ($liga['status'] === 'w trakcie'): ?>
    <form method="post" style="margin-top:18px">
      <?= csrfPole() ?>
      <button name="akcja" value="zakoncz" <?= $rozegrane < count($mecze) ? 'disabled' : '' ?>
              onclick="return confirm('Zakończyć ligę?')">Zakończ ligę</button>
      <?php if ($rozegrane < count($mecze)): ?>
        <span class="przygaszony">&nbsp;Dostępne po rozegraniu wszystkich meczów.</span>
      <?php endif; ?>
    </form>
  <?php endif; ?>
<?php endif; ?>
<p><a href="index.php">&larr; Wróć do panelu</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
