{{--
  The degree an expert holds, as it is written after a name: MIT, PhD.
  Choosing one also sets the level the Panel Chair rule reads (a master's or
  higher may chair). Pass: id, selected (a title), attrs (raw attributes).
--}}
<select id="{{ $id }}" {!! $attrs ?? '' !!}>
  <option value="">{{ $blank ?? 'None on record' }}</option>
  @foreach (\App\Models\CapstoneExpert::DEGREE_TITLES as $level => $titles)
    <optgroup label="{{ \App\Models\CapstoneExpert::DEGREES[$level] }}">
      @foreach ($titles as $title)
        <option value="{{ $title }}" @selected(($selected ?? null) === $title)>{{ $title }}</option>
      @endforeach
    </optgroup>
  @endforeach
</select>
