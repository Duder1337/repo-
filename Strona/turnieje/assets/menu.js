// Menu profilu w prawym górnym rogu oraz wyskakujące powiadomienia o zaproszeniach
(function () {
  /* ---- Menu profilu: zamykanie po kliknięciu poza nim i klawiszem Esc ---- */
  var menu = document.getElementById('profil-menu');
  if (menu) {
    document.addEventListener('click', function (e) {
      if (menu.open && !menu.contains(e.target)) menu.open = false;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.open) {
        menu.open = false;
        menu.querySelector('summary').focus();
      }
    });
  }

  /* ---- Powiadomienia ---- */
  var stos = document.getElementById('powiad-stos');
  if (!stos) return;

  function zamknij(t) {
    t.classList.add('znika');
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 250);
  }
  function podepnij(t) {
    var x = t.querySelector('.toast-x');
    if (x) x.addEventListener('click', function () { zamknij(t); });
    var link = t.querySelector('.toast-link');
    if (link) link.addEventListener('click', function () { zamknij(t); });
    setTimeout(function () { if (t.parentNode) zamknij(t); }, 12000);
  }
  Array.prototype.forEach.call(stos.querySelectorAll('.toast'), podepnij);

  function pokazToast(tytul, opis) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');

    var ikona = document.createElement('span');
    ikona.className = 'toast-ikona';
    ikona.textContent = '\u2709\uFE0F';

    var tresc = document.createElement('div');
    tresc.className = 'toast-tresc';
    var b = document.createElement('b');
    b.textContent = tytul;
    var s = document.createElement('span');
    s.textContent = opis;
    tresc.appendChild(b);
    tresc.appendChild(s);

    var a = document.createElement('a');
    a.className = 'toast-link';
    a.href = stos.dataset.zaproszenia;
    a.textContent = 'Zobacz';

    var x = document.createElement('button');
    x.type = 'button';
    x.className = 'toast-x';
    x.setAttribute('aria-label', 'Zamknij');
    x.innerHTML = '&times;';

    t.appendChild(ikona);
    t.appendChild(tresc);
    t.appendChild(a);
    t.appendChild(x);
    stos.appendChild(t);
    podepnij(t);
  }

  // Licznik na zdjęciu profilowym i w pozycji „Zaproszenia”
  function ustawLicznik(n) {
    var awatar = document.querySelector('.pm-awatar');
    var kropka = document.querySelector('.pm-kropka');
    if (awatar) {
      if (n > 0) {
        if (!kropka) {
          kropka = document.createElement('span');
          kropka.className = 'pm-kropka';
          kropka.title = 'Oczekujące zaproszenia';
          awatar.appendChild(kropka);
        }
        kropka.textContent = n;
      } else if (kropka) {
        kropka.parentNode.removeChild(kropka);
      }
    }
    var wMenu = document.querySelector('.pm-licznik');
    if (wMenu) {
      wMenu.textContent = n;
      wMenu.hidden = n <= 0;
    }
  }

  var endpoint = stos.dataset.endpoint;
  if (!endpoint || !window.fetch) return;

  function sprawdz() {
    fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        ustawLicznik(d.licznik);
        if (d.nowe && stos.dataset.bezToastow !== '1') pokazToast(d.tytul, d.opis);
      })
      .catch(function () { /* brak sieci – spróbujemy za chwilę */ });
  }
  setInterval(function () { if (!document.hidden) sprawdz(); }, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) sprawdz(); });
})();
