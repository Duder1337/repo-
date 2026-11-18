<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dane_testowe_funkcje.php';
wymagajRoli('administrator');
zapewnijKolumnyTestowe();

$pdo = db();
$idAdmina = (int)uzytkownik()['id'];

// Wyniki zapamiętane po przekierowaniu (post → redirect → get), żeby odświeżenie strony nie tworzyło danych drugi raz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $f = [];
    @set_time_limit(120);

    try {
        if ($akcja === 'zawodnicy') {
            $n = max(1, min(50, (int)($_POST['liczba'] ?? 0)));
            $zKontem = !empty($_POST['konta']);
            $hash = null;
            $nicki = [];
            for ($i = 0; $i < $n; $i++) $nicki[] = utworzZawodnikaTestowego($zKontem, $hash)['nick'];
            $f['ok'] = 'Utworzono zawodników testowych: ' . $n . ($zKontem ? ' (z kontami, hasło: ' . TEST_HASLO . ').' : ' (bez kont).');
            $f['nicki'] = $nicki;

        } elseif ($akcja === 'druzyny') {
            $n = max(1, min(16, (int)($_POST['liczba'] ?? 0)));
            $rez = max(0, min(3, (int)($_POST['rezerwowi'] ?? 0)));
            $trener = !empty($_POST['trener']);
            $zKontami = !empty($_POST['konta']);
            $idTurnieju = (int)($_POST['id_turnieju'] ?? 0);
            $hash = null;
            $utworzone = [];
            $nazwy = [];
            $zgloszone = 0;
            for ($i = 0; $i < $n; $i++) {
                $nazwa = nazwaDruzynyTestowej($nazwy);
                $nazwy[] = $nazwa;
                $d = utworzDruzyneTestowa($nazwa, $rez, $trener, $zKontami, $idAdmina, $hash);
                if ($idTurnieju && zglosDruzyneTestowa($idTurnieju, $d['id'])) { $zgloszone++; $d['zgloszona'] = true; }
                $utworzone[] = $d;
            }
            $f['ok'] = 'Utworzono drużyn testowych: ' . $n . ' (każda z pełną piątką).';
            if ($idTurnieju) $f['ok'] .= ' Zgłoszono do turnieju: ' . $zgloszone . ' z ' . $n . '.';
            $f['druzyny'] = $utworzone;

        } elseif ($akcja === 'usun') {
            $w = usunDaneTestowe();
            $f['ok'] = 'Usunięto drużyn testowych: ' . $w['druzyny'] . ', zawodników testowych: ' . $w['zawodnicy'] . '.';
            if ($w['pominiete']) $f['blad'] = 'Część danych została celowo – drużyny z meczami lub w trwających rozgrywkach oraz ich zawodnicy (pozostało: ' . $w['pominiete'] . '). Zakończ lub usuń turniej i uruchom sprzątanie ponownie.';
        }
    } catch (Throwable $ex) {
        $f['blad'] = 'Nie udało się zakończyć operacji: ' . $ex->getMessage();
    }
    $_SESSION['flash_testowe'] = $f;
    header('Location: dane_testowe.php');
    exit;
}

$f = $_SESSION['flash_testowe'] ?? [];
unset($_SESSION['flash_testowe']);

$licz = liczbyDanychTestowych();
$turnieje = $pdo->query("SELECT t.id_turnieju, t.nazwa, t.limit_druzyn,
        (SELECT COUNT(*) FROM zgloszenie z WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zajete
    FROM turniej t WHERE t.status = 'otwarty' ORDER BY t.data_startu")->fetchAll();

$tytul = 'Dane testowe';
require __DIR__ . '/../includes/header.php';
?>
<h1>🧪 Dane testowe</h1>
<p class="przygaszony">Narzędzie na czas testów: tworzy zawodników i gotowe drużyny, żeby nie trzeba było zakładać kont ręcznie.
  Wszystko, co tu powstaje, jest oznaczone jako testowe i można to usunąć jednym przyciskiem.</p>

<?php if (!empty($f['ok'])): ?><p class="ok"><?= e($f['ok']) ?></p><?php endif; ?>
<?php if (!empty($f['blad'])): ?><p class="blad"><?= e($f['blad']) ?></p><?php endif; ?>

<?php if (!empty($f['druzyny'])): ?>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna</th><th>Skład</th><th>Kapitan</th><th>Logowanie kapitana</th><th>Turniej</th></tr>
    <?php foreach ($f['druzyny'] as $d): ?>
      <tr>
        <td><a href="../druzyna.php?id=<?= (int)$d['id'] ?>"><?= e($d['nazwa']) ?></a></td>
        <td><?= (int)$d['liczba'] ?> os.</td>
        <td><?= e($d['kapitan']) ?></td>
        <td><?= $d['email'] ? e($d['email']) . ' / ' . e(TEST_HASLO) : '<span class="przygaszony">bez konta</span>' ?></td>
        <td><?= !empty($d['zgloszona']) ? '✔ zgłoszona' : '–' ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<?php if (!empty($f['nicki'])): ?>
  <p class="przygaszony">Nowi zawodnicy: <?= e(implode(', ', $f['nicki'])) ?></p>
<?php endif; ?>

<div class="info-kafle">
  <div class="stat"><small>Drużyny testowe</small><b><?= $licz['druzyny'] ?></b></div>
  <div class="stat"><small>Zawodnicy testowi</small><b><?= $licz['zawodnicy'] ?></b></div>
  <div class="stat"><small>Wolni (bez drużyny)</small><b><?= $licz['wolni'] ?></b></div>
  <div class="stat"><small>Z kontem do logowania</small><b><?= $licz['konta'] ?></b></div>
</div>

<h2>Dodaj drużyny testowe</h2>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Liczba drużyn (1–16) <input type="number" name="liczba" min="1" max="16" value="8" required></label>
  <label>Rezerwowych w każdej drużynie
    <select name="rezerwowi">
      <?php for ($i = 0; $i <= 3; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
    </select>
  </label>
  <label class="checkbox"><input type="checkbox" name="trener" value="1"> Dodaj trenera do każdej drużyny</label>
  <label class="checkbox"><input type="checkbox" name="konta" value="1"> Utwórz konta do logowania (hasło: <?= e(TEST_HASLO) ?>) – żeby wejść jako kapitan</label>
  <label>Od razu zgłoś drużyny do turnieju
    <select name="id_turnieju">
      <option value="0">– nie zgłaszaj –</option>
      <?php foreach ($turnieje as $t): ?>
        <option value="<?= (int)$t['id_turnieju'] ?>"><?= e($t['nazwa']) ?> (<?= (int)$t['zajete'] ?>/<?= (int)$t['limit_druzyn'] ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="przyciski-rzad"><button name="akcja" value="druzyny">Utwórz drużyny</button></div>
  <small class="przygaszony">Każda drużyna dostaje pełną piątkę (kapitan + 4 graczy) z losowymi nickami i nazwiskami. Zgłaszać można tylko do turniejów z otwartymi zapisami i wolnymi miejscami.</small>
</form>

<h2>Dodaj zawodników testowych</h2>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Liczba zawodników (1–50) <input type="number" name="liczba" min="1" max="50" value="5" required></label>
  <label class="checkbox"><input type="checkbox" name="konta" value="1"> Utwórz konta do logowania (hasło: <?= e(TEST_HASLO) ?>)</label>
  <div class="przyciski-rzad"><button name="akcja" value="zawodnicy">Utwórz zawodników</button></div>
  <small class="przygaszony">Wolni zawodnicy trafiają do puli, z której możesz ich dodawać do drużyn w „Zarządzaj drużynami” lub przez zaproszenia.</small>
</form>

<h2>Sprzątanie</h2>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <p class="przygaszony">Usuwa wszystkie drużyny i zawodników testowych (razem z ich kontami). Drużyny z rozegranymi meczami lub w trwających rozgrywkach zostają razem ze swoimi zawodnikami.</p>
  <div class="przyciski-rzad">
    <button name="akcja" value="usun" class="drugi" <?= ($licz['druzyny'] + $licz['zawodnicy']) ? '' : 'disabled' ?>
            onclick="return confirm('Usunąć wszystkie dane testowe?')">Usuń wszystkie dane testowe</button>
  </div>
</form>
<p><a href="index.php">&larr; Wróć do panelu</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
