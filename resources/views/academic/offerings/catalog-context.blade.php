@foreach(request()->only(['view', 'availability', 'cycle_id', 'catalog_page']) as $filterName => $filterValue)
    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
@endforeach
