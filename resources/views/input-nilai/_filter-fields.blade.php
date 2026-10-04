@foreach ($filter as $key => $value)
    <input type="hidden" name="filter[{{ $key }}]" value="{{ $value }}">
@endforeach
