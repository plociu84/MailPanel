<!-- MODAL: Historia IP -->
<div class="modal fade" id="modalIpHistory" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historia banów: <span id="histIp" class="font-monospace text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="histSummary" class="mb-3"></div>
                <div class="small text-muted mb-2">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Lista obejmuje bany z okna retencji ({{ $retentionDays }} dni) — starsze wpisy usuwa
                    <code>fail2ban:prune</code>. Notatka i flaga FortiGate nie podlegają czyszczeniu.
                </div>
                <div id="histTableWrapper">
                    <table class="table table-sm table-condensed table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Jail</th>
                                <th>Ban od</th>
                                <th>Unban</th>
                                <th>Czas trwania</th>
                                <th>Powód</th>
                            </tr>
                        </thead>
                        <tbody id="histTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
