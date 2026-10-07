{{--
  A student page comes on screen whole, never half-drawn.

  The browser paints a page while it is still reading it, so the sidebar -
  first in the page - used to appear before the content, leaving the content
  area empty for a moment on every click: a flash. A module script runs only
  once the whole page has been read, and blocking="render" holds the first
  paint until it has run; until then the browser keeps the previous page on
  screen, then swaps to the new one in a single step. Browsers without
  blocking="render" simply draw as they always have.
--}}
<script type="module" blocking="render" nonce="{{ Vite::cspNonce() }}">/* The page has been read in full. */</script>
