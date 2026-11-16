// Wybór zawodników do składu drużyny przez karty i okno wyszukiwania
(function () {
  var kreator = document.querySelector('.kreator');
  if (!kreator) return;

  var url    = kreator.dataset.szukaj;
  var modal  = document.getElementById('modal-gracza');
  var pole   = document.getElementById('modal-szukaj');
  var lista  = document.getElementById('modal-wyniki');
  var tytul  = document.getElementById('modal-tytul');
  var karty  = document.querySelectorAll('.gcard');
  var aktualna = null, timer = null, numer = 0;

  function nickKarty(karta) { return karta.querySelector('input').value.trim(); }

  function ustaw(karta, nick) {
    karta.querySelector('input').value = nick;
    karta.querySelector('.gcard-nick').textContent = nick;
    karta.querySelector('.gcard-avatar').textContent = nick ? nick.charAt(0).toUpperCase() : '+';
    if (nick) karta.classList.remove('pusta', 'brak'); else karta.classList.add('pusta');
  }

  function uzyte() {
    var u = {};
    karty.forEach(function (k) {
      var n = nickKarty(k).toLowerCase();
      if (n && k !== aktualna) u[n] = true;
    });
    return u;
  }

  function szukaj() {
    var moj = ++numer;
    fetch(url + '?q=' + encodeURIComponent(pole.value.trim()))
      .then(function (r) { return r.json(); })
      .then(function (nicki) {
        if (moj !== numer) return;
        var zajete = uzyte(), ile = 0;
        lista.innerHTML = '';
        nicki.forEach(function (n) {
          if (zajete[n.toLowerCase()] || ile >= 8) return;
          ile++;
          var li = document.createElement('li');
          li.textContent = n;
          li.addEventListener('click', function () { wybierz(n); });
          lista.appendChild(li);
        });
        if (!ile) {
          var brak = document.createElement('li');
          brak.className = 'brak-wynikow';
          brak.textContent = 'Brak pasujących zawodników';
          lista.appendChild(brak);
        }
      })
      .catch(function () {});
  }

  function otworz(karta) {
    aktualna = karta;
    tytul.textContent = 'Wybierz zawodnika: ' + karta.dataset.etykieta;
    pole.value = '';
    lista.innerHTML = '';
    modal.classList.add('otwarty');
    szukaj();
    setTimeout(function () { pole.focus(); }, 50);
  }

  function zamknij() {
    modal.classList.remove('otwarty');
    aktualna = null;
  }

  function wybierz(nick) {
    if (aktualna) ustaw(aktualna, nick);
    zamknij();
  }

  karty.forEach(function (karta) {
    karta.addEventListener('click', function (e) {
      if (e.target.closest('.gcard-x')) {
        ustaw(karta, '');
        e.stopPropagation();
        return;
      }
      otworz(karta);
    });
    karta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target === karta) otworz(karta);
    });
  });

  pole.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(szukaj, 200);
  });
  pole.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var pierwszy = lista.querySelector('li:not(.brak-wynikow)');
      if (pierwszy) wybierz(pierwszy.textContent);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('otwarty')) zamknij();
  });
  document.getElementById('modal-zamknij').addEventListener('click', zamknij);
  modal.addEventListener('click', function (e) { if (e.target === modal) zamknij(); });

  // Przed wysłaniem sprawdzamy, czy podstawowy skład jest kompletny
  kreator.addEventListener('submit', function (e) {
    var pierwsza = null;
    karty.forEach(function (k) {
      if (k.dataset.wymagane === '1' && !nickKarty(k)) {
        k.classList.add('brak');
        if (!pierwsza) pierwsza = k;
      }
    });
    if (pierwsza) {
      e.preventDefault();
      pierwsza.scrollIntoView({ behavior: 'smooth', block: 'center' });
      alert('Uzupełnij podstawowy skład: kapitan i 4 graczy.');
    }
  });
})();
