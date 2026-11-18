<?php
require_once __DIR__ . '/auth.php';

// Punkty w statystykach profilu: 3 za wygraną, 1 za remis, 0 za porażkę
// (tak samo jak w NEXORA PRO LEAGUE). Remisy są wspierane przez kod, ale baza
// obecnie nie pozwala zapisać remisowego wyniku (CHECK wynik_a <> wynik_b).
const STAT_PUNKTY_WYGRANA = 3;
const STAT_PUNKTY_REMIS   = 1;

// Historia rozegranych spotkań drużyny (mecze turniejowe + ligowe), od najnowszych.
// Mecze z wolnym losem (brak przeciwnika) nie są liczone.
function historiaMeczow(int $idDruzyny): array {
    $sql = "
        SELECT 'turniej' AS typ, t.id_turnieju AS id_rozgrywek, t.nazwa AS rozgrywki,
               t.data_startu AS data_rozgrywek, m.id_meczu,
               m.id_druzyny_a, m.id_druzyny_b, a.nazwa AS nazwa_a, b.nazwa AS nazwa_b,
               m.wynik_a, m.wynik_b, m.id_zwyciezcy
        FROM mecz m
        JOIN turniej t ON t.id_turnieju = m.id_turnieju
        JOIN druzyna a ON a.id_druzyny = m.id_druzyny_a
        JOIN druzyna b ON b.id_druzyny = m.id_druzyny_b
        WHERE m.status = 'rozegrany' AND (m.id_druzyny_a = :d1 OR m.id_druzyny_b = :d2)
        UNION ALL
        SELECT 'liga' AS typ, l.id_ligi AS id_rozgrywek, l.nazwa AS rozgrywki,
               l.data_utworzenia AS data_rozgrywek, m.id_meczu,
               m.id_druzyny_a, m.id_druzyny_b, a.nazwa AS nazwa_a, b.nazwa AS nazwa_b,
               m.wynik_a, m.wynik_b, m.id_zwyciezcy
        FROM liga_mecz m
        JOIN liga l ON l.id_ligi = m.id_ligi
        JOIN druzyna a ON a.id_druzyny = m.id_druzyny_a
        JOIN druzyna b ON b.id_druzyny = m.id_druzyny_b
        WHERE m.status = 'rozegrany' AND (m.id_druzyny_a = :d3 OR m.id_druzyny_b = :d4)
        ORDER BY data_rozgrywek DESC, id_meczu DESC";
    // PDO bez emulacji nie pozwala powtarzać tej samej nazwy parametru, stąd :d1..:d4
    $st = db()->prepare($sql);
    $st->execute([':d1' => $idDruzyny, ':d2' => $idDruzyny, ':d3' => $idDruzyny, ':d4' => $idDruzyny]);

    $wynik = [];
    foreach ($st->fetchAll() as $m) {
        $jestA = (int)$m['id_druzyny_a'] === $idDruzyny;
        if ($m['id_zwyciezcy'] === null)                      $m['rezultat'] = 'R';
        elseif ((int)$m['id_zwyciezcy'] === $idDruzyny)       $m['rezultat'] = 'W';
        else                                                  $m['rezultat'] = 'P';
        $m['przeciwnik_id']   = (int)($jestA ? $m['id_druzyny_b'] : $m['id_druzyny_a']);
        $m['przeciwnik']      = $jestA ? $m['nazwa_b'] : $m['nazwa_a'];
        $m['nasze']           = $jestA ? $m['wynik_a'] : $m['wynik_b'];
        $m['ich']             = $jestA ? $m['wynik_b'] : $m['wynik_a'];
        $wynik[] = $m;
    }
    return $wynik;
}

// Statystyki liczone z historii meczów
function statystykiDruzyny(array $historia): array {
    $s = ['rozegrane' => count($historia), 'wygrane' => 0, 'przegrane' => 0, 'remisy' => 0, 'punkty' => 0];
    foreach ($historia as $m) {
        if ($m['rezultat'] === 'W')      $s['wygrane']++;
        elseif ($m['rezultat'] === 'P')  $s['przegrane']++;
        else                             $s['remisy']++;
    }
    $s['punkty'] = $s['wygrane'] * STAT_PUNKTY_WYGRANA + $s['remisy'] * STAT_PUNKTY_REMIS;
    return $s;
}
