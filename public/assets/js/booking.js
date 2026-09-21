/**
 * booking.js — kalendarz dostępności + wybór godziny + wysyłka formularza.
 * Korzysta z Fetch API i endpointów /api/slots.php oraz /api/create_booking.php.
 * Walidacja backendowa jest źródłem prawdy — tu robimy jedynie wygodę UX.
 */
(function () {
  'use strict';

  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var BASE = document.body.getAttribute('data-base') || '';
  var MONTHS = ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];

  var grid = document.getElementById('calGrid');
  var title = document.getElementById('calTitle');
  var slotsBox = document.getElementById('slots');
  var selectedDateEl = document.getElementById('selectedDate');
  var form = document.getElementById('bookingForm');
  var fDate = document.getElementById('fDate');
  var fTime = document.getElementById('fTime');
  var submitBtn = document.getElementById('submitBtn');
  var formHint = document.getElementById('formHint');
  var formAlert = document.getElementById('formAlert');

  var view = new Date();
  view.setDate(1);
  var availableDates = {};   // 'YYYY-MM-DD' -> true
  var chosenDate = null;
  var chosenTime = null;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

  function loadMonth() {
    var y = view.getFullYear(), m = view.getMonth() + 1;
    title.textContent = MONTHS[view.getMonth()] + ' ' + y;
    grid.setAttribute('aria-busy', 'true');
    fetch(BASE + '/api/slots.php?action=month&month=' + y + '-' + pad(m))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        availableDates = {};
        if (data.ok) { data.dates.forEach(function (d) { availableDates[d] = true; }); }
        renderCalendar();
      })
      .catch(function () { renderCalendar(); })
      .finally(function () { grid.removeAttribute('aria-busy'); });
  }

  function renderCalendar() {
    grid.innerHTML = '';
    var year = view.getFullYear(), month = view.getMonth();
    var first = new Date(year, month, 1);
    // Poniedziałek jako pierwszy dzień tygodnia.
    var startDow = (first.getDay() + 6) % 7;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var today = new Date(); today.setHours(0, 0, 0, 0);

    for (var i = 0; i < startDow; i++) {
      var empty = document.createElement('span');
      empty.className = 'cal-cell empty';
      grid.appendChild(empty);
    }
    for (var day = 1; day <= daysInMonth; day++) {
      var cellDate = new Date(year, month, day);
      var key = ymd(cellDate);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cal-cell';
      btn.textContent = day;
      btn.setAttribute('data-date', key);

      var isPast = cellDate < today;
      var isFree = !!availableDates[key];

      if (isPast || !isFree) {
        btn.classList.add('disabled');
        btn.disabled = true;
        if (!isPast && !isFree) { btn.classList.add('none'); btn.setAttribute('aria-label', day + ' — brak wolnych terminów'); }
      } else {
        btn.classList.add('free');
        btn.setAttribute('aria-label', day + ' — dostępny');
        btn.addEventListener('click', function () { selectDate(this); });
      }
      if (chosenDate === key) { btn.classList.add('selected'); }
      grid.appendChild(btn);
    }
  }

  function selectDate(btn) {
    chosenDate = btn.getAttribute('data-date');
    chosenTime = null;
    fDate.value = chosenDate;
    fTime.value = '';
    grid.querySelectorAll('.cal-cell').forEach(function (c) { c.classList.remove('selected'); });
    btn.classList.add('selected');
    selectedDateEl.textContent = 'Wybrany dzień: ' + chosenDate + '. Wybierz godzinę:';
    loadSlots(chosenDate);
    updateSubmitState();
  }

  function loadSlots(date) {
    slotsBox.innerHTML = '<p class="muted">Ładowanie godzin…</p>';
    fetch(BASE + '/api/slots.php?date=' + encodeURIComponent(date))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        slotsBox.innerHTML = '';
        if (!data.ok || !data.slots.length) {
          slotsBox.innerHTML = '<p class="muted">Brak wolnych terminów w tym dniu.</p>';
          return;
        }
        data.slots.forEach(function (t) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'slot-btn';
          b.textContent = t;
          b.setAttribute('data-time', t);
          b.setAttribute('data-testid', 'slot-' + t);
          b.addEventListener('click', function () { selectTime(this); });
          slotsBox.appendChild(b);
        });
      })
      .catch(function () { slotsBox.innerHTML = '<p class="muted">Nie udało się pobrać godzin.</p>'; });
  }

  function selectTime(btn) {
    chosenTime = btn.getAttribute('data-time');
    fTime.value = chosenTime;
    slotsBox.querySelectorAll('.slot-btn').forEach(function (s) { s.classList.remove('selected'); });
    btn.classList.add('selected');
    updateSubmitState();
  }

  function updateSubmitState() {
    var ready = !!(chosenDate && chosenTime);
    submitBtn.disabled = !ready;
    formHint.textContent = ready
      ? 'Wybrany termin: ' + chosenDate + ' o ' + chosenTime + '. Uzupełnij dane i wyślij.'
      : 'Wybierz termin, aby aktywować formularz.';
  }

  // --- Licznik znaków opisu ---
  var problem = document.getElementById('fProblem');
  var charCount = document.getElementById('charCount');
  if (problem && charCount) {
    problem.addEventListener('input', function () { charCount.textContent = problem.value.length; });
  }

  function clearErrors() {
    form.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
    formAlert.className = 'form-alert';
    formAlert.textContent = '';
  }

  function showErrors(errors) {
    Object.keys(errors || {}).forEach(function (field) {
      var el = form.querySelector('.field-error[data-for="' + field + '"]');
      if (el) { el.textContent = errors[field]; }
    });
  }

  function alertMsg(msg, type) {
    formAlert.className = 'form-alert show ' + (type || 'error');
    formAlert.textContent = msg;
    formAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // --- Wysyłka formularza ---
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearErrors();

    if (!chosenDate || !chosenTime) {
      alertMsg('Najpierw wybierz termin dostarczenia sprzętu.', 'error');
      return;
    }

    var payload = {
      csrf_token: CSRF,
      date: fDate.value,
      time: fTime.value,
      full_name: form.full_name.value,
      phone: form.phone.value,
      email: form.email.value,
      device_type: form.device_type.value,
      device_manufacturer: form.device_manufacturer.value,
      device_model: '',
      problem_description: form.problem_description.value,
      privacy_accepted: form.privacy_accepted.checked ? '1' : '',
      website: form.website ? form.website.value : ''
    };

    submitBtn.disabled = true;
    submitBtn.textContent = 'Wysyłanie…';

    fetch(BASE + '/api/create_booking.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
      .then(function (res) {
        var d = res.body;
        if (d.ok) {
          window.location.href = d.redirect || (BASE + '/booking-success.php');
          return;
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Wyślij zgłoszenie';
        if (d.errors) { showErrors(d.errors); }
        if (d.code === 'slot_taken') {
          alertMsg(d.error, 'error');
          loadSlots(chosenDate); // odśwież dostępne godziny
          chosenTime = null; fTime.value = ''; updateSubmitState();
        } else {
          alertMsg(d.error || 'Nie udało się wysłać zgłoszenia.', 'error');
        }
      })
      .catch(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Wyślij zgłoszenie';
        alertMsg('Błąd połączenia. Spróbuj ponownie.', 'error');
      });
  });

  // --- Nawigacja miesiącami ---
  document.getElementById('calPrev').addEventListener('click', function () {
    var now = new Date(); now.setDate(1); now.setHours(0,0,0,0);
    var prev = new Date(view.getFullYear(), view.getMonth() - 1, 1);
    if (prev < new Date(now.getFullYear(), now.getMonth(), 1)) { return; }
    view = prev; loadMonth();
  });
  document.getElementById('calNext').addEventListener('click', function () {
    view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
    loadMonth();
  });

  loadMonth();
})();
