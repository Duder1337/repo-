<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
require_once 'includes/statystyki_funkcje.php';
require_once 'includes/zawodnik_funkcje.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM druzyna WHERE id_druzyny = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Nie znaleziono drużyny.'); }

$komunikat = '';
$blad = '';

$moja   = mozeZarzadzac($id);          // kapitan lub administrator
$zablok = druzynaZablokowana($id);

// Zwykły członek może sam opuścić drużynę (kapitan musi najpierw przekazać funkcję)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcja'] ?? '') === 'opusc') {
    wymagajLogowania();
    csrfSprawdz();
    $mojaD = mojaDruzyna();
    if (!$mojaD || (int)$mojaD['id_druzyny'] !== $id) {
        $blad = 'Nie należysz do tej drużyny.';
    } elseif ($mojaD['rola'] === 'kapitan') {
        $blad = 'Kapitan nie może opuścić drużyny. Przekaż kapitana innemu graczowi lub rozwiąż drużynę.';
    } elseif ($zablok) {
        $blad = 'Nie można opuścić drużyny uczestniczącej w trwających rozgrywkach.';
    } else {
        db()->prepare('DELETE FROM sklad WHERE id_druzyny = ? AND id_zawodnika = ?')->execute([$id, mojZawodnik()['id_zawodnika']]);
        $komunikat = 'Opuściłeś drużynę.';
    }
}

// Zaproszenia wysyłane z okna otwieranego plusem (kapitan / administrator)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['akcja'] ?? '', ['zapros', 'anuluj_zaproszenie'], true)) {
    wymagajLogowania();
    csrfSprawdz();
    if (!$moja) { http_response_code(403); exit('Drużyną może zarządzać tylko jej kapitan.'); }

    if ($zablok) {
        $f = ['blad' => 'Drużyna uczestniczy w trwających rozgrywkach – zmiany są zablokowane.'];
    } elseif ($_POST['akcja'] === 'zapros') {
        $nick = '';
        $b = wyslijZaproszenie($id, (string)($_POST['nick'] ?? ''), (string)($_POST['rola'] ?? ''), $nick);
        $f = $b === '' ? ['ok' => 'Zaproszenie zostało wysłane do zawodnika ' . $nick . '.'] : ['blad' => $b, 'modal' => 1];
    } else {
        $f = anulujZaproszenie($id, (int)($_POST['id_zaproszenia'] ?? 0))
            ? ['ok' => 'Zaproszenie zostało anulowane.']
            : ['blad' => 'To zaproszenie jest już nieaktualne.', 'modal' => 1];
    }
    flashDruzynyUstaw($f);
    header('Location: ' . BASE_URL . '/druzyna.php?id=' . $id . '#sklad');
    exit;
}

// Komunikaty po przekierowaniu: sukces na górze strony, błąd w oknie zaproszeń
$flash = flashDruzynyPobierz();
if (!empty($flash['ok'])) $komunikat = $flash['ok'];
$bladOkna = '';
if (!empty($flash['blad'])) {
    if (!empty($flash['modal'])) $bladOkna = $flash['blad']; else $blad = $flash['blad'];
}

$sklad = pobierzSklad($id);
$licz  = array_fill_keys(array_keys(LIMITY_ROL), 0);
$trener = null;
foreach ($sklad as $s) {
    $licz[$s['rola']]++;
    if ($s['rola'] === 'trener') $trener = $s;
}
$kartyGraczy = array_values(array_filter($sklad, fn($s) => $s['rola'] !== 'trener'));
$liczPodst = $licz['kapitan'] + $licz['gracz'];
$statGraczy = statystykiZawodnikow(array_column($sklad, 'id_zawodnika'));

$kapitan = null;
foreach ($sklad as $s) if ($s['rola'] === 'kapitan') { $kapitan = $s; break; }

// Zaproszenia i wolne miejsca (tylko dla kapitana / administratora)
$zaproszenia = $moja ? oczekujaceZaproszenia($id) : [];
$oczekuje = ['gracz' => 0, 'rezerwowy' => 0, 'trener' => 0];
foreach ($zaproszenia as $zp) $oczekuje[$zp['rola']]++;
$wolne = [];
foreach (['gracz', 'rezerwowy', 'trener'] as $r) $wolne[$r] = LIMITY_ROL[$r] - $licz[$r] - $oczekuje[$r];
$saWolne = max($wolne) > 0;

$mojaD         = mojaDruzyna();
$jestCzlonkiem = $mojaD && (int)$mojaD['id_druzyny'] === $id && $mojaD['rola'] !== 'kapitan';

$historia = historiaMeczow($id);
$stat = statystykiDruzyny($historia);
$etykietyWyniku = ['W' => 'Wygrana', 'P' => 'Porażka', 'R' => 'Remis'];

$st = db()->prepare("SELECT t.id_turnieju, t.nazwa, t.status FROM zgloszenie z
                     JOIN turniej t ON t.id_turnieju = z.id_turnieju
                     WHERE z.id_druzyny = ? AND z.status = 'przyjęte' AND t.status <> 'szkic'
                     ORDER BY t.data_startu DESC");
$st->execute([$id]);
$turnieje = $st->fetchAll();

$tytul = $d['nazwa'];
require 'includes/header.php';
?>
<div class="dr-widok">

<section class="dr-baner" style="--h:<?= (int)(crc32($d['nazwa']) % 360) ?>">
  <div class="dr-baner-lewo">
    <div class="dr-logo"><span><?= e(inicjalyDruzyny($d['nazwa'])) ?></span></div>
    <div class="dr-nazwa">
      <h1><?= e($d['nazwa']) ?></h1>
      <small>Drużyna CS2<?php if ($kapitan): ?> · kapitan: <a href="zawodnik.php?id=<?= (int)$kapitan['id_zawodnika'] ?>"><?= e($kapitan['nick']) ?></a><?php endif; ?></small>
    </div>
  </div>
  <div class="dr-baner-gra" aria-hidden="true">COUNTER<br>STRIKE <b>2</b></div>
  <?php if (!empty($d['data_utworzenia'])): ?>
    <span class="dr-data">utworzona <?= e(date('d.m.Y', strtotime($d['data_utworzenia']))) ?></span>
  <?php endif; ?>
</section>

<?php if ($komunikat): ?><p class="ok"><?= e($komunikat) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<nav class="dr-tabs" aria-label="Sekcje profilu drużyny">
  <button type="button" class="aktywna" data-tab="sklad">Skład</button>
  <button type="button" data-tab="mecze">Mecze (<?= count($historia) ?>)</button>
  <button type="button" data-tab="turnieje">Turnieje (<?= count($turnieje) ?>)</button>
</nav>

<!-- ============ SKŁAD ============ -->
<section class="dr-panel aktywny" data-panel="sklad" id="sklad">
  <div class="dr-sklad-gora">
    <div class="dr-sklad-tytul">
      <h2>SKŁAD</h2>
      <span class="dr-licz"><?= $liczPodst ?>/5 podstawowych · <?= $licz['rezerwowy'] ?>/<?= LIMITY_ROL['rezerwowy'] ?> rezerwowych</span>
    </div>
    <div class="dr-filtry" role="group" aria-label="Filtr składu">
      <button type="button" class="aktywny" data-filtr="wszyscy">Wszyscy</button>
      <button type="button" data-filtr="podstawowi">Podstawowi</button>
      <button type="button" data-filtr="rezerwowi">Rezerwowi</button>
    </div>
    <div class="dr-akcje">
      <?php if ($moja): ?>
        <?php if (!$zablok && $saWolne): ?>
          <button type="button" class="dr-link" data-otworz-zaproszenie data-rola="gracz">＋ Dodaj zawodnika</button>
          <?php if ($wolne['trener'] > 0): ?>
            <button type="button" class="dr-link" data-otworz-zaproszenie data-rola="trener">＋ Zaproś trenera</button>
          <?php endif; ?>
        <?php endif; ?>
        <a class="dr-link" href="druzyna_form.php?id=<?= (int)$id ?>">⚙ Zarządzaj drużyną</a>
      <?php elseif ($jestCzlonkiem): ?>
        <form method="post" class="inline">
          <?= csrfPole() ?>
          <button name="akcja" value="opusc" class="dr-link" onclick="return confirm('Opuścić drużynę?')">Opuść drużynę</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($moja && $zablok): ?>
    <p class="przygaszony">Skład jest zablokowany – drużyna uczestniczy w trwających rozgrywkach.</p>
  <?php endif; ?>

  <div class="pk-rzad">
    <?php foreach ($kartyGraczy as $z) kartaZawodnika($z, $statGraczy[(int)$z['id_zawodnika']] ?? null, $d['nazwa']); ?>

    <?php if ($moja && !$zablok && $saWolne): ?>
      <div class="pk-wrap plus" data-grupa="plus">
        <span class="pk-odznaka"></span>
        <button type="button" class="pk pk-plus" data-otworz-zaproszenie data-rola="gracz" aria-label="Dodaj zawodnika">
          <span class="pk-in"><b>+</b><small>Dodaj zawodnika</small></span>
        </button>
      </div>
    <?php endif; ?>
  </div>

  <div class="dr-dol">
    <div class="dr-box">
      <h3>Statystyki</h3>
      <div class="dr-liczby">
        <div><b><?= $stat['rozegrane'] ?></b><small>Rozegrane mecze</small></div>
        <div><b><?= $stat['wygrane'] ?></b><small>Zwycięstwa</small></div>
        <div><b><?= $stat['przegrane'] ?></b><small>Porażki</small></div>
        <?php if ($stat['remisy']): ?><div><b><?= $stat['remisy'] ?></b><small>Remisy</small></div><?php endif; ?>
        <div><b><?= $stat['punkty'] ?></b><small>Punkty</small></div>
      </div>
      <p class="dr-nota">Punkty: <?= STAT_PUNKTY_WYGRANA ?> za zwycięstwo, <?= STAT_PUNKTY_REMIS ?> za remis, 0 za porażkę. Statystyki obejmują mecze turniejowe i ligowe.</p>
    </div>

    <div class="dr-box">
      <h3>Trener</h3>
      <?php if ($trener): ?>
        <a class="dr-trener" href="zawodnik.php?id=<?= (int)$trener['id_zawodnika'] ?>">
          <?= awatarHtml($trener) ?> <span><?= e($trener['nick']) ?><small>Trener drużyny</small></span>
        </a>
      <?php elseif ($moja && !$zablok && $wolne['trener'] > 0): ?>
        <p class="przygaszony">Drużyna nie ma jeszcze trenera.</p>
        <button type="button" data-otworz-zaproszenie data-rola="trener">Szukamy trenera</button>
      <?php else: ?>
        <p class="przygaszony">Drużyna nie ma trenera.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ MECZE ============ -->
<section class="dr-panel" data-panel="mecze" id="mecze">
  <h2>Historia spotkań</h2>
  <?php if (!$historia): ?>
    <p class="przygaszony">Drużyna nie rozegrała jeszcze żadnego meczu.</p>
  <?php else: ?>
    <div class="tabela-wrap"><table>
      <tr><th>Rozgrywki</th><th>Przeciwnik</th><th class="mecz-wynik">Wynik</th><th>Rezultat</th></tr>
      <?php foreach ($historia as $m): ?>
        <tr>
          <td>
            <?php if ($m['typ'] === 'turniej'): ?>
              <a href="turniej.php?id=<?= (int)$m['id_rozgrywek'] ?>"><?= e($m['rozgrywki']) ?></a>
            <?php else: ?>
              <a href="liga.php"><?= e($m['rozgrywki']) ?></a>
            <?php endif; ?>
          </td>
          <td><a href="druzyna.php?id=<?= $m['przeciwnik_id'] ?>"><?= e($m['przeciwnik']) ?></a></td>
          <td class="mecz-wynik"><?= $m['nasze'] !== null ? (int)$m['nasze'] . ' : ' . (int)$m['ich'] : '–' ?></td>
          <td><span class="rezultat rez-<?= e($m['rezultat']) ?>"><?= e($etykietyWyniku[$m['rezultat']]) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</section>

<!-- ============ TURNIEJE ============ -->
<section class="dr-panel" data-panel="turnieje" id="turnieje">
  <h2>Turnieje</h2>
  <?php if (!$turnieje): ?>
    <p class="przygaszony">Drużyna nie bierze udziału w żadnym turnieju.</p>
  <?php else: ?>
    <div class="tabela-wrap"><table>
      <tr><th>Turniej</th><th>Status turnieju</th></tr>
      <?php foreach ($turnieje as $t): ?>
        <tr>
          <td><a href="turniej.php?id=<?= (int)$t['id_turnieju'] ?>"><?= e($t['nazwa']) ?></a></td>
          <td><?= e(etykietaStatusu($t['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</section>

<p><a href="druzyny.php">&larr; Wróć do listy drużyn</a></p>

<?php if ($moja && !$zablok): ?>
<!-- ============ OKNO ZAPROSZEŃ (plus) ============ -->
<div class="modal<?= $bladOkna ? ' otwarty' : '' ?>" id="modal-zaproszenie" role="dialog" aria-modal="true" aria-labelledby="modal-zap-tytul">
  <div class="modal-okno">
    <div class="modal-gora">
      <h3 id="modal-zap-tytul">Dodaj zawodnika do drużyny</h3>
      <button type="button" class="modal-zamknij" id="modal-zamknij" aria-label="Zamknij">&times;</button>
    </div>
    <?php if ($bladOkna): ?><p class="blad"><?= e($bladOkna) ?></p><?php endif; ?>

    <form method="post" class="modal-form" id="form-zapros"
          data-szukaj="<?= BASE_URL ?>/szukaj_zawodnika.php" data-druzyna="<?= (int)$id ?>">
      <?= csrfPole() ?>
      <label>Nick zawodnika
        <input type="text" name="nick" autocomplete="off" placeholder="Zacznij pisać nick…" required>
      </label>
      <ul class="podpowiedzi" id="podpowiedzi"></ul>
      <label>Rola w drużynie
        <select name="rola" class="pole">
          <?php foreach (['gracz', 'rezerwowy', 'trener'] as $r): ?>
            <option value="<?= $r ?>" <?= $wolne[$r] <= 0 ? 'disabled' : '' ?>>
              <?= e(etykietaRoli($r)) ?> (<?= $licz[$r] + $oczekuje[$r] ?>/<?= LIMITY_ROL[$r] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button name="akcja" value="zapros">Wyślij zaproszenie</button>
      <small class="przygaszony">Zaprosić można tylko zawodników z kontem, którzy nie należą jeszcze do żadnej drużyny. Do ligi i turniejów potrzebna jest pełna piątka (kapitan + 4 graczy).</small>
    </form>

    <?php if ($zaproszenia): ?>
      <h4 class="modal-podtytul">Wysłane zaproszenia</h4>
      <ul class="dr-zap">
        <?php foreach ($zaproszenia as $zp): ?>
          <li>
            <span><b><?= e($zp['nick']) ?></b> <small><?= e(etykietaRoli($zp['rola'])) ?> · <?= e(date('d.m.Y H:i', strtotime($zp['data_wyslania']))) ?></small></span>
            <form method="post" class="inline">
              <?= csrfPole() ?>
              <input type="hidden" name="id_zaproszenia" value="<?= (int)$zp['id_zaproszenia'] ?>">
              <button name="akcja" value="anuluj_zaproszenie" class="drugi maly-btn">Anuluj</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div><!-- /.dr-widok -->
<script src="<?= BASE_URL ?>/assets/zaproszenia.js"></script>
<script src="<?= BASE_URL ?>/assets/zakladki.js"></script>
<?php require 'includes/footer.php'; ?>
