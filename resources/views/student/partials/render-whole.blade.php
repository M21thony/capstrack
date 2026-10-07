{{--
  A student page comes on screen whole, never half-drawn.

  The browser paints a page while it is still reading it, so the sidebar -
  first in the page - used to appear before the content, leaving the content
  area empty for a moment on every click: a flash. This holds the first paint
  until the page has been read: "expect" waits for an element with this id,
  and as no page has one, for the end of the page. It waits for the HTML
  only, not for scripts or pictures. Until then the browser keeps the
  previous page on screen, then swaps to the new one in a single step.
  Browsers without blocking="render" simply draw as they always have.
--}}
<link rel="expect" href="#student-page-read" blocking="render">
