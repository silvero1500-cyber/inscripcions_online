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

    <div class="scanner-actions" style="text-align:center;margin:1rem 0;">
        <button id="btn-switch" class="btn btn-secondary" style="display:none;">↻ Canviar càmera</button>
        <button id="btn-stop" class="btn btn-secondary" style="display:none;">⏸ Pausar</button>
    </div>

    <div class="scanner-help">
        <h3>O bé entra el token manualment:</h3>
        <form method="get" action="" id="manualForm" class="scanner-manual">
            <input type="text" id="manualToken" placeholder="Token (32-64 chars hex)" pattern="[a-f0-9]{32,64}" maxlength="64">
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

    var base = <?= json_encode(base_url('/admin/checkin/')) ?>;
    var reader = new Html5Qrcode("reader");
    var stopped = false;
    var cameras = [];
    var currentIdx = 0;

    function extractToken(decoded) {
        var token = decoded.trim();
        var m = token.match(/checkin\/([a-f0-9]{32,64})/i);
        if (m) token = m[1];
        return /^[a-f0-9]{32,64}$/i.test(token) ? token : null;
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
        reader.stop().then(function () { window.location.href = base + token; });
    }

    function onError(_err) { /* ignorar frames sense QR */ }

    var config = {
        fps: 10,
        qrbox: function (w, h) {
            var min = Math.min(w, h);
            var size = Math.floor(min * 0.7);
            return { width: size, height: size };
        },
        aspectRatio: 1.7777778, // 16:9 = millor enfoc en mòbils
        videoConstraints: {
            facingMode: { ideal: "environment" },
            width:  { ideal: 1280 },
            height: { ideal: 720 }
        }
    };

    function startWithCamera(cameraId) {
        return reader.start(cameraId, config, onSuccess, onError);
    }

    function startBackPreferred() {
        return Html5Qrcode.getCameras().then(function (cams) {
            cameras = cams || [];
            if (cameras.length === 0) {
                setStatus('No hi ha cap càmera disponible.', 'err');
                return;
            }
            // Buscar back/rear/environment
            var back = cameras.find(function (c) { return /back|rear|environment|trasera|posterior/i.test(c.label); });
            var camera = back || cameras[cameras.length - 1];
            currentIdx = cameras.indexOf(camera);

            return startWithCamera(camera.id).then(function () {
                setStatus('Apunta al QR del corredor', 'ok');
                btnStop.style.display = 'inline-block';
                if (cameras.length > 1) btnSwitch.style.display = 'inline-block';
            }).catch(function (err) {
                // Si falla amb deviceId, provar amb facingMode directament
                return reader.start({ facingMode: { ideal: "environment" } }, config, onSuccess, onError)
                    .then(function () {
                        setStatus('Apunta al QR del corredor', 'ok');
                        btnStop.style.display = 'inline-block';
                    });
            });
        }).catch(function (err) {
            setStatus('Error iniciant càmera: ' + err.name + ' - ' + err.message, 'err');
        });
    }

    btnSwitch.addEventListener('click', function () {
        if (cameras.length < 2) return;
        reader.stop().then(function () {
            currentIdx = (currentIdx + 1) % cameras.length;
            startWithCamera(cameras[currentIdx].id).then(function () {
                setStatus('Càmera: ' + (cameras[currentIdx].label || 'desconeguda'), 'ok');
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
        e.preventDefault();
        var tok = document.getElementById('manualToken').value.trim();
        if (/^[a-f0-9]{32,64}$/i.test(tok)) window.location.href = base + tok;
    });

    // Iniciar al carregar
    startBackPreferred();
})();
</script>
