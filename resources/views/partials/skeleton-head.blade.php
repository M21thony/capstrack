{{-- Decide BEFORE the first paint whether this page needs its loading state.

     The reveal scripts all ran at the end of the body, so the browser painted
     the skeleton first and swapped it out afterwards - on every load, saves
     included. That is the flicker: you press Save, the page comes back, and
     the content you were looking at disappears and returns.

     This runs in <head>. When the page has already been opened during this
     login, it puts a class on <html> and the CSS below means the skeleton is
     never painted at all. A stylesheet !important beats a plain inline style,
     which is how it wins against the display:none / opacity:0 each page sets
     on its own content.

     Still skeletons on a first visit - a fresh login, or a page reached from
     the sidebar for the first time - which is the only time there is really
     nothing on screen yet.

     Keyed to the login: session()->getId() changes when the session is
     invalidated at logout, so signing back in skeletons again without every
     logout button having to clear sessionStorage. --}}
<script>
(function () {
  try {
    var key = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;
    if (sessionStorage.getItem(key)) {
      document.documentElement.className += ' sk-seen';
    }
  } catch (e) {
    // Private mode or blocked storage: fall through and show the skeleton,
    // which is the old behaviour and no worse than it was.
  }
})();
</script>
<style>
  html.sk-seen #skeleton-content { display: none !important; }
@if (($skReveal ?? 'block') === 'opacity')
  html.sk-seen #real-content { opacity: 1 !important; transition: none !important; }
@else
  html.sk-seen #real-content { display: {{ $skReveal ?? 'block' }} !important; opacity: 1 !important; transition: none !important; }
@endif
</style>
