// Profil drużyny: zakładki, filtr składu i okno zaproszeń (plus)
(function () {
  var widok = document.querySelector('.dr-widok');
  if (!widok) return;
  widok.classList.add('js');

  /* Zakładki (zapamiętywane w #hash) */
  var przyciski = widok.querySelectorAll('.dr-tabs button');
  var panele = widok.querySelectorAll('.dr-panel');

  function pokaz(nazwa) {
    var jest = false;
    panele.forEach(function (p) { if (p.dataset.panel === nazwa) jest = true; });
    if (!jest) nazwa = 'sklad';
    panele.forEach(function (p) { p.classList.toggle('aktywny', p.dataset.panel === nazwa); });
    przyciski.forEach(function (b) { b.classList.toggle('aktywna', b.dataset.tab === nazwa); });
  }
  przyciski.forEach(function (b) {
    b.addEventListener('click', function () {
      pokaz(b.dataset.tab);
      if (history.replaceState) history.replaceState(null, '', '#' + b.dataset.tab);
    });
  });
  pokaz((location.hash || '').replace('#', ''));
  window.addEventListener('hashchange', function () { pokaz((location.hash || '').replace('#', '')); });

  /* Filtr składu */
  var filtry = widok.querySelectorAll('.dr-filtry button');
  var karty = widok.querySelectorAll('.pk-wrap');
  filtry.forEach(function (f) {
    f.addEventListener('click', function () {
      filtry.forEach(function (x) { x.classList.toggle('aktywny', x === f); });
      var w = f.dataset.filtr;
      karty.forEach(function (k) {
        var g = k.dataset.grupa;
        k.hidden = !(w === 'wszyscy' || g === w || g === 'plus' && w === 'wszyscy');
      });
    });
  });

  /* Okno zaproszeń */
  var modal = document.getElementById('modal-zaproszenie');
  if (!modal) return;
  var pole = modal.querySelector('input[name="nick"]');
  var rola = modal.querySelector('select[name="rola"]');

  function otworz(preferowana) {
    if (rola && preferowana) {
      var opcja = rola.querySelector('option[value="' + preferowana + '"]');
      if (opcja && !opcja.disabled) rola.value = preferowana;
      else {
        var wolna = rola.querySelector('option:not([disabled])');
        if (wolna) rola.value = wolna.value;
      }
    }
    modal.classList.add('otwarty');
    setTimeout(function () { if (pole) pole.focus(); }, 50);
  }
  function zamknij() { modal.classList.remove('otwarty'); }

  widok.querySelectorAll('[data-otworz-zaproszenie]').forEach(function (b) {
    b.addEventListener('click', function () { otworz(b.dataset.rola); });
  });
  document.getElementById('modal-zamknij').addEventListener('click', zamknij);
  modal.addEventListener('click', function (e) { if (e.target === modal) zamknij(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('otwarty')) zamknij();
  });
})();
