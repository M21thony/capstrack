{{-- The face of every anonymous team leader: one default profile for all of
     them, never a colour or initial that could tell one sender from another,
     with a lock to say why there is no name. --}}
<span class="ev-anon-av{{ ($size ?? '') === 'sm' ? ' is-sm' : '' }}" role="img" aria-label="Anonymous team leader">
  <svg viewBox="0 0 40 40" aria-hidden="true">
    <circle cx="20" cy="20" r="20" class="ev-anon-bg"/>
    <circle cx="20" cy="15.2" r="6.6" class="ev-anon-fg"/>
    <path d="M6.6 34.8C8.5 28.4 13.7 24.6 20 24.6s11.5 3.8 13.4 10.2A19.94 19.94 0 0 1 20 40a19.94 19.94 0 0 1-13.4-5.2z" class="ev-anon-fg"/>
  </svg>
  <span class="ev-anon-lock" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
</span>
