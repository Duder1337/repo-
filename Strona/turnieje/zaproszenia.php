<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
wymagajLogowania();

$z = mojZawodnik();
if (!$z) exit('To konto nie ma profilu zawodnika, więc nie otrzymuje zaproszeń.');
$idZ = (int)$z['id_zawodnika'];
$pdo = db();
$ok = '';
$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $idZap = (int)($_POST['id_zaproszenia'] ?? 0);

    if ($akcja === 'odrzuc') {
        $st = $pdo->prepare("UPDATE zaproszenie SET status = 'odrzucone'
                             WHERE id_zaproszenia = ? AND id_zawodnika = ? AND status = 'oczekujące'");
        $st->execute([$idZap, $idZ]);
        $ok = $st->rowCount() ? 'Zaproszenie zostało odrzucone.' : '';
        if (!$ok) $blad = 'To zaproszenie jest już nieaktualne.';

    } elseif ($akcja === 'akceptuj') {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM zaproszenie
                                 WHERE id_zaproszenia = ? AND id_zawodnika = ? AND status = 'oczekujące' FOR UPDATE");
            $st->execute([$idZap, $idZ]);
            $zap = $st->fetch();
            $problem = '';

            if (!$zap) {
                $problem = 'To zaproszenie jest już nieaktualne.';
            } else {
                $idD = (int)$zap['id_druzyny'];
                // Blokujemy wiersz drużyny, żeby dwie osoby naraz nie zajęły ostatniego miejsca
                $pdo->prepare('SELECT id_druzyny FROM druzyna WHERE id_druzyny = ? FOR UPDATE')->execute([$idD]);

                if (druzynaZawodnika($idZ)) {
                    $problem = 'Należysz już do drużyny. Zawodnik może być tylko w jednej drużynie.';
                } elseif (druzynaZablokowana($idD)) {
                    $problem = 'Ta drużyna uczestniczy w trwających rozgrywkach – nie można do niej dołączyć.';
                } elseif (liczbaWRoli($idD, $zap['rola']) >= LIMITY_ROL[$zap['rola']]) {
                    $problem = 'W drużynie nie ma już wolnych miejsc w tej roli.';
                }
            }

            if ($problem) {
                $pdo->rollBack();
                $blad = $problem;
            } else {
                $pdo->prepare('INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, ?)')
                    ->execute([$idD, $idZ, $zap['rola']]);
                $pdo->prepare("UPDATE zaproszenie SET status = 'zaakceptowane' WHERE id_zaproszenia = ?")->execute([$idZap]);
                // Zawodnik ma już drużynę, więc pozostałe zaproszenia tracą sens
                $pdo->prepare("UPDATE zaproszenie SET status = 'anulowane'
                               WHERE id_zawodnika = ? AND status = 'oczekujące'")->execute([$idZ]);
                $pdo->commit();
                $ok = 'Zaproszenie zaakceptowane – jesteś w drużynie!';
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($ex->getCode() === '23000') $blad = 'Należysz już do drużyny.';
            else throw $ex;
        }
    }
}

$mojaD = druzynaZawodnika($idZ);

$st = $pdo->prepare("SELECT zp.id_zaproszenia, zp.rola, zp.data_wyslania, d.id_druzyny, d.nazwa, u.nick AS zapraszajacy
                     FROM zaproszenie zp
                     JOIN druzyna d ON d.id_druzyny = zp.id_druzyny
                     JOIN uzytkownik u ON u.id_uzytkownika = zp.id_zapraszajacego
                     WHERE zp.id_zawodnika = ? AND zp.status = 'oczekujące'
                     ORDER BY zp.data_wyslania DESC");
$st->execute([$idZ]);
$oczekujace = $st->fetchAll();

$st = $pdo->prepare("SELECT zp.rola, zp.status, zp.data_wyslania, d.nazwa
                     FROM zaproszenie zp JOIN druzyna d ON d.id_druzyny = zp.id_druzyny
                     WHERE zp.id_zawodnika = ? AND zp.status <> 'oczekujące'
                     ORDER BY zp.data_wyslania DESC LIMIT 10");
$st->execute([$idZ]);
$historia = $st->fetchAll();

$tytul = 'Zaproszenia';
require 'includes/header.php';
?>
<h1>Zaproszenia do drużyn</h1>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<?php if ($mojaD): ?>
  <p class="przygaszony">Należysz do drużyny
    <a href="druzyna.php?id=<?= (int)$mojaD['id_druzyny'] ?>"><?= e($mojaD['nazwa']) ?></a>.
    Żeby przyjąć zaproszenie do innej, najpierw musisz opuścić obecną drużynę.</p>
<?php endif; ?>

<?php if (!$oczekujace): ?>
  <p class="przygaszony">Nie masz żadnych oczekujących zaproszeń.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna</th><th>Rola</th><th>Od</th><th>Data</th><th></th></tr>
    <?php foreach ($oczekujace as $zp): ?>
      <tr>
        <td><a href="druzyna.php?id=<?= (int)$zp['id_druzyny'] ?>"><?= e($zp['nazwa']) ?></a></td>
        <td><?= e(etykietaRoli($zp['rola'])) ?></td>
        <td><?= e($zp['zapraszajacy']) ?></td>
        <td><?= e(date('d.m.Y H:i', strtotime($zp['data_wyslania']))) ?></td>
        <td class="akcje">
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id_zaproszenia" value="<?= (int)$zp['id_zaproszenia'] ?>">
            <button name="akcja" value="akceptuj" <?= $mojaD ? 'disabled' : '' ?>>Akceptuj</button>
            <button name="akcja" value="odrzuc" class="drugi">Odrzuć</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

<?php if ($historia): ?>
  <h2>Ostatnie zaproszenia</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna</th><th>Rola</th><th>Status</th><th>Data</th></tr>
    <?php foreach ($historia as $h): ?>
      <tr>
        <td><?= e($h['nazwa']) ?></td>
        <td><?= e(etykietaRoli($h['rola'])) ?></td>
        <td><?= e($h['status']) ?></td>
        <td><?= e(date('d.m.Y H:i', strtotime($h['data_wyslania']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
