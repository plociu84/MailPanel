    <!-- Prawa kolumna: Logi Amavis -->
    <div class="col-xl-5">
        <div class="card h-100" id="amavisLogsCard">
            <div class="card-header py-2">
                <h5 class="mb-0">
                    <i class="fa-solid fa-file-lines icon-muted me-2"></i> 
                    Ostatnie 200 wpisów (amavis.log)
                </h5>
            </div>
            <div class="card-body p-2 d-flex flex-column">
                <div class="flex-grow-1" id="amavisLogsContainer" 
                     style="height: 500px; overflow-y: auto; overflow-x: hidden; background-color: #1e1e2e; color: #cdd6f4; border: 1px solid #313244; font-family: Consolas, Monaco, 'Courier New', monospace; font-size: 0.82rem; padding: 12px; border-radius: 6px;">
                    {!! nl2br(e($logs)) !!}
                </div>
            </div>
        </div>
    </div>
