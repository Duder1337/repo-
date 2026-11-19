<?php
// Symulacja turnieju: 8 drużyn, wylosowana drabinka, gotowa historia statystyk zawodników
// oraz przycisk „Symuluj następną fazę” (ćwierćfinały → półfinały → finał).
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/druzyna_funkcje.php';
require_once __DIR__ . '/turnieje_funkcje.php';
require_once __DIR__ . '/zawodnik_funkcje.php';
require_once __DIR__ . '/dane_testowe_funkcje.php';

const SYM_PREFIKS         = '[SYMULACJA]';
const SYM_NAZWA_POPRZEDNI = '[SYMULACJA] Puchar Nexory – edycja poprzednia';
const SYM_NAZWA_AKTUALNY  = '[SYMULACJA] Puchar Nexory – edycja aktualna';
const SYM_PUNKTY_ZA_WYGRANA = 3;

// Tabela z udziałem zawodników w meczach (podstawa statystyk w profilach). Jeśli już istnieje, nic się nie zmienia.
// Uwaga: CREATE TABLE zatwierdza transakcję w MySQL, więc wołamy to przed rozpoczęciem własnych transakcji.
function zapewnijTabeleUdzialu(): void {
    static $gotowe = false;
    if ($gotowe) return;
    db()->exec('CREATE TABLE IF NOT EXISTS udzial_zawodnika (
        id_udzialu        INT AUTO_INCREMENT PRIMARY KEY,
        id_zawodnika      INT NOT NULL,
        id_druzyny        INT NOT NULL,
        id_meczu_ligi     INT NULL,
        id_meczu_turnieju INT NULL,
        wygrana           TINYINT(1) NOT NULL DEFAULT 0,
        KEY idx_udzial_zawodnik (id_zawodnika),
        KEY idx_udzial_turniej  (id_meczu_turnieju),
        KEY idx_udzial_liga     (id_meczu_ligi)
    ) ENGINE=InnoDB');
    $gotowe = true;
}

// „1 mecz”, „4 mecze”, „8 meczów”
function symMeczow(int $n): string {
    $m10 = $n % 10;
    $m100 = $n % 100;
    if ($n === 1) return '1 mecz';
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return $n . ' mecze';
    return $n . ' meczów';
}

// Losowy wynik zgodny z formatem meczu (zawsze ktoś wygrywa – baza nie dopuszcza remisów).
// BO1: wynik jak na mapie CS2 (13:x albo dogrywka), BO3: 2:0 lub 2:1, BO5: 3:0, 3:1 lub 3:2.
function losujWynik(string $format): array {
    if ($format === 'BO5')      { $w = 3; $p = random_int(0, 2); }
    elseif ($format === 'BO3')  { $w = 2; $p = random_int(0, 1); }
    else {
        if (random_int(1, 5) === 1) { $w = 16; $p = random_int(13, 14); }   // dogrywka
        else                        { $w = 13; $p = random_int(2, 11); }
    }
    return random_int(0, 1) === 1 ? [$w, $p] : [$p, $w];
}

// Zapisuje wynik meczu turnieju: wynik, awans zwycięzcy do kolejnej rundy (albo zakończenie turnieju po finale),
// ranking drużyn i udział zawodników (statystyki profili). Wszystko w jednej transakcji.
function zapiszWynikMeczuTurnieju(int $idMeczu, int $wa, int $wb): string {
    if ($wa < 0 || $wb < 0 || $wa === $wb) return 'Nieprawidłowy wynik (bez remisów i liczb ujemnych).';
    zapewnijTabeleUdzialu();
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM mecz WHERE id_meczu = ? FOR UPDATE');
        $st->execute([$idMeczu]);
        $m = $st->fetch();
        if (!$m) { $pdo->rollBack(); return 'Nie znaleziono meczu.'; }
        if ($m['status'] === 'rozegrany') { $pdo->rollBack(); return 'Ten mecz został już rozegrany.'; }
        if ($m['id_druzyny_a'] === null || $m['id_druzyny_b'] === null) { $pdo->rollBack(); return 'W tym meczu nie ma jeszcze dwóch drużyn.'; }

        $zw = $wa > $wb ? (int)$m['id_druzyny_a'] : (int)$m['id_druzyny_b'];
        $pr = $wa > $wb ? (int)$m['id_druzyny_b'] : (int)$m['id_druzyny_a'];

        $pdo->prepare("UPDATE mecz SET wynik_a = ?, wynik_b = ?, id_zwyciezcy = ?, status = 'rozegrany' WHERE id_meczu = ?")
            ->execute([$wa, $wb, $zw, $idMeczu]);

        if ($m['id_nastepnego_meczu'] !== null) {
            // Awans: mecze nr 1, 3, 5… dają drużynę A następnego meczu, nr 2, 4, 6… drużynę B
            $kolumna = ((int)$m['nr_w_rundzie'] % 2 === 1) ? 'id_druzyny_a' : 'id_druzyny_b';
            $pdo->prepare("UPDATE mecz SET $kolumna = ? WHERE id_meczu = ?")->execute([$zw, $m['id_nastepnego_meczu']]);
        } else {
            $pdo->prepare("UPDATE turniej SET status = 'zakończony', id_zwyciezcy = ? WHERE id_turnieju = ?")
                ->execute([$zw, $m['id_turnieju']]);
        }

        $pdo->prepare('INSERT IGNORE INTO ranking (id_druzyny) VALUES (?)')->execute([$zw]);
        $pdo->prepare('INSERT IGNORE INTO ranking (id_druzyny) VALUES (?)')->execute([$pr]);
        $pdo->prepare('UPDATE ranking SET punkty = punkty + ?, wygrane = wygrane + 1 WHERE id_druzyny = ?')->execute([SYM_PUNKTY_ZA_WYGRANA, $zw]);
        $pdo->prepare('UPDATE ranking SET przegrane = przegrane + 1 WHERE id_druzyny = ?')->execute([$pr]);

        zapiszUdzialTurnieju($idMeczu);   // statystyki zawodników (kapitan + 4 graczy obu drużyn)
        $pdo->commit();
        return '';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'Nie udało się zapisać wyniku: ' . $ex->getMessage();
    }
}

// Najbliższa faza do rozegrania: pierwsza runda, w której są mecze z kompletem dwóch drużyn i bez wyniku
function nastepnaFaza(array $t, array $rundy): ?array {
    ksort($rundy);
    foreach ($rundy as $mecze) {
        $doGry = array_values(array_filter($mecze, fn($m) => $m['status'] === 'oczekujący'
            && $m['id_druzyny_a'] !== null && $m['id_druzyny_b'] !== null));
        if ($doGry) {
            $ile = count($mecze);
            return ['do_gry' => $doGry, 'nazwa' => nazwaRundy($ile), 'format' => formatRundy($t, $ile)];
        }
    }
    return null;
}

// Przycisk „Symuluj następną fazę”: losuje wyniki wszystkich meczów najbliższej rundy.
// Zwraca ['ok' => komunikat] albo ['blad' => komunikat].
function symulujNastepnaFaze(int $idTurnieju): array {
    zapewnijTabeleUdzialu();
    zapewnijKolumnyOpisuTurnieju();
    $st = db()->prepare('SELECT * FROM turniej WHERE id_turnieju = ?');
    $st->execute([$idTurnieju]);
    $t = $st->fetch();
    if (!$t) return ['blad' => 'Nie znaleziono turnieju.'];
    if ($t['status'] !== 'w trakcie') return ['blad' => 'Turniej nie jest w trakcie – nie ma czego symulować.'];

    $faza = nastepnaFaza($t, pobierzDrabinke($idTurnieju));
    if ($faza === null) return ['blad' => 'Brak meczów do rozegrania.'];

    $bledy = [];
    foreach ($faza['do_gry'] as $m) {
        [$a, $b] = losujWynik($faza['format']);
        $blad = zapiszWynikMeczuTurnieju((int)$m['id_meczu'], $a, $b);
        if ($blad !== '') $bledy[] = $blad;
    }
    if ($bledy) return ['blad' => implode(' ', $bledy)];

    $msg = 'Rozegrano fazę: ' . $faza['nazwa'] . ' (' . symMeczow(count($faza['do_gry'])) . ', ' . $faza['format'] . ').';
    $st = db()->prepare("SELECT d.nazwa FROM turniej t JOIN druzyna d ON d.id_druzyny = t.id_zwyciezcy
                         WHERE t.id_turnieju = ? AND t.status = 'zakończony'");
    $st->execute([$idTurnieju]);
    $zwyciezca = $st->fetchColumn();
    if ($zwyciezca) $msg .= ' Turniej zakończony – zwycięzca: ' . $zwyciezca . '.';
    return ['ok' => $msg];
}

// Tworzy turniej na 8 drużyn, zgłasza drużyny i od razu go startuje (losuje drabinkę)
function symUtworzTurniej(string $nazwa, string $format, ?string $formatFinalu, string $data, string $podtytul, int $idAdmina, array $druzyny): int {
    $pdo = db();
    $pdo->prepare("INSERT INTO turniej (nazwa, dyscyplina, format_meczu, format_finalu, limit_druzyn, data_startu, status, id_administratora, podtytul)
                   VALUES (?, 'Counter-Strike 2', ?, ?, 8, ?, 'otwarty', ?, ?)")
        ->execute([$nazwa, $format, $formatFinalu, $data, $idAdmina, $podtytul]);
    $id = (int)$pdo->lastInsertId();
    $zg = $pdo->prepare('INSERT INTO zgloszenie (id_turnieju, id_druzyny) VALUES (?, ?)');
    foreach ($druzyny as $idD) $zg->execute([$id, $idD]);
    $wykluczone = [];
    $blad = wystartujTurniej($id, $wykluczone);
    if ($blad !== '') throw new RuntimeException($blad);
    return $id;
}

// Tworzy całą symulację:
//  1) 8 drużyn testowych (pełne piątki),
//  2) „edycję poprzednią” – turniej już rozegrany do końca, dzięki czemu zawodnicy mają od razu wypełnione statystyki,
//  3) „edycję aktualną” – ta sama ósemka, drabinka wylosowana, nic jeszcze nie rozegrane (do klikania „Symuluj następną fazę”).
function stworzSymulacje(int $idAdmina): array {
    @set_time_limit(180);
    zapewnijTabeleUdzialu();
    zapewnijKolumnyTestowe();
    zapewnijKolumnyOpisuTurnieju();
    try {
        usunSymulacje();   // zaczynamy od czystej symulacji

        $hash = null;
        $druzyny = [];
        $zajete = [];
        for ($i = 0; $i < 8; $i++) {
            $nazwa = nazwaDruzynyTestowej($zajete);
            $zajete[] = $nazwa;
            $d = utworzDruzyneTestowa($nazwa, 0, false, false, $idAdmina, $hash);
            $druzyny[] = (int)$d['id'];
        }

        $idStary = symUtworzTurniej(SYM_NAZWA_POPRZEDNI, 'BO1', null, date('Y-m-d', strtotime('-30 days')),
            'Zakończona edycja – historia meczów zawodników', $idAdmina, $druzyny);
        for ($i = 0; $i < 6; $i++) {
            $faza = symulujNastepnaFaze($idStary);   // ćwierćfinały, półfinały, finał
            if (isset($faza['blad'])) break;         // błąd albo turniej już zakończony
        }

        symUtworzTurniej(SYM_NAZWA_AKTUALNY, 'BO3', 'BO5', date('Y-m-d'),
            'Symulacja: 8 drużyn, drabinka pucharowa, finał BO5', $idAdmina, $druzyny);

        return ['ok' => 'Symulacja gotowa: 8 drużyn, zakończona edycja poprzednia (statystyki zawodników są już wypełnione) '
                      . 'oraz aktualna edycja z wylosowaną drabinką. Kliknij „Symuluj następną fazę”.'];
    } catch (Throwable $ex) {
        return ['blad' => 'Nie udało się utworzyć symulacji: ' . $ex->getMessage()];
    }
}

// Usuwa turnieje symulacji, ich drużyny testowe i zawodników (ranking i statystyki znikają razem z nimi)
function usunSymulacje(): array {
    zapewnijTabeleUdzialu();
    $pdo = db();
    $st = $pdo->prepare('SELECT id_turnieju FROM turniej WHERE nazwa LIKE ?');
    $st->execute([SYM_PREFIKS . '%']);
    $idsT = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $druzyny = [];
    $zg = $pdo->prepare('SELECT DISTINCT id_druzyny FROM zgloszenie WHERE id_turnieju = ?');
    foreach ($idsT as $idT) {
        $zg->execute([$idT]);
        foreach ($zg->fetchAll(PDO::FETCH_COLUMN) as $d) $druzyny[(int)$d] = true;
    }

    $wynik = ['turnieje' => 0, 'druzyny' => 0, 'zawodnicy' => 0];
    foreach ($idsT as $idT) {
        if (usunTurniej($idT) === '') $wynik['turnieje']++;
    }

    $czyTestowa = $pdo->prepare('SELECT testowy FROM druzyna WHERE id_druzyny = ?');
    $sklad = $pdo->prepare('SELECT s.id_zawodnika FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
                            WHERE s.id_druzyny = ? AND z.testowy = 1');
    foreach (array_keys($druzyny) as $idD) {
        $czyTestowa->execute([$idD]);
        if ((int)$czyTestowa->fetchColumn() !== 1) continue;   // prawdziwych drużyn nie ruszamy
        $sklad->execute([$idD]);
        $zawodnicy = array_map('intval', $sklad->fetchAll(PDO::FETCH_COLUMN));
        if (usunDruzyne($idD) !== '') continue;
        $wynik['druzyny']++;
        foreach ($zawodnicy as $idZ) {
            try {
                $pdo->prepare('DELETE FROM udzial_zawodnika WHERE id_zawodnika = ?')->execute([$idZ]);
                $pdo->prepare('DELETE FROM zawodnik WHERE id_zawodnika = ?')->execute([$idZ]);
                $wynik['zawodnicy']++;
            } catch (PDOException $ex) { /* zawodnik zostaje, jeśli jest jeszcze gdzieś używany */ }
        }
    }
    return $wynik;
}
