/**
 * admin.js — drobna interaktywność panelu (menu mobilne).
 */
(function () {
  'use strict';
  var burger = document.getElementById('adminBurger');
  var sidebar = document.getElementById('adminSidebar');
  if (burger && sidebar) {
    burger.addEventListener('click', function () {
      sidebar.classList.toggle('is-open');
    });
    document.querySelectorAll('.admin-nav a').forEach(function (a) {
      a.addEventListener('click', function () { sidebar.classList.remove('is-open'); });
    });
  }
})();
