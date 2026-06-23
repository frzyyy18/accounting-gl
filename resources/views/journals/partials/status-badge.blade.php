@php
    $statusClass = [
        'draft' => 'text-bg-secondary',
        'submitted' => 'text-bg-warning text-dark',
        'approved' => 'text-bg-primary',
        'rejected' => 'text-bg-danger',
        'posted' => 'text-bg-success',
        'cancelled' => 'text-bg-dark',
    ][$status] ?? 'text-bg-light border';
@endphp

<span class="badge journal-status-badge {{ $statusClass }}">{{ strtoupper($status) }}</span>
