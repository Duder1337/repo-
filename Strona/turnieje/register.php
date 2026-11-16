<?php
require_once 'includes/auth.php';
$bledy = [];
$email = $nick = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $email = trim($_POST['email'] ?? '');
    $nick  = trim($_POST['nick'] ?? '');
    $haslo = $_POST['haslo'] ?? '';
    $haslo2 = $_POST['haslo2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $bledy[] = 'Podaj poprawny adres e-mail.';
    if (mb_strlen($nick) < 3 || mb_strlen($nick) > 50) $bledy[] = 'Nick musi mieć od 3 do 50 znaków.';
    if (strlen($haslo) < 8) $bledy[] = 'Hasło musi mieć co najmniej 8 znaków.';
    if ($haslo !== $haslo2) $bledy[] = 'Hasła nie są takie same.';

    if (!$bledy) {
        $st = db()->prepare('SELECT 1 FROM uzytkownik WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $bledy[] = 'Konto z tym adresem e-mail już istnieje.';
        } else {
            $st = db()->prepare('INSERT INTO uzytkownik (email, nick, haslo_hash, rola) VALUES (?, ?, ?, "kapitan")');
            $st->execute([$email, $nick, password_hash($haslo, PASSWORD_DEFAULT)]);
            header('Location: ' . BASE_URL . '/login.php?zarejestrowano=1');
            exit;
        }
    }
}
$tytul = 'Rejestracja';
require 'includes/header.php';
?>
<h1>Rejestracja kapitana</h1>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz">
  <?= csrfPole() ?>
  <label>E-mail <input type="email" name="email" value="<?= e($email) ?>" required></label>
  <label>Nick <input type="text" name="nick" value="<?= e($nick) ?>" required></label>
  <label>Hasło <input type="password" name="haslo" required></label>
  <label>Powtórz hasło <input type="password" name="haslo2" required></label>
  <button type="submit">Załóż konto</button>
</form>
<?php require 'includes/footer.php'; ?>
