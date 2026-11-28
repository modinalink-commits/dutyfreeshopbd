(function () {
  var D = window.DFD;
  /* age gate (only when config.ageGate is true) */
  var gate = D.$('gate');
  function seen() { try { return sessionStorage.getItem('dfd-age-ok') === '1'; } catch (e) { return false; } }
  if (!D.config.ageGate) { gate.remove(); document.querySelectorAll('[data-age]').forEach(function (n) { n.remove(); }); }
  else if (!seen()) {
    gate.showModal();
    D.$('gate-yes').addEventListener('click', function () { try { sessionStorage.setItem('dfd-age-ok', '1'); } catch (e) {} gate.close(); });
    D.$('gate-no').addEventListener('click', function () { D.$('gate-t').textContent = 'Sorry, you cannot enter'; D.$('gate-p').textContent = 'This store is for adults only.'; D.$('gate-a').innerHTML = ''; });
    gate.addEventListener('cancel', function (e) { e.preventDefault(); });
  }
  D.start();
})();
