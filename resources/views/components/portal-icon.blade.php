{{--
  One icon from the shared portal set (App\Support\PortalIcons), by name:
    <x-portal-icon name="dashboard" />
  Sized and coloured by whatever holds it, unless a size is given.
--}}
@props(['name', 'size' => null, 'stroke' => 2])
{!! App\Support\PortalIcons::svg($name, $size ? (int) $size : null, (float) $stroke) !!}
