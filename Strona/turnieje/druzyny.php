<?php
require_once 'includes/auth.php';

$strona   = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 20;
$razem  = (int)db()->query('SELECT COUNT(*) FROM druzyna')->fetchColumn();
$stron  = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$druzyny = db()->query(
    "SELECT d.id_druzyny, d.nazwa, d.id_wlasciciela,
            (SELECT z.nick FROM sklad s JOIN zawodnik z ON z.id_zawodnika = s.id_zawodnika
              WHERE s.id_druzyny = d.id_druzyny AND s.rola = 'kapitan' LIMIT 1) AS kapitan,
            (SELECT COUNT(*) FROM sklad s WHERE s.id_druzyny = d.id_druzyny) AS liczba
     FROM druzyna d ORDER BY d.nazwa LIMIT $naStrone OFFSET $offset"
)->fetchAll();

$tytul = 'Drużyny';
require 'includes/header.php';
?>
<h1>Drużyny</h1>
<p>
  <?php if (zalogowany()): ?>
    <a class="przycisk" href="<?= BASE_URL ?>/druzyna_form.php">+ Utwórz drużynę</a>
  <?php else: ?>
    <span class="przygaszony"><a href="<?= BASE_URL ?>/login.php">Zaloguj się</a>, aby utworzyć drużynę.</span>
  <?php endif; ?>
</p>

<?php if (!$druzyny): ?>
  <p class="przygaszony">Nie ma jeszcze żadnych drużyn.</p>
<?php else: ?>
  <div class="tabela-wrap"><table>
    <tr><th>Drużyna</th><th>Kapitan</th><th>Skład</th><th></th></tr>
    <?php foreach ($druzyny as $d): ?>
      <tr>
        <td><a href="druzyna.php?id=<?= (int)$d['id_druzyny'] ?>"><?= e($d['nazwa']) ?></a></td>
        <td><?= e($d['kapitan'] ?? '–') ?></td>
        <td><?= (int)$d['liczba'] ?> os.</td>
        <td>
          <?php if (zalogowany() && ((int)$d['id_wlasciciela'] === uzytkownik()['id'] || maRole('administrator'))): ?>
            <a href="druzyna_form.php?id=<?= (int)$d['id_druzyny'] ?>">Edytuj</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($stron > 1): ?>
  <p class="strony">
    <?php for ($i = 1; $i <= $stron; $i++): ?>
      <?php if ($i === $strona): ?><strong><?= $i ?></strong>
      <?php else: ?><a href="?strona=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </p>
  <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
