/**
 * main.js — wspólny skrypt stron publicznych.
 * Menu hamburger + rozwijanie ikon usług.
 */
(function () {
  'use strict';

  // --- Menu mobilne ---
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('mainNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.classList.toggle('is-open', open);
    });
    // Zamknij menu po kliknięciu linku (mobile).
    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        nav.classList.remove('is-open');
        toggle.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // --- Ikony usług (proste inline SVG bez zewnętrznych bibliotek) ---
  var ICONS = {
    stethoscope: '<path d="M6 3v6a5 5 0 0 0 10 0V3M6 3H4m2 0h2m6 0h2m-2 0h-2M11 19a3 3 0 1 0 6 0v-3"/><circle cx="20" cy="14" r="2"/>',
    wind: '<path d="M3 8h11a3 3 0 1 0-3-3M3 16h15a3 3 0 1 1-3 3M3 12h9"/>',
    download: '<path d="M12 3v12m0 0l4-4m-4 4l-4-4M4 21h16"/>',
    bug: '<path d="M8 6a4 4 0 0 1 8 0M5 10h14M6 14h12M4 12h2m12 0h2M9 6l-1-2m8 2l1-2M10 21v-2m4 2v-2"/><rect x="7" y="6" width="10" height="12" rx="5"/>',
    sliders: '<path d="M4 6h10m4 0h2M4 12h2m4 0h10M4 18h14m4 0h-2"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    printer: '<path d="M6 9V3h12v6M6 18H4v-6h16v6h-2M8 14h8v7H8z"/>',
    headset: '<path d="M4 13v-1a8 8 0 0 1 16 0v1M4 13v3a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 1zm16 0v3a4 4 0 0 1-4 4h-3m7-7v-1a2 2 0 0 0-2-1h-1v6h1"/>',
    wrench: '<path d="M14 7a4 4 0 0 0-5 5l-6 6 2 2 6-6a4 4 0 0 0 5-5l-2 2-2-2 2-2z"/>'
  };
  document.querySelectorAll('.card-icon[data-icon]').forEach(function (el) {
    var name = el.getAttribute('data-icon');
    if (ICONS[name]) {
      el.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }
  });
})();
