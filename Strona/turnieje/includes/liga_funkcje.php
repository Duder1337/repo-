<?php
require_once __DIR__ . '/auth.php';

// Zasady NEXORA PRO LEAGUE
const LIGA_MECZE_NA_DRUZYNE = 8;   // każda drużyna gra 8 meczów
const LIGA_MIN_DRUZYN       = 9;   // żeby każdy miał 8 różnych przeciwników, trzeba min. 9 drużyn
const LIGA_PUNKTY_ZA_WYGRANA = 3;  // porażka = 0 punktów, remisów nie ma (BO1)

function pobierzLige(): ?array {
    $l = db()->query('SELECT * FROM liga ORDER BY id_ligi DESC LIMIT 1')->fetch();
    return $l ?: null;
}

function etykietaStatusuLigi(string $s): string {
    return ['zapisy' => 'Zapisy otwarte', 'w trakcie' => 'W trakcie', 'zakończona' => 'Zakończona'][$s] ?? $s;
}

// Drużyny zgłoszone do ligi: [['id_druzyny' =>, 'nazwa' =>], ...]
function druzynyLigi(int $idLigi): array {
    $st = db()->prepare(
        'SELECT d.id_druzyny, d.nazwa FROM liga_zgloszenie z
         JOIN druzyna d ON d.id_druzyny = z.id_druzyny
         WHERE z.id_ligi = ? ORDER BY z.data_zgloszenia, d.nazwa'
    );
    $st->execute([$idLigi]);
    return $st->fetchAll();
}

// Tabela ligowa liczona z rozegranych meczów.
// Kolejność: punkty, potem zwycięstwa, potem nazwa.
function tabelaLigi(int $idLigi): array {
    $st = db()->prepare(
        "SELECT d.id_druzyny, d.nazwa,
                COUNT(m.id_meczu) AS rozegrane,
                COALESCE(SUM(m.id_zwyciezcy = d.id_druzyny), 0) AS wygrane
         FROM liga_zgloszenie z
         JOIN druzyna d ON d.id_druzyny = z.id_druzyny
         LEFT JOIN liga_mecz m ON m.id_ligi = z.id_ligi AND m.status = 'rozegrany'
              AND (m.id_druzyny_a = d.id_druzyny OR m.id_druzyny_b = d.id_druzyny)
         WHERE z.id_ligi = ?
         GROUP BY d.id_druzyny, d.nazwa"
    );
    $st->execute([$idLigi]);
    $wiersze = [];
    foreach ($st->fetchAll() as $w) {
        $wyg = (int)$w['wygrane'];
        $roz = (int)$w['rozegrane'];
        $wiersze[] = [
            'id_druzyny' => (int)$w['id_druzyny'],
            'nazwa'      => $w['nazwa'],
            'rozegrane'  => $roz,
            'wygrane'    => $wyg,
            'przegrane'  => $roz - $wyg,
            'punkty'     => $wyg * LIGA_PUNKTY_ZA_WYGRANA,
        ];
    }
    usort($wiersze, function ($a, $b) {
        return [$b['punkty'], $b['wygrane'], $a['nazwa']] <=> [$a['punkty'], $a['wygrane'], $b['nazwa']];
    });
    return $wiersze;
}

// Mecze ligi z nazwami drużyn
function meczeLigi(int $idLigi): array {
    $st = db()->prepare(
        'SELECT m.*, a.nazwa AS nazwa_a, b.nazwa AS nazwa_b
         FROM liga_mecz m
         JOIN druzyna a ON a.id_druzyny = m.id_druzyny_a
         JOIN druzyna b ON b.id_druzyny = m.id_druzyny_b
         WHERE m.id_ligi = ? ORDER BY m.id_meczu'
    );
    $st->execute([$idLigi]);
    return $st->fetchAll();
}

// Losuje terminarz: każda drużyna dostaje dokładnie 8 różnych przeciwników.
// Sposób: ustawiamy drużyny w losowej kolejności "w kółku" i łączymy każdą
// z 4 następnymi (czyli każda ma 4 przeciwników "do przodu" i 4 "z tyłu" = 8).
// Działa dla 9..32 drużyn. Zwraca listę par [id_a, id_b] w losowej kolejności.
function losujTerminarz(array $idDruzyn): array {
    $n = count($idDruzyn);
    if ($n < LIGA_MIN_DRUZYN) {
        throw new InvalidArgumentException('Za mało drużyn do ligi.');
    }
    shuffle($idDruzyn);
    $pary = [];
    for ($i = 0; $i < $n; $i++) {
        for ($k = 1; $k <= LIGA_MECZE_NA_DRUZYNE / 2; $k++) {
            $j = ($i + $k) % $n;
            $pary[] = mt_rand(0, 1) ? [$idDruzyn[$i], $idDruzyn[$j]] : [$idDruzyn[$j], $idDruzyn[$i]];
        }
    }
    shuffle($pary);
    return $pary;
}
