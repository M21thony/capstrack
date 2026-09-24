{{-- A person's profile photo over their initials. Google photos refuse a request that carries a referrer, and a photo that fails removes itself so the initials underneath show. --}}
@if (filled($url))
<img src="{{ $url }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">
@endif
