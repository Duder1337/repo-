<?php
require_once __DIR__ . '/auth.php';

// Ile osób może być w danej roli (kapitan + 4 graczy = podstawowa piątka)
const LIMITY_ROL = ['kapitan' => 1, 'gracz' => 4, 'rezerwowy' => 3, 'trener' => 1];

function etykietaRoli(string $rola): string {
    return ['kapitan' => 'Kapitan', 'gracz' => 'Gracz', 'rezerwowy' => 'Rezerwowy', 'trener' => 'Trener'][$rola] ?? $rola;
}

function pobierzSklad(int $idDruzyny): array {
    $st = db()->prepare(
        "SELECT z.id_zawodnika, z.nick, s.rola
         FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
         WHERE s.id_druzyny = ?
         ORDER BY FIELD(s.rola, 'kapitan', 'gracz', 'rezerwowy', 'trener'), z.nick"
    );
    $st->execute([$idDruzyny]);
    return $st->fetchAll();
}

// Skład jest zablokowany, gdy drużyna bierze udział w trwającym turnieju lub trwającej lidze
function druzynaZablokowana(int $idDruzyny): bool {
    $st = db()->prepare(
        "SELECT 1 FROM zgloszenie z JOIN turniej t ON t.id_turnieju = z.id_turnieju
         WHERE z.id_druzyny = ? AND z.status = 'przyjęte' AND t.status = 'w trakcie' LIMIT 1"
    );
    $st->execute([$idDruzyny]);
    if ($st->fetchColumn()) return true;

    $st = db()->prepare(
        "SELECT 1 FROM liga_zgloszenie z JOIN liga l ON l.id_ligi = z.id_ligi
         WHERE z.id_druzyny = ? AND l.status = 'w trakcie' LIMIT 1"
    );
    $st->execute([$idDruzyny]);
    return (bool)$st->fetchColumn();
}

/* ---------- Zawodnik zalogowanego konta i jego drużyna ---------- */

function mojZawodnik(): ?array {
    static $z = false;
    if ($z === false) {
        $z = null;
        if (zalogowany()) {
            $st = db()->prepare('SELECT * FROM zawodnik WHERE id_uzytkownika = ?');
            $st->execute([uzytkownik()['id']]);
            $z = $st->fetch() ?: null;
        }
    }
    return $z;
}

// Drużyna zawodnika (id_druzyny, nazwa, rola) albo null, jeśli nie ma drużyny
function druzynaZawodnika(int $idZawodnika): ?array {
    $st = db()->prepare(
        'SELECT d.id_druzyny, d.nazwa, s.rola
         FROM sklad s JOIN druzyna d ON d.id_druzyny = s.id_druzyny
         WHERE s.id_zawodnika = ?'
    );
    $st->execute([$idZawodnika]);
    return $st->fetch() ?: null;
}

function mojaDruzyna(): ?array {
    $z = mojZawodnik();
    return $z ? druzynaZawodnika((int)$z['id_zawodnika']) : null;
}

/* ---------- Uprawnienia (zadanie 4) ---------- */

// Tylko kapitan drużyny zarządza nią
function czyKapitan(int $idDruzyny): bool {
    $d = mojaDruzyna();
    return $d !== null && (int)$d['id_druzyny'] === $idDruzyny && $d['rola'] === 'kapitan';
}

// Kapitan oraz administrator platformy
function mozeZarzadzac(int $idDruzyny): bool {
    return czyKapitan($idDruzyny) || maRole('administrator');
}

/* ---------- Liczby w składzie ---------- */

// Ile osób jest w roli; z flagą liczy też oczekujące zaproszenia na tę rolę
function liczbaWRoli(int $idDruzyny, string $rola, bool $zZaproszeniami = false): int {
    $st = db()->prepare('SELECT COUNT(*) FROM sklad WHERE id_druzyny = ? AND rola = ?');
    $st->execute([$idDruzyny, $rola]);
    $n = (int)$st->fetchColumn();
    if ($zZaproszeniami) {
        $st = db()->prepare("SELECT COUNT(*) FROM zaproszenie WHERE id_druzyny = ? AND rola = ? AND status = 'oczekujące'");
        $st->execute([$idDruzyny, $rola]);
        $n += (int)$st->fetchColumn();
    }
    return $n;
}

// Do rozgrywek można zgłosić tylko drużynę z pełną piątką: kapitan + 4 graczy
function skladKompletny(int $idDruzyny): bool {
    return liczbaWRoli($idDruzyny, 'kapitan') === 1
        && liczbaWRoli($idDruzyny, 'gracz') === LIMITY_ROL['gracz'];
}

/* ---------- Usuwanie drużyn i obsługa zaproszeń (używane też w panelu admina) ---------- */

// Usuwa drużynę razem ze zgłoszeniami i zaproszeniami. Zwraca '' po sukcesie albo komunikat błędu.
function usunDruzyne(int $id): string {
    $pdo = db();
    $st = $pdo->prepare('SELECT 1 FROM mecz WHERE id_druzyny_a = ? OR id_druzyny_b = ?
                         UNION ALL SELECT 1 FROM liga_mecz WHERE id_druzyny_a = ? OR id_druzyny_b = ? LIMIT 1');
    $st->execute([$id, $id, $id, $id]);
    if ($st->fetchColumn()) return 'Nie można usunąć drużyny, która ma mecze w lidze lub turnieju.';
    if (druzynaZablokowana($id)) return 'Nie można usunąć drużyny uczestniczącej w trwających rozgrywkach.';
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM zgloszenie WHERE id_druzyny = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM liga_zgloszenie WHERE id_druzyny = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM zaproszenie WHERE id_druzyny = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM druzyna WHERE id_druzyny = ?')->execute([$id]);
        $pdo->commit();
        return '';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'Nie udało się usunąć drużyny.';
    }
}

// Przyjmuje zaproszenie w imieniu zawodnika. Wołać tylko po sprawdzeniu uprawnień (administrator).
// Zwraca '' po sukcesie albo komunikat błędu.
function akceptujZaproszenie(int $idZap): string {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM zaproszenie WHERE id_zaproszenia = ? AND status = 'oczekujące' FOR UPDATE");
        $st->execute([$idZap]);
        $zap = $st->fetch();
        $problem = '';
        if (!$zap) {
            $problem = 'To zaproszenie jest już nieaktualne.';
        } else {
            $idD = (int)$zap['id_druzyny'];
            $idZ = (int)$zap['id_zawodnika'];
            $pdo->prepare('SELECT id_druzyny FROM druzyna WHERE id_druzyny = ? FOR UPDATE')->execute([$idD]);
            if (druzynaZawodnika($idZ)) {
                $problem = 'Zawodnik należy już do drużyny.';
            } elseif (druzynaZablokowana($idD)) {
                $problem = 'Ta drużyna uczestniczy w trwających rozgrywkach – nie można do niej dołączyć.';
            } elseif (liczbaWRoli($idD, $zap['rola']) >= LIMITY_ROL[$zap['rola']]) {
                $problem = 'W drużynie nie ma już wolnych miejsc w tej roli.';
            }
        }
        if ($problem) { $pdo->rollBack(); return $problem; }

        $pdo->prepare('INSERT INTO sklad (id_druzyny, id_zawodnika, rola) VALUES (?, ?, ?)')
            ->execute([$idD, $idZ, $zap['rola']]);
        $pdo->prepare("UPDATE zaproszenie SET status = 'zaakceptowane' WHERE id_zaproszenia = ?")->execute([$idZap]);
        $pdo->prepare("UPDATE zaproszenie SET status = 'anulowane' WHERE id_zawodnika = ? AND status = 'oczekujące'")->execute([$idZ]);
        $pdo->commit();
        return '';
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex->getCode() === '23000') return 'Zawodnik należy już do drużyny.';
        throw $ex;
    }
}
