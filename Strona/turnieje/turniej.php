<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
require_once 'includes/turnieje_funkcje.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT t.*, d.nazwa AS zwyciezca FROM turniej t
                     LEFT JOIN druzyna d ON d.id_druzyny = t.id_zwyciezcy
                     WHERE t.id_turnieju = ?');
$st->execute([$id]);
$t = $st->fetch();

// Szkice widzi tylko administrator
if (!$t || ($t['status'] === 'szkic' && !maRole('administrator'))) {
    http_response_code(404);
    exit('Nie znaleziono turnieju.');
}

$ok = '';
$blad = '';

// Zgłaszanie i wycofywanie drużyny (tylko kapitan, tylko gdy zapisy są otwarte)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wymagajLogowania();
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $moja  = mojaDruzyna();
    $idD   = $moja ? (int)$moja['id_druzyny'] : 0;

    if (!$moja || $moja['rola'] !== 'kapitan') {
        $blad = 'Do turnieju może zgłosić drużynę tylko jej kapitan.';
    } elseif ($t['status'] !== 'otwarty') {
        $blad = 'Zapisy do tego turnieju są zamknięte.';
    } elseif ($akcja === 'zapisz') {
        if (!skladKompletny($idD)) {
            $blad = 'Drużyna musi mieć pełną piątkę (kapitan + 4 graczy), żeby wziąć udział w turnieju.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                // Blokada wiersza turnieju: dwie drużyny naraz nie zajmą ostatniego miejsca
                $pdo->prepare('SELECT id_turnieju FROM turniej WHERE id_turnieju = ? FOR UPDATE')->execute([$id]);
                $st = $pdo->prepare("SELECT COUNT(*) FROM zgloszenie WHERE id_turnieju = ? AND status = 'przyjęte'");
                $st->execute([$id]);
                $zajete = (int)$st->fetchColumn();
                $st = $pdo->prepare('SELECT status FROM zgloszenie WHERE id_turnieju = ? AND id_druzyny = ?');
                $st->execute([$id, $idD]);
                $istn = $st->fetchColumn();

                $problem = '';
                if ($istn === 'przyjęte') {
                    $problem = 'Ta drużyna jest już zgłoszona.';
                } elseif ($istn === 'odrzucone') {
                    $problem = 'Zgłoszenie tej drużyny zostało odrzucone przez organizatora.';
                } elseif ($zajete >= (int)$t['limit_druzyn']) {
                    $problem = 'Limit drużyn w turnieju został osiągnięty.';
                } elseif ($istn === 'wycofane') {
                    $pdo->prepare("UPDATE zgloszenie SET status = 'przyjęte', data_zgloszenia = CURRENT_DATE
                                   WHERE id_turnieju = ? AND id_druzyny = ?")->execute([$id, $idD]);
                } else {
                    $pdo->prepare('INSERT INTO zgloszenie (id_turnieju, id_druzyny) VALUES (?, ?)')->execute([$id, $idD]);
                }

                if ($problem) { $pdo->rollBack(); $blad = $problem; }
                else { $pdo->commit(); $ok = 'Drużyna została zgłoszona do turnieju.'; }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') $blad = 'Ta drużyna jest już zgłoszona.';
                else throw $ex;
            }
        }
    } elseif ($akcja === 'wycofaj') {
        $st = db()->prepare("UPDATE zgloszenie SET status = 'wycofane'
                             WHERE id_turnieju = ? AND id_druzyny = ? AND status = 'przyjęte'");
        $st->execute([$id, $idD]);
        if ($st->rowCount()) $ok = 'Zgłoszenie zostało wycofane.';
        else $blad = 'Ta drużyna nie jest zgłoszona do turnieju.';
    }
}

$st = db()->prepare("SELECT d.id_druzyny, d.nazwa, z.data_zgloszenia FROM zgloszenie z
                     JOIN druzyna d ON d.id_druzyny = z.id_druzyny
                     WHERE z.id_turnieju = ? AND z.status = 'przyjęte'
                     ORDER BY z.data_zgloszenia, d.nazwa");
$st->execute([$id]);
$druzyny = $st->fetchAll();
$wolne = max(0, (int)$t['limit_druzyn'] - count($druzyny));
$h = crc32($t['nazwa']) % 360;

// Drużyna zalogowanego kapitana i jej stan zgłoszenia
$mojaD = zalogowany() ? mojaDruzyna() : null;
$kapitanD = ($mojaD && $mojaD['rola'] === 'kapitan') ? $mojaD : null;
$zapisana = $kapitanD && in_array((int)$kapitanD['id_druzyny'], array_map('intval', array_column($druzyny, 'id_druzyny')), true);
$kompletny = $kapitanD ? skladKompletny((int)$kapitanD['id_druzyny']) : false;

$zasady = zasadyJakoLista($t['zasady'] ?? '');

// Drabinka i postęp rozgrywek (wolne losy nie są liczone jako mecze)
$drabinka = in_array($t['status'], ['w trakcie', 'zakończony'], true) ? pobierzDrabinke($id) : [];
$meczeRazem = $meczeRozegrane = 0;
$etap = '';
foreach ($drabinka as $mecze) {
    foreach ($mecze as $m) {
        if ($m['status'] === 'rozegrany' && $m['wynik_a'] === null) continue; // wolny los
        $meczeRazem++;
        if ($m['status'] === 'rozegrany') $meczeRozegrane++;
        elseif ($etap === '') $etap = nazwaRundy(count($mecze));
    }
}
if ($drabinka && $etap === '') $etap = 'Zakończony';
$domyslna = $drabinka ? 'drabinka' : 'przeglad'; // drabinka zawsze na pierwszym planie, gdy już istnieje

$tytul = $t['nazwa'];
require 'includes/header.php';
?>
<section class="baner-turnieju" style="--h:<?= (int)$h ?>">
  <span class="plakietka st-<?= e(klasaStatusu($t['status'])) ?>"><?= e(etykietaStatusu($t['status'])) ?></span>
  <small><?= e($t['dyscyplina']) ?></small>
  <h1><?= e($t['nazwa']) ?></h1>
  <?php if (!empty($t['podtytul'])): ?><p class="podtytul"><?= e($t['podtytul']) ?></p><?php endif; ?>
  <?php if ($t['zwyciezca']): ?><p class="zwyciezca">🏆 Zwycięzca: <strong><?= e($t['zwyciezca']) ?></strong></p><?php endif; ?>
</section>

<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<div class="dr-widok" data-domyslna="<?= e($domyslna) ?>">

<nav class="dr-tabs" aria-label="Sekcje turnieju">
  <button type="button" data-tab="przeglad">Przegląd</button>
  <button type="button" data-tab="druzyny">Drużyny (<?= count($druzyny) ?>)</button>
  <button type="button" data-tab="drabinka">Drabinka</button>
  <button type="button" data-tab="zasoby">Zasoby</button>
</nav>

<!-- ============ PRZEGLĄD ============ -->
<section class="dr-panel" data-panel="przeglad" id="przeglad">
  <div class="info-kafle">
    <div class="stat"><small>Data startu</small><b><?= e($t['data_startu']) ?></b></div>
    <div class="stat"><small>Zapisanych drużyn</small><b><?= count($druzyny) ?> / <?= (int)$t['limit_druzyn'] ?></b></div>
    <div class="stat"><small>Wolne miejsca</small><b><?= $wolne ?></b></div>
    <div class="stat"><small>Format meczu</small><b><?= e(opisFormatu($t)) ?></b></div>
    <?php if ($drabinka): ?>
      <div class="stat"><small>Rozegrane mecze</small><b><?= $meczeRozegrane ?> / <?= $meczeRazem ?></b></div>
      <div class="stat"><small>Aktualny etap</small><b><?= e($etap) ?></b></div>
    <?php endif; ?>
  </div>

  <?php if ($t['zwyciezca']): ?>
    <p class="ok">🏆 Zwycięzcą turnieju została drużyna <strong><?= e($t['zwyciezca']) ?></strong>.</p>
  <?php endif; ?>

  <?php if ($t['status'] === 'otwarty'): ?>
    <h2>Zgłoś drużynę</h2>
    <?php if (!zalogowany()): ?>
      <p class="przygaszony"><a href="<?= BASE_URL ?>/login.php">Zaloguj się</a>, aby zgłosić swoją drużynę.</p>
    <?php elseif (!$kapitanD): ?>
      <p class="przygaszony">Do turnieju może zgłosić drużynę tylko jej kapitan (potrzebna pełna piątka).
        <?php if (!$mojaD): ?>Możesz <a href="<?= BASE_URL ?>/druzyna_form.php">utworzyć drużynę</a>.
        <?php else: ?>Poproś kapitana swojej drużyny o zgłoszenie.<?php endif; ?></p>
    <?php else: ?>
      <div class="tabela-wrap"><table>
        <tr><th>Twoja drużyna</th><th>Status</th><th></th></tr>
        <tr>
          <td><a href="druzyna.php?id=<?= (int)$kapitanD['id_druzyny'] ?>"><?= e($kapitanD['nazwa']) ?></a></td>
          <td><?= $zapisana ? 'Zgłoszona' : '–' ?></td>
          <td>
            <form method="post" class="inline">
              <?= csrfPole() ?>
              <?php if ($zapisana): ?>
                <button name="akcja" value="wycofaj" class="drugi" onclick="return confirm('Wycofać zgłoszenie?')">Wycofaj</button>
              <?php else: ?>
                <button name="akcja" value="zapisz" <?= (!$kompletny || $wolne === 0) ? 'disabled' : '' ?>>Zgłoś do turnieju</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      </table></div>
      <?php if (!$zapisana && !$kompletny): ?>
        <p class="przygaszony">Potrzebna jest pełna piątka: kapitan + 4 graczy (rezerwowi i trener nie są wymagani).</p>
      <?php elseif (!$zapisana && $wolne === 0): ?>
        <p class="przygaszony">Brak wolnych miejsc w turnieju.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php elseif ($t['status'] === 'w trakcie'): ?>
    <p class="przygaszony">Turniej trwa – zapisy są zamknięte. Przebieg rozgrywek śledzisz w zakładce <a href="#drabinka">Drabinka</a>.</p>
  <?php elseif ($t['status'] === 'zakończony'): ?>
    <p class="przygaszony">Turniej został zakończony. Wyniki znajdziesz w zakładce <a href="#drabinka">Drabinka</a>.</p>
  <?php endif; ?>
</section>

<!-- ============ DRUŻYNY ============ -->
<section class="dr-panel" data-panel="druzyny" id="druzyny">
  <h2>Zgłoszone drużyny <small class="przygaszony">(<?= count($druzyny) ?> / <?= (int)$t['limit_druzyn'] ?>)</small></h2>
  <?php if (!$druzyny): ?>
    <p class="przygaszony">Brak zgłoszonych drużyn.</p>
  <?php else: ?>
    <div class="tabela-wrap"><table>
      <tr><th>#</th><th>Drużyna</th><th>Data zgłoszenia</th></tr>
      <?php foreach ($druzyny as $i => $d): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><a href="druzyna.php?id=<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></a></td>
          <td><?= e($d['data_zgloszenia']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</section>

<!-- ============ DRABINKA ============ -->
<section class="dr-panel" data-panel="drabinka" id="drabinka">
  <h2>Drabinka turnieju</h2>
  <?php if (!$drabinka): ?>
    <p class="przygaszony">
      <?php if (in_array($t['status'], ['w trakcie', 'zakończony'], true)): ?>
        Brak meczów w drabince.
      <?php elseif ($t['status'] === 'anulowany'): ?>
        Turniej został anulowany.
      <?php else: ?>
        Drabinka zostanie wylosowana w chwili startu turnieju (zgłoszone drużyny: <?= count($druzyny) ?> / <?= (int)$t['limit_druzyn'] ?>).
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div class="drabinka">
      <?php foreach ($drabinka as $nr => $mecze): $ileMeczow = count($mecze); ?>
        <div class="dr-runda">
          <h3><?= e(nazwaRundy($ileMeczow)) ?> <small><?= e(formatRundy($t, $ileMeczow)) ?></small></h3>
          <div class="dr-mecze">
            <?php foreach ($mecze as $m):
                $wolny = $m['status'] === 'rozegrany' && $m['wynik_a'] === null;
                $strony = [['id_druzyny_a', 'nazwa_a', 'wynik_a'], ['id_druzyny_b', 'nazwa_b', 'wynik_b']]; ?>
              <div class="dr-mecz<?= $m['status'] === 'rozegrany' ? ' rozegrany' : '' ?>">
                <?php foreach ($strony as [$kId, $kNazwa, $kWynik]): ?>
                  <div class="dr-strona<?= ($m['id_zwyciezcy'] !== null && (int)$m['id_zwyciezcy'] === (int)$m[$kId]) ? ' wygrana' : '' ?>">
                    <?php if ($m[$kId] !== null): ?>
                      <a href="druzyna.php?id=<?= (int)$m[$kId] ?>"><?= e($m[$kNazwa]) ?></a>
                    <?php else: ?>
                      <span class="przygaszony"><?= $wolny ? 'wolny los' : 'do ustalenia' ?></span>
                    <?php endif; ?>
                    <b><?= $m[$kWynik] !== null ? (int)$m[$kWynik] : '' ?></b>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="przygaszony">Zwycięskie drużyny przechodzą do kolejnego etapu drabinki. Drużyny z wolnym losem awansują bez gry.</p>
  <?php endif; ?>
</section>

<!-- ============ ZASOBY ============ -->
<section class="dr-panel" data-panel="zasoby" id="zasoby">
  <div class="zasoby-siatka">
    <div class="dr-box">
      <h3>📜 Regulamin turnieju</h3>
      <?php if ($zasady): ?>
        <ul class="dr-lista">
          <?php foreach ($zasady as $z): ?><li><?= e($z) ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="przygaszony">Organizator nie dodał jeszcze regulaminu tego turnieju.</p>
      <?php endif; ?>
    </div>

    <div class="dr-box">
      <h3>🏆 Format rozgrywek</h3>
      <ul class="dr-lista">
        <li>System pucharowy – przegrany odpada, zwycięzca awansuje dalej.</li>
        <li>Mecze: <?= e(opisFormatu($t)) ?>, 5 vs 5 (<?= e($t['dyscyplina']) ?>).</li>
        <li>Limit drużyn: <?= (int)$t['limit_druzyn'] ?>, obowiązuje kolejność zgłoszeń.</li>
        <li>Drabinka jest losowana w chwili startu turnieju.</li>
        <li>Gdy liczba drużyn nie jest potęgą dwójki, część z nich dostaje wolny los w pierwszej rundzie.</li>
      </ul>
    </div>

    <div class="dr-box">
      <h3>👥 Wymagania dla drużyn</h3>
      <ul class="dr-lista">
        <li>Drużynę zgłasza jej kapitan.</li>
        <li>Wymagana jest pełna piątka: kapitan + 4 graczy. Rezerwowi (do 3) i trener są opcjonalni.</li>
        <li>Zgłoszenie można wycofać do czasu startu turnieju.</li>
        <li>Po starcie składy zgłoszonych drużyn są zablokowane.</li>
        <li>Drużyny z niepełnym składem w chwili startu są wykluczane z turnieju.</li>
      </ul>
    </div>
  </div>
</section>

</div><!-- /.dr-widok -->

<p><a href="turnieje.php">&larr; Wróć do listy turniejów</a></p>
<script src="<?= BASE_URL ?>/assets/zakladki.js"></script>
<?php require 'includes/footer.php'; ?>
