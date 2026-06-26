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

    <div class="scanner-actions" style="text-align:center;margin:1rem 0;">
        <button id="btn-switch" class="btn btn-secondary" style="display:none;">↻ Canviar càmera</button>
        <button id="btn-stop" class="btn btn-secondary" style="display:none;">⏸ Pausar</button>
    </div>

    <div class="scanner-help">
        <h3>O bé entra el token manualment:</h3>
        <form method="get" action="<?= e(base_url('/admin/recollida')) ?>" id="manualForm" class="scanner-manual">
            <input type="text" id="manualToken" name="token" placeholder="Token (32-64 chars hex)" pattern="[a-f0-9]{32,64}" maxlength="64">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<script src="<?= e(asset('js/html5-qrcode.min.js')) ?>"
        onerror="document.getElementById('reader-status').innerHTML='<b style=color:red>ERROR: no s\\'ha pogut carregar html5-qrcode.min.js</b>'"></script>
<script>
(function () {
    var statusEl = document.getElementById('reader-status');
    var btnSwitch = document.getElementById('btn-switch');
    var btnStop = document.getElementById('btn-stop');
    function setStatus(msg, cls) {
        statusEl.textContent = msg;
        statusEl.className = 'scanner-status' + (cls ? ' ' + cls : '');
    }

    if (typeof Html5Qrcode === 'undefined') {
        setStatus('Llibreria no carregada.', 'err');
        return;
    }

    // El QR del corredor apunta a /admin/checkin/{token}; aquí reaprofitem el
    // mateix token però redirigim a la pàgina de recollida.
    var dest = <?= json_encode(base_url('/admin/recollida')) ?>;
    var reader = new Html5Qrcode("reader");
    var stopped = false;
    var cameras = [];
    var currentIdx = 0;

    function extractToken(decoded) {
        var token = decoded.trim();
        var m = token.match(/(?:checkin|recollida)\/([a-f0-9]{32,64})/i);
        if (m) token = m[1];
        var m2 = token.match(/[?&]token=([a-f0-9]{32,64})/i);
        if (m2) token = m2[1];
        return /^[a-f0-9]{32,64}$/i.test(token) ? token : null;
    }

    function goToToken(token) {
        window.location.href = dest + '?token=' + token;
    }

    function onSuccess(decodedText) {
        if (stopped) return;
        var token = extractToken(decodedText);
        if (!token) {
            setStatus('QR no vàlid: ' + decodedText.substring(0, 40), 'err');
            return;
        }
        stopped = true;
        setStatus('QR llegit, redirigint…', 'ok');
        reader.stop().then(function () { goToToken(token); });
    }

    function onError(_err) { /* ignorar frames sense QR */ }

    var config = {
        fps: 10,
        qrbox: function (w, h) {
            var min = Math.min(w, h);
            var size = Math.floor(min * 0.7);
            return { width: size, height: size };
        },
        // Sense forçar resolució: el navegador obre la càmera més de pressa amb la
        // resolució nativa que negociant 1280×720 (sobretot al mòbil).
        aspectRatio: 1.7777778
    };

    function startWithCamera(cameraId) {
        return reader.start(cameraId, config, onSuccess, onError);
    }

    // Llista de càmeres en segon pla (NOMÉS per al botó "Canviar càmera").
    // No bloqueja l'arrencada: enumerar abans obre i tanca una càmera de més.
    function loadCamerasLazily() {
        Html5Qrcode.getCameras().then(function (cams) {
            cameras = cams || [];
            if (cameras.length > 1 && !stopped) btnSwitch.style.display = 'inline-block';
        }).catch(function () { /* sense permís encara, ho ignorem */ });
    }

    function startBackPreferred() {
        setStatus('Obrint càmera…', '');
        // Arrenca DIRECTE amb la càmera del darrere (facingMode), sense enumerar
        // primer: és bastant més ràpid en obrir-se al mòbil.
        return reader.start({ facingMode: { exact: "environment" } }, config, onSuccess, onError)
            .then(onStarted)
            .catch(function () {
                // Alguns mòbils no accepten {exact}; provem {ideal}
                return reader.start({ facingMode: { ideal: "environment" } }, config, onSuccess, onError).then(onStarted);
            })
            .catch(function (err) {
                setStatus('Error iniciant càmera: ' + (err && err.name ? err.name + ' - ' + err.message : err), 'err');
            });
    }

    function onStarted() {
        setStatus('Apunta al QR del corredor', 'ok');
        btnStop.style.display = 'inline-block';
        loadCamerasLazily();
    }

    btnSwitch.addEventListener('click', function () {
        if (cameras.length < 2) return;
        reader.stop().then(function () {
            currentIdx = (currentIdx + 1) % cameras.length;
            startWithCamera(cameras[currentIdx].id).then(function () {
                setStatus('Càmera: ' + (cameras[currentIdx].label || 'desconeguda'), 'ok');
                btnStop.style.display = 'inline-block';
            });
        });
    });

    btnStop.addEventListener('click', function () {
        if (reader.isScanning) {
            reader.stop().then(function () {
                setStatus('Pausat. Recarrega per tornar a escanejar.', '');
                btnStop.style.display = 'none';
                btnSwitch.style.display = 'none';
            });
        }
    });

    document.getElementById('manualForm').addEventListener('submit', function (e) {
        var tok = document.getElementById('manualToken').value.trim();
        if (!/^[a-f0-9]{32,64}$/i.test(tok)) { e.preventDefault(); }
    });

    startBackPreferred();
})();
</script>
