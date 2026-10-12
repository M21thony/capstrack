{{--
  A person's face at the start of a table row (public/css/table-kit.css).

  Their photo when they have one, their initials on a tint when they do not -
  a grey silhouette repeated down a list said nothing and told nobody apart.
  An account nobody has signed in to yet gets an envelope instead: it is an
  address waiting for its owner, not a person with no picture.

    'image'    => photo URL, or null/'' for none
    'initials' => two letters
    'seed'     => any number; picks the tint, so one person keeps one colour
    'awaiting' => true for an account that has never been signed in to
    'presence' => 'on' (online now) or 'off'; leave out to show no dot
--}}
@php
  $tkTone = 'tk-tone-'.(abs((int) ($seed ?? 0)) % 6);
  $tkPresence = $presence ?? null;
@endphp
@if ($awaiting ?? false)
  <span class="tk-av tk-av-inv" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
@elseif (filled($image ?? null))
  <span class="tk-av" aria-hidden="true"><img src="{{ $image }}" alt="" loading="lazy"
    onerror="this.parentNode.classList.add('{{ $tkTone }}');this.replaceWith(document.createTextNode({{ Illuminate\Support\Js::from($initials) }}))">@if ($tkPresence)<i class="tk-pres tk-pres-{{ $tkPresence }}"></i>@endif</span>
@else
  <span class="tk-av {{ $tkTone }}" aria-hidden="true">{{ $initials }}@if ($tkPresence)<i class="tk-pres tk-pres-{{ $tkPresence }}"></i>@endif</span>
@endif
