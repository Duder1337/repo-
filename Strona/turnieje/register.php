<?php
require_once 'includes/auth.php';

$bledy = [];
$dane  = ['email' => '', 'nick' => '', 'imie' => '', 'nazwisko' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    foreach ($dane as $k => $_) $dane[$k] = trim($_POST[$k] ?? '');
    $haslo  = $_POST['haslo'] ?? '';
    $haslo2 = $_POST['haslo2'] ?? '';

    if (!filter_var($dane['email'], FILTER_VALIDATE_EMAIL)) $bledy[] = 'Podaj poprawny adres e-mail.';
    if (!preg_match('/^[\p{L}0-9_.\-]{3,50}$/u', $dane['nick'])) $bledy[] = 'Nick musi mieć 3–50 znaków (litery, cyfry oraz _ . -).';
    if (!preg_match('/^[\p{L}\s\-]{2,50}$/u', $dane['imie'])) $bledy[] = 'Podaj poprawne imię (2–50 znaków).';
    if (!preg_match('/^[\p{L}\s\-]{2,80}$/u', $dane['nazwisko'])) $bledy[] = 'Podaj poprawne nazwisko (2–80 znaków).';
    if (strlen($haslo) < 8) $bledy[] = 'Hasło musi mieć co najmniej 8 znaków.';
    if ($haslo !== $haslo2) $bledy[] = 'Hasła nie są takie same.';

    if (!$bledy) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO uzytkownik (email, nick, haslo_hash, rola) VALUES (?, ?, ?, 'uzytkownik')")
                ->execute([$dane['email'], $dane['nick'], password_hash($haslo, PASSWORD_DEFAULT)]);
            $idKonta = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO zawodnik (id_uzytkownika, nick, imie, nazwisko) VALUES (?, ?, ?, ?)')
                ->execute([$idKonta, $dane['nick'], $dane['imie'], $dane['nazwisko']]);
            $pdo->commit();
            // Po rejestracji od razu logujemy użytkownika
            session_regenerate_id(true);
            $_SESSION['uzytkownik'] = ['id' => $idKonta, 'nick' => $dane['nick'], 'rola' => 'uzytkownik'];
            header('Location: ' . BASE_URL . '/druzyny.php');
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($ex->getCode() === '23000') {
                $bledy[] = 'Konto z takim adresem e-mail lub nickiem już istnieje.';
            } else {
                throw $ex;
            }
        }
    }
}
$tytul = 'Rejestracja';
require 'includes/header.php';
?>
<h1>Załóż konto</h1>
<p class="przygaszony">Konto daje Ci profil zawodnika, dzięki któremu można dodać Cię do składu drużyny, oraz możliwość tworzenia własnych drużyn.</p>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz">
  <?= csrfPole() ?>
  <label>E-mail <input type="email" name="email" value="<?= e($dane['email']) ?>" required></label>
  <label>Nick (jako gracz) <input type="text" name="nick" value="<?= e($dane['nick']) ?>" maxlength="50" required></label>
  <label>Imię <input type="text" name="imie" value="<?= e($dane['imie']) ?>" maxlength="50" required></label>
  <label>Nazwisko <input type="text" name="nazwisko" value="<?= e($dane['nazwisko']) ?>" maxlength="80" required></label>
  <label>Hasło <input type="password" name="haslo" required></label>
  <label>Powtórz hasło <input type="password" name="haslo2" required></label>
  <button type="submit">Załóż konto</button>
</form>
<?php require 'includes/footer.php'; ?>
