<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
wymagajLogowania();

$id = (int)($_GET['id'] ?? 0);
$ja = uzytkownik()['id'];
$d  = null;

if ($id) {
    $st = db()->prepare('SELECT * FROM druzyna WHERE id_druzyny = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) { http_response_code(404); exit('Nie znaleziono drużyny.'); }
    // Użytkownik zarządza wyłącznie własnymi drużynami (WN-07)
    if ((int)$d['id_wlasciciela'] !== $ja && !maRole('administrator')) {
        http_response_code(403); exit('Możesz edytować tylko własne drużyny.');
    }
    if (druzynaZablokowana($id)) {
        exit('Nie można zmieniać składu drużyny zgłoszonej do trwającego turnieju.');
    }
} else {
    $st = db()->prepare('SELECT COUNT(*) FROM druzyna WHERE id_wlasciciela = ?');
    $st->execute([$ja]);
    if ((int)$st->fetchColumn() >= 3) exit('Możesz utworzyć maksymalnie 3 drużyny.');
}

$bledy = [];
$nazwa = $d['nazwa'] ?? '';
if ($d) {
    $pola = skladDoPol(pobierzSklad($id));
} else {
    $pola = array_fill_keys(POLA_SKLADU, '');
    // Domyślnie kapitanem jest twórca drużyny (można to zmienić kliknięciem w kartę)
    $st = db()->prepare('SELECT nick FROM zawodnik WHERE id_uzytkownika = ?');
    $st->execute([$ja]);
    $mojNick = $st->fetchColumn();
    if ($mojNick) $pola['kapitan'] = $mojNick;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $nazwa = trim($_POST['nazwa'] ?? '');
    if (mb_strlen($nazwa) < 3 || mb_strlen($nazwa) > 30) {
        $bledy[] = 'Nazwa drużyny musi mieć od 3 do 30 znaków.';
    }
    [$sklad, $pola] = wczytajSklad($_POST, $bledy);

    if (!$bledy) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($d) {
                $idDruzyny = $id;
                $pdo->prepare('UPDATE druzyna SET nazwa = ? WHERE id_druzyny = ?')->execute([$nazwa, $idDruzyny]);
                $pdo->prepare('DELETE FROM sklad WHERE id_druzyny = ?')->execute([$idDruzyny]);
            } else {
                $pdo->prepare('INSERT INTO druzyna (nazwa, id_wlasciciela) VALUES (?, ?)')->execute([$nazwa, $ja]);
                $idDruzyny = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO ranking (id_druzyny) VALUES (?)')->execute([$idDruzyny]);
            }
            $ins = $pdo->prepare('INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, ?)');
            foreach ($sklad as $w) $ins->execute([$idDruzyny, $w[0], $w[1]]);
            $pdo->commit();
            header('Location: ' . BASE_URL . '/druzyna.php?id=' . $idDruzyny);
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($ex->getCode() === '23000') {
                $bledy[] = 'Drużyna o takiej nazwie już istnieje.';
            } else {
                throw $ex;
            }
        }
    }
}

// Karta zawodnika (kliknięcie otwiera wyszukiwarkę zarejestrowanych graczy)
function kartaGracza(string $klucz, string $etykieta, array $pola, bool $wymagane, bool $mala = false): void {
    $nick  = $pola[$klucz] ?? '';
    $puste = ($nick === '');
    ?>
    <div class="gcard<?= $mala ? ' mala' : '' ?><?= $puste ? ' pusta' : '' ?>" role="button" tabindex="0"
         data-etykieta="<?= e($etykieta) ?>" data-wymagane="<?= $wymagane ? '1' : '0' ?>">
      <div class="gcard-in">
        <span class="gcard-rola"><?= e($etykieta) ?></span>
        <button type="button" class="gcard-x" title="Usuń z drużyny">&times;</button>
        <div class="gcard-avatar"><?= $puste ? '+' : e(mb_strtoupper(mb_substr($nick, 0, 1))) ?></div>
        <div class="gcard-nick"><?= e($nick) ?></div>
      </div>
      <input type="hidden" name="<?= e($klucz) ?>" value="<?= e($nick) ?>">
    </div>
<?php }

$tytul = $d ? 'Edytuj drużynę' : 'Utwórz drużynę';
require 'includes/header.php';
?>
<h1><?= e($tytul) ?></h1>
<p class="przygaszony">Kliknij kartę, aby wyszukać zarejestrowanego zawodnika. Pierwsza karta to kapitan (domyślnie Ty), potem 4 graczy podstawowego składu. Rezerwowi i trener są opcjonalni.</p>

<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>

<form method="post" class="kreator" data-szukaj="<?= BASE_URL ?>/szukaj_zawodnika.php">
  <?= csrfPole() ?>

  <label class="kreator-nazwa">Nazwa drużyny
    <input type="text" name="nazwa" value="<?= e($nazwa) ?>" minlength="3" maxlength="30" placeholder="np. The Lycans" required>
  </label>

  <section>
    <h2>Podstawowy skład</h2>
    <div class="rzad-kart">
      <?php kartaGracza('kapitan', 'Kapitan', $pola, true); ?>
      <?php kartaGracza('gracz1', 'Gracz', $pola, true); ?>
      <?php kartaGracza('gracz2', 'Gracz', $pola, true); ?>
      <?php kartaGracza('gracz3', 'Gracz', $pola, true); ?>
      <?php kartaGracza('gracz4', 'Gracz', $pola, true); ?>
    </div>
  </section>

  <div class="rzad-sekcji">
    <section>
      <h2>Rezerwowi</h2>
      <div class="rzad-kart">
        <?php kartaGracza('rez1', 'Rezerwowy', $pola, false, true); ?>
        <?php kartaGracza('rez2', 'Rezerwowy', $pola, false, true); ?>
        <?php kartaGracza('rez3', 'Rezerwowy', $pola, false, true); ?>
      </div>
    </section>
    <section>
      <h2>Trener</h2>
      <div class="rzad-kart">
        <?php kartaGracza('trener', 'Trener', $pola, false, true); ?>
      </div>
    </section>
  </div>

  <div class="przyciski-rzad">
    <button type="submit">Zapisz drużynę</button>
    <a class="przycisk drugi" href="<?= BASE_URL ?>/druzyny.php">Anuluj</a>
  </div>
</form>

<div class="modal" id="modal-gracza" aria-hidden="true">
  <div class="modal-okno" role="dialog" aria-labelledby="modal-tytul">
    <div class="modal-gora">
      <h3 id="modal-tytul">Wybierz zawodnika</h3>
      <button type="button" class="modal-zamknij" id="modal-zamknij" title="Zamknij">&times;</button>
    </div>
    <input type="text" id="modal-szukaj" placeholder="Wpisz nick zawodnika…" autocomplete="off">
    <ul class="modal-wyniki" id="modal-wyniki"></ul>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/druzyna.js"></script>
<?php require 'includes/footer.php'; ?>
