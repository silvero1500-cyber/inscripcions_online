<?php
/** @var object $user */
?>
<section class="page-head">
    <div>
        <h1>Test càmera (diagnòstic)</h1>
        <p class="muted">Mostra què suporta realment la càmera del teu mòbil (enfocament, zoom, resolució).</p>
    </div>
</section>

<div style="max-width:640px;margin:0 auto;">
    <div id="cam-buttons" style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;">
        <button id="btn-auto" class="btn btn-primary btn-large" style="width:100%;">📷 Activar càmera (darrere)</button>
    </div>

    <video id="vid" autoplay playsinline muted
           style="width:100%;background:#000;border-radius:12px;display:none;"></video>

    <div id="controls" style="display:none;margin-top:1rem;">
        <label style="display:block;font-size:.85rem;margin-bottom:.3rem;">🔍 Zoom: <span id="zoom-val">—</span></label>
        <input type="range" id="zoom" style="width:100%;" disabled>
        <button id="btn-focus" class="btn btn-secondary" style="width:100%;margin-top:.6rem;">🎯 Forçar enfocament (toca aquí o la imatge)</button>
    </div>

    <pre id="log" style="background:#111;color:#0f0;padding:1rem;border-radius:8px;font-size:.8rem;white-space:pre-wrap;word-break:break-all;min-height:200px;margin-top:1rem;"></pre>

    <p style="margin-top:1rem;"><a href="<?= e(base_url('/admin/checkin')) ?>">← Tornar al scanner</a></p>
</div>

<script>
var log = document.getElementById('log');
function out(msg) { log.textContent += msg + '\n'; }
var currentStream = null;

out('Protocol: ' + location.protocol + ' · Host: ' + location.host);
out('UA: ' + navigator.userAgent.substring(0, 90));
out('getUserMedia: ' + (navigator.mediaDevices && navigator.mediaDevices.getUserMedia ? 'OK' : 'NO'));
out('---');

function stopCurrent() {
    if (currentStream) {
        currentStream.getTracks().forEach(function (t) { t.stop(); });
        currentStream = null;
    }
}

function inspectTrack(track) {
    out('=== Càmera activa: ' + track.label + ' ===');
    var settings = track.getSettings ? track.getSettings() : {};
    out('Resolució real: ' + (settings.width || '?') + '×' + (settings.height || '?'));
    out('facingMode: ' + (settings.facingMode || '?'));

    var caps = null;
    try { caps = track.getCapabilities ? track.getCapabilities() : null; } catch (e) { out('getCapabilities error: ' + e); }

    if (!caps) {
        out('⚠️ getCapabilities NO disponible en aquest navegador.');
        out('   → El navegador no deixa controlar enfocament ni zoom.');
        return;
    }

    // Enfocament
    if (caps.focusMode) {
        out('✅ focusMode suportat: [' + caps.focusMode.join(', ') + ']');
    } else {
        out('❌ focusMode NO exposat (el navegador no controla l\'enfocament)');
    }
    if (caps.focusDistance) {
        out('   focusDistance: ' + caps.focusDistance.min + ' a ' + caps.focusDistance.max);
    }

    // Zoom
    var zoom = document.getElementById('zoom');
    if (caps.zoom && caps.zoom.max > caps.zoom.min) {
        out('✅ zoom suportat: ' + caps.zoom.min + ' a ' + caps.zoom.max + ' (step ' + (caps.zoom.step || '?') + ')');
        zoom.min = caps.zoom.min; zoom.max = caps.zoom.max;
        zoom.step = caps.zoom.step || 0.1; zoom.value = caps.zoom.min;
        zoom.disabled = false;
        document.getElementById('zoom-val').textContent = caps.zoom.min;
        zoom.oninput = function () {
            document.getElementById('zoom-val').textContent = zoom.value;
            track.applyConstraints({ advanced: [{ zoom: parseFloat(zoom.value) }] })
                .then(function () { out('zoom → ' + zoom.value); })
                .catch(function (e) { out('zoom error: ' + e); });
        };
    } else {
        out('❌ zoom NO suportat');
        zoom.disabled = true;
    }

    // Botó forçar enfocament
    document.getElementById('btn-focus').onclick = function () { forceFocus(track, caps); };
    document.getElementById('vid').onclick = function () { forceFocus(track, caps); };

    // Intentar enfocament continu d'entrada
    if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) {
        track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] })
            .then(function () { out('→ Aplicat focusMode: continuous'); })
            .catch(function (e) { out('No s\'ha pogut aplicar continuous: ' + e); });
    }
}

function forceFocus(track, caps) {
    if (!caps || !caps.focusMode) { out('No es pot forçar: focusMode no suportat'); return; }
    var mode = caps.focusMode.indexOf('single-shot') !== -1 ? 'single-shot'
             : (caps.focusMode.indexOf('manual') !== -1 ? 'manual' : caps.focusMode[0]);
    track.applyConstraints({ advanced: [{ focusMode: mode }] })
        .then(function () {
            out('🎯 Enfocament forçat (' + mode + ')');
            if (caps.focusMode.indexOf('continuous') !== -1) {
                setTimeout(function () {
                    track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
                }, 1200);
            }
        })
        .catch(function (e) { out('Error forçant enfocament: ' + e); });
}

function activar(constraints) {
    out('---');
    out('Sol·licitant càmera...');
    stopCurrent();
    navigator.mediaDevices.getUserMedia({ video: constraints, audio: false })
        .then(function (stream) {
            currentStream = stream;
            var vid = document.getElementById('vid');
            vid.srcObject = stream;
            vid.style.display = 'block';
            document.getElementById('controls').style.display = 'block';
            inspectTrack(stream.getVideoTracks()[0]);
        })
        .catch(function (err) {
            out('❌ ERROR: ' + err.name + ' - ' + err.message);
        });
}

document.getElementById('btn-auto').addEventListener('click', function () {
    activar({ facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } });
});

// Un cop hi ha permís, llistar càmeres amb botó per provar cadascuna
function listCameras() {
    navigator.mediaDevices.enumerateDevices().then(function (devs) {
        var cams = devs.filter(function (d) { return d.kind === 'videoinput'; });
        out('---');
        out('Càmeres detectades: ' + cams.length);
        var box = document.getElementById('cam-buttons');
        cams.forEach(function (c, i) {
            out(' #' + i + ' "' + (c.label || '(sense label)') + '"');
            if (c.label) {
                var b = document.createElement('button');
                b.className = 'btn btn-secondary';
                b.style.width = '100%';
                b.textContent = '📷 ' + c.label;
                b.onclick = function () {
                    activar({ deviceId: { exact: c.deviceId }, width: { ideal: 1920 }, height: { ideal: 1080 } });
                };
                box.appendChild(b);
            }
        });
        if (cams.length && !cams[0].label) out('(Activa la càmera un cop per veure els noms i tenir un botó per cada lent)');
    });
}
if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) listCameras();
</script>
