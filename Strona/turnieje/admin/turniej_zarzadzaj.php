<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/turnieje_funkcje.php';
wymagajRoli('administrator');
usunStareAnulowane();
zapewnijKolumnyOpisuTurnieju();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM turniej WHERE id_turnieju = ?');
$st->execute([$id]);
if (!$st->fetch()) { http_response_code(404); exit('Nie znaleziono turnieju.'); }

// Akcje: wynik zapamiętujemy w sesji i przekierowujemy, żeby odświeżenie strony nie powtarzało operacji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $f = [];
    if ($akcja === 'opublikuj') {
        $f = opublikujTurniej($id) ? ['ok' => 'Turniej opublikowany – zapisy są otwarte.'] : ['blad' => 'Nie można opublikować tego turnieju.'];
    } elseif ($akcja === 'wystartuj') {
        $wykl = [];
        $b = wystartujTurniej($id, $wykl);
        if ($b === '') {
            $f['ok'] = 'Turniej wystartował: zapisy zamknięte, drabinka wylosowana.';
            if ($wykl) $f['ok'] .= ' Wykluczono drużyny z niepełnym składem: ' . implode(', ', $wykl) . '.';
        } else {
            $f['blad'] = $b;
        }
    } elseif ($akcja === 'anuluj') {
        $f = anulujTurniej($id)
            ? ['ok' => 'Turniej anulowany. Zniknie z listy po ' . ANULOWANE_USUWAJ_PO_DNIACH . ' dniach.']
            : ['blad' => 'Tego turnieju nie można anulować.'];
    } elseif ($akcja === 'usun_turniej') {
        $b = usunTurniej($id);
        $_SESSION['flash_turniej'] = $b === '' ? ['ok' => 'Turniej został usunięty.'] : ['blad' => $b];
        header('Location: ' . ($b === '' ? 'turnieje.php' : 'turniej_zarzadzaj.php?id=' . $id));
        exit;
    } elseif (in_array($akcja, ['dodaj', 'odrzuc', 'usun'], true)) {
        $b = zmienZgloszenie($id, (int)($_POST['id_druzyny'] ?? 0), $akcja);
        $f = $b === '' ? ['ok' => ['dodaj' => 'Drużyna została zgłoszona do turnieju.', 'odrzuc' => 'Zgłoszenie zostało odrzucone.',
                                    'usun' => 'Zgłoszenie zostało usunięte.'][$akcja]] : ['blad' => $b];
    }
    $_SESSION['flash_turniej'] = $f;
    header('Location: turniej_zarzadzaj.php?id=' . $id);
    exit;
}
$f = $_SESSION['flash_turniej'] ?? [];
unset($_SESSION['flash_turniej']);

$st = db()->prepare('SELECT * FROM turniej WHERE id_turnieju = ?');
$st->execute([$id]);
$t = $st->fetch();

$zgloszenia = zgloszeniaTurnieju($id);
$przyjete = array_values(array_filter($zgloszenia, fn($z) => $z['status'] === 'przyjęte'));
$pelne    = count(array_filter($przyjete, fn($z) => $z['kompletny']));
$otwarty  = $t['status'] === 'otwarty';
$doDodania = $otwarty ? druzynyDoDodania($id) : [];
$etykietyZgl = ['przyjęte' => 'Przyjęte', 'odrzucone' => 'Odrzucone', 'wycofane' => 'Wycofane'];

$tytul = 'Turniej: ' . $t['nazwa'];
require __DIR__ . '/../includes/header.php';
?>
<div class="tz-gora">
  <h1><?= e($t['nazwa']) ?></h1>
  <span class="tz-status st-<?= e(klasaStatusu($t['status'])) ?>"><?= e(etykietaStatusu($t['status'])) ?></span>
</div>
<?php if (!empty($t['podtytul'])): ?><p class="przygaszony"><?= e($t['podtytul']) ?></p><?php endif; ?>
<p class="tz-linki">
  <a href="../turniej.php?id=<?= (int)$id ?>">🌐 Zobacz stronę turnieju</a>
  <?php if (in_array($t['status'], ['szkic', 'otwarty'], true)): ?><a href="turniej_form.php?id=<?= (int)$id ?>">✏️ Edytuj dane</a><?php endif; ?>
  <?php if (in_array($t['status'], ['w trakcie', 'zakończony'], true)): ?><a href="../turniej.php?id=<?= (int)$id ?>#drabinka">🏆 Drabinka</a><?php endif; ?>
</p>

<?php if (!empty($f['ok'])): ?><p class="ok"><?= e($f['ok']) ?></p><?php endif; ?>
<?php if (!empty($f['blad'])): ?><p class="blad"><?= e($f['blad']) ?></p><?php endif; ?>

<div class="info-kafle">
  <div class="stat"><small>Data startu</small><b><?= e($t['data_startu']) ?></b></div>
  <div class="stat"><small>Zgłoszone drużyny</small><b><?= count($przyjete) ?> / <?= (int)$t['limit_druzyn'] ?></b></div>
  <div class="stat"><small>Z pełnym składem</small><b><?= $pelne ?></b></div>
  <div class="stat"><small>Format meczu</small><b><?= e(opisFormatu($t)) ?></b></div>
</div>

<h2>Etap turnieju</h2>
<div class="dr-box tz-etap">
  <?php if ($t['status'] === 'szkic'): ?>
    <p>Turniej jest szkicem – widzisz go tylko Ty. Po opublikowaniu ruszą zapisy dla kapitanów drużyn.</p>
    <form method="post" class="inline"><?= csrfPole() ?>
      <button name="akcja" value="opublikuj">Opublikuj i otwórz zapisy</button>
      <button name="akcja" value="anuluj" class="drugi" onclick="return confirm('Anulować turniej?')">Anuluj turniej</button>
    </form>
  <?php elseif ($otwarty): ?>
    <p>Zapisy są otwarte. Start zamyka zapisy, losuje drabinkę i wyklucza drużyny z niepełnym składem.
      <?php if (count($przyjete) < TURNIEJ_MIN_DRUZYN): ?><br><span class="przygaszony">Do startu potrzeba co najmniej <?= TURNIEJ_MIN_DRUZYN ?> zgłoszonych drużyn.</span><?php endif; ?></p>
    <form method="post" class="inline"><?= csrfPole() ?>
      <button name="akcja" value="wystartuj" <?= count($przyjete) < TURNIEJ_MIN_DRUZYN ? 'disabled' : '' ?>
              onclick="return confirm('Wystartować turniej? Zapisy zostaną zamknięte, a drabinka wylosowana. Drużyny z niepełnym składem zostaną wykluczone.')">▶ Wystartuj turniej</button>
      <button name="akcja" value="anuluj" class="drugi" onclick="return confirm('Anulować turniej?')">Anuluj turniej</button>
    </form>
  <?php elseif ($t['status'] === 'w trakcie'): ?>
    <p>Turniej trwa – zapisy są zamknięte, a składy drużyn zablokowane. Przebieg rozgrywek jest w zakładce Drabinka na stronie turnieju.</p>
  <?php elseif ($t['status'] === 'zakończony'): ?>
    <p>Turniej został zakończony.</p>
  <?php else: ?>
    <p>Turniej został anulowany i zniknie z listy po <?= (int)ANULOWANE_USUWAJ_PO_DNIACH ?> dniach od anulowania.</p>
  <?php endif; ?>
</div>

<h2>Zgłoszone drużyny</h2>
<?php if (!$zgloszenia): ?>
  <p class="przygaszony">Żadna drużyna nie zgłosiła się jeszcze do turnieju.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>#</th><th>Drużyna</th><th>Kapitan</th><th>Skład</th><th>Zgłoszenie</th><th>Data</th><?php if ($otwarty): ?><th>Akcje</th><?php endif; ?></tr>
    <?php $nr = 0; foreach ($zgloszenia as $z): ?>
      <tr>
        <td><?= $z['status'] === 'przyjęte' ? ++$nr : '–' ?></td>
        <td><a href="../druzyna.php?id=<?= (int)$z['id_druzyny'] ?>"><?= e($z['nazwa']) ?></a></td>
        <td><?= e($z['kapitan'] ?? '–') ?></td>
        <td><?= $z['kompletny'] ? '✔ pełny' : '<span class="przygaszony" title="Przy starcie drużyna zostanie wykluczona">✖ niepełny</span>' ?></td>
        <td><?= e($etykietyZgl[$z['status']] ?? $z['status']) ?></td>
        <td><?= e($z['data_zgloszenia']) ?></td>
        <?php if ($otwarty): ?>
        <td class="akcje">
          <form method="post" class="inline"><?= csrfPole() ?>
            <input type="hidden" name="id_druzyny" value="<?= (int)$z['id_druzyny'] ?>">
            <?php if ($z['status'] === 'przyjęte'): ?>
              <button name="akcja" value="odrzuc" class="drugi" onclick="return confirm('Odrzucić zgłoszenie drużyny?')">Odrzuć</button>
            <?php else: ?>
              <button name="akcja" value="dodaj" class="drugi">Przywróć</button>
            <?php endif; ?>
            <button name="akcja" value="usun" class="drugi" onclick="return confirm('Usunąć zgłoszenie?')">Usuń</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

<?php if ($otwarty): ?>
  <h2>Dodaj drużynę ręcznie</h2>
  <?php if (!$doDodania): ?>
    <p class="przygaszony">Brak drużyn z pełnym składem, które można dodać.</p>
  <?php else: ?>
    <form method="post" class="formularz szeroki"><?= csrfPole() ?>
      <label>Drużyna z pełnym składem
        <select name="id_druzyny" required>
          <?php foreach ($doDodania as $d): ?><option value="<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="przyciski-rzad"><button name="akcja" value="dodaj" <?= count($przyjete) >= (int)$t['limit_druzyn'] ? 'disabled' : '' ?>>Zgłoś drużynę do turnieju</button></div>
      <small class="przygaszony">Do rozgrywek potrzebna jest pełna piątka (kapitan + 4 graczy). Limit: <?= (int)$t['limit_druzyn'] ?> drużyn.</small>
    </form>
  <?php endif; ?>
<?php endif; ?>

<h2>Usuwanie turnieju</h2>
<div class="dr-box tz-etap">
  <p>Usuwa turniej na stałe razem z drabinką, wynikami i zgłoszeniami. Drużyny i zawodnicy zostają, a drużyny zgłoszone do turnieju
    odzyskują możliwość zmiany składu.</p>
  <form method="post" class="inline"><?= csrfPole() ?>
    <button name="akcja" value="usun_turniej" class="drugi"
            onclick="return confirm('Usunąć turniej „<?= e(addslashes($t['nazwa'])) ?>” na stałe? Tej operacji nie można cofnąć.')">🗑 Usuń turniej</button>
  </form>
</div>

<p><a href="turnieje.php">&larr; Wróć do listy turniejów</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
