<?php
require_once 'includes/auth.php';
wymagajLogowania();

$st = db()->prepare('SELECT email, nick, rola, data_rejestracji, haslo_hash FROM uzytkownik WHERE id_uzytkownika = ?');
$st->execute([uzytkownik()['id']]);
$u = $st->fetch();

$bledy = [];
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfSprawdz();
    $stare = $_POST['stare'] ?? '';
    $nowe  = $_POST['nowe'] ?? '';
    $nowe2 = $_POST['nowe2'] ?? '';
    if (!password_verify($stare, $u['haslo_hash'])) $bledy[] = 'Aktualne hasło jest nieprawidłowe.';
    if (strlen($nowe) < 8) $bledy[] = 'Nowe hasło musi mieć co najmniej 8 znaków.';
    if ($nowe !== $nowe2) $bledy[] = 'Nowe hasła nie są takie same.';
    if (!$bledy) {
        db()->prepare('UPDATE uzytkownik SET haslo_hash = ? WHERE id_uzytkownika = ?')
            ->execute([password_hash($nowe, PASSWORD_DEFAULT), uzytkownik()['id']]);
        $ok = true;
    }
}

$tytul = 'Moje konto';
require 'includes/header.php';
?>
<h1>Moje konto</h1>
<ul class="szczegoly">
  <li><strong>E-mail:</strong> <?= e($u['email']) ?></li>
  <li><strong>Nick:</strong> <?= e($u['nick']) ?></li>
  <li><strong>Rola:</strong> <?= e($u['rola']) ?></li>
  <li><strong>Konto założone:</strong> <?= e($u['data_rejestracji']) ?></li>
</ul>

<h2>Zmiana hasła</h2>
<?php if ($ok): ?><p class="ok">Hasło zostało zmienione.</p><?php endif; ?>
<?php foreach ($bledy as $b): ?><p class="blad"><?= e($b) ?></p><?php endforeach; ?>
<form method="post" class="formularz">
  <?= csrfPole() ?>
  <label>Aktualne hasło <input type="password" name="stare" required></label>
  <label>Nowe hasło <input type="password" name="nowe" required></label>
  <label>Powtórz nowe hasło <input type="password" name="nowe2" required></label>
  <button type="submit">Zmień hasło</button>
</form>
<?php require 'includes/footer.php'; ?>
