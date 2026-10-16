@props([
    'label',
    'icon' => null,
    'col' => 'col-md-3 col-6',
    'badge' => null,
    'badgeTitle' => null,
])

<div class="{{ $col }}">
    <div class="card">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">
                        {{ $label }}@if ($badge) <span class="badge bg-secondary ms-1" title="{{ $badgeTitle }}"> {{ $badge }} </span>@endif
                    </div>
                    <div class="fs-3 fw-bold text-primary">
                        {{ $slot }}
                    </div>
                </div>
                @if ($icon)
                    <i class="{{ $icon }} fs-4 icon-muted" style="opacity: 0.7;"></i>
                @endif
            </div>
        </div>
    </div>
</div>
