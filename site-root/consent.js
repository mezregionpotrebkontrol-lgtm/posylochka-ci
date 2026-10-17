/*
 * Согласие на обработку персональных данных (152-ФЗ) для всех форм сайта.
 *
 * Подключение: одна строка перед </body> на каждой странице с формами
 * (index.html, dostavka.html, app.html):
 *     <script src="/consent.js" defer></script>
 *
 * Что делает:
 *  1. В каждую форму, где есть поле телефона, добавляет галочку
 *     «Согласен на обработку персональных данных» со ссылкой на политику.
 *  2. Отправка заявки/заказа на /api/... без галочки
 *     останавливается с понятным сообщением.
 *  3. В запрос автоматически добавляется consent: true — CRM запоминает,
 *     когда клиент дал согласие.
 * Код сайта менять не нужно.
 */
(function () {
  'use strict';

  var POLICY_URL = '/politika.html';
  var API_RE = /\/api\/(create_order|create_shipment|submit_order_request|submit_business_request)\.php/;
  var CLS = 'pd-consent';

  function addCheckbox(container) {
    if (container.querySelector('.' + CLS) || container.querySelector('input[name="consent"]')) return;
    var wrap = document.createElement('label');
    wrap.className = CLS;
    wrap.style.cssText = 'display:flex;gap:8px;align-items:flex-start;margin:10px 0;font-size:13px;line-height:1.35;cursor:pointer;text-align:left;';
    wrap.innerHTML =
      '<input type="checkbox" name="consent" value="1" style="margin-top:2px;flex:0 0 auto;width:16px;height:16px;">' +
      '<span>Я согласен(на) на <a href="' + POLICY_URL + '" target="_blank" rel="noopener">обработку персональных данных</a> ' +
      'для оформления и доставки посылки</span>';
    var btn = container.querySelector('button[type="submit"], input[type="submit"]') ||
      container.querySelectorAll('button')[container.querySelectorAll('button').length - 1];
    if (btn && btn.parentNode) {
      btn.parentNode.insertBefore(wrap, btn);
    } else {
      container.appendChild(wrap);
    }
  }

  function scan() {
    var phones = document.querySelectorAll('input[type="tel"], input[name="phone"], input[name*="phone" i]');
    for (var i = 0; i < phones.length; i++) {
      var container = phones[i].closest('form') || phones[i].closest('.modal, .card, section, div');
      // Вход и регистрация (есть поле пароля) пропускаем: у регистрации своя галочка.
      if (container && !container.querySelector('input[type="password"]')) addCheckbox(container);
    }
  }

  function consentGiven() {
    var boxes = document.querySelectorAll('input[name="consent"]');
    for (var i = 0; i < boxes.length; i++) {
      // Учитываем только видимые формы (у скрытых модальных окон offsetParent === null).
      if (boxes[i].checked) return true;
    }
    return boxes.length === 0; // если галочки на странице нет — не блокируем
  }

  function refuse() {
    var msg = 'Пожалуйста, отметьте согласие на обработку персональных данных.';
    var boxes = document.querySelectorAll('.' + CLS);
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].style.outline = '2px solid #e5484d';
      boxes[i].style.borderRadius = '6px';
    }
    try { alert(msg); } catch (e) {}
    return Promise.resolve(new Response(JSON.stringify({ ok: false, error: msg }), {
      status: 400, headers: { 'Content-Type': 'application/json' }
    }));
  }

  if (window.fetch) {
    var origFetch = window.fetch;
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      if (API_RE.test(url) && init && typeof init.body === 'string') {
        if (!consentGiven()) return refuse();
        try {
          var data = JSON.parse(init.body);
          if (data && typeof data === 'object') {
            data.consent = true;
            init = Object.assign({}, init, { body: JSON.stringify(data) });
          }
        } catch (e) { /* тело не JSON — отправляем как есть */ }
      }
      return origFetch.call(this, input, init);
    };
  }

  // Обычные HTML-формы (без fetch): не даём отправить без галочки.
  document.addEventListener('submit', function (ev) {
    var box = ev.target && ev.target.querySelector && ev.target.querySelector('input[name="consent"]');
    if (box && !box.checked) {
      ev.preventDefault();
      refuse();
    }
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }
  // Формы, которые появляются позже (модальные окна, личный кабинет).
  if (window.MutationObserver) {
    var t = null;
    new MutationObserver(function () {
      clearTimeout(t);
      t = setTimeout(scan, 200);
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
