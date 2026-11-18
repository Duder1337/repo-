<?php
// Odpowiedź JSON dla powiadomień o zaproszeniach (odpytywana co kilkadziesiąt sekund z nagłówka strony)
require_once 'includes/auth.php';
require_once 'includes/druzyna_funkcje.php';
require_once 'includes/powiadomienia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!zalogowany()) { http_response_code(401); echo '{"blad":"Brak logowania."}'; exit; }

$z = mojZawodnik();
if (!$z) { echo '{"licznik":0,"nowe":[]}'; exit; }

$lista = oczekujaceZaproszeniaZawodnika((int)$z['id_zawodnika']);
$nowe  = noweZaproszeniaDoPowiadomienia((int)$z['id_zawodnika'], $lista);
$tresc = $nowe ? trescPowiadomieniaZaproszen($nowe) : ['', ''];

echo json_encode(['licznik' => count($lista), 'nowe' => (bool)$nowe, 'tytul' => $tresc[0], 'opis' => $tresc[1]], JSON_UNESCAPED_UNICODE);
