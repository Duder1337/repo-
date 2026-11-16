<?php
// Jednorazowy skrypt: zakłada konto administratora. PO UŻYCIU USUŃ TEN PLIK!
require_once __DIR__ . '/../includes/db.php';

$email = 'admin@turnieje.local';
$nick  = 'admin';
$haslo = 'ZmienMnie123!';   // zmień przed uruchomieniem

$st = db()->prepare('SELECT 1 FROM uzytkownik WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) { exit('Administrator już istnieje.'); }

$st = db()->prepare('INSERT INTO uzytkownik (email, nick, haslo_hash, rola) VALUES (?, ?, ?, "administrator")');
$st->execute([$email, $nick, password_hash($haslo, PASSWORD_DEFAULT)]);
echo 'Utworzono administratora: ' . htmlspecialchars($email) . '. Usuń plik install/seed_admin.php.';
