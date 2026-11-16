// Podpowiedzi nicków przy wysyłaniu zaproszenia do drużyny
(function () {
  var form = document.getElementById('form-zapros');
  if (!form) return;

  var pole   = form.querySelector('input[name="nick"]');
  var lista  = document.getElementById('podpowiedzi');
  var url    = form.dataset.szukaj;
  var druzyna = form.dataset.druzyna;
  var timer = null, numer = 0;

  function ukryj() { lista.innerHTML = ''; }

  function szukaj() {
    var moj = ++numer;
    fetch(url + '?druzyna=' + encodeURIComponent(druzyna) + '&q=' + encodeURIComponent(pole.value.trim()))
      .then(function (r) { return r.json(); })
      .then(function (nicki) {
        if (moj !== numer) return;
        lista.innerHTML = '';
        if (!nicki.length) {
          var brak = document.createElement('li');
          brak.className = 'brak-wynikow';
          brak.textContent = 'Brak wolnych zawodników o takim nicku';
          lista.appendChild(brak);
          return;
        }
        nicki.forEach(function (n) {
          var li = document.createElement('li');
          li.textContent = n;
          li.addEventListener('mousedown', function (e) {
            e.preventDefault();
            pole.value = n;
            ukryj();
          });
          lista.appendChild(li);
        });
      })
      .catch(function () {});
  }

  pole.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(szukaj, 200);
  });
  pole.addEventListener('focus', szukaj);
  pole.addEventListener('blur', function () { setTimeout(ukryj, 150); });
})();
