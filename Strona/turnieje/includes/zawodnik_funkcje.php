<?php
require_once __DIR__ . '/auth.php';

const AVATAR_DIR = __DIR__ . '/../uploads/avatary';

/* ---------- Zdjęcie profilowe ---------- */

function urlZdjecia(?string $plik): ?string {
    return $plik ? BASE_URL . '/uploads/avatary/' . rawurlencode($plik) : null;
}

// Okrągły awatar: zdjęcie albo pierwsza litera nicku
function awatarHtml(array $z, string $klasa = ''): string {
    $url = urlZdjecia($z['zdjecie'] ?? null);
    if ($url) {
        return '<img class="awatar ' . e($klasa) . '" src="' . e($url) . '" alt="">';
    }
    return '<span class="awatar ' . e($klasa) . '">' . e(mb_strtoupper(mb_substr($z['nick'], 0, 1))) . '</span>';
}

function usunPlikZdjecia(?string $plik): void {
    if ($plik && $plik === basename($plik) && is_file(AVATAR_DIR . '/' . $plik)) {
        @unlink(AVATAR_DIR . '/' . $plik);
    }
}

// Zapisuje wgrane zdjęcie. Zwraca true, a przy błędzie wpisuje komunikat do $blad.
function zapiszZdjecie(int $idZawodnika, array $plik, string &$blad): bool {
    $kod = $plik['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kod === UPLOAD_ERR_NO_FILE) { $blad = 'Wybierz plik ze zdjęciem.'; return false; }
    if ($kod !== UPLOAD_ERR_OK)      { $blad = 'Nie udało się wgrać pliku.'; return false; }
    if ($plik['size'] > 2 * 1024 * 1024) { $blad = 'Zdjęcie może mieć maksymalnie 2 MB.'; return false; }

    // Typ sprawdzamy po zawartości pliku, a nie po jego nazwie
    $info = @getimagesize($plik['tmp_name']);
    $dozwolone = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($dozwolone[$info[2]])) { $blad = 'Dozwolone formaty zdjęć: JPG, PNG, WEBP.'; return false; }
    if ($info[0] > 4000 || $info[1] > 4000)      { $blad = 'Zdjęcie jest zbyt duże (max 4000×4000 px).'; return false; }

    if (!is_dir(AVATAR_DIR) && !@mkdir(AVATAR_DIR, 0755, true)) {
        $blad = 'Nie można utworzyć folderu na zdjęcia (uploads/avatary).'; return false;
    }
    $nazwa = $idZawodnika . '_' . bin2hex(random_bytes(6)) . '.' . $dozwolone[$info[2]];
    if (!move_uploaded_file($plik['tmp_name'], AVATAR_DIR . '/' . $nazwa)) {
        $blad = 'Nie udało się zapisać zdjęcia na serwerze.'; return false;
    }

    $st = db()->prepare('SELECT zdjecie FROM zawodnik WHERE id_zawodnika = ?');
    $st->execute([$idZawodnika]);
    $stare = $st->fetchColumn() ?: null;
    db()->prepare('UPDATE zawodnik SET zdjecie = ? WHERE id_zawodnika = ?')->execute([$nazwa, $idZawodnika]);
    usunPlikZdjecia($stare);
    return true;
}

function usunZdjecie(int $idZawodnika): void {
    $st = db()->prepare('SELECT zdjecie FROM zawodnik WHERE id_zawodnika = ?');
    $st->execute([$idZawodnika]);
    $stare = $st->fetchColumn() ?: null;
    db()->prepare('UPDATE zawodnik SET zdjecie = NULL WHERE id_zawodnika = ?')->execute([$idZawodnika]);
    usunPlikZdjecia($stare);
}

/* ---------- Zapis udziału zawodników w meczu (podstawa statystyk) ---------- */

function zapiszUdzialDruzyn(int $idMeczu, string $kolumna, array $m): void {
    $pdo = db();
    $sel = $pdo->prepare("SELECT id_zawodnika FROM sklad WHERE id_druzyny = ? AND rola IN ('kapitan','gracz')");
    $ins = $pdo->prepare("INSERT INTO udzial_zawodnika (id_zawodnika, id_druzyny, $kolumna, wygrana) VALUES (?, ?, ?, ?)");
    foreach ([(int)$m['id_druzyny_a'], (int)$m['id_druzyny_b']] as $idD) {
        $sel->execute([$idD]);
        foreach ($sel->fetchAll(PDO::FETCH_COLUMN) as $idZ) {
            $ins->execute([(int)$idZ, $idD, $idMeczu, (int)$m['id_zwyciezcy'] === $idD ? 1 : 0]);
        }
    }
}

// Wołane po zapisaniu wyniku meczu ligowego: zapamiętuje, kto grał (kapitan + 4 graczy) i czy wygrał
function zapiszUdzialLigi(int $idMeczu): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM liga_mecz WHERE id_meczu = ?');
    $st->execute([$idMeczu]);
    $m = $st->fetch();
    if (!$m || $m['status'] !== 'rozegrany') return;
    $pdo->prepare('DELETE FROM udzial_zawodnika WHERE id_meczu_ligi = ?')->execute([$idMeczu]);
    zapiszUdzialDruzyn($idMeczu, 'id_meczu_ligi', $m);
}

// To samo dla meczów turniejowych (tabela mecz) – do wywołania przy wpisywaniu wyników turnieju
function zapiszUdzialTurnieju(int $idMeczu): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM mecz WHERE id_meczu = ?');
    $st->execute([$idMeczu]);
    $m = $st->fetch();
    if (!$m || $m['status'] !== 'rozegrany' || !$m['id_zwyciezcy']) return;
    $pdo->prepare('DELETE FROM udzial_zawodnika WHERE id_meczu_turnieju = ?')->execute([$idMeczu]);
    zapiszUdzialDruzyn($idMeczu, 'id_meczu_turnieju', $m);
}

/* ---------- Dane do profilu ---------- */

// Zwraca ['stat' => liczby, 'rozgrywki' => podsumowanie po turniejach/ligach, 'mecze' => lista meczów (od najnowszych)]
function daneProfilu(int $idZawodnika): array {
    $moja = 'COALESCE(lm.id_druzyny_a, tm.id_druzyny_a) = u.id_druzyny';
    $sql = "SELECT u.id_udzialu, u.wygrana, d.nazwa AS druzyna,
                   CASE WHEN u.id_meczu_ligi IS NOT NULL THEN 'Liga' ELSE 'Turniej' END AS typ,
                   COALESCE(l.nazwa, t.nazwa) AS rozgrywki, t.id_turnieju,
                   CASE WHEN $moja THEN COALESCE(lm.wynik_a, tm.wynik_a) ELSE COALESCE(lm.wynik_b, tm.wynik_b) END AS moje,
                   CASE WHEN $moja THEN COALESCE(lm.wynik_b, tm.wynik_b) ELSE COALESCE(lm.wynik_a, tm.wynik_a) END AS ich,
                   (SELECT p.nazwa FROM druzyna p WHERE p.id_druzyny =
                        CASE WHEN $moja THEN COALESCE(lm.id_druzyny_b, tm.id_druzyny_b)
                             ELSE COALESCE(lm.id_druzyny_a, tm.id_druzyny_a) END) AS przeciwnik
            FROM udzial_zawodnika u
            JOIN druzyna d ON d.id_druzyny = u.id_druzyny
            LEFT JOIN liga_mecz lm ON lm.id_meczu = u.id_meczu_ligi
            LEFT JOIN liga l ON l.id_ligi = lm.id_ligi
            LEFT JOIN mecz tm ON tm.id_meczu = u.id_meczu_turnieju
            LEFT JOIN turniej t ON t.id_turnieju = tm.id_turnieju
            WHERE u.id_zawodnika = ?
            ORDER BY u.id_udzialu ASC";
    $st = db()->prepare($sql);
    $st->execute([$idZawodnika]);
    $wiersze = $st->fetchAll();

    $stat = ['mecze' => 0, 'wygrane' => 0, 'przegrane' => 0, 'procent' => 0, 'seria' => 0,
             'liga' => ['mecze' => 0, 'wygrane' => 0], 'turniej' => ['mecze' => 0, 'wygrane' => 0]];
    $rozgrywki = [];
    $seria = 0;
    foreach ($wiersze as $w) {
        $wyg = ((int)$w['wygrana'] === 1);
        $typ = ($w['typ'] === 'Liga') ? 'liga' : 'turniej';
        $stat['mecze']++;
        $stat[$typ]['mecze']++;
        if ($wyg) { $stat['wygrane']++; $stat[$typ]['wygrane']++; }
        $seria = $wyg ? $seria + 1 : 0;
        $stat['seria'] = max($stat['seria'], $seria);

        $k = $w['typ'] . '|' . $w['rozgrywki'] . '|' . $w['druzyna'];
        if (!isset($rozgrywki[$k])) {
            $rozgrywki[$k] = ['typ' => $w['typ'], 'nazwa' => $w['rozgrywki'], 'druzyna' => $w['druzyna'],
                              'id_turnieju' => $w['id_turnieju'], 'mecze' => 0, 'wygrane' => 0];
        }
        $rozgrywki[$k]['mecze']++;
        if ($wyg) $rozgrywki[$k]['wygrane']++;
    }
    $stat['przegrane'] = $stat['mecze'] - $stat['wygrane'];
    $stat['procent']   = $stat['mecze'] ? (int)round(100 * $stat['wygrane'] / $stat['mecze']) : 0;

    return ['stat' => $stat, 'rozgrywki' => array_values($rozgrywki), 'mecze' => array_reverse($wiersze)];
}
