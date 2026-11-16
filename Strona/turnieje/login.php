<?php
require_once 'includes/auth.php';
$blad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $email = trim($_POST['email'] ?? '');
    $st = db()->prepare('SELECT * FROM uzytkownik WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['haslo'] ?? '', $u['haslo_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uzytkownik'] = [
            'id'   => (int)$u['id_uzytkownika'],
            'nick' => $u['nick'],
            'rola' => $u['rola'],
        ];
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $blad = 'Nieprawidłowy e-mail lub hasło.';
}
$tytul = 'Logowanie';
require 'includes/header.php';
?>
<h1>Logowanie</h1>
<?php if (isset($_GET['zarejestrowano'])): ?><p class="ok">Konto utworzone. Możesz się zalogować.</p><?php endif; ?>
<?php if ($blad): ?><p class="blad"><?= e($blad) ?></p><?php endif; ?>
<form method="post" class="formularz">
  <?= csrfPole() ?>
  <label>E-mail <input type="email" name="email" required></label>
  <label>Hasło <input type="password" name="haslo" required></label>
  <button type="submit">Zaloguj</button>
</form>
<?php require 'includes/footer.php'; ?>
