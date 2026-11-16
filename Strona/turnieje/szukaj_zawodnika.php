<?php
require_once 'includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!zalogowany()) { echo '[]'; exit; }
$q = trim($_GET['q'] ?? '');

if ($q === '') {
    $nicki = db()->query('SELECT nick FROM zawodnik ORDER BY nick LIMIT 12')->fetchAll(PDO::FETCH_COLUMN);
} else {
    $wzor = addcslashes($q, '%_\\');
    $st = db()->prepare('SELECT nick FROM zawodnik WHERE nick LIKE ? ORDER BY (nick LIKE ?) DESC, nick LIMIT 12');
    $st->execute(['%' . $wzor . '%', $wzor . '%']);
    $nicki = $st->fetchAll(PDO::FETCH_COLUMN);
}
echo json_encode($nicki, JSON_UNESCAPED_UNICODE);
