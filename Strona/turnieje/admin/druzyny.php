<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/druzyna_funkcje.php';
require_once __DIR__ . '/../includes/dane_testowe_funkcje.php';
wymagajRoli('administrator');
zapewnijKolumnyTestowe();

$pdo = db();
$ok = '';
$blad = '';
$nazwaNowa = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';

    if ($akcja === 'usun') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $blad = usunDruzyne($id);
            if ($blad === '') $ok = 'Drużyna została usunięta.';
        }

    } elseif ($akcja === 'dodaj') {
        $nazwaNowa = trim($_POST['nazwa'] ?? '');
        $idK = (int)($_POST['id_kapitana'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM zawodnik WHERE id_zawodnika = ?');
        $st->execute([$idK]);
        $w = $st->fetch();

        if (mb_strlen($nazwaNowa) < 3 || mb_strlen($nazwaNowa) > 30) {
            $blad = 'Nazwa drużyny musi mieć od 3 do 30 znaków.';
        } elseif (!$w) {
            $blad = 'Wybierz kapitana drużyny.';
        } elseif (druzynaZawodnika($idK)) {
            $blad = 'Ten zawodnik należy już do drużyny.';
        } else {
            // Zawodnik bez konta (dane testowe): właścicielem zostaje administrator
            $wlasciciel = $w['id_uzytkownika'] !== null ? (int)$w['id_uzytkownika'] : uzytkownik()['id'];
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO druzyna (nazwa, id_wlasciciela) VALUES (?, ?)')->execute([$nazwaNowa, $wlasciciel]);
                $nowe = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, 'kapitan')")->execute([$nowe, $idK]);
                $pdo->prepare('INSERT INTO ranking (id_druzyny) VALUES (?)')->execute([$nowe]);
                $pdo->commit();
                $ok = 'Drużyna „' . $nazwaNowa . '” została utworzona. Resztę składu dodasz przez zaproszenia.';
                $nazwaNowa = '';
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') $blad = 'Drużyna o takiej nazwie już istnieje (lub zawodnik jest już w innej drużynie).';
                else throw $ex;
            }
        }
    }
}

$druzyny = $pdo->query(
    "SELECT d.id_druzyny, d.nazwa, d.testowy, u.nick AS wlasciciel,
            (SELECT z.nick FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
              WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan' LIMIT 1) AS kapitan,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny) AS liczba,
            (SELECT COUNT(*) FROM zgloszenie g WHERE g.id_druzyny = d.id_druzyny AND g.status = 'przyjęte') AS turnieje
     FROM druzyna d JOIN uzytkownik u ON u.id_uzytkownika = d.id_wlasciciela
     ORDER BY d.nazwa"
)->fetchAll();

$wolni = $pdo->query(
    'SELECT w.id_zawodnika, w.nick, w.id_uzytkownika FROM zawodnik w
     WHERE NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = w.id_zawodnika)
     ORDER BY w.nick'
)->fetchAll();

$tytul = 'Zarządzaj drużynami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj drużynami</h1>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<h2>Dodaj drużynę</h2>
<?php if (!$wolni): ?>
  <p class="przygaszony">Brak zawodników bez drużyny, którzy mogliby zostać kapitanem.</p>
<?php else: ?>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Nazwa drużyny
    <input type="text" name="nazwa" value="<?= e($nazwaNowa) ?>" minlength="3" maxlength="30" required>
  </label>
  <label>Kapitan
    <select name="id_kapitana" required>
      <?php foreach ($wolni as $w): ?>
        <option value="<?= (int)$w['id_zawodnika'] ?>"><?= e($w['nick']) ?><?= $w['id_uzytkownika'] === null ? ' (bez konta)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="przyciski-rzad"><button name="akcja" value="dodaj">Utwórz drużynę</button></div>
  <small class="przygaszony">Rezerwowi i trener nie są wymagani. Do rozgrywek potrzebna jest pełna piątka (kapitan + 4 graczy).</small>
</form>
<?php endif; ?>

<h2>Wszystkie drużyny</h2>
<?php if (!$druzyny): ?>
  <p class="przygaszony">Brak zarejestrowanych drużyn.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Drużyna</th><th>Właściciel</th><th>Kapitan</th><th>Skład</th><th>Turnieje</th><th>Akcje</th></tr>
  <?php foreach ($druzyny as $d): ?>
    <tr>
      <td><a href="../druzyna.php?id=<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></a><?= $d['testowy'] ? ' <small class="przygaszony" title="Drużyna testowa">🧪</small>' : '' ?></td>
      <td><?= e($d['wlasciciel']) ?></td>
      <td><?= e($d['kapitan'] ?? '–') ?></td>
      <td><?= (int)$d['liczba'] ?> os.</td>
      <td><?= (int)$d['turnieje'] ?></td>
      <td class="akcje">
        <a href="../druzyna_form.php?id=<?= (int)$d['id_druzyny'] ?>">Zarządzaj</a>
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
