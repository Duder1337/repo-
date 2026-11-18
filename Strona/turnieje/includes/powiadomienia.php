<?php
// Powiadomienia o zaproszeniach do drużyn (menu profilu i wyskakujące okienko w nagłówku)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/druzyna_funkcje.php';

// Oczekujące zaproszenia zawodnika, od najstarszego
function oczekujaceZaproszeniaZawodnika(int $idZawodnika): array {
    try {
        $st = db()->prepare("SELECT z.id_zaproszenia AS id, d.nazwa AS druzyna, z.rola
                             FROM zaproszenie z JOIN druzyna d ON d.id_druzyny = z.id_druzyny
                             WHERE z.id_zawodnika = ? AND z.status = 'oczekujące'
                             ORDER BY z.id_zaproszenia");
        $st->execute([$idZawodnika]);
        return $st->fetchAll();
    } catch (PDOException $ex) {
        return [];
    }
}

// Zwraca zaproszenia, o których zawodnik nie został jeszcze powiadomiony w tej sesji, i zapamiętuje, że już je zgłoszono.
function noweZaproszeniaDoPowiadomienia(int $idZawodnika, array $lista): array {
    $ostatnie = (int)($_SESSION['zap_powiadomione'][$idZawodnika] ?? 0);
    $nowe = array_values(array_filter($lista, fn($z) => (int)$z['id'] > $ostatnie));
    if ($nowe) $_SESSION['zap_powiadomione'][$idZawodnika] = (int)max(array_column($nowe, 'id'));
    return $nowe;
}

// Tytuł i opis wyskakującego powiadomienia dla jednej lub kilku nowych zaproszeń
function trescPowiadomieniaZaproszen(array $nowe): array {
    if (count($nowe) === 1) {
        return ['Nowe zaproszenie', 'Drużyna ' . $nowe[0]['druzyna'] . ' zaprasza Cię jako: ' . mb_strtolower(etykietaRoli($nowe[0]['rola'])) . '.'];
    }
    return ['Nowe zaproszenia', 'Masz ' . count($nowe) . ' nowe zaproszenia do drużyn.'];
}
