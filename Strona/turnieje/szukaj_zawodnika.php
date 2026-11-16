<?php
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
header('Content-Type: application/json; charset=utf-8');

$dla = (int)($_GET['druzyna'] ?? 0);
// Podpowiedzi dostępne tylko dla osoby, która może zarządzać tą drużyną (kapitan / admin)
if (!zalogowany() || !$dla || !mozeZarzadzac($dla)) { echo '[]'; exit; }

$q = trim($_GET['q'] ?? '');

// Tylko zawodnicy z kontem, bez drużyny i bez oczekującego zaproszenia od tej drużyny
$sql = "SELECT w.nick FROM zawodnik w
        WHERE w.id_uzytkownika IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = w.id_zawodnika)
          AND NOT EXISTS (SELECT 1 FROM zaproszenie z WHERE z.id_zawodnika = w.id_zawodnika
                          AND z.id_druzyny = ? AND z.status = 'oczekujące')";
$param = [$dla];

if ($q === '') {
    $sql .= ' ORDER BY w.nick LIMIT 12';
} else {
    $wzor = addcslashes($q, '%_\\');
    $sql .= ' AND w.nick LIKE ? ORDER BY (w.nick LIKE ?) DESC, w.nick LIMIT 12';
    $param[] = '%' . $wzor . '%';
    $param[] = $wzor . '%';
}
$st = db()->prepare($sql);
$st->execute($param);
echo json_encode($st->fetchAll(PDO::FETCH_COLUMN), JSON_UNESCAPED_UNICODE);
