/**
 * Quoodle — client-side JavaScript
 * - Dark mode toggle, copy-to-clipboard, stepper
 * - Immediate per-question feedback with 5 s delay on wrong answers
 * - Elapsed time + tab-switch tracking
 */

(function () {
  'use strict';

  if (document.body) document.body.classList.remove('no-js');

  // ── Dark mode toggle ───────────────────────────────────────────────────────
  var toggleBtn = document.getElementById('themeToggle');
  if (toggleBtn) {
    updateToggleIcon();
    toggleBtn.addEventListener('click', function () {
      var html   = document.documentElement;
      var isDark = html.getAttribute('data-theme') === 'dark';
      var next   = isDark ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      document.cookie = 'theme=' + next + ';path=/;max-age=' + (365 * 86400) + ';SameSite=Lax';
      updateToggleIcon();
    });
  }
  function updateToggleIcon() {
    if (!toggleBtn) return;
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var icon   = toggleBtn.querySelector('.theme-icon');
    if (icon) icon.textContent = isDark ? '☀' : '☾';
  }

  // ── Copy-to-clipboard buttons ──────────────────────────────────────────────
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sel    = btn.dataset.copy;
      var target = sel ? document.querySelector(sel) : null;
      var text   = target ? (target.textContent || target.value || '').trim() : '';
      if (!text) return;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { flashCopied(btn); })
          .catch(function () { fallbackCopy(text, btn); });
      } else {
        fallbackCopy(text, btn);
      }
    });
  });

  function fallbackCopy(text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy'); flashCopied(btn); } catch (e) {}
    document.body.removeChild(ta);
  }

  function flashCopied(btn) {
    var original = btn.textContent;
    var copied   = btn.dataset.copiedLabel || 'Kopiert';
    btn.textContent = copied;
    btn.disabled    = true;
    setTimeout(function () {
      btn.textContent = original;
      btn.disabled    = false;
    }, 1500);
  }

  // ── Quiz stepper (only runs on quiz page) ──────────────────────────────────
  var steps = Array.from(document.querySelectorAll('.quiz-step'));
  if (steps.length > 0 && window.__QUOODLE) {
    initQuiz(steps, window.__QUOODLE);
  }

  function initQuiz(steps, data) {
    var total        = steps.length;
    var current      = 0;
    var prevBtn      = document.getElementById('stepPrev');
    var nextBtn      = document.getElementById('stepNext');
    var submitBtn    = document.getElementById('stepSubmit');
    var progressFill = document.getElementById('progressFill');
    var progressText = document.getElementById('progressText');
    var validMsg     = document.getElementById('validationMsg');
    var elapsedInp   = document.getElementById('elapsedTime');
    var tabInp       = document.getElementById('tabSwitches');
    var form         = document.getElementById('quiz-form');

    var WAIT_SECONDS = 5;
    var startTs      = Date.now();
    var tabSwitches  = 0;
    var countdownId  = null;
    var answered     = new Array(total).fill(false);

    // ── Tab-switch detection ─────────────────────────────────────────────
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) tabSwitches++;
    });

    // ── Handle answer selection ──────────────────────────────────────────
    steps.forEach(function (step, qi) {
      var labels = step.querySelectorAll('.choice-label');

      labels.forEach(function (label) {
        label.addEventListener('click', function (e) {
          if (answered[qi]) { e.preventDefault(); return; }

          var input = label.querySelector('input[type="radio"]');
          if (!input) return;

          answered[qi]  = true;
          input.checked = true;

          step.querySelectorAll('.choice-label').forEach(function (l) {
            l.classList.add('is-locked');
          });

          var selected  = input.value;
          var correct   = data.correct[qi];
          var isCorrect = (selected === correct);

          labels.forEach(function (l) {
            var v    = l.querySelector('input[type="radio"]').value;
            var mark = l.querySelector('.choice-mark');
            if (v === correct) {
              l.classList.add('fc-correct');
              if (mark) mark.textContent = '✓';
            } else if (v === selected) {
              l.classList.add('fc-wrong');
              if (mark) mark.textContent = '✗';
            }
          });

          var expl = step.querySelector('.quiz-inline-expl');
          var txt  = data.explanations[qi] || '';
          if (expl && txt !== '') {
            var textEl = expl.querySelector('.quiz-inline-expl-text');
            if (textEl) textEl.textContent = txt;
            expl.style.display = 'block';
          }

          if (validMsg) validMsg.style.display = 'none';

          if (isCorrect) {
            enableAdvance();
          } else {
            startCountdown(WAIT_SECONDS);
          }
        });
      });
    });

    function startCountdown(seconds) {
      var btn = currentAdvanceBtn();
      if (!btn) return;
      var baseLabel = (current === total - 1) ? data.labels.submit : (data.labels.next + ' →');
      var remaining = seconds;

      btn.disabled    = true;
      btn.textContent = baseLabel + ' (' + remaining + 's)';
      if (countdownId) clearInterval(countdownId);

      countdownId = setInterval(function () {
        remaining--;
        if (remaining <= 0) {
          clearInterval(countdownId);
          countdownId = null;
          btn.disabled    = false;
          btn.textContent = baseLabel;
        } else {
          btn.textContent = baseLabel + ' (' + remaining + 's)';
        }
      }, 1000);
    }

    function enableAdvance() {
      var btn = currentAdvanceBtn();
      if (!btn) return;
      btn.disabled    = false;
      btn.textContent = (current === total - 1) ? data.labels.submit : (data.labels.next + ' →');
    }

    function currentAdvanceBtn() {
      return (current === total - 1) ? submitBtn : nextBtn;
    }

    function show(idx) {
      if (countdownId) { clearInterval(countdownId); countdownId = null; }

      steps.forEach(function (s, i) { s.classList.toggle('active', i === idx); });
      var isLast = (idx === total - 1);

      if (prevBtn)   prevBtn.style.display   = (idx > 0) ? '' : 'none';
      if (nextBtn)   nextBtn.style.display   = isLast ? 'none' : '';
      if (submitBtn) submitBtn.style.display = isLast ? '' : 'none';
      if (validMsg)  validMsg.style.display  = 'none';

      var btn = isLast ? submitBtn : nextBtn;
      if (btn) {
        btn.disabled    = !answered[idx];
        btn.textContent = isLast ? data.labels.submit : (data.labels.next + ' →');
      }

      if (progressFill) {
        var pct = Math.round(((idx + 1) / total) * 100);
        progressFill.style.width = pct + '%';
      }
      if (progressText) {
        var tmpl = progressText.dataset.template || '{0} / {1}';
        progressText.textContent = tmpl.replace('{0}', idx + 1).replace('{1}', total);
      }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        if (current > 0) { current--; show(current); }
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        if (!answered[current]) {
          if (validMsg) validMsg.style.display = 'block';
          return;
        }
        if (current < total - 1) { current++; show(current); }
      });
    }

    if (form) {
      form.addEventListener('submit', function (e) {
        if (!answered[total - 1]) {
          e.preventDefault();
          if (validMsg) validMsg.style.display = 'block';
          return;
        }
        var elapsedSec = Math.max(0, Math.round((Date.now() - startTs) / 1000));
        if (elapsedInp) elapsedInp.value = String(elapsedSec);
        if (tabInp)     tabInp.value     = String(tabSwitches);
      });
    }

    show(0);
  }

})();
