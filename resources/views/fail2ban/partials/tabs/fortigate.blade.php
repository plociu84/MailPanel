    <!-- ZAKŁADKA: FORTIGATE BLOCKS (zablokowani na FortiGate) -->
    <div class="tab-pane fade {{ $activeTab === 'fortigate' ? 'show active' : '' }}" id="tab-fortigate" role="tabpanel">
        <div class="card">
            <div class="card-body p-2">
                <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                    <table class="table table-sm table-hover table-condensed align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>IP</th>
                                <th>Jail(e)</th>
                                <th class="text-center">Razy banowany</th>
                                <th>Data blokady na Forti</th>
                                <th>Notatka</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($fortigateBlocked as $i => $stat)
                                <tr>
                                    <td class="text-muted">{{ $fortigateBlocked->firstItem() + $i }}</td>
                                    <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                    <td>
                                        @foreach($stat->jails ?? [] as $j)
                                            <span class="badge bg-secondary">{{ $j }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">{{ $stat->total_bans }}×</span>
                                    </td>
                                    <td class="small text-muted">{{ $stat->fortigate_banned_at?->format('Y-m-d H:i') }}</td>
                                    <td class="small text-muted" style="max-width: 200px;">
                                        @if($stat->notes)
                                            <span title="{{ $stat->notes }}">{{ Str::limit($stat->notes, 40) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showIpHistory('{{ $stat->ip }}')" title="Historia">
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0" onclick="showNoteModal('{{ $stat->ip }}', @js($stat->notes))" title="Notatka">
                                                <i class="fa-solid fa-note-sticky"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success py-1 px-2 border-0"
                                                    onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                    title="Odblokuj na FortiGate">
                                                <i class="fa-solid fa-unlock"></i> Odblokuj
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        @if($search !== '')
                                            Brak zablokowanych na FortiGate pasujących do „{{ $search }}”.
                                        @else
                                            Brak zablokowanych na FortiGate.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $fortigateBlocked->links() }}
                </div>
            </div>
        </div>
    </div>
