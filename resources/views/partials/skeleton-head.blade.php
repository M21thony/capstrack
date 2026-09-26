{{-- How a page's content comes on screen, decided in <head> before the
     first paint.

     Every page arrives drawn by the server, so the loading skeleton only
     ever stood in front of content that was already there - for 400 to
     600ms on a first visit, and on the expert pages it then jumped the whole
     page up as it left. It is no longer shown: the class below hides it and
     shows the content at once, settling in with a short fade. Each page's
     own reveal script sees the class and simply removes its skeleton.

     A stylesheet !important beats a plain inline style, which is how this
     wins against the display:none each page sets on its content inline. --}}
@include('partials.smooth-navigation')
<script>document.documentElement.className += ' sk-seen';</script>
<style>
  html.sk-seen #skeleton-content { display: none !important; }
@if (($skReveal ?? 'block') === 'opacity')
  html.sk-seen #real-content { opacity: 1 !important; transition: none !important; animation: sk-page-in .2s ease-out; }
@else
  html.sk-seen #real-content { display: {{ $skReveal ?? 'block' }} !important; opacity: 1 !important; transition: none !important; animation: sk-page-in .2s ease-out; }
@endif
  /* A fade through filter, not opacity: opacity is held at 1 above, and the
     animation keeps nothing once it ends - an animation held on opacity would
     leave the content its own layer, and a pop-up inside it under the tab
     bar and the sidebar. */
  @keyframes sk-page-in { from { filter: opacity(0); } to { filter: opacity(1); } }
  /* A browser with view transitions cross-fades the whole page already
     (partials/smooth-navigation); a second fade inside it would dim twice. */
  @supports (view-transition-name: none) {
    html.sk-seen #real-content { animation: none; }
  }
  @media (prefers-reduced-motion: reduce) {
    html.sk-seen #real-content { animation: none; }
  }
</style>
