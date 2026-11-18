<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
require_once 'includes/statystyki_funkcje.php';

$q        = trim($_GET['q'] ?? '');
$strona   = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 12;
$where    = $q !== '' ? 'WHERE d.nazwa LIKE :q' : '';
$param    = $q !== '' ? [':q' => '%' . addcslashes($q, '%_\\') . '%'] : [];

$st = db()->prepare("SELECT COUNT(*) FROM druzyna d $where");
$st->execute($param);
$razem  = (int)$st->fetchColumn();
$stron  = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$st = db()->prepare(
    "SELECT d.id_druzyny, d.nazwa,
            (SELECT z.nick FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
              WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan' LIMIT 1) AS kapitan,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny AND s.rola IN ('kapitan','gracz')) AS podstawowi,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'rezerwowy') AS rezerwowi,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'trener') AS trener
     FROM druzyna d $where ORDER BY d.nazwa LIMIT $naStrone OFFSET $offset"
);
$st->execute($param);
$druzyny = $st->fetchAll();

// Drużyna zalogowanego zawodnika i to, czy jest jej kapitanem
$mojaD = zalogowany() ? mojaDruzyna() : null;
$mojaId = $mojaD ? (int)$mojaD['id_druzyny'] : 0;
$kapitanId = ($mojaD && $mojaD['rola'] === 'kapitan') ? $mojaId : 0;

$tytul = 'Drużyny';
require 'includes/header.php';
?>
<div class="dk-gora">
  <h1>Drużyny <small class="przygaszony">(<?= $razem ?>)</small></h1>
  <form method="get" class="dk-szukaj" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Szukaj drużyny…" aria-label="Szukaj drużyny">
    <button type="submit">Szukaj</button>
    <?php if ($q !== ''): ?><a href="druzyny.php">Wyczyść</a><?php endif; ?>
  </form>
  <div class="dk-akcje">
    <?php if (!zalogowany()): ?>
      <span class="przygaszony"><a href="<?= BASE_URL ?>/login.php">Zaloguj się</a>, aby utworzyć drużynę.</span>
    <?php elseif (!$mojaD && mojZawodnik()): ?>
      <a class="przycisk" href="<?= BASE_URL ?>/druzyna_form.php">+ Utwórz drużynę</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$druzyny): ?>
  <p class="przygaszony"><?= $q !== '' ? 'Nie znaleziono drużyn pasujących do „' . e($q) . '”.' : 'Nie ma jeszcze żadnych drużyn.' ?></p>
<?php else: ?>
  <div class="dk-siatka">
    <?php foreach ($druzyny as $d):
        $id = (int)$d['id_druzyny'];
        $stat = statystykiDruzyny(historiaMeczow($id));
        $pelny = (int)$d['podstawowi'] >= 5;
        $moja  = $id === $mojaId;
    ?>
      <article class="dk<?= $moja ? ' dk-moja' : '' ?>" style="--h:<?= (int)(crc32($d['nazwa']) % 360) ?>">
        <div class="dk-pasek">
          <span class="dk-logo"><span><?= e(inicjalyDruzyny($d['nazwa'])) ?></span></span>
          <span class="dk-gra" aria-hidden="true">CS<b>2</b></span>
          <?php if ($moja): ?><span class="dk-znacznik moja">TWOJA</span>
          <?php elseif ($pelny): ?><span class="dk-znacznik">PEŁNY SKŁAD</span><?php endif; ?>
        </div>
        <div class="dk-tresc">
          <h3><a class="dk-link" href="druzyna.php?id=<?= $id ?>"><?= e($d['nazwa']) ?></a></h3>
          <small class="przygaszony">Kapitan: <?= e($d['kapitan'] ?? '–') ?></small>

          <div class="dk-sklad" title="Podstawowi: <?= (int)$d['podstawowi'] ?>/5, rezerwowi: <?= (int)$d['rezerwowi'] ?>/<?= LIMITY_ROL['rezerwowy'] ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= (int)$d['podstawowi'] ? 'zajety' : '' ?>"></i><?php endfor; ?>
            <?php for ($i = 1; $i <= (int)$d['rezerwowi']; $i++): ?><i class="rez"></i><?php endfor; ?>
            <span><?= (int)$d['podstawowi'] ?>/5<?= $d['rezerwowi'] ? ' + ' . (int)$d['rezerwowi'] . ' rez.' : '' ?><?= $d['trener'] ? ' · trener' : '' ?></span>
          </div>

          <div class="dk-stat">
            <div><b><?= $stat['rozegrane'] ?></b><small>Mecze</small></div>
            <div><b><?= $stat['wygrane'] ?></b><small>Wygrane</small></div>
            <div><b><?= $stat['punkty'] ?></b><small>Punkty</small></div>
          </div>
        </div>
        <?php if ($id === $kapitanId || maRole('administrator')): ?>
          <a class="dk-zarzadzaj" href="druzyna_form.php?id=<?= $id ?>">⚙ Zarządzaj</a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($stron > 1): ?>
  <p class="strony">
    <?php for ($i = 1; $i <= $stron; $i++): ?>
      <?php if ($i === $strona): ?><strong><?= $i ?></strong>
      <?php else: ?><a href="?<?= e(http_build_query(array_filter(['q' => $q, 'strona' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
