<?php
// Funkcje pomocnicze do wyświetlania kart turniejów
require_once __DIR__ . '/turnieje_funkcje.php';

function etykietaStatusu(string $s): string {
    return ['otwarty' => 'Zapisy otwarte', 'w trakcie' => 'W trakcie', 'zakończony' => 'Zakończony',
            'anulowany' => 'Anulowany', 'szkic' => 'Szkic'][$s] ?? $s;
}
function klasaStatusu(string $s): string {
    return ['otwarty' => 'otwarty', 'w trakcie' => 'trakcie', 'zakończony' => 'koniec',
            'anulowany' => 'anulowany', 'szkic' => 'szkic'][$s] ?? 'szkic';
}
function dniDoStartu(string $data): int {
    return (int)floor((strtotime($data) - strtotime(date('Y-m-d'))) / 86400);
}
function opisStartu(array $t): string {
    if (in_array($t['status'], ['otwarty', 'szkic'], true)) {
        $d = dniDoStartu($t['data_startu']);
        if ($d > 1)  return 'Turniej startuje za ' . $d . ' dni';
        if ($d === 1) return 'Turniej startuje jutro';
        if ($d === 0) return 'Turniej startuje dzisiaj';
    }
    return 'Start: ' . $t['data_startu'];
}

function kartaTurnieju(array $t): void {
    $h = crc32($t['nazwa']) % 360;
    $url = BASE_URL . '/turniej.php?id=' . (int)$t['id_turnieju'];
    ?>
    <a class="karta" href="<?= e($url) ?>">
      <div class="karta-img" style="--h:<?= (int)$h ?>">
        <span class="plakietka st-<?= e(klasaStatusu($t['status'])) ?>"><?= e(etykietaStatusu($t['status'])) ?></span>
        <span class="karta-gra"><?= e($t['dyscyplina']) ?></span>
      </div>
      <div class="karta-tresc">
        <small><?= e(opisStartu($t)) ?></small>
        <h3><?= e($t['nazwa']) ?></h3>
        <div class="tagi">
          <span>5 vs 5</span>
          <span><?= e(opisFormatu($t)) ?></span>
          <span>Drabinka pucharowa</span>
        </div>
      </div>
      <div class="karta-stopka">
        <span>ZAPISANYCH</span>
        <b><?= (int)$t['zgloszone'] ?> / <?= (int)$t['limit_druzyn'] ?></b>
      </div>
    </a>
    <?php
}

// Karta zawodnika w składzie drużyny (kształt tarczy, jak w profilach drużyn)
function kartaZawodnika(array $z, ?array $stat, string $nazwaDruzyny): void {
    $rola   = $z['rola'];
    $podst  = in_array($rola, ['kapitan', 'gracz'], true);
    $odznaka = ['kapitan' => 'K', 'rezerwowy' => 'R', 'trener' => 'T'][$rola] ?? '';
    $m = $stat['mecze'] ?? 0;
    ?>
    <div class="pk-wrap <?= $podst ? 'podst' : 'rez' ?>" data-grupa="<?= $podst ? 'podstawowi' : 'rezerwowi' ?>">
      <span class="pk-odznaka <?= e($rola) ?>"><?= e($odznaka) ?></span>
      <a class="pk" href="<?= BASE_URL ?>/zawodnik.php?id=<?= (int)$z['id_zawodnika'] ?>">
        <span class="pk-in">
          <span class="pk-rola"><?= e(mb_strtoupper(etykietaRoli($rola))) ?></span>
          <span class="pk-druzyna"><?= e($nazwaDruzyny) ?></span>
          <span class="pk-foto"><?= awatarHtml($z) ?></span>
          <span class="pk-nick"><?= e($z['nick']) ?></span>
          <span class="pk-stat">
            <span><i>MECZE</i><b><?= $m ?: '–' ?></b></span>
            <span><i>WYGRANE</i><b><?= $m ? (int)$stat['wygrane'] : '–' ?></b></span>
            <span><i>WIN %</i><b><?= $m ? (int)$stat['procent'] . '%' : '–' ?></b></span>
          </span>
        </span>
      </a>
    </div>
    <?php
}
