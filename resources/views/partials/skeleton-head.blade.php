{{-- How a page's content comes on screen, decided in <head> before the
     first paint.

     Every page arrives drawn by the server, so the loading skeleton only
     ever stood in front of content that was already there - for 400 to
     600ms on a first visit, and on the expert pages it then jumped the whole
     page up as it left. It is no longer shown: the class below hides it and
     shows the content at once, with no fade - a fade-in on every page read
     as a flash. Each page's own reveal script sees the class and simply
     removes its skeleton.

     A stylesheet !important beats a plain inline style, which is how this
     wins against the display:none each page sets on its content inline. --}}
@include('partials.smooth-navigation')
<script>document.documentElement.className += ' sk-seen';</script>
<style>
  html.sk-seen #skeleton-content { display: none !important; }
@if (($skReveal ?? 'block') === 'opacity')
  html.sk-seen #real-content { opacity: 1 !important; transition: none !important; }
@else
  html.sk-seen #real-content { display: {{ $skReveal ?? 'block' }} !important; opacity: 1 !important; transition: none !important; }
@endif
</style>
