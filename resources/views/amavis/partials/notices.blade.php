<!-- Informacja o przechowywaniu -->
<div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
    <i class="fa-solid fa-info-circle me-2"></i>
    Wiadomości w kwarantannie są przechowywane przez <strong>30 dni</strong>, po czym są automatycznie usuwane.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

@if(count($spammyMessages ?? []) > 0)
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        Znaleziono {{ count($spammyMessages) }} wiadomości oznaczonych jako SPAMMY w logach!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
