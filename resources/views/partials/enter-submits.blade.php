{{--
  Enter does what the form's own button does, on every portal page.

  A page's own <form> with a submit button already goes on Enter - the
  browser sees to that. But most pop-ups here are a box of fields with a
  button that runs a script (Add student, Send Request, Save, Done), and on
  those Enter did nothing: a phone's keyboard showed its Done or Go key and
  pressing it only closed the keyboard, so the button had to be found and
  tapped as well.

  One listener, like partials/escape-closes-modals: from the field being
  typed in it looks outwards, nearest first, for the button that finishes
  the job - a primary-looking one, or one whose name is what finishing is
  called (Save, Send, Add, Done...) - and presses it. Cancel, Close and
  anything that deletes are never pressed. The phone keyboard's Enter key is
  also labelled to match: Send, Search, Go or Done.

  Out of its way:
    - a field whose own script already handles Enter (it prevents the
      default, which this checks for) - tag inputs, suggestion lists;
    - a <form> with a submit button of its own;
    - a textarea, where Enter is a new line, unless the box is for a quick
      message (its button is Send or Reply) or carries data-enter-sends -
      then Shift+Enter is the new line;
    - a search or filter box, unless there is a Search button to press;
    - anything inside data-enter-off.
  A button carrying data-enter-default is the one pressed, whatever it says.

  Included by partials/mobile-tabbar, which every portal page has.
--}}
@once
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  // The shapes a pop-up takes in this codebase (as in escape-closes-modals),
  // plus a form: the button is looked for no further out than these.
  var SCOPES = [
    'form', 'dialog', '[role="dialog"]', '[aria-modal="true"]',
    '.modal-overlay', '.upload-scrim', '.ct-modal-overlay', '.profile-overlay',
    '.pp-modal-overlay', '.popover-overlay', '.modal', '.drawer',
  ].join(',');
  var TYPED = /^(text|email|number|tel|url|password|search|date|time|datetime-local|month|week)$/;
  var NEVER = /\b(cancel|close|back|clear|reset|delete|remove|archive|reject|decline|disband|leave|revoke|kick|discard|sign ?out|log ?out)\b/i;
  var FINISHES = /^(done|send|save|submit|confirm|ok|okay|add|apply|post|publish|update|create|search|find|go|continue|next|sign in|log in|join|invite|request|book|reply|upload|review|set|change|verify|schedule|assign|finish|rename)\b/i;
  var LOOKS_PRIMARY = /\b(btn-primary|btn-solid|btn-gold|primary|solid|gold|confirm|submit|save|done)\b/i;
  var LOOKS_SECONDARY = /\b(btn-soft|btn-ghost|btn-outline|btn-secondary|soft|ghost|outline|secondary|danger|link)\b/i;

  var nameOf = function (button) {
    return (button.value || button.textContent || button.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim();
  };

  var showing = function (button) {
    if (button.disabled || button.getAttribute('aria-disabled') === 'true') return false;
    if (button.closest('[hidden], [inert], [data-enter-off]')) return false;
    var box = button.getBoundingClientRect();
    return box.width > 0 && box.height > 0 && getComputedStyle(button).visibility !== 'hidden';
  };

  // How surely a button is the one that finishes: 0 is never.
  var score = function (button) {
    if (button.hasAttribute('data-enter-default')) return 10;
    var name = nameOf(button);
    if (!name || NEVER.test(name)) return 0;
    if (button.hasAttribute('aria-expanded') || button.hasAttribute('aria-pressed') || button.getAttribute('role') === 'tab') return 0;
    var looks = (button.className || '') + ' ' + (button.id || '');
    var points = 0;
    if (button.getAttribute('type') === 'submit') points += 3;
    if (LOOKS_PRIMARY.test(looks)) points += 3;
    if (LOOKS_SECONDARY.test(looks)) points -= 3;
    if (FINISHES.test(name)) points += 2;
    return Math.max(0, points);
  };

  // A box for finding things only ever presses a Search button: Enter in
  // the filter above a list must not open the list's Add form.
  var isSearchBox = function (field) {
    var about = (field.placeholder || '') + ' ' + (field.getAttribute('aria-label') || '') + ' ' + (field.id || '') + ' ' + (field.name || '');
    return field.type === 'search' || /search|filter|find/i.test(about);
  };

  // Outwards from the field, the nearest box holding a finishing button,
  // and in it the surest (the last of equals: a footer's primary sits last).
  // Outside a pop-up only a button named for finishing, close by, will do -
  // a search box beside a page's big Add button must not open it.
  var finisherFor = function (field) {
    var scope = field.closest(SCOPES);
    var searching = isSearchBox(field);
    var node = field.parentElement;
    var depth = 0;

    while (node && node !== document.body) {
      var best = null;
      var bestScore = 0;
      node.querySelectorAll('button, input[type="submit"], input[type="button"]').forEach(function (button) {
        if (!showing(button)) return;
        var points = score(button);
        if (!scope && !FINISHES.test(nameOf(button)) && !button.hasAttribute('data-enter-default')) return;
        if (searching && !/^(search|find|go)\b/i.test(nameOf(button)) && !button.hasAttribute('data-enter-default')) return;
        if (points >= 2 && points >= bestScore) { best = button; bestScore = points; }
      });
      if (best) return best;
      if (node === scope) return null;
      if (!scope && ++depth >= 3) return null;
      node = node.parentElement;
    }

    return null;
  };

  var takesEnter = function (field) {
    if (!field || field.closest('[data-enter-off]')) return false;
    if (field.tagName === 'TEXTAREA') return true;
    return field.tagName === 'INPUT' && TYPED.test(field.type || 'text');
  };

  // A form that submits itself on Enter is left to the browser.
  var submitsItself = function (field) {
    var form = field.form;
    if (!form) return false;
    return Array.prototype.some.call(form.elements, function (el) {
      return (el.type === 'submit' || el.type === 'image') && !el.disabled;
    });
  };

  var isQuickMessage = function (field, button) {
    return field.hasAttribute('data-enter-sends') || /^(send|reply)\b/i.test(nameOf(button));
  };

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' || event.defaultPrevented || event.isComposing || event.keyCode === 229) return;
    if (event.ctrlKey || event.metaKey || event.altKey) return;

    var field = event.target;
    if (!takesEnter(field) || submitsItself(field)) return;
    if (field.tagName !== 'TEXTAREA' && event.shiftKey) return;

    var button = finisherFor(field);
    if (!button) return;
    if (field.tagName === 'TEXTAREA' && (event.shiftKey || !isQuickMessage(field, button))) return;

    event.preventDefault();
    // On a phone the keyboard goes away, as its Done key promises.
    if (window.matchMedia && matchMedia('(pointer:coarse)').matches) field.blur();
    button.click();
  });

  // The phone keyboard's Enter key says what it will do.
  document.addEventListener('focusin', function (event) {
    var field = event.target;
    if (!takesEnter(field) || field.hasAttribute('enterkeyhint') || submitsItself(field)) return;
    var button = finisherFor(field);
    if (!button) return;
    if (field.tagName === 'TEXTAREA' && !isQuickMessage(field, button)) return;
    var name = nameOf(button);
    var hint = /^(send|reply)\b/i.test(name) ? 'send'
      : /^(search|find)\b/i.test(name) ? 'search'
      : /^(go|continue|next|sign in|log in)\b/i.test(name) ? 'go'
      : 'done';
    field.setAttribute('enterkeyhint', hint);
  });
})();
</script>
@endonce
