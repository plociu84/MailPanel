<!-- MODAL: Notatka -->
<div class="modal fade" id="modalNote" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Notatka dla IP: <span id="noteIp" class="font-monospace text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea id="noteText" class="form-control" rows="5" maxlength="2000" placeholder="Wpisz notatkę..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-primary" onclick="saveNote()">Zapisz</button>
            </div>
        </div>
    </div>
</div>
