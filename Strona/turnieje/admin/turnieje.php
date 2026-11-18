<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/turnieje_funkcje.php';
wymagajRoli('administrator');
usunStareAnulowane();

$komunikat = '';
$blad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $id = (int)($_POST['id'] ?? 0);
    $akcja = $_POST['akcja'] ?? '';
    if ($akcja === 'opublikuj') {
        $komunikat = opublikujTurniej($id) ? 'Turniej opublikowany.' : 'Nie można opublikować tego turnieju.';
    } elseif ($akcja === 'wystartuj') {
        $wykl = [];
        $blad = wystartujTurniej($id, $wykl);
        if ($blad === '') {
            $komunikat = 'Turniej wystartował: zapisy zamknięte, drabinka wylosowana.';
            if ($wykl) $komunikat .= ' Wykluczono drużyny z niepełnym składem: ' . implode(', ', $wykl) . '.';
        }
    } elseif ($akcja === 'usun') {
        $blad = usunTurniej($id);
        if ($blad === '') $komunikat = 'Turniej został usunięty.';
    } elseif ($akcja === 'anuluj') {
        $komunikat = anulujTurniej($id)
            ? 'Turniej anulowany. Zniknie z listy po ' . ANULOWANE_USUWAJ_PO_DNIACH . ' dniach.'
            : 'Tego turnieju nie można anulować.';
    }
}

// Komunikat z innej strony panelu (np. po usunięciu turnieju ze strony zarządzania)
$flash = $_SESSION['flash_turniej'] ?? [];
unset($_SESSION['flash_turniej']);
if (!empty($flash['ok']) && $komunikat === '') $komunikat = $flash['ok'];
if (!empty($flash['blad']) && $blad === '') $blad = $flash['blad'];

$turnieje = db()->query(
    "SELECT t.*, (SELECT COUNT(*) FROM zgloszenie z
                  WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zgloszone,
            TIMESTAMPDIFF(HOUR, NOW(), DATE_ADD(t.data_anulowania, INTERVAL " . (int)ANULOWANE_USUWAJ_PO_DNIACH . " DAY)) AS godzin_do_usuniecia
     FROM turniej t ORDER BY t.data_startu DESC"
)->fetchAll();

$tytul = 'Zarządzaj turniejami';
require __DIR__ . '/../includes/header.php';
?>
<h1>Zarządzaj turniejami</h1>
<?php if ($komunikat): ?><p class="ok"><?= e($komunikat) ?></p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>
<p><a class="przycisk" href="turniej_form.php">+ Dodaj turniej</a></p>

<?php if (!$turnieje): ?>
  <p>Brak turniejów.</p>
<?php else: ?>
<div class="tabela-wrap"><table>
  <tr><th>Nazwa</th><th>Start</th><th>Status</th><th>Drużyny</th><th>Akcje</th></tr>
  <?php foreach ($turnieje as $t): ?>
    <tr>
      <td><a href="../turniej.php?id=<?= (int)$t['id_turnieju'] ?>"><?= e($t['nazwa']) ?></a></td>
      
      <td><?= e($t['data_startu']) ?></td>
      <td><?= e($t['status']) ?>
        <?php if ($t['status'] === 'anulowany' && $t['godzin_do_usuniecia'] !== null): $h = max(0, (int)$t['godzin_do_usuniecia']); ?>
          <br><small class="przygaszony">zniknie <?= $h >= 24 ? 'za ' . (int)ceil($h / 24) . ' dn.' : 'w ciągu doby' ?></small>
        <?php endif; ?></td>
      <td><?= (int)$t['zgloszone'] ?> / <?= (int)$t['limit_druzyn'] ?></td>
      <td class="akcje">
        <?php if ($t['status'] !== 'anulowany'): ?><a href="turniej_zarzadzaj.php?id=<?= (int)$t['id_turnieju'] ?>">Zarządzaj</a><?php endif; ?>
        <?php if (in_array($t['status'], ['szkic', 'otwarty'], true)): ?>
          <a href="turniej_form.php?id=<?= (int)$t['id_turnieju'] ?>">Edytuj</a>
          <form method="post" class="inline">
            <?= csrfPole() ?>
            <input type="hidden" name="id" value="<?= (int)$t['id_turnieju'] ?>">
            <?php if ($t['status'] === 'szkic'): ?>
              <button name="akcja" value="opublikuj">Opublikuj</button>
            <?php endif; ?>
            <?php if ($t['status'] === 'otwarty'): ?>
              <button name="akcja" value="wystartuj"
                      <?= (int)$t['zgloszone'] < TURNIEJ_MIN_DRUZYN ? 'disabled title="Do startu potrzeba co najmniej ' . TURNIEJ_MIN_DRUZYN . ' zgłoszonych drużyn"' : '' ?>
                      onclick="return confirm('Wystartować turniej? Zapisy zostaną zamknięte, a drabinka wylosowana. Drużyny z niepełnym składem zostaną wykluczone.')">▶ Wystartuj</button>
            <?php endif; ?>
            <button name="akcja" value="anuluj" class="drugi" onclick="return confirm('Anulować turniej?')">Anuluj</button>
          </form>
        <?php elseif (in_array($t['status'], ['w trakcie', 'zakończony'], true)): ?>
          <a href="../turniej.php?id=<?= (int)$t['id_turnieju'] ?>#drabinka">Drabinka</a>
        <?php endif; ?>
        <form method="post" class="inline"><?= csrfPole() ?>
          <input type="hidden" name="id" value="<?= (int)$t['id_turnieju'] ?>">
          <button name="akcja" value="usun" class="drugi"
                  onclick="return confirm('Usunąć turniej na stałe razem z drabinką i zgłoszeniami?')">Usuń</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
