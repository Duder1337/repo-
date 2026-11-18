<?php
// Generator danych testowych dla panelu administratora (faza testów): zawodnicy, drużyny, zgłoszenia do turnieju.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/druzyna_funkcje.php';

const TEST_HASLO  = 'test1234';
const TEST_DOMENA = 'test.nexora.local';

// Dane testowe są oznaczone kolumną „testowy” – dzięki temu można je usunąć jednym kliknięciem
function zapewnijKolumnyTestowe(): void {
    static $gotowe = false;
    if ($gotowe) return;
    $pdo = db();
    foreach (['zawodnik', 'druzyna'] as $tab) {
        if (!$pdo->query("SHOW COLUMNS FROM $tab LIKE 'testowy'")->fetch()) {
            $pdo->exec("ALTER TABLE $tab ADD COLUMN testowy TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
    $gotowe = true;
}

function liczbyDanychTestowych(): array {
    zapewnijKolumnyTestowe();
    $pdo = db();
    return [
        'druzyny'   => (int)$pdo->query('SELECT COUNT(*) FROM druzyna WHERE testowy = 1')->fetchColumn(),
        'zawodnicy' => (int)$pdo->query('SELECT COUNT(*) FROM zawodnik WHERE testowy = 1')->fetchColumn(),
        'wolni'     => (int)$pdo->query('SELECT COUNT(*) FROM zawodnik w WHERE w.testowy = 1
                                          AND NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = w.id_zawodnika)')->fetchColumn(),
        'konta'     => (int)$pdo->query('SELECT COUNT(*) FROM zawodnik WHERE testowy = 1 AND id_uzytkownika IS NOT NULL')->fetchColumn(),
    ];
}

function nickJestWolny(string $nick): bool {
    $st = db()->prepare('SELECT 1 FROM zawodnik WHERE nick = ? UNION SELECT 1 FROM uzytkownik WHERE nick = ?');
    $st->execute([$nick, $nick]);
    return !$st->fetchColumn();
}

function losowyNickTestowy(): string {
    $bazy = ['Viper','Ghost','Blaze','Raven','Falcon','Shadow','Rogue','Titan','Nova','Cobra','Hunter','Phantom','Storm',
             'Sniper','Wolf','Reaper','Ace','Zero','Frost','Havoc','Pixel','Turbo','Echo','Rush','Hawk','Jinx','Onyx','Drift'];
    for ($i = 0; $i < 60; $i++) {
        $nick = $bazy[array_rand($bazy)] . random_int(10, 999);
        if (nickJestWolny($nick)) return $nick;
    }
    return 'Test' . bin2hex(random_bytes(4)); // awaryjnie – praktycznie zawsze unikalny
}

// Tworzy zawodnika testowego (opcjonalnie z kontem do logowania). Zwraca dane zawodnika.
function utworzZawodnikaTestowego(bool $zKontem, ?string &$hashHasla = null): array {
    static $imiona = ['Adam','Bartek','Cezary','Dawid','Emil','Filip','Grzegorz','Hubert','Igor','Jakub','Kamil','Łukasz',
                      'Marek','Norbert','Oskar','Paweł','Radek','Szymon','Tomek','Wojtek'];
    static $nazwiska = ['Nowak','Kowalski','Wiśniewski','Wójcik','Kowalczyk','Kamiński','Lewandowski','Zieliński',
                        'Szymański','Woźniak','Dąbrowski','Kozłowski','Jankowski','Mazur','Krawczyk','Piotrowski'];
    $pdo  = db();
    $nick = losowyNickTestowy();
    $idKonta = null;
    $email = null;
    if ($zKontem) {
        if ($hashHasla === null) $hashHasla = password_hash(TEST_HASLO, PASSWORD_DEFAULT);
        $email = strtolower($nick) . '@' . TEST_DOMENA;
        $pdo->prepare("INSERT INTO uzytkownik (email, nick, haslo_hash, rola) VALUES (?, ?, ?, 'uzytkownik')")
            ->execute([$email, $nick, $hashHasla]);
        $idKonta = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO zawodnik (id_uzytkownika, nick, imie, nazwisko, testowy) VALUES (?, ?, ?, ?, 1)')
        ->execute([$idKonta, $nick, $imiona[array_rand($imiona)], $nazwiska[array_rand($nazwiska)]]);
    return ['id_zawodnika' => (int)$pdo->lastInsertId(), 'id_uzytkownika' => $idKonta, 'nick' => $nick, 'email' => $email];
}

function nazwaDruzynyTestowej(array $zajete = []): string {
    $bazy = ['Wilki Nexory','Czarne Smoki','Orły Północy','Vortex Squad','Nova Team','Red Phoenix','Iron Wolves','Cyber Hawks',
             'Storm Riders','Neon Knights','Shadow Pack','Arctic Foxes','Blitz Crew','Silent Force','Dark Matter','Rapid Fire',
             'Golden Eagles','Night Owls','Steel Titans','Lunar Gaming'];
    shuffle($bazy);
    $st = db()->prepare('SELECT 1 FROM druzyna WHERE nazwa = ?');
    foreach ($bazy as $n) {
        $st->execute([$n]);
        if (!$st->fetchColumn() && !in_array($n, $zajete, true)) return $n;
    }
    do { $n = 'Test Team ' . random_int(100, 9999); $st->execute([$n]); } while ($st->fetchColumn() || in_array($n, $zajete, true));
    return $n;
}

// Tworzy drużynę z pełną piątką (kapitan + 4 graczy) oraz opcjonalnymi rezerwowymi i trenerem.
function utworzDruzyneTestowa(string $nazwa, int $rezerwowi, bool $trener, bool $zKontami, int $idAdmina, ?string &$hashHasla = null): array {
    $pdo = db();
    $role = array_merge(['kapitan'], array_fill(0, 4, 'gracz'), array_fill(0, max(0, min(3, $rezerwowi)), 'rezerwowy'), $trener ? ['trener'] : []);
    $pdo->beginTransaction();
    try {
        $sklad = [];
        foreach ($role as $r) $sklad[] = ['rola' => $r] + utworzZawodnikaTestowego($zKontami, $hashHasla);
        $wlasciciel = $sklad[0]['id_uzytkownika'] ?? $idAdmina; // bez kont właścicielem jest administrator
        $pdo->prepare('INSERT INTO druzyna (nazwa, id_wlasciciela, testowy) VALUES (?, ?, 1)')->execute([$nazwa, $wlasciciel]);
        $idD = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, ?)');
        foreach ($sklad as $z) $ins->execute([$idD, $z['id_zawodnika'], $z['rola']]);
        $pdo->prepare('INSERT INTO ranking (id_druzyny) VALUES (?)')->execute([$idD]);
        $pdo->commit();
        return ['id' => $idD, 'nazwa' => $nazwa, 'kapitan' => $sklad[0]['nick'], 'email' => $sklad[0]['email'], 'liczba' => count($sklad)];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

// Zgłasza drużynę do turnieju z otwartymi zapisami, o ile jest wolne miejsce. Zwraca true, gdy się udało.
function zglosDruzyneTestowa(int $idTurnieju, int $idDruzyny): bool {
    $pdo = db();
    $st = $pdo->prepare("SELECT t.limit_druzyn, (SELECT COUNT(*) FROM zgloszenie z WHERE z.id_turnieju = t.id_turnieju AND z.status = 'przyjęte') AS zajete
                         FROM turniej t WHERE t.id_turnieju = ? AND t.status = 'otwarty'");
    $st->execute([$idTurnieju]);
    $t = $st->fetch();
    if (!$t || (int)$t['zajete'] >= (int)$t['limit_druzyn']) return false;
    $pdo->prepare('INSERT INTO zgloszenie (id_turnieju, id_druzyny) VALUES (?, ?)')->execute([$idTurnieju, $idDruzyny]);
    return true;
}

// Usuwa wszystkie dane testowe (drużyny z meczami lub w trwających rozgrywkach zostają). Zwraca liczby usuniętych i pominiętych.
function usunDaneTestowe(): array {
    $pdo = db();
    $przed = liczbyDanychTestowych();

    foreach ($pdo->query('SELECT id_druzyny FROM druzyna WHERE testowy = 1')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        usunDruzyne((int)$id);
    }
    // Zawodnicy testowi, którzy nadal są w jakiejś drużynie (np. w trwającym turnieju albo w prawdziwej drużynie), zostają nietknięci,
    // żeby nie wyczyścić składu. Usunięcie konta kasuje też profil zawodnika; błąd (np. są statystyki) tylko pomija wpis.
    $konta = $pdo->query("SELECT u.id_uzytkownika FROM uzytkownik u JOIN zawodnik z ON z.id_uzytkownika = u.id_uzytkownika
                          WHERE z.testowy = 1 AND u.rola = 'uzytkownik'
                            AND NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = z.id_zawodnika)")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($konta as $idK) {
        try { $pdo->prepare('DELETE FROM uzytkownik WHERE id_uzytkownika = ?')->execute([(int)$idK]); } catch (PDOException $ex) {}
    }
    $wolni = $pdo->query('SELECT w.id_zawodnika FROM zawodnik w WHERE w.testowy = 1
                          AND NOT EXISTS (SELECT 1 FROM sklad s WHERE s.id_zawodnika = w.id_zawodnika)')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($wolni as $idZ) {
        try { $pdo->prepare('DELETE FROM zawodnik WHERE id_zawodnika = ?')->execute([(int)$idZ]); } catch (PDOException $ex) {}
    }

    $po = liczbyDanychTestowych();
    return [
        'druzyny'   => $przed['druzyny'] - $po['druzyny'],
        'zawodnicy' => $przed['zawodnicy'] - $po['zawodnicy'],
        'pominiete' => $po['druzyny'] + $po['zawodnicy'],
    ];
}
