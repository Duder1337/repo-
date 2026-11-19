<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/symulacja_funkcje.php';
wymagajRoli('administrator');
zapewnijTabeleUdzialu();
zapewnijKolumnyTestowe();
zapewnijKolumnyOpisuTurnieju();

// Akcje: wynik zapamiętujemy w sesji i przekierowujemy, żeby odświeżenie strony nie powtórzyło operacji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $akcja = $_POST['akcja'] ?? '';
    $f = [];
    if ($akcja === 'utworz') {
        $f = stworzSymulacje((int)uzytkownik()['id']);
    } elseif ($akcja === 'faza') {
        $f = symulujNastepnaFaze((int)($_POST['id'] ?? 0));
    } elseif ($akcja === 'usun') {
        $w = usunSymulacje();
        $f = ['ok' => 'Symulacja usunięta (turnieje: ' . $w['turnieje'] . ', drużyny: ' . $w['druzyny'] . ', zawodnicy: ' . $w['zawodnicy'] . ').'];
    }
    $_SESSION['flash_sym'] = $f;
    header('Location: symulacja.php');
    exit;
}
$f = $_SESSION['flash_sym'] ?? [];
unset($_SESSION['flash_sym']);

$pdo = db();
$pobierz = function (string $nazwa) use ($pdo) {
    $st = $pdo->prepare('SELECT t.*, d.nazwa AS zwyciezca FROM turniej t
                         LEFT JOIN druzyna d ON d.id_druzyny = t.id_zwyciezcy
                         WHERE t.nazwa = ? ORDER BY t.id_turnieju DESC LIMIT 1');
    $st->execute([$nazwa]);
    return $st->fetch() ?: null;
};
$t       = $pobierz(SYM_NAZWA_AKTUALNY);
$poprz   = $pobierz(SYM_NAZWA_POPRZEDNI);
$drabinka = $t ? pobierzDrabinke((int)$t['id_turnieju']) : [];
$faza    = ($t && $t['status'] === 'w trakcie') ? nastepnaFaza($t, $drabinka) : null;

// Statystyki zawodników: tylko z meczów aktualnej edycji oraz łącznie z obu edycji symulacji
$gracze = [];
if ($t) {
    $ids = [(int)$t['id_turnieju']];
    if ($poprz) $ids[] = (int)$poprz['id_turnieju'];
    $in = implode(',', array_map('intval', $ids));   // same liczby całkowite z bazy
    $gracze = $pdo->query(
        "SELECT w.id_zawodnika, w.nick, d.nazwa AS druzyna,
                COUNT(*) AS mecze, SUM(u.wygrana) AS wygrane,
                SUM(CASE WHEN m.id_turnieju = " . (int)$t['id_turnieju'] . " THEN 1 ELSE 0 END) AS mecze_teraz
         FROM udzial_zawodnika u
         JOIN mecz m ON m.id_meczu = u.id_meczu_turnieju AND m.id_turnieju IN ($in)
         JOIN zawodnik w ON w.id_zawodnika = u.id_zawodnika
         JOIN druzyna d ON d.id_druzyny = u.id_druzyny
         GROUP BY w.id_zawodnika, w.nick, d.nazwa
         ORDER BY wygrane DESC, mecze DESC, w.nick ASC
         LIMIT 20"
    )->fetchAll();
}

$tytul = 'Symulacja turnieju';
require __DIR__ . '/../includes/header.php';
?>
<h1>🎮 Symulacja turnieju</h1>
<p class="przygaszony">8 drużyn, drabinka pucharowa i gotowe statystyki zawodników. Przycisk „Symuluj następną fazę” losuje wyniki
  wszystkich meczów najbliższej rundy, awansuje zwycięzców i aktualizuje ranking oraz profile zawodników.</p>

<?php if (!empty($f['ok'])): ?><p class="ok"><?= e($f['ok']) ?></p><?php endif; ?>
<?php if (!empty($f['blad'])): ?><p class="blad"><?= e($f['blad']) ?></p><?php endif; ?>

<?php if (!$t): ?>
  <form method="post" class="formularz">
    <?= csrfPole() ?>
    <input type="hidden" name="akcja" value="utworz">
    <p>Po kliknięciu powstanie: 8 drużyn testowych (pełne piątki), <b>zakończona edycja poprzednia</b> (żeby zawodnicy mieli już statystyki)
       oraz <b>edycja aktualna</b> z wylosowaną drabinką, w której nic jeszcze nie zostało rozegrane.</p>
    <button class="przycisk">▶ Utwórz symulację (8 drużyn)</button>
  </form>
<?php else: ?>
  <div class="info-kafle">
    <div class="stat"><small>Turniej</small><b style="font-size:1rem"><?= e($t['nazwa']) ?></b></div>
    <div class="stat"><small>Status</small><b style="font-size:1rem"><?= e(etykietaStatusu($t['status'])) ?></b></div>
    <div class="stat"><small>Format</small><b style="font-size:1rem"><?= e(opisFormatu($t)) ?></b></div>
    <div class="stat"><small>Zwycięzca</small><b style="font-size:1rem"><?= $t['zwyciezca'] ? '🏆 ' . e($t['zwyciezca']) : '–' ?></b></div>
  </div>

  <div class="tz-linki">
    <?php if ($faza): ?>
      <form method="post" style="display:inline">
        <?= csrfPole() ?>
        <input type="hidden" name="akcja" value="faza">
        <input type="hidden" name="id" value="<?= (int)$t['id_turnieju'] ?>">
        <button class="przycisk">▶ Symuluj następną fazę: <?= e($faza['nazwa']) ?> (<?= e(symMeczow(count($faza['do_gry']))) ?>, <?= e($faza['format']) ?>)</button>
      </form>
    <?php elseif ($t['status'] === 'zakończony'): ?>
      <span class="ok">Turniej zakończony – zwycięzca: <b><?= e($t['zwyciezca']) ?></b></span>
    <?php endif; ?>
    <form method="post" style="display:inline">
      <?= csrfPole() ?>
      <input type="hidden" name="akcja" value="utworz">
      <button class="przycisk drugi" onclick="return confirm('Usunąć obecną symulację i utworzyć nową?')">🔄 Zacznij od nowa</button>
    </form>
    <form method="post" style="display:inline">
      <?= csrfPole() ?>
      <input type="hidden" name="akcja" value="usun">
      <button class="przycisk drugi" onclick="return confirm('Usunąć symulację wraz z drużynami i zawodnikami testowymi?')">🗑️ Usuń symulację</button>
    </form>
  </div>
  <p>
    <a href="../turniej.php?id=<?= (int)$t['id_turnieju'] ?>#drabinka">🌐 Strona turnieju</a> ·
    <a href="../ranking.php">📊 Ranking drużyn</a> ·
    <a href="../zawodnicy.php">👤 Lista zawodników</a>
    <?php if ($poprz): ?> · <a href="../turniej.php?id=<?= (int)$poprz['id_turnieju'] ?>#drabinka">📜 Edycja poprzednia</a><?php endif; ?>
  </p>

  <h2>Drabinka</h2>
  <div class="drabinka">
    <?php foreach ($drabinka as $nr => $mecze): $ile = count($mecze); ?>
      <div class="dr-runda">
        <h3><?= e(nazwaRundy($ile)) ?> <small><?= e(formatRundy($t, $ile)) ?></small></h3>
        <div class="dr-mecze">
          <?php foreach ($mecze as $m):
              $strony = [['id_druzyny_a', 'nazwa_a', 'wynik_a'], ['id_druzyny_b', 'nazwa_b', 'wynik_b']]; ?>
            <div class="dr-mecz<?= $m['status'] === 'rozegrany' ? ' rozegrany' : '' ?>">
              <?php foreach ($strony as [$kId, $kNazwa, $kWynik]): ?>
                <div class="dr-strona<?= ($m['id_zwyciezcy'] !== null && (int)$m['id_zwyciezcy'] === (int)$m[$kId]) ? ' wygrana' : '' ?>">
                  <?php if ($m[$kId] !== null): ?>
                    <a href="../druzyna.php?id=<?= (int)$m[$kId] ?>"><?= e($m[$kNazwa]) ?></a>
                  <?php else: ?>
                    <span class="przygaszony">do ustalenia</span>
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

  <h2>Statystyki zawodników <small class="przygaszony">(obie edycje symulacji)</small></h2>
  <?php if (!$gracze): ?>
    <p class="przygaszony">Brak statystyk.</p>
  <?php else: ?>
    <div class="tabela-wrap"><table>
      <tr><th>#</th><th>Zawodnik</th><th>Drużyna</th><th>Mecze</th><th>Wygrane</th><th>% wygranych</th><th>W tej edycji</th></tr>
      <?php foreach ($gracze as $i => $g): $mecze = (int)$g['mecze']; $wyg = (int)$g['wygrane']; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><a href="../zawodnik.php?id=<?= (int)$g['id_zawodnika'] ?>"><?= e($g['nick']) ?></a></td>
          <td><?= e($g['druzyna']) ?></td>
          <td><?= $mecze ?></td>
          <td><?= $wyg ?></td>
          <td><?= $mecze ? (int)round(100 * $wyg / $mecze) : 0 ?>%</td>
          <td><?= (int)$g['mecze_teraz'] ?> meczów</td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="przygaszony">Tabela pokazuje 20 najlepszych. Pełną historię (mecz po meczu) zobaczysz w profilu każdego zawodnika.</p>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
