<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/druzyna_funkcje.php';
wymagajRoli('administrator');

$pdo = db();
$ok = '';
$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $idZap = (int)($_POST['id_zaproszenia'] ?? 0);

    if ($akcja === 'wyslij') {
        $idD  = (int)($_POST['id_druzyny'] ?? 0);
        $idZ  = (int)($_POST['id_zawodnika'] ?? 0);
        $rola = $_POST['rola'] ?? '';

        $st = $pdo->prepare('SELECT 1 FROM druzyna WHERE id_druzyny = ?');
        $st->execute([$idD]);
        $druzynaIstnieje = (bool)$st->fetchColumn();
        $st = $pdo->prepare('SELECT nick FROM zawodnik WHERE id_zawodnika = ?');
        $st->execute([$idZ]);
        $nick = $st->fetchColumn();

        if (!in_array($rola, ['gracz', 'rezerwowy', 'trener'], true)) {
            $blad = 'Wybierz rolę zawodnika.';
        } elseif (!$druzynaIstnieje) {
            $blad = 'Wybierz drużynę.';
        } elseif ($nick === false) {
            $blad = 'Wybierz zawodnika.';
        } elseif (druzynaZawodnika($idZ)) {
            $blad = 'Zawodnik ' . $nick . ' należy już do drużyny.';
        } elseif (druzynaZablokowana($idD)) {
            $blad = 'Drużyna uczestniczy w trwających rozgrywkach – skład jest zablokowany.';
        } else {
            $st = $pdo->prepare("SELECT 1 FROM zaproszenie WHERE id_druzyny = ? AND id_zawodnika = ? AND status = 'oczekujące'");
            $st->execute([$idD, $idZ]);
            if ($st->fetchColumn()) {
                $blad = 'Zawodnik ' . $nick . ' ma już oczekujące zaproszenie od tej drużyny.';
            } elseif (liczbaWRoli($idD, $rola, true) >= LIMITY_ROL[$rola]) {
                $blad = 'Brak wolnych miejsc w roli: ' . etykietaRoli($rola) . ' (wliczając wysłane zaproszenia).';
            } else {
                $pdo->prepare('INSERT INTO zaproszenie (id_druzyny, id_zawodnika, rola, id_zapraszajacego) VALUES (?, ?, ?, ?)')
                    ->execute([$idD, $idZ, $rola, uzytkownik()['id']]);
                $ok = 'Zaproszenie zostało wysłane do zawodnika ' . $nick . '.';
            }
        }

    } elseif ($akcja === 'akceptuj') {
        $blad = akceptujZaproszenie($idZap);
        if ($blad === '') $ok = 'Zaproszenie zaakceptowane – zawodnik został dodany do drużyny.';

    } elseif ($akcja === 'odrzuc' || $akcja === 'anuluj') {
        $nowy = ($akcja === 'odrzuc') ? 'odrzucone' : 'anulowane';
        $st = $pdo->prepare("UPDATE zaproszenie SET status = ? WHERE id_zaproszenia = ? AND status = 'oczekujące'");
        $st->execute([$nowy, $idZap]);
        if ($st->rowCount()) $ok = $akcja === 'odrzuc' ? 'Zaproszenie zostało odrzucone.' : 'Zaproszenie zostało anulowane.';
        else $blad = 'To zaproszenie jest już nieaktualne.';
    }
}

$oczekujace = $pdo->query(
    "SELECT zp.id_zaproszenia, zp.rola, zp.data_wyslania, d.nazwa AS druzyna, w.nick, w.id_uzytkownika AS konto,
            u.nick AS zapraszajacy
     FROM zaproszenie zp
     JOIN druzyna d ON d.id_druzyny = zp.id_druzyny
     JOIN zawodnik w ON w.id_zawodnika = zp.id_zawodnika
     JOIN uzytkownik u ON u.id_uzytkownika = zp.id_zapraszajacego
     WHERE zp.status = 'oczekujące'
     ORDER BY zp.data_wyslania DESC"
)->fetchAll();

$historia = $pdo->query(
    "SELECT zp.rola, zp.status, zp.data_wyslania, d.nazwa AS druzyna, w.nick
     FROM zaproszenie zp
     JOIN druzyna d ON d.id_druzyny = zp.id_druzyny
     JOIN zawodnik w ON w.id_zawodnika = zp.id_zawodnika
     WHERE zp.status <> 'oczekujące'
     ORDER BY zp.data_wyslania DESC LIMIT 20"
)->fetchAll();

$druzyny = $pdo->query('SELECT id_druzyny, nazwa FROM druzyna ORDER BY nazwa')->fetchAll();
$wolni = $pdo->query(
    'SELECT w.id_zawodnika, w.nick, w.id_uzytkownika FROM zawodnik w
     WHERE NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = w.id_zawodnika)
     ORDER BY w.nick'
)->fetchAll();

$tytul = 'Zarządzaj zaproszeniami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj zaproszeniami</h1>
<p class="przygaszony">Panel testowy: możesz wysyłać zaproszenia w imieniu drużyn oraz akceptować je za zawodników
  (także tych bez konta). Obowiązują te same limity składu i blokady co dla zwykłych użytkowników.</p>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<h2>Wyślij zaproszenie</h2>
<?php if (!$druzyny || !$wolni): ?>
  <p class="przygaszony">Potrzebna jest co najmniej jedna drużyna i jeden zawodnik bez drużyny.</p>
<?php else: ?>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Drużyna
    <select name="id_druzyny" required>
      <?php foreach ($druzyny as $d): ?>
        <option value="<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Zawodnik (bez drużyny)
    <select name="id_zawodnika" required>
      <?php foreach ($wolni as $w): ?>
        <option value="<?= (int)$w['id_zawodnika'] ?>"><?= e($w['nick']) ?><?= $w['id_uzytkownika'] === null ? ' (bez konta)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Rola
    <select name="rola">
      <?php foreach (['gracz', 'rezerwowy', 'trener'] as $r): ?>
        <option value="<?= $r ?>"><?= e(etykietaRoli($r)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="przyciski-rzad"><button name="akcja" value="wyslij">Wyślij zaproszenie</button></div>
</form>
<?php endif; ?>

<h2>Oczekujące zaproszenia</h2>
<?php if (!$oczekujace): ?>
  <p class="przygaszony">Brak oczekujących zaproszeń.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Drużyna</th><th>Zawodnik</th><th>Rola</th><th>Od</th><th>Data</th><th>Akcje</th></tr>
  <?php foreach ($oczekujace as $zp): ?>
    <tr>
      <td><?= e($zp['druzyna']) ?></td>
      <td><?= e($zp['nick']) ?><?= $zp['konto'] === null ? ' <span class="przygaszony">(bez konta)</span>' : '' ?></td>
      <td><?= e(etykietaRoli($zp['rola'])) ?></td>
      <td><?= e($zp['zapraszajacy']) ?></td>
      <td><?= e(date('d.m.Y H:i', strtotime($zp['data_wyslania']))) ?></td>
      <td class="akcje">
        <form method="post" class="inline">
          <?= csrfPole() ?>
          <input type="hidden" name="id_zaproszenia" value="<?= (int)$zp['id_zaproszenia'] ?>">
          <button name="akcja" value="akceptuj">Akceptuj</button>
          <button name="akcja" value="odrzuc" class="drugi">Odrzuć</button>
          <button name="akcja" value="anuluj" class="drugi" onclick="return confirm('Anulować zaproszenie?')">Anuluj</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>

<?php if ($historia): ?>
  <h2>Ostatnie zaproszenia</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna</th><th>Zawodnik</th><th>Rola</th><th>Status</th><th>Data</th></tr>
    <?php foreach ($historia as $h): ?>
      <tr>
        <td><?= e($h['druzyna']) ?></td>
        <td><?= e($h['nick']) ?></td>
        <td><?= e(etykietaRoli($h['rola'])) ?></td>
        <td><?= e($h['status']) ?></td>
        <td><?= e(date('d.m.Y H:i', strtotime($h['data_wyslania']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<p><a href="index.php">&larr; Wróć do panelu</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
