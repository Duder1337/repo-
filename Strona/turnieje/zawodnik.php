<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
require_once 'includes/zawodnik_funkcje.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM zawodnik WHERE id_zawodnika = ?');
$st->execute([$id]);
$w = $st->fetch();
if (!$w) { http_response_code(404); exit('Nie znaleziono zawodnika.'); }

$wlasny = zalogowany() && $w['id_uzytkownika'] !== null && (int)$w['id_uzytkownika'] === uzytkownik()['id'];
$ok = '';
$blad = '';

// Zdjęcie profilowe może zmieniać tylko właściciel profilu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$wlasny) { http_response_code(403); exit('Możesz zmieniać tylko własne zdjęcie.'); }
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    if ($akcja === 'zdjecie') {
        if (zapiszZdjecie($id, $_FILES['zdjecie'] ?? [], $blad)) $ok = 'Zdjęcie zostało zapisane.';
    } elseif ($akcja === 'usun_zdjecie') {
        usunZdjecie($id);
        $ok = 'Zdjęcie zostało usunięte.';
    }
    $st = db()->prepare('SELECT * FROM zawodnik WHERE id_zawodnika = ?');
    $st->execute([$id]);
    $w = $st->fetch();
}

$druzyna = druzynaZawodnika($id);
$profil  = daneProfilu($id);
$stat    = $profil['stat'];
$h       = crc32($w['nick']) % 360;

$tytul = $w['nick'];
require 'includes/header.php';
?>
<section class="baner-turnieju" style="--h:<?= (int)$h ?>">
  <div class="profil-gora">
    <?= awatarHtml($w, 'duzy') ?>
    <div>
      <small>Profil zawodnika</small>
      <h1><?= e($w['nick']) ?></h1>
      <?php if (zalogowany()): ?><p class="przygaszony" style="margin:0"><?= e($w['imie'] . ' ' . $w['nazwisko']) ?></p><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($ok): ?><p class="ok"><?= e($ok) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>

<h2>Drużyna</h2>
<?php if ($druzyna): ?>
  <p><a href="druzyna.php?id=<?= (int)$druzyna['id_druzyny'] ?>"><strong><?= e($druzyna['nazwa']) ?></strong></a>
    · <?= e(etykietaRoli($druzyna['rola'])) ?></p>
<?php else: ?>
  <p class="przygaszony">Zawodnik nie należy obecnie do żadnej drużyny.</p>
<?php endif; ?>

<h2>Statystyki</h2>
<div class="info-kafle">
  <div class="stat"><small>Rozegrane mecze</small><b><?= $stat['mecze'] ?></b></div>
  <div class="stat"><small>Zwycięstwa</small><b><?= $stat['wygrane'] ?></b></div>
  <div class="stat"><small>Porażki</small><b><?= $stat['przegrane'] ?></b></div>
  <div class="stat"><small>Skuteczność</small><b><?= $stat['procent'] ?>%</b></div>
  <div class="stat"><small>Najdłuższa seria wygranych</small><b><?= $stat['seria'] ?></b></div>
</div>
<div class="tabela-wrap"><table>
  <tr><th>Rozgrywki</th><th>Mecze</th><th>Zwycięstwa</th><th>Porażki</th></tr>
  <tr><td>Liga</td><td><?= $stat['liga']['mecze'] ?></td><td><?= $stat['liga']['wygrane'] ?></td>
      <td><?= $stat['liga']['mecze'] - $stat['liga']['wygrane'] ?></td></tr>
  <tr><td>Turnieje</td><td><?= $stat['turniej']['mecze'] ?></td><td><?= $stat['turniej']['wygrane'] ?></td>
      <td><?= $stat['turniej']['mecze'] - $stat['turniej']['wygrane'] ?></td></tr>
</table></div>
<p class="przygaszony">Do statystyk liczą się mecze, w których zawodnik grał w podstawowym składzie (kapitan lub gracz).</p>

<h2>Historia turniejów i lig</h2>
<?php if (!$profil['rozgrywki']): ?>
  <p class="przygaszony">Zawodnik nie rozegrał jeszcze żadnych meczów.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Rozgrywki</th><th>Typ</th><th>Drużyna</th><th>Mecze</th><th>W</th><th>P</th></tr>
    <?php foreach ($profil['rozgrywki'] as $r): ?>
      <tr>
        <td>
          <?php if ($r['id_turnieju']): ?><a href="turniej.php?id=<?= (int)$r['id_turnieju'] ?>"><?= e($r['nazwa']) ?></a>
          <?php elseif ($r['typ'] === 'Liga'): ?><a href="liga.php"><?= e($r['nazwa']) ?></a>
          <?php else: ?><?= e($r['nazwa']) ?><?php endif; ?>
        </td>
        <td><?= e($r['typ']) ?></td>
        <td><?= e($r['druzyna']) ?></td>
        <td><?= $r['mecze'] ?></td><td><?= $r['wygrane'] ?></td><td><?= $r['mecze'] - $r['wygrane'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>

  <h2>Ostatnie mecze</h2>
  <div class="tabela-wrap"><table>
    <tr><th>Rozgrywki</th><th>Drużyna</th><th>Przeciwnik</th><th class="mecz-wynik">Wynik</th><th>Rezultat</th></tr>
    <?php foreach (array_slice($profil['mecze'], 0, 20) as $m): $wyg = ((int)$m['wygrana'] === 1); ?>
      <tr>
        <td><?= e($m['rozgrywki']) ?></td>
        <td><?= e($m['druzyna']) ?></td>
        <td><?= e($m['przeciwnik'] ?? '–') ?></td>
        <td class="mecz-wynik"><?= (int)$m['moje'] ?> : <?= (int)$m['ich'] ?></td>
        <td class="<?= $wyg ? 'mecz-wygrana' : '' ?>"><?= $wyg ? 'Zwycięstwo' : 'Porażka' ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

<?php if ($wlasny): ?>
  <h2>Zdjęcie profilowe</h2>
  <form method="post" enctype="multipart/form-data" class="formularz">
    <?= csrfPole() ?>
    <label>Wybierz zdjęcie (JPG, PNG lub WEBP, max 2 MB)
      <input type="file" name="zdjecie" accept="image/jpeg,image/png,image/webp" required>
    </label>
    <div class="przyciski-rzad">
      <button name="akcja" value="zdjecie">Zapisz zdjęcie</button>
      <?php if ($w['zdjecie']): ?>
        <button name="akcja" value="usun_zdjecie" class="drugi" formnovalidate>Usuń zdjęcie</button>
      <?php endif; ?>
    </div>
  </form>
<?php endif; ?>
<p><a href="zawodnicy.php">&larr; Wróć do listy zawodników</a></p>
<?php require 'includes/footer.php'; ?>
