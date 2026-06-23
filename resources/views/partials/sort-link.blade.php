@php
    $active = request('sort') === $field;
    $nextDirection = $active && request('dir', 'asc') === 'asc' ? 'desc' : 'asc';
    $icon = $active
        ? (request('dir', 'asc') === 'asc' ? 'sortAsc' : 'sortDesc')
        : 'arrowDownUp';
@endphp

<a class="sort-link {{ $active ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDirection]) }}">
    <span>{{ $label }}</span>
    <span data-icon="{{ $icon }}" class="app-icon" aria-hidden="true"></span>
</a>
