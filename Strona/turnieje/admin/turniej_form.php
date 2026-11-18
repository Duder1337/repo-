<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/turnieje_funkcje.php';
wymagajRoli('administrator');
zapewnijKolumnyOpisuTurnieju();

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
    'podtytul'     => $t['podtytul'] ?? '',
    'format_finalu'=> $t['format_finalu'] ?? '',
    'zasady'       => $t['zasady'] ?? '',
];
$bledy = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $dane['nazwa']        = trim($_POST['nazwa'] ?? '');
    $dane['format_meczu'] = $_POST['format_meczu'] ?? '';
    $dane['limit_druzyn'] = (int)($_POST['limit_druzyn'] ?? 0);
    $dane['data_startu']  = trim($_POST['data_startu'] ?? '');
    $dane['podtytul']     = trim($_POST['podtytul'] ?? '');
    $dane['format_finalu']= $_POST['format_finalu'] ?? '';
    $dane['zasady']       = trim(str_replace("\r\n", "\n", $_POST['zasady'] ?? ''));

    if (mb_strlen($dane['nazwa']) < 3 || mb_strlen($dane['nazwa']) > 100) $bledy[] = 'Nazwa turnieju musi mieć od 3 do 100 znaków.';
    if (!in_array($dane['format_meczu'], ['BO1', 'BO3', 'BO5'], true)) $bledy[] = 'Wybierz format meczu.';
    if (mb_strlen($dane['podtytul']) > 150) $bledy[] = 'Podtytuł może mieć najwyżej 150 znaków.';
    if (!in_array($dane['format_finalu'], ['', 'BO1', 'BO3', 'BO5'], true)) $bledy[] = 'Wybierz format finału.';
    if (mb_strlen($dane['zasady']) > 3000) $bledy[] = 'Zasady mogą mieć najwyżej 3000 znaków.';
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
            $st = db()->prepare('UPDATE turniej SET nazwa=?, format_meczu=?, limit_druzyn=?, data_startu=?, podtytul=?, format_finalu=?, zasady=? WHERE id_turnieju=?');
            $st->execute([$dane['nazwa'], $dane['format_meczu'], $dane['limit_druzyn'], $dane['data_startu'],
                          $dane['podtytul'] ?: null, $dane['format_finalu'] ?: null, $dane['zasady'] ?: null, $id]);
        } else {
            $st = db()->prepare("INSERT INTO turniej (nazwa, format_meczu, limit_druzyn, data_startu, status, id_administratora, podtytul, format_finalu, zasady)
                                 VALUES (?, ?, ?, ?, 'szkic', ?, ?, ?, ?)");
            $st->execute([$dane['nazwa'], $dane['format_meczu'], $dane['limit_druzyn'], $dane['data_startu'], uzytkownik()['id'],
                          $dane['podtytul'] ?: null, $dane['format_finalu'] ?: null, $dane['zasady'] ?: null]);
        }
        $dokad = $t ? $id : (int)db()->lastInsertId();
        $_SESSION['flash_turniej'] = ['ok' => $t ? 'Zmiany zostały zapisane.'
            : 'Turniej został utworzony jako szkic. Opublikuj go, aby otworzyć zapisy dla drużyn.'];
        header('Location: turniej_zarzadzaj.php?id=' . $dokad);
        exit;
    }
}

$tytul = $t ? 'Edytuj turniej' : 'Dodaj turniej';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($tytul) ?></h1>
<p class="przygaszony">Gra: Counter-Strike 2, mecze 5 vs 5, drabinka pucharowa.</p>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz" id="form-turniej">
  <?= csrfPole() ?>
  <?php if (!$t): ?>
    <button type="button" class="drugi" id="wypelnij-winter">Wypełnij danymi NEXORA WINTER CUP 2026</button>
  <?php endif; ?>
  <label>Nazwa turnieju <input type="text" name="nazwa" value="<?= e($dane['nazwa']) ?>" required></label>
  <label>Podtytuł (opcjonalnie) <input type="text" name="podtytul" maxlength="150" value="<?= e($dane['podtytul']) ?>"></label>
  <label>Format meczu
    <select name="format_meczu">
      <?php foreach (['BO1', 'BO3', 'BO5'] as $f): ?>
        <option value="<?= $f ?>" <?= $dane['format_meczu'] === $f ? 'selected' : '' ?>><?= $f ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Format finału
    <select name="format_finalu">
      <option value="" <?= $dane['format_finalu'] === '' ? 'selected' : '' ?>>Taki sam jak pozostałe mecze</option>
      <?php foreach (['BO1', 'BO3', 'BO5'] as $f): ?>
        <option value="<?= $f ?>" <?= $dane['format_finalu'] === $f ? 'selected' : '' ?>><?= $f ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Limit drużyn (4–64) <input type="number" name="limit_druzyn" min="4" max="64" value="<?= (int)$dane['limit_druzyn'] ?>" required></label>
  <label>Data startu <input type="date" name="data_startu" value="<?= e($dane['data_startu']) ?>" required></label>
  <label>Zasady turnieju (jedna zasada w wierszu)
    <textarea name="zasady" rows="9" maxlength="3000"><?= e($dane['zasady']) ?></textarea>
  </label>
  <button type="submit">Zapisz</button>
  <a href="turnieje.php">Anuluj</a>
</form>
<?php if (!$t): ?>
<script>
// Gotowe dane pierwszego turnieju platformy (data startu do wpisania ręcznie)
document.getElementById('wypelnij-winter').addEventListener('click', function () {
  var f = document.getElementById('form-turniej');
  f.nazwa.value = 'NEXORA WINTER CUP 2026';
  f.podtytul.value = 'PIERWSZY TURNIEJ PLATFORMY NEXORA ESPORTS';
  f.format_meczu.value = 'BO3';
  f.format_finalu.value = 'BO5';
  f.limit_druzyn.value = 8;
  f.zasady.value = [
    'Turniej będzie rozgrywany w systemie pucharowym, z udziałem 8 pierwszych zarejestrowanych drużyn.',
    'Udział bierze 8 pierwszych zarejestrowanych drużyn.',
    'Turniej rozpoczyna się od ćwierćfinałów (1/4 finału).',
    'Wszystkie mecze poza finałem rozgrywane są w formacie BO3.',
    'Finał rozgrywany jest w formacie BO5.',
    'Losowanie map odbywa się krótko przed rozpoczęciem meczu.',
    'Zwycięskie drużyny przechodzą do kolejnego etapu drabinki.'
  ].join('\n');
  f.data_startu.focus();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
