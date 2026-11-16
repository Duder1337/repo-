<?php
// Funkcje pomocnicze do wyświetlania kart turniejów

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
          <span><?= e($t['format_meczu']) ?></span>
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
