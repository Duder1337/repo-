<?php
require_once __DIR__ . '/auth.php';

const POLA_SKLADU = ['kapitan', 'gracz1', 'gracz2', 'gracz3', 'gracz4', 'rez1', 'rez2', 'rez3', 'trener'];

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

// Skład drużyny zgłoszonej do trwającego turnieju jest zablokowany
function druzynaZablokowana(int $idDruzyny): bool {
    $st = db()->prepare(
        "SELECT 1 FROM zgloszenie z JOIN turniej t ON t.id_turnieju = z.id_turnieju
         WHERE z.id_druzyny = ? AND z.status = 'przyjęte' AND t.status = 'w trakcie' LIMIT 1"
    );
    $st->execute([$idDruzyny]);
    return (bool)$st->fetchColumn();
}

function skladDoPol(array $sklad): array {
    $pola = array_fill_keys(POLA_SKLADU, '');
    $licz = ['gracz' => 0, 'rezerwowy' => 0];
    foreach ($sklad as $s) {
        if ($s['rola'] === 'kapitan') {
            $pola['kapitan'] = $s['nick'];
        } elseif ($s['rola'] === 'trener') {
            $pola['trener'] = $s['nick'];
        } elseif ($s['rola'] === 'gracz') {
            $licz['gracz']++;
            $pola['gracz' . $licz['gracz']] = $s['nick'];
        } elseif ($s['rola'] === 'rezerwowy') {
            $licz['rezerwowy']++;
            $pola['rez' . $licz['rezerwowy']] = $s['nick'];
        }
    }
    return $pola;
}

// Czyta pola składu z formularza, sprawdza je i zwraca [lista [id_zawodnika, rola], wpisane nicki]
function wczytajSklad(array $post, array &$bledy): array {
    $definicje = [
        'kapitan' => ['kapitan', true, 'Kapitan'],
        'gracz1'  => ['gracz', true, 'Gracz 2'],
        'gracz2'  => ['gracz', true, 'Gracz 3'],
        'gracz3'  => ['gracz', true, 'Gracz 4'],
        'gracz4'  => ['gracz', true, 'Gracz 5'],
        'rez1'    => ['rezerwowy', false, 'Rezerwowy 1'],
        'rez2'    => ['rezerwowy', false, 'Rezerwowy 2'],
        'rez3'    => ['rezerwowy', false, 'Rezerwowy 3'],
        'trener'  => ['trener', false, 'Trener'],
    ];
    $wpisane = [];
    $wynik   = [];
    $uzyte   = [];
    foreach ($definicje as $klucz => $def) {
        [$rola, $wymagane, $etykieta] = $def;
        $nick = trim($post[$klucz] ?? '');
        $wpisane[$klucz] = $nick;
        if ($nick === '') {
            if ($wymagane) $bledy[] = 'Pole ' . $etykieta . ' jest wymagane.';
            continue;
        }
        $st = db()->prepare('SELECT id_zawodnika, nick FROM zawodnik WHERE nick = ?');
        $st->execute([$nick]);
        $z = $st->fetch();
        if (!$z) {
            $bledy[] = 'Nie znaleziono zawodnika o nicku ' . $nick . ' (' . $etykieta . ').';
            continue;
        }
        $k = mb_strtolower($z['nick']);
        if (isset($uzyte[$k])) {
            $bledy[] = 'Zawodnik ' . $z['nick'] . ' jest wpisany więcej niż raz.';
            continue;
        }
        $uzyte[$k] = true;
        $wynik[] = [(int)$z['id_zawodnika'], $rola];
    }
    return [$wynik, $wpisane];
}
