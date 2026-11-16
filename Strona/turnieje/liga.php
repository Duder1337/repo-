<?php
require_once 'includes/auth.php';
require_once 'includes/liga_funkcje.php';
require_once 'includes/druzyna_funkcje.php';

$liga = pobierzLige();
if (!$liga) {
    $tytul = 'Liga';
    require 'includes/header.php';
    echo '<h1>Liga</h1><p class="przygaszony">Liga nie została jeszcze utworzona.</p>';
    require 'includes/footer.php';
    exit;
}
$idLigi = (int)$liga['id_ligi'];
$ok = '';
$blad = '';

// Zgłaszanie i wycofywanie własnych drużyn (tylko w czasie zapisów)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wymagajLogowania();
    csrfSprawdz();
    $idD   = (int)($_POST['id_druzyny'] ?? 0);
    $akcja = $_POST['akcja'] ?? '';

    if (!czyKapitan($idD)) {
        $blad = 'Do ligi może zgłaszać drużynę tylko jej kapitan.';
    } elseif ($liga['status'] !== 'zapisy') {
        $blad = 'Zapisy do ligi są zamknięte.';
    } elseif ($akcja === 'zapisz' && !skladKompletny($idD)) {
        $blad = 'Drużyna musi mieć pełną piątkę (kapitan + 4 graczy), żeby wziąć udział w lidze.';
    } elseif ($akcja === 'zapisz') {
        $st = db()->prepare('SELECT COUNT(*) FROM liga_zgloszenie WHERE id_ligi = ?');
        $st->execute([$idLigi]);
        if ((int)$st->fetchColumn() >= (int)$liga['limit_druzyn']) {
            $blad = 'Limit drużyn w lidze został osiągnięty.';
        } else {
            try {
                db()->prepare('INSERT INTO liga_zgloszenie (id_ligi, id_druzyny) VALUES (?, ?)')->execute([$idLigi, $idD]);
                $ok = 'Drużyna została zgłoszona do ligi.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') $blad = 'Ta drużyna jest już zgłoszona.';
                else throw $ex;
            }
        }
    } elseif ($akcja === 'wycofaj') {
        db()->prepare('DELETE FROM liga_zgloszenie WHERE id_ligi = ? AND id_druzyny = ?')->execute([$idLigi, $idD]);
        $ok = 'Zgłoszenie zostało wycofane.';
    }
}

$druzyny = druzynyLigi($idLigi);
$zapisaneId = array_map('intval', array_column($druzyny, 'id_druzyny'));

// Zgłaszać może tylko kapitan swojej drużyny
$moje = [];
if (zalogowany()) {
    $md = mojaDruzyna();
    if ($md && $md['rola'] === 'kapitan') {
        $moje = [['id_druzyny' => $md['id_druzyny'], 'nazwa' => $md['nazwa']]];
    }
}

$zapisy = ($liga['status'] === 'zapisy');
$tabela = $zapisy ? [] : tabelaLigi($idLigi);
$mecze  = $zapisy ? [] : meczeLigi($idLigi);

$tytul = 'Liga';
require 'includes/header.php';
?>
<section class="baner-turnieju" style="--h:<?= (int)(crc32($liga['nazwa']) % 360) ?>">
  <span class="plakietka st-<?= $zapisy ? 'otwarty' : ($liga['status'] === 'w trakcie' ? 'trakcie' : 'koniec') ?>"><?= e(etykietaStatusuLigi($liga['status'])) ?></span>
  <small>Counter-Strike 2 · Liga</small>
  <h1><?= e($liga['nazwa']) ?></h1>
</section>

<div class="info-kafle">
  <div class="stat"><small>Zgłoszone drużyny</small><b><?= count($druzyny) ?> / <?= (int)$liga['limit_druzyn'] ?></b></div>
  <div class="stat"><small>Meczów na drużynę</small><b><?= LIGA_MECZE_NA_DRUZYNE ?></b></div>
  <div class="stat"><small>Format meczu</small><b>BO1</b></div>
  <div class="stat"><small>Za zwycięstwo</small><b><?= LIGA_PUNKTY_ZA_WYGRANA ?> pkt</b></div>
</div>

<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<?php if ($zapisy): ?>
  <h2>Zgłoś drużynę</h2>
  <?php if (!zalogowany()): ?>
    <p class="przygaszony"><a href="<?= BASE_URL ?>/login.php">Zaloguj się</a>, aby zgłosić swoją drużynę.</p>
  <?php elseif (!$moje): ?>
    <p class="przygaszony">Do ligi może zgłosić drużynę tylko jej kapitan (potrzebna pełna piątka). Możesz
      <a href="<?= BASE_URL ?>/druzyna_form.php">utworzyć drużynę</a> albo poprosić kapitana o zgłoszenie.</p>
  <?php else: ?>
    <div class="tabela-wrap"><table>
      <tr><th>Twoja drużyna</th><th>Status</th><th></th></tr>
      <?php foreach ($moje as $d): $jest = in_array((int)$d['id_druzyny'], $zapisaneId, true); ?>
        <tr>
          <td><?= e($d['nazwa']) ?></td>
          <td><?= $jest ? 'Zgłoszona' : '–' ?></td>
          <td>
            <form method="post" class="inline">
              <?= csrfPole() ?>
              <input type="hidden" name="id_druzyny" value="<?= (int)$d['id_druzyny'] ?>">
              <?php if ($jest): ?>
                <button name="akcja" value="wycofaj" class="drugi">Wycofaj</button>
              <?php else: ?>
                <button name="akcja" value="zapisz">Zgłoś do ligi</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>

  <p class="przygaszony">Liga wystartuje, gdy administrator rozpocznie rozgrywki (potrzeba min. <?= LIGA_MIN_DRUZYN ?> drużyn).
    Każda drużyna rozegra <?= LIGA_MECZE_NA_DRUZYNE ?> meczów z losowymi, różnymi przeciwnikami.</p>

  <h2>Zgłoszone drużyny</h2>
  <?php if (!$druzyny): ?>
    <p class="przygaszony">Brak zgłoszonych drużyn.</p>
  <?php else: ?>
    <ul class="druzyny-ligi">
      <?php foreach ($druzyny as $d): ?>
        <li><a href="druzyna.php?id=<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

<?php else: ?>
  <h2>Tabela ligowa</h2>
  <div class="tabela-wrap"><table class="tabela-liga">
    <tr><th class="liczba">#</th><th>Drużyna</th><th class="liczba">Mecze</th><th class="liczba">Zwycięstwa</th><th class="liczba">Porażki</th><th class="liczba">Punkty</th></tr>
    <?php foreach ($tabela as $i => $w): ?>
      <tr class="<?= $i < 3 ? 'top' : '' ?>">
        <td class="liczba"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></td>
        <td><a href="druzyna.php?id=<?= (int)$w['id_druzyny'] ?>"><?= e($w['nazwa']) ?></a></td>
        <td class="liczba"><?= $w['rozegrane'] ?> / <?= LIGA_MECZE_NA_DRUZYNE ?></td>
        <td class="liczba"><?= $w['wygrane'] ?></td>
        <td class="liczba"><?= $w['przegrane'] ?></td>
        <td class="liczba"><strong><?= $w['punkty'] ?></strong></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="przygaszony">Przy równej liczbie punktów decyduje liczba zwycięstw, a potem nazwa drużyny.</p>

  <h2>Mecze</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna A</th><th class="mecz-wynik">Wynik</th><th>Drużyna B</th></tr>
    <?php foreach ($mecze as $m): $roz = ($m['status'] === 'rozegrany'); ?>
      <tr>
        <td class="<?= $roz && (int)$m['id_zwyciezcy'] === (int)$m['id_druzyny_a'] ? 'mecz-wygrana' : '' ?>"><?= e($m['nazwa_a']) ?></td>
        <td class="mecz-wynik <?= $roz ? '' : 'mecz-oczekuje' ?>"><?= $roz ? (int)$m['wynik_a'] . ' : ' . (int)$m['wynik_b'] : 'vs' ?></td>
        <td class="<?= $roz && (int)$m['id_zwyciezcy'] === (int)$m['id_druzyny_b'] ? 'mecz-wygrana' : '' ?>"><?= e($m['nazwa_b']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
