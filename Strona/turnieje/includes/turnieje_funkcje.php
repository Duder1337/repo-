<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/druzyna_funkcje.php';

const TURNIEJ_MIN_DRUZYN = 4; // tyle samo, ile wynosi minimalny limit w formularzu turnieju

// Dodaje kolumnę z datą anulowania, jeśli jej jeszcze nie ma (starsze bazy), i nalicza czas dla już anulowanych turniejów.
function zapewnijKolumneAnulowania(): void {
    static $gotowe = false;
    if ($gotowe) return;
    $pdo = db();
    if (!$pdo->query("SHOW COLUMNS FROM turniej LIKE 'data_anulowania'")->fetch()) {
        $pdo->exec('ALTER TABLE turniej ADD COLUMN data_anulowania DATETIME NULL');
    }
    // Turnieje anulowane zanim pojawiła się kolumna: odliczanie startuje od teraz
    $pdo->exec("UPDATE turniej SET data_anulowania = NOW() WHERE status = 'anulowany' AND data_anulowania IS NULL");
    $gotowe = true;
}

// Usuwa anulowane turnieje starsze niż ANULOWANE_USUWAJ_PO_DNIACH (zgłoszenia znikają razem z nimi,
// ogłoszenia powiązane z turniejem zostają, tylko tracą odnośnik). Zwraca liczbę usuniętych turniejów.
function usunStareAnulowane(): int {
    static $zrobione = false;
    if ($zrobione) return 0;
    $zrobione = true;
    try {
        zapewnijKolumneAnulowania();
        $dni = max(1, (int)ANULOWANE_USUWAJ_PO_DNIACH);
        return db()->exec("DELETE FROM turniej
                           WHERE status = 'anulowany'
                             AND data_anulowania < DATE_SUB(NOW(), INTERVAL $dni DAY)
                             AND NOT EXISTS (SELECT 1 FROM mecz m WHERE m.id_turnieju = turniej.id_turnieju)");
    } catch (PDOException $ex) {
        return 0; // sprzątanie nie może psuć wyświetlania strony
    }
}

// Podtytuł, osobny format finału i zasady turnieju – kolumny dodawane automatycznie w starszych bazach
function zapewnijKolumnyOpisuTurnieju(): void {
    static $gotowe = false;
    if ($gotowe) return;
    $pdo = db();
    foreach (['podtytul' => 'VARCHAR(150) NULL', 'format_finalu' => 'VARCHAR(3) NULL', 'zasady' => 'TEXT NULL'] as $kol => $def) {
        if (!$pdo->query("SHOW COLUMNS FROM turniej LIKE '$kol'")->fetch()) {
            $pdo->exec("ALTER TABLE turniej ADD COLUMN $kol $def");
        }
    }
    $gotowe = true;
}

// „BO3” albo „BO3 (finał BO5)”, gdy finał ma inny format niż reszta meczów
function opisFormatu(array $t): string {
    $f   = $t['format_meczu'];
    $fin = $t['format_finalu'] ?? null;
    return ($fin && $fin !== $f) ? $f . ' (finał ' . $fin . ')' : $f;
}

// Zasady zapisane jako zwykły tekst (jedna zasada w wierszu, ewentualny znak „*” lub „-” na początku jest pomijany)
function zasadyJakoLista(?string $tekst): array {
    $wynik = [];
    foreach (preg_split('/\R/u', (string)$tekst) as $w) {
        $w = trim(preg_replace('/^[*\-•]\s*/u', '', trim($w)));
        if ($w !== '') $wynik[] = $w;
    }
    return $wynik;
}

/* ---------- Start turnieju i drabinka pucharowa ---------- */

// Kolejność rozstawienia w drabince o p miejscach: [1,8,4,5,2,7,3,6] dla p = 8.
// Sąsiednie pozycje tworzą pary pierwszej rundy, a rozstawienie dzieli drabinkę tak, by najwyżej rozstawieni trafili na siebie dopiero późno.
function kolejnoscRozstawienia(int $p): array {
    $kolejnosc = [1];
    while (count($kolejnosc) < $p) {
        $nowa = [];
        $suma = count($kolejnosc) * 2 + 1;
        foreach ($kolejnosc as $miejsce) { $nowa[] = $miejsce; $nowa[] = $suma - $miejsce; }
        $kolejnosc = $nowa;
    }
    return $kolejnosc;
}

// Nazwa rundy na podstawie liczby meczów w niej: 1 → Finał, 2 → Półfinały, 4 → Ćwierćfinały, 8 → 1/8 finału…
function nazwaRundy(int $meczow): string {
    return [1 => 'Finał', 2 => 'Półfinały', 4 => 'Ćwierćfinały'][$meczow] ?? '1/' . $meczow . ' finału';
}

// Format meczu w danej rundzie: finał może mieć inny format niż reszta turnieju
function formatRundy(array $t, int $meczow): string {
    $f = $t['format_meczu'];
    return ($meczow === 1 && !empty($t['format_finalu'])) ? $t['format_finalu'] : $f;
}

// Tworzy wszystkie mecze drabinki pucharowej dla podanych drużyn (kolejność w tablicy = losowe rozstawienie).
// Drużyny bez przeciwnika w pierwszej rundzie dostają wolny los i od razu awansują.
function utworzDrabinke(int $idTurnieju, array $druzyny): void {
    $pdo = db();
    $n = count($druzyny);
    $p = 1;
    while ($p < $n) $p *= 2;
    $rundy = (int)round(log($p, 2));
    $kolejnosc = kolejnoscRozstawienia($p);

    // Mecze wstawiamy od finału do pierwszej rundy, żeby znać identyfikator następnego meczu
    $id = [];   // $id[runda][nr] = id_meczu
    $ins = $pdo->prepare('INSERT INTO mecz (id_turnieju, runda, nr_w_rundzie, id_druzyny_a, id_druzyny_b, id_zwyciezcy, id_nastepnego_meczu, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    for ($r = $rundy; $r >= 1; $r--) {
        $ile = $p / (2 ** $r);
        for ($k = 1; $k <= $ile; $k++) {
            $nast = $r < $rundy ? $id[$r + 1][(int)ceil($k / 2)] : null;
            $a = $b = $zw = null;
            $status = 'oczekujący';
            if ($r === 1) {
                $a = $druzyny[$kolejnosc[2 * $k - 2] - 1] ?? null;
                $b = $druzyny[$kolejnosc[2 * $k - 1] - 1] ?? null;
                if (($a === null) !== ($b === null)) { $zw = $a ?? $b; $status = 'rozegrany'; } // wolny los
            }
            $ins->execute([$idTurnieju, $r, $k, $a, $b, $zw, $nast, $status]);
            $id[$r][$k] = (int)$pdo->lastInsertId();
        }
    }

    // Awans drużyn z wolnym losem do drugiej rundy
    if ($rundy >= 2) {
        $st = $pdo->prepare('SELECT nr_w_rundzie, id_zwyciezcy, id_nastepnego_meczu FROM mecz
                             WHERE id_turnieju = ? AND runda = 1 AND id_zwyciezcy IS NOT NULL');
        $st->execute([$idTurnieju]);
        foreach ($st->fetchAll() as $m) {
            $kolumna = ((int)$m['nr_w_rundzie'] % 2 === 1) ? 'id_druzyny_a' : 'id_druzyny_b';
            $pdo->prepare("UPDATE mecz SET $kolumna = ? WHERE id_meczu = ?")->execute([$m['id_zwyciezcy'], $m['id_nastepnego_meczu']]);
        }
    }
}

// Rozpoczyna turniej: zamyka zapisy, wyklucza drużyny z niepełnym składem, losuje drabinkę i ustawia status „w trakcie”.
// Zwraca '' po sukcesie albo komunikat błędu; w $wykluczone trafiają nazwy drużyn wyłączonych z powodu niepełnego składu.
function wystartujTurniej(int $id, array &$wykluczone = []): string {
    $pdo = db();
    $wykluczone = [];
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT status FROM turniej WHERE id_turnieju = ? FOR UPDATE');
        $st->execute([$id]);
        $status = $st->fetchColumn();
        if ($status === false) { $pdo->rollBack(); return 'Nie znaleziono turnieju.'; }
        if ($status !== 'otwarty') { $pdo->rollBack(); return 'Wystartować można tylko turniej z otwartymi zapisami.'; }

        $st = $pdo->prepare("SELECT z.id_druzyny, d.nazwa FROM zgloszenie z JOIN druzyna d ON d.id_druzyny = z.id_druzyny
                             WHERE z.id_turnieju = ? AND z.status = 'przyjęte' ORDER BY z.id_zgloszenia");
        $st->execute([$id]);
        $gotowe = [];
        foreach ($st->fetchAll() as $z) {
            if (skladKompletny((int)$z['id_druzyny'])) {
                $gotowe[] = (int)$z['id_druzyny'];
            } else {
                $wykluczone[] = $z['nazwa'];
                $pdo->prepare("UPDATE zgloszenie SET status = 'odrzucone' WHERE id_turnieju = ? AND id_druzyny = ?")
                    ->execute([$id, $z['id_druzyny']]);
            }
        }
        if (count($gotowe) < TURNIEJ_MIN_DRUZYN) {
            $pdo->rollBack();
            $wykluczone = [];
            return 'Do startu potrzeba co najmniej ' . TURNIEJ_MIN_DRUZYN . ' drużyn z pełnym składem (kapitan + 4 graczy). Masz: ' . count($gotowe) . '.';
        }

        shuffle($gotowe); // losowe rozstawienie
        utworzDrabinke($id, $gotowe);
        $pdo->prepare("UPDATE turniej SET status = 'w trakcie' WHERE id_turnieju = ?")->execute([$id]);
        $pdo->commit();
        return '';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $wykluczone = [];
        return 'Nie udało się wystartować turnieju: ' . $ex->getMessage();
    }
}

// Drabinka turnieju: tablica rund (numer → lista meczów z nazwami drużyn)
function pobierzDrabinke(int $idTurnieju): array {
    $st = db()->prepare('SELECT m.*, a.nazwa AS nazwa_a, b.nazwa AS nazwa_b
                         FROM mecz m
                         LEFT JOIN druzyna a ON a.id_druzyny = m.id_druzyny_a
                         LEFT JOIN druzyna b ON b.id_druzyny = m.id_druzyny_b
                         WHERE m.id_turnieju = ? ORDER BY m.runda, m.nr_w_rundzie');
    $st->execute([$idTurnieju]);
    $rundy = [];
    foreach ($st->fetchAll() as $m) $rundy[(int)$m['runda']][] = $m;
    return $rundy;
}

/* ---------- Zarządzanie turniejem w panelu admina ---------- */

function opublikujTurniej(int $id): bool {
    $st = db()->prepare("UPDATE turniej SET status = 'otwarty' WHERE id_turnieju = ? AND status = 'szkic'");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

function anulujTurniej(int $id): bool {
    zapewnijKolumneAnulowania();
    $st = db()->prepare("UPDATE turniej SET status = 'anulowany', data_anulowania = NOW() WHERE id_turnieju = ? AND status IN ('szkic','otwarty')");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// Wszystkie zgłoszenia turnieju (przyjęte najpierw) wraz z kapitanem i informacją, czy skład jest pełny
function zgloszeniaTurnieju(int $idTurnieju): array {
    $st = db()->prepare("SELECT z.id_druzyny, z.status, z.data_zgloszenia, d.nazwa,
            (SELECT w.nick FROM sklad s JOIN zawodnik w ON w.id_zawodnika = s.id_zawodnika
              WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan' LIMIT 1) AS kapitan
         FROM zgloszenie z JOIN druzyna d ON d.id_druzyny = z.id_druzyny
         WHERE z.id_turnieju = ?
         ORDER BY FIELD(z.status, 'przyjęte', 'odrzucone', 'wycofane'), z.id_zgloszenia");
    $st->execute([$idTurnieju]);
    $wiersze = $st->fetchAll();
    foreach ($wiersze as &$w) $w['kompletny'] = skladKompletny((int)$w['id_druzyny']);
    unset($w);
    return $wiersze;
}

// Drużyny z pełnym składem, które nie mają jeszcze przyjętego zgłoszenia do turnieju
function druzynyDoDodania(int $idTurnieju): array {
    $st = db()->prepare("SELECT d.id_druzyny, d.nazwa FROM druzyna d
         WHERE NOT EXISTS (SELECT 1 FROM zgloszenie z WHERE z.id_turnieju = ? AND z.id_druzyny = d.id_druzyny AND z.status = 'przyjęte')
           AND (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan') = 1
           AND (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'gracz') = 4
         ORDER BY d.nazwa");
    $st->execute([$idTurnieju]);
    return $st->fetchAll();
}

// Zmiana zgłoszenia przez administratora: 'dodaj' (też przywrócenie), 'odrzuc' albo 'usun'.
// Działa tylko, gdy zapisy są otwarte. Zwraca '' po sukcesie albo komunikat błędu.
function zmienZgloszenie(int $idTurnieju, int $idDruzyny, string $akcja): string {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT status, limit_druzyn FROM turniej WHERE id_turnieju = ? FOR UPDATE');
        $st->execute([$idTurnieju]);
        $t = $st->fetch();
        if (!$t) { $pdo->rollBack(); return 'Nie znaleziono turnieju.'; }
        if ($t['status'] !== 'otwarty') { $pdo->rollBack(); return 'Zgłoszeniami można zarządzać tylko wtedy, gdy zapisy są otwarte.'; }

        $st = $pdo->prepare('SELECT status FROM zgloszenie WHERE id_turnieju = ? AND id_druzyny = ?');
        $st->execute([$idTurnieju, $idDruzyny]);
        $istn = $st->fetchColumn(); // false, gdy drużyna nie ma zgłoszenia
        $st = $pdo->prepare("SELECT COUNT(*) FROM zgloszenie WHERE id_turnieju = ? AND status = 'przyjęte'");
        $st->execute([$idTurnieju]);
        $zajete = (int)$st->fetchColumn();

        $blad = '';
        if ($akcja === 'odrzuc') {
            if ($istn !== 'przyjęte') $blad = 'Ta drużyna nie ma przyjętego zgłoszenia.';
            else $pdo->prepare("UPDATE zgloszenie SET status = 'odrzucone' WHERE id_turnieju = ? AND id_druzyny = ?")->execute([$idTurnieju, $idDruzyny]);
        } elseif ($akcja === 'usun') {
            if ($istn === false) $blad = 'Ta drużyna nie ma zgłoszenia do tego turnieju.';
            else $pdo->prepare('DELETE FROM zgloszenie WHERE id_turnieju = ? AND id_druzyny = ?')->execute([$idTurnieju, $idDruzyny]);
        } elseif ($akcja === 'dodaj') {
            if ($istn === 'przyjęte')                  $blad = 'Ta drużyna jest już zgłoszona.';
            elseif (!skladKompletny($idDruzyny))       $blad = 'Drużyna nie ma pełnego składu (kapitan + 4 graczy).';
            elseif ($zajete >= (int)$t['limit_druzyn']) $blad = 'Limit drużyn w turnieju został osiągnięty.';
            elseif ($istn === false) $pdo->prepare('INSERT INTO zgloszenie (id_turnieju, id_druzyny) VALUES (?, ?)')->execute([$idTurnieju, $idDruzyny]);
            else $pdo->prepare("UPDATE zgloszenie SET status = 'przyjęte', data_zgloszenia = CURRENT_DATE WHERE id_turnieju = ? AND id_druzyny = ?")->execute([$idTurnieju, $idDruzyny]);
        } else {
            $blad = 'Nieznana akcja.';
        }

        if ($blad !== '') { $pdo->rollBack(); return $blad; }
        $pdo->commit();
        return '';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'Nie udało się zmienić zgłoszenia.';
    }
}

// Usuwa turniej (w dowolnym statusie) razem z drabinką, wynikami, zgłoszeniami i zapisanym udziałem zawodników w jego meczach.
// Drużyny i zawodnicy zostają, a ogłoszenia powiązane z turniejem tracą tylko odnośnik. Zwraca '' po sukcesie albo komunikat błędu.
function usunTurniej(int $id): string {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT 1 FROM turniej WHERE id_turnieju = ? FOR UPDATE');
        $st->execute([$id]);
        if (!$st->fetchColumn()) { $pdo->rollBack(); return 'Nie znaleziono turnieju.'; }

        try { // tabela ze statystykami zawodników może jeszcze nie istnieć
            $pdo->prepare('DELETE FROM udzial_zawodnika WHERE id_meczu_turnieju IN (SELECT id_meczu FROM mecz WHERE id_turnieju = ?)')->execute([$id]);
        } catch (PDOException $ex) {}

        // Mecze wskazują na kolejne mecze drabinki – najpierw zrywamy te powiązania
        $pdo->prepare('UPDATE mecz SET id_nastepnego_meczu = NULL WHERE id_turnieju = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM mecz WHERE id_turnieju = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM turniej WHERE id_turnieju = ?')->execute([$id]);
        $pdo->commit();
        return '';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'Nie udało się usunąć turnieju: ' . $ex->getMessage();
    }
}
