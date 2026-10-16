<!-- MODAL: Podgląd feedu FortiGate -->
<div class="modal fade" id="modalFeedPreview" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-shield me-1"></i>
                    Podgląd feedu FortiGate (<span id="feedCountLabel">0</span>)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">
                    Plik: <code id="feedPathLabel">-</code>
                </div>
                <pre id="feedPre" class="border rounded p-3 mb-0"
                     style="background:#f8f9fa; font-size:0.85rem; max-height:500px; overflow:auto; white-space:pre;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                <button type="button" class="btn btn-primary" onclick="copyFeed()">
                    <i class="fa-solid fa-copy me-1"></i> Kopiuj
                </button>
            </div>
        </div>
    </div>
</div>
