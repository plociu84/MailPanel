    <!-- ZAKŁADKA: LOGI -->
    <div class="tab-pane fade {{ $activeTab === 'logs' ? 'show active' : '' }}" id="tab-logs" role="tabpanel">
        <div class="card">
            <div class="card-body p-2">
                <div style="background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, monospace; font-size: 0.82rem; padding: 12px; border-radius: 6px;">
                    @forelse($recentEvents as $ev)
                        <div>
                            <span class="text-secondary">{{ $ev->banned_at->format('Y-m-d H:i:s') }}</span>
                            <span class="text-warning">[{{ $ev->jail }}]</span>
                            <span class="text-danger">Ban</span>
                            <span class="text-info fw-bold">{{ $ev->ip }}</span>
                            @if($ev->unbanned_at)
                                <span class="text-secondary">→ Unban {{ $ev->unbanned_at->format('Y-m-d H:i:s') }}
                                    ({{ $ev->ban_duration ? gmdate('H:i:s', $ev->ban_duration) : '?' }})
                                </span>
                            @else
                                <span class="text-success">→ aktywny</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">Brak zdarzeń w bazie.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
