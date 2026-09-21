/**
 * status.js — sprawdzanie statusu zlecenia przez /api/status.php.
 */
(function () {
  'use strict';

  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var form = document.getElementById('statusForm');
  var alertBox = document.getElementById('statusAlert');
  var result = document.getElementById('statusResult');
  if (!form) { return; }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    alertBox.className = 'form-alert';
    alertBox.textContent = '';
    result.hidden = true;

    var payload = {
      csrf_token: CSRF,
      number: form.number.value.trim(),
      token: form.token.value.trim()
    };

    fetch('/api/status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) {
          document.getElementById('rNumber').textContent = d.number;
          var badge = document.getElementById('rStatus');
          badge.textContent = d.status_label;
          badge.className = 'badge badge-' + d.status;
          document.getElementById('rUpdated').textContent = d.updated_at;
          result.hidden = false;
          result.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          alertBox.className = 'form-alert show error';
          alertBox.textContent = d.error || 'Nie znaleziono zlecenia.';
        }
      })
      .catch(function () {
        alertBox.className = 'form-alert show error';
        alertBox.textContent = 'Błąd połączenia. Spróbuj ponownie.';
      });
  });
})();
