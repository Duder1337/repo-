<?php
require_once __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');

$id = (int)($_GET['id'] ?? 0);
$o = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM ogloszenie WHERE id_ogloszenia = ?');
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) { http_response_code(404); exit('Nie znaleziono ogłoszenia.'); }
}

// Powiązanie zapisujemy w jednym polu: "" (brak), "liga" albo "t:ID" (turniej)
$powiazanie = '';
if ($o) {
    if ($o['id_turnieju']) $powiazanie = 't:' . (int)$o['id_turnieju'];
    elseif ($o['id_ligi']) $powiazanie = 'liga';
}
$dane = ['tytul' => $o['tytul'] ?? '', 'tresc' => $o['tresc'] ?? '', 'powiazanie' => $powiazanie];
$bledy = [];

$liga = db()->query('SELECT id_ligi, nazwa FROM liga ORDER BY id_ligi DESC LIMIT 1')->fetch();
$turnieje = db()->query("SELECT id_turnieju, nazwa FROM turniej WHERE status <> 'szkic' ORDER BY data_startu DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $dane['tytul']      = trim($_POST['tytul'] ?? '');
    $dane['tresc']      = trim($_POST['tresc'] ?? '');
    $dane['powiazanie'] = $_POST['powiazanie'] ?? '';

    if (mb_strlen($dane['tytul']) < 3 || mb_strlen($dane['tytul']) > 150) $bledy[] = 'Tytuł musi mieć od 3 do 150 znaków.';
    if (mb_strlen($dane['tresc']) < 5 || mb_strlen($dane['tresc']) > 5000) $bledy[] = 'Treść musi mieć od 5 do 5000 znaków.';

    $idTurnieju = null;
    $idLigi = null;
    if ($dane['powiazanie'] === 'liga' && $liga) {
        $idLigi = (int)$liga['id_ligi'];
    } elseif (preg_match('/^t:(\d+)$/', $dane['powiazanie'], $m)) {
        $idTurnieju = (int)$m[1];
        $st = db()->prepare("SELECT 1 FROM turniej WHERE id_turnieju = ? AND status <> 'szkic'");
        $st->execute([$idTurnieju]);
        if (!$st->fetchColumn()) $bledy[] = 'Wybrany turniej nie istnieje.';
    } elseif ($dane['powiazanie'] !== '') {
        $bledy[] = 'Nieprawidłowe powiązanie.';
    }

    if (!$bledy) {
        if ($o) {
            $st = db()->prepare('UPDATE ogloszenie SET tytul=?, tresc=?, id_turnieju=?, id_ligi=? WHERE id_ogloszenia=?');
            $st->execute([$dane['tytul'], $dane['tresc'], $idTurnieju, $idLigi, $id]);
        } else {
            $st = db()->prepare('INSERT INTO ogloszenie (tytul, tresc, id_autora, id_turnieju, id_ligi) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$dane['tytul'], $dane['tresc'], uzytkownik()['id'], $idTurnieju, $idLigi]);
        }
        header('Location: ogloszenia.php');
        exit;
    }
}

$tytul = $o ? 'Edytuj ogłoszenie' : 'Dodaj ogłoszenie';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($tytul) ?></h1>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Tytuł <input type="text" name="tytul" value="<?= e($dane['tytul']) ?>" maxlength="150" required></label>
  <label>Treść <textarea name="tresc" maxlength="5000" required><?= e($dane['tresc']) ?></textarea></label>
  <label>Dotyczy (opcjonalnie)
    <select name="powiazanie">
      <option value="">– ogłoszenie ogólne –</option>
      <?php if ($liga): ?>
        <option value="liga" <?= $dane['powiazanie'] === 'liga' ? 'selected' : '' ?>>Liga: <?= e($liga['nazwa']) ?></option>
      <?php endif; ?>
      <?php foreach ($turnieje as $t): ?>
        <option value="t:<?= (int)$t['id_turnieju'] ?>" <?= $dane['powiazanie'] === 't:' . (int)$t['id_turnieju'] ? 'selected' : '' ?>>
          Turniej: <?= e($t['nazwa']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="przyciski-rzad">
    <button type="submit">Zapisz</button>
    <a href="ogloszenia.php">Anuluj</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
