    <!-- ZAKŁADKA: RECYDYWIŚCI (niezablokowani na FortiGate) -->
    <div class="tab-pane fade {{ $activeTab === 'repeat' ? 'show active' : '' }}" id="tab-repeat" role="tabpanel">
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
                                <th>Pierwszy ban</th>
                                <th>Ostatni ban</th>
                                <th>Status</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($repeatOffenders as $i => $stat)
                                <tr>
                                    <td class="text-muted">{{ $repeatOffenders->firstItem() + $i }}</td>
                                    <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                    <td>
                                        @foreach($stat->jails ?? [] as $j)
                                            <span class="badge bg-secondary">{{ $j }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark fs-6">{{ $stat->total_bans }}×</span>
                                    </td>
                                    <td class="small text-muted">{{ $stat->first_banned_at?->format('Y-m-d H:i') }}</td>
                                    <td class="small text-muted">{{ $stat->last_banned_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($stat->currently_banned)
                                            <span class="badge bg-danger">Zbanowany</span>
                                        @else
                                            <span class="badge bg-success">Odbanowany</span>
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
                                            <button class="btn btn-sm btn-outline-dark py-1 px-2 border-0"
                                                    onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                    title="Zablokuj na FortiGate">
                                                <i class="fa-solid fa-shield"></i> Zablokuj
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        @if($search !== '')
                                            Brak recydywistów pasujących do „{{ $search }}”.
                                        @else
                                            Brak recydywistów (wszyscy zablokowani na FortiGate).
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $repeatOffenders->links() }}
                </div>
            </div>
        </div>
    </div>
