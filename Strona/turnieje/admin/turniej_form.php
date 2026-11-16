<?php
require_once __DIR__ . '/../includes/auth.php';
wymagajRoli('administrator');

$id = (int)($_GET['id'] ?? 0);
$t = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM turniej WHERE id_turnieju = ?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) { http_response_code(404); exit('Nie znaleziono turnieju.'); }
    if (!in_array($t['status'], ['szkic', 'otwarty'], true)) {
        exit('Ten turniej można edytować tylko do czasu startu.');
    }
}

$dane = [
    'nazwa'        => $t['nazwa'] ?? '',
    'format_meczu' => $t['format_meczu'] ?? 'BO1',
    'limit_druzyn' => $t['limit_druzyn'] ?? 16,
    'data_startu'  => $t['data_startu'] ?? '',
];
$bledy = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $dane['nazwa']        = trim($_POST['nazwa'] ?? '');
    $dane['format_meczu'] = $_POST['format_meczu'] ?? '';
    $dane['limit_druzyn'] = (int)($_POST['limit_druzyn'] ?? 0);
    $dane['data_startu']  = trim($_POST['data_startu'] ?? '');

    if (mb_strlen($dane['nazwa']) < 3 || mb_strlen($dane['nazwa']) > 100) $bledy[] = 'Nazwa turnieju musi mieć od 3 do 100 znaków.';
    if (!in_array($dane['format_meczu'], ['BO1', 'BO3', 'BO5'], true)) $bledy[] = 'Wybierz format meczu.';
    if ($dane['limit_druzyn'] < 4 || $dane['limit_druzyn'] > 64) $bledy[] = 'Limit drużyn musi wynosić od 4 do 64.';

    $d = DateTime::createFromFormat('Y-m-d', $dane['data_startu']);
    $dOk = $d && $d->format('Y-m-d') === $dane['data_startu'];
    if (!$dOk) {
        $bledy[] = 'Data startu jest nieprawidłowa.';
    } elseif ($dane['data_startu'] <= date('Y-m-d')) {
        $bledy[] = 'Data startu musi być w przyszłości.';
    }

    if ($t) {
        $st = db()->prepare("SELECT COUNT(*) FROM zgloszenie WHERE id_turnieju = ? AND status = 'przyjęte'");
        $st->execute([$id]);
        if ($dane['limit_druzyn'] < (int)$st->fetchColumn()) {
            $bledy[] = 'Limit nie może być mniejszy niż liczba zgłoszonych drużyn.';
        }
    }

    if (!$bledy) {
        if ($t) {
            $st = db()->prepare('UPDATE turniej SET nazwa=?, format_meczu=?, limit_druzyn=?, data_startu=? WHERE id_turnieju=?');
            $st->execute([$dane['nazwa'], $dane['format_meczu'], $dane['limit_druzyn'], $dane['data_startu'], $id]);
        } else {
            $st = db()->prepare("INSERT INTO turniej (nazwa, format_meczu, limit_druzyn, data_startu, status, id_administratora)
                                 VALUES (?, ?, ?, ?, 'szkic', ?)");
            $st->execute([$dane['nazwa'], $dane['format_meczu'], $dane['limit_druzyn'], $dane['data_startu'], uzytkownik()['id']]);
        }
        header('Location: turnieje.php');
        exit;
    }
}

$tytul = $t ? 'Edytuj turniej' : 'Dodaj turniej';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($tytul) ?></h1>
<p class="przygaszony">Gra: Counter-Strike 2, mecze 5 vs 5, drabinka pucharowa.</p>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz">
  <?= csrfPole() ?>
  <label>Nazwa turnieju <input type="text" name="nazwa" value="<?= e($dane['nazwa']) ?>" required></label>
  <label>Format meczu
    <select name="format_meczu">
      <?php foreach (['BO1', 'BO3', 'BO5'] as $f): ?>
        <option value="<?= $f ?>" <?= $dane['format_meczu'] === $f ? 'selected' : '' ?>><?= $f ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Limit drużyn (4–64) <input type="number" name="limit_druzyn" min="4" max="64" value="<?= (int)$dane['limit_druzyn'] ?>" required></label>
  <label>Data startu <input type="date" name="data_startu" value="<?= e($dane['data_startu']) ?>" required></label>
  <button type="submit">Zapisz</button>
  <a href="turnieje.php">Anuluj</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
