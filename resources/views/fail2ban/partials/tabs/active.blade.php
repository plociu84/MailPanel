    <!-- ZAKŁADKA: AKTYWNE -->
    <div class="tab-pane fade {{ $activeTab === 'active' ? 'show active' : '' }}" id="tab-active" role="tabpanel">
        <div class="card">
            <div class="card-body p-2">
                <div style="border: 1px solid var(--border-soft-2); border-radius: 8px; overflow: hidden;">
                    <table class="table table-sm table-hover table-condensed align-middle mb-0" id="tblActive">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Jail</th>
                                <th class="text-center">Razy <span class="text-muted fw-normal">({{ $retentionDays }} dni)</span></th>
                                <th>Ostatni ban</th>
                                <th>Notatka</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeBans as $stat)
                                <tr class="f2b-row">
                                    <td class="fw-bold font-monospace">{{ $stat->ip }}</td>
                                    <td>
                                        @foreach($stat->jails ?? [] as $j)
                                            <span class="badge bg-secondary">{{ $j }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        @if($stat->total_bans > 1)
                                            <span class="badge bg-warning text-dark">{{ $stat->total_bans }}×</span>
                                        @else
                                            <span class="text-muted">1</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">
                                        {{ $stat->last_banned_at?->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="small text-muted" style="max-width: 200px;">
                                        @if($stat->notes)
                                            <span title="{{ $stat->notes }}">{{ Str::limit($stat->notes, 40) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        @if($stat->fortigate_banned)
                                            <i class="fa-solid fa-shield icon-muted ms-1" title="W feedzie FortiGate od {{ $stat->fortigate_banned_at?->format('Y-m-d H:i') }}"></i>
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
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 border-0 {{ $stat->fortigate_banned ? 'active' : '' }}"
                                                    onclick="toggleFortigate('{{ $stat->ip }}', this)"
                                                    title="{{ $stat->fortigate_banned ? 'Usuń z feedu FortiGate' : 'Dodaj do feedu FortiGate' }}">
                                                <i class="fa-solid fa-shield"></i>
                                            </button>
                                            @if($stat->currently_banned && !empty($stat->jails))
                                                <form action="{{ route('fail2ban.unban') }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Odblokować IP {{ $stat->ip }} w jailu {{ $stat->jails[0] }}?');">
                                                    @csrf
                                                    <input type="hidden" name="ip" value="{{ $stat->ip }}">
                                                    <input type="hidden" name="jail" value="{{ $stat->jails[0] }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 border-0" title="Odblokuj">
                                                        <i class="fa-solid fa-unlock"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        @if($search !== '')
                                            Brak aktywnych banów pasujących do „{{ $search }}”.
                                        @else
                                            Brak aktywnych banów.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $activeBans->links() }}
                </div>
            </div>
        </div>
    </div>
