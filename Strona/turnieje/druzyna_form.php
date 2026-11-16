<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
wymagajLogowania();

$id    = (int)($_GET['id'] ?? 0);
$pdo   = db();
$bledy = [];
$ok    = '';

/* ================= Tworzenie nowej drużyny ================= */
if (!$id) {
    $z = mojZawodnik();
    if (!$z) exit('To konto nie ma profilu zawodnika, więc nie może założyć drużyny.');
    $obecna = druzynaZawodnika((int)$z['id_zawodnika']);
    if ($obecna) {
        exit('Należysz już do drużyny „' . e($obecna['nazwa']) . '”. Zawodnik może być tylko w jednej drużynie.');
    }

    $nazwa = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfSprawdz();
        $nazwa = trim($_POST['nazwa'] ?? '');
        if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 30) {
            $bledy[] = 'Nazwa drużyny musi mieć od 3 do 30 znaków.';
        }
        if (!$bledy) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO druzyna (nazwa, id_wlasciciela) VALUES (?, ?)')->execute([$nazwa, uzytkownik()['id']]);
                $nowe = (int)$pdo->lastInsertId();
                // Twórca zostaje kapitanem; resztę składu zaprasza już jako kapitan
                $pdo->prepare("INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, 'kapitan')")
                    ->execute([$nowe, $z['id_zawodnika']]);
                $pdo->prepare('INSERT INTO ranking (id_druzyny) VALUES (?)')->execute([$nowe]);
                $pdo->commit();
                header('Location: ' . BASE_URL . '/druzyna_form.php?id=' . $nowe);
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') {
                    $bledy[] = 'Drużyna o takiej nazwie już istnieje (lub jesteś już w innej drużynie).';
                } else {
                    throw $ex;
                }
            }
        }
    }

    $tytul = 'Utwórz drużynę';
    require 'includes/header.php';
    ?>
    <h1>Utwórz drużynę</h1>
    <p class="przygaszony">Zostaniesz kapitanem drużyny. Pozostałych zawodników zaprosisz po jej utworzeniu.</p>
    <?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
    <form method="post" class="formularz">
      <?= csrfPole() ?>
      <label>Nazwa drużyny
        <input type="text" name="nazwa" value="<?= e($nazwa) ?>" minlength="3" maxlength="30" placeholder="np. The Lycans" required>
      </label>
      <div class="przyciski-rzad">
        <button type="submit">Utwórz drużynę</button>
        <a class="przycisk drugi" href="<?= BASE_URL ?>/druzyny.php">Anuluj</a>
      </div>
    </form>
    <?php
    require 'includes/footer.php';
    exit;
}

/* ================= Zarządzanie istniejącą drużyną ================= */
$st = $pdo->prepare('SELECT * FROM druzyna WHERE id_druzyny = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Nie znaleziono drużyny.'); }
if (!mozeZarzadzac($id)) { http_response_code(403); exit('Drużyną może zarządzać tylko jej kapitan.'); }
$zablok = druzynaZablokowana($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';

    if ($zablok) {
        $bledy[] = 'Drużyna uczestniczy w trwających rozgrywkach – zmiany są zablokowane.';

    } elseif ($akcja === 'nazwa') {
        $nazwa = trim($_POST['nazwa'] ?? '');
        if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 30) {
            $bledy[] = 'Nazwa drużyny musi mieć od 3 do 30 znaków.';
        } else {
            try {
                $pdo->prepare('UPDATE druzyna SET nazwa = ? WHERE id_druzyny = ?')->execute([$nazwa, $id]);
                $d['nazwa'] = $nazwa;
                $ok = 'Nazwa drużyny została zmieniona.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') $bledy[] = 'Drużyna o takiej nazwie już istnieje.';
                else throw $ex;
            }
        }

    } elseif ($akcja === 'zapros') {
        $nick = trim($_POST['nick'] ?? '');
        $rola = $_POST['rola'] ?? '';
        $st = $pdo->prepare('SELECT * FROM zawodnik WHERE nick = ?');
        $st->execute([$nick]);
        $w = $st->fetch();
        if (!in_array($rola, ['gracz', 'rezerwowy', 'trener'], true)) {
            $bledy[] = 'Wybierz rolę zawodnika.';
        } elseif (!$w) {
            $bledy[] = 'Nie znaleziono zawodnika o nicku ' . $nick . '.';
        } elseif ($w['id_uzytkownika'] === null) {
            $bledy[] = 'Zawodnik ' . $w['nick'] . ' nie ma konta, więc nie może przyjąć zaproszenia.';
        } elseif (druzynaZawodnika((int)$w['id_zawodnika'])) {
            $bledy[] = 'Zawodnik ' . $w['nick'] . ' należy już do innej drużyny.';
        } else {
            $st = $pdo->prepare("SELECT 1 FROM zaproszenie WHERE id_druzyny = ? AND id_zawodnika = ? AND status = 'oczekujące'");
            $st->execute([$id, $w['id_zawodnika']]);
            if ($st->fetchColumn()) {
                $bledy[] = 'Zawodnik ' . $w['nick'] . ' ma już oczekujące zaproszenie od Twojej drużyny.';
            } elseif (liczbaWRoli($id, $rola, true) >= LIMITY_ROL[$rola]) {
                $bledy[] = 'Brak wolnych miejsc w roli: ' . etykietaRoli($rola) . ' (wliczając wysłane zaproszenia).';
            } else {
                $pdo->prepare('INSERT INTO zaproszenie (id_druzyny, id_zawodnika, rola, id_zapraszajacego) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $w['id_zawodnika'], $rola, uzytkownik()['id']]);
                $ok = 'Zaproszenie zostało wysłane do zawodnika ' . $w['nick'] . '.';
            }
        }

    } elseif ($akcja === 'anuluj_zaproszenie') {
        $st = $pdo->prepare("UPDATE zaproszenie SET status = 'anulowane' WHERE id_zaproszenia = ? AND id_druzyny = ? AND status = 'oczekujące'");
        $st->execute([(int)($_POST['id_zaproszenia'] ?? 0), $id]);
        if ($st->rowCount()) $ok = 'Zaproszenie zostało anulowane.';

    } elseif ($akcja === 'usun_czlonka') {
        $st = $pdo->prepare("DELETE FROM sklad WHERE id_druzyny = ? AND id_zawodnika = ? AND rola <> 'kapitan'");
        $st->execute([$id, (int)($_POST['id_zawodnika'] ?? 0)]);
        if ($st->rowCount()) $ok = 'Zawodnik został usunięty ze składu.';
        else $bledy[] = 'Nie można usunąć tego zawodnika.';

    } elseif ($akcja === 'przekaz_kapitana') {
        $idZ = (int)($_POST['id_zawodnika'] ?? 0);
        $st = $pdo->prepare("SELECT w.id_uzytkownika FROM sklad s JOIN zawodnik w ON w.id_zawodnika = s.id_zawodnika
                             WHERE s.id_druzyny = ? AND s.id_zawodnika = ? AND s.rola = 'gracz'");
        $st->execute([$id, $idZ]);
        $nowy = $st->fetch();
        if (!$nowy) {
            $bledy[] = 'Kapitanem może zostać tylko gracz podstawowego składu.';
        } elseif ($nowy['id_uzytkownika'] === null) {
            $bledy[] = 'Nowy kapitan musi mieć konto na platformie.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE sklad SET rola = 'gracz' WHERE id_druzyny = ? AND rola = 'kapitan'")->execute([$id]);
            $pdo->prepare("UPDATE sklad SET rola = 'kapitan' WHERE id_druzyny = ? AND id_zawodnika = ?")->execute([$id, $idZ]);
            $pdo->prepare('UPDATE druzyna SET id_wlasciciela = ? WHERE id_druzyny = ?')->execute([$nowy['id_uzytkownika'], $id]);
            $pdo->commit();
            header('Location: ' . BASE_URL . '/druzyna.php?id=' . $id);
            exit;
        }

    } elseif ($akcja === 'rozwiaz') {
        $blad = usunDruzyne($id);
        if ($blad === '') { header('Location: ' . BASE_URL . '/druzyny.php'); exit; }
        $bledy[] = $blad;
    }
}

// Dane do wyświetlenia (po ewentualnych zmianach)
$sklad = pobierzSklad($id);
$licz  = array_fill_keys(array_keys(LIMITY_ROL), 0);
foreach ($sklad as $s) $licz[$s['rola']]++;

$st = $pdo->prepare("SELECT z.id_zaproszenia, w.nick, z.rola, z.data_wyslania
                     FROM zaproszenie z JOIN zawodnik w ON w.id_zawodnika = z.id_zawodnika
                     WHERE z.id_druzyny = ? AND z.status = 'oczekujące' ORDER BY z.data_wyslania");
$st->execute([$id]);
$zaproszenia = $st->fetchAll();
$oczekuje = ['gracz' => 0, 'rezerwowy' => 0, 'trener' => 0];
foreach ($zaproszenia as $zp) $oczekuje[$zp['rola']]++;

$tytul = 'Zarządzaj drużyną';
require 'includes/header.php';
?>
<h1>Zarządzaj drużyną: <?= e($d['nazwa']) ?></h1>
<p><a href="druzyna.php?id=<?= (int)$id ?>">&larr; Profil drużyny</a></p>

<?php if ($zablok): ?>
  <p class="blad">Drużyna uczestniczy w trwających rozgrywkach – skład i zaproszenia są zablokowane.</p>
<?php endif; ?>
<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>

<h2>Nazwa drużyny</h2>
<form method="post" class="formularz szeroki">
  <?= csrfPole() ?>
  <label>Nazwa <input type="text" name="nazwa" value="<?= e($d['nazwa']) ?>" minlength="3" maxlength="30" required></label>
  <div class="przyciski-rzad"><button name="akcja" value="nazwa" <?= $zablok ? 'disabled' : '' ?>>Zmień nazwę</button></div>
</form>

<h2>Skład</h2>
<p class="przygaszony">Podstawowa piątka: kapitan + 4 graczy (<?= $licz['kapitan'] + $licz['gracz'] ?>/5).
  Rezerwowi <?= $licz['rezerwowy'] ?>/<?= LIMITY_ROL['rezerwowy'] ?>, trener <?= $licz['trener'] ?>/<?= LIMITY_ROL['trener'] ?>.
  Do ligi i turniejów można zgłosić drużynę dopiero z pełną piątką.</p>
<div class="tabela-wrap"><table>
  <tr><th>Zawodnik</th><th>Rola</th><th>Akcje</th></tr>
  <?php foreach ($sklad as $s): ?>
    <tr>
      <td><a href="zawodnik.php?id=<?= (int)$s['id_zawodnika'] ?>"><?= e($s['nick']) ?></a></td>
      <td><?= e(etykietaRoli($s['rola'])) ?></td>
      <td class="akcje">
        <?php if ($s['rola'] === 'kapitan'): ?>
          –
        <?php else: ?>
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id_zawodnika" value="<?= (int)$s['id_zawodnika'] ?>">
            <?php if ($s['rola'] === 'gracz'): ?>
              <button name="akcja" value="przekaz_kapitana" <?= $zablok ? 'disabled' : '' ?>
                      onclick="return confirm('Przekazać funkcję kapitana? Stracisz uprawnienia do zarządzania drużyną.')">Przekaż kapitana</button>
            <?php endif; ?>
            <button name="akcja" value="usun_czlonka" class="drugi" <?= $zablok ? 'disabled' : '' ?>
                    onclick="return confirm('Usunąć zawodnika z drużyny?')">Usuń</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>

<h2>Zaproś zawodnika</h2>
<form method="post" class="formularz szeroki" id="form-zapros"
      data-szukaj="<?= BASE_URL ?>/szukaj_zawodnika.php" data-druzyna="<?= (int)$id ?>">
  <?= csrfPole() ?>
  <label>Nick zawodnika
    <input type="text" name="nick" autocomplete="off" placeholder="Zacznij pisać nick…" required>
  </label>
  <ul class="podpowiedzi" id="podpowiedzi"></ul>
  <label>Rola w drużynie
    <select name="rola">
      <?php foreach (['gracz', 'rezerwowy', 'trener'] as $r):
        $zajete = $licz[$r] + $oczekuje[$r]; ?>
        <option value="<?= $r ?>" <?= $zajete >= LIMITY_ROL[$r] ? 'disabled' : '' ?>>
          <?= e(etykietaRoli($r)) ?> (<?= $zajete ?>/<?= LIMITY_ROL[$r] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="przyciski-rzad"><button name="akcja" value="zapros" <?= $zablok ? 'disabled' : '' ?>>Wyślij zaproszenie</button></div>
  <small class="przygaszony">Zaprosić można tylko zawodników z kontem, którzy nie należą jeszcze do żadnej drużyny.</small>
</form>

<?php if ($zaproszenia): ?>
  <h2>Wysłane zaproszenia</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Zawodnik</th><th>Rola</th><th>Wysłano</th><th></th></tr>
    <?php foreach ($zaproszenia as $zp): ?>
      <tr>
        <td><?= e($zp['nick']) ?></td>
        <td><?= e(etykietaRoli($zp['rola'])) ?></td>
        <td><?= e(date('d.m.Y H:i', strtotime($zp['data_wyslania']))) ?></td>
        <td class="akcje">
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id_zaproszenia" value="<?= (int)$zp['id_zaproszenia'] ?>">
            <button name="akcja" value="anuluj_zaproszenie" class="drugi" <?= $zablok ? 'disabled' : '' ?>>Anuluj</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

<h2>Rozwiązanie drużyny</h2>
<form method="post">
  <?= csrfPole() ?>
  <button name="akcja" value="rozwiaz" class="drugi" <?= $zablok ? 'disabled' : '' ?>
          onclick="return confirm('Na pewno rozwiązać drużynę? Tej operacji nie można cofnąć.')">Rozwiąż drużynę</button>
  <span class="przygaszony">&nbsp;Niemożliwe, jeśli drużyna ma już mecze w lidze lub turnieju.</span>
</form>

<script src="<?= BASE_URL ?>/assets/zaproszenia.js"></script>
<?php require 'includes/footer.php'; ?>
