<?php
/** @var object $user */
?>
<section class="page-head">
    <div>
        <h1>Check-in QR</h1>
        <p class="muted">Apunta la càmera al QR del corredor.</p>
    </div>
</section>

<div class="scanner-wrap">
    <div id="reader"></div>
    <div id="reader-status" class="scanner-status">Iniciant càmera…</div>

    <div class="scanner-actions" style="text-align:center;margin:1rem 0;display:flex;flex-direction:column;gap:.6rem;align-items:center;">
        <select id="cam-select" style="display:none;max-width:320px;"></select>
        <select id="res-select" style="display:none;max-width:320px;">
            <option value="auto">Resolució: automàtica</option>
            <option value="720">Resolució: 1280×720</option>
            <option value="1080" selected>Resolució: 1920×1080</option>
            <option value="1440">Resolució: 2560×1440</option>
            <option value="2160">Resolució: 4K (3840×2160)</option>
        </select>
        <div id="zoom-wrap" style="display:none;width:100%;max-width:320px;">
            <label for="zoom-input" style="font-size:.85rem;">🔍 Zoom</label>
            <input type="range" id="zoom-input" style="width:100%;">
        </div>
        <button id="btn-stop" class="btn btn-secondary" style="display:none;">⏸ Pausar</button>
    </div>

    <div class="scanner-help">
        <p class="muted small" style="margin:.4rem 0 1rem;">Si el QR es veu borrós, tria una altra càmera al desplegable (les lents gran angular no enfoquen de prop) o fes servir el zoom.</p>
        <h3>O bé entra el token manualment:</h3>
        <form method="get" action="" id="manualForm" class="scanner-manual">
            <input type="text" id="manualToken" placeholder="Token (32-64 chars hex)" pattern="[a-f0-9]{32,64}" maxlength="64">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<script src="<?= e(asset('js/html5-qrcode.min.js')) ?>"
        onerror="document.getElementById('reader-status').innerHTML='<b style=color:red>ERROR: no s\\'ha pogut carregar html5-qrcode.min.js</b>'"></script>
<script src="<?= e(asset('js/qr-scanner-ui.js')) ?>"></script>
<script>
(function () {
    var base = <?= json_encode(base_url('/admin/checkin/')) ?>;

    WerunQrScanner.init({
        extract: function (decoded) {
            var token = decoded.trim();
            var m = token.match(/checkin\/([a-f0-9]{32,64})/i);
            if (m) token = m[1];
            return /^[a-f0-9]{32,64}$/i.test(token) ? token : null;
        },
        onToken: function (token) {
            window.location.href = base + token;
        }
    });

    document.getElementById('manualForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var tok = document.getElementById('manualToken').value.trim();
        if (/^[a-f0-9]{32,64}$/i.test(tok)) window.location.href = base + tok;
    });
})();
</script>
