<?php
/** @var object $user */
?>
<section class="page-head with-action hide-text-mobile">
    <div>
        <h1>Escanejar QR · Recollida</h1>
        <p class="muted">Apunta la càmera al QR del comprovant del corredor.</p>
    </div>
    <a class="btn btn-secondary" href="<?= e(base_url('/admin/recollida')) ?>">← Tornar al llistat</a>
</section>

<div class="scanner-wrap">
    <div id="reader"></div>
    <div id="reader-status" class="scanner-status">Iniciant càmera…</div>

    <div class="scanner-actions" style="text-align:center;margin:1rem 0;display:flex;flex-direction:column;gap:.6rem;align-items:center;">
        <select id="cam-select" style="display:none;max-width:320px;"></select>
        <div id="zoom-wrap" style="display:none;width:100%;max-width:320px;">
            <label for="zoom-input" style="font-size:.85rem;">🔍 Zoom</label>
            <input type="range" id="zoom-input" style="width:100%;">
        </div>
        <button id="btn-stop" class="btn btn-secondary" style="display:none;">⏸ Pausar</button>
    </div>

    <div class="scanner-help">
        <p class="muted small" style="margin:.4rem 0 1rem;">Si el QR es veu borrós, tria una altra càmera al desplegable (les lents gran angular no enfoquen de prop) o fes servir el zoom.</p>
        <h3>O bé entra el token manualment:</h3>
        <form method="get" action="<?= e(base_url('/admin/recollida')) ?>" id="manualForm" class="scanner-manual">
            <input type="text" id="manualToken" name="token" placeholder="Token (32-64 chars hex)" pattern="[a-f0-9]{32,64}" maxlength="64">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<script src="<?= e(asset('js/html5-qrcode.min.js')) ?>"
        onerror="document.getElementById('reader-status').innerHTML='<b style=color:red>ERROR: no s\\'ha pogut carregar html5-qrcode.min.js</b>'"></script>
<script src="<?= e(asset('js/qr-scanner-ui.js')) ?>"></script>
<script>
(function () {
    var dest = <?= json_encode(base_url('/admin/recollida')) ?>;

    WerunQrScanner.init({
        extract: function (decoded) {
            var token = decoded.trim();
            var m = token.match(/(?:checkin|recollida)\/([a-f0-9]{32,64})/i);
            if (m) token = m[1];
            var m2 = token.match(/[?&]token=([a-f0-9]{32,64})/i);
            if (m2) token = m2[1];
            return /^[a-f0-9]{32,64}$/i.test(token) ? token : null;
        },
        onToken: function (token) {
            window.location.href = dest + '?token=' + token;
        }
    });

    document.getElementById('manualForm').addEventListener('submit', function (e) {
        var tok = document.getElementById('manualToken').value.trim();
        if (!/^[a-f0-9]{32,64}$/i.test(tok)) { e.preventDefault(); }
    });
})();
</script>
