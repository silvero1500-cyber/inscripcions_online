<?php
/** @var object $user */
?>
<section class="page-head">
    <div>
        <h1>Test càmera (diagnòstic)</h1>
        <p class="muted">Prova mínima de getUserMedia. Si això no funciona, html5-qrcode tampoc.</p>
    </div>
</section>

<div style="max-width:600px;margin:0 auto;">
    <button id="btn" class="btn btn-primary btn-large" style="width:100%;margin-bottom:1rem;">
        📷 Activar càmera
    </button>

    <video id="vid" autoplay playsinline muted
           style="width:100%;background:#000;border-radius:12px;display:none;"></video>

    <pre id="log" style="background:#111;color:#0f0;padding:1rem;border-radius:8px;font-size:.85rem;white-space:pre-wrap;word-break:break-all;min-height:200px;margin-top:1rem;"></pre>

    <p style="margin-top:1rem;"><a href="<?= e(base_url('/admin/checkin')) ?>">← Tornar al scanner</a></p>
</div>

<script>
var log = document.getElementById('log');
function out(msg) { log.textContent += msg + '\n'; }

out('Protocol: ' + location.protocol);
out('Host: ' + location.host);
out('User-Agent: ' + navigator.userAgent.substring(0, 100));
out('mediaDevices: ' + (navigator.mediaDevices ? 'OK' : 'NO'));
out('getUserMedia: ' + (navigator.mediaDevices && navigator.mediaDevices.getUserMedia ? 'OK' : 'NO'));

document.getElementById('btn').addEventListener('click', function () {
    out('---');
    out('Click rebut. Sol·licitant càmera...');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        out('FAIL: getUserMedia no disponible');
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
    }).then(function (stream) {
        out('OK: stream rebut. Tracks: ' + stream.getVideoTracks().length);
        stream.getVideoTracks().forEach(function (t) {
            out(' track: ' + t.label + ' (kind=' + t.kind + ', enabled=' + t.enabled + ')');
        });
        var vid = document.getElementById('vid');
        vid.srcObject = stream;
        vid.style.display = 'block';
        out('Vídeo connectat.');
    }).catch(function (err) {
        out('ERROR: ' + err.name + ' - ' + err.message);
        out('Constraint: ' + (err.constraint || 'n/a'));
    });
});

// Llista de cameres (no requereix permis previ, pero sense permis no dóna labels)
if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
    navigator.mediaDevices.enumerateDevices().then(function (devs) {
        var cams = devs.filter(function (d) { return d.kind === 'videoinput'; });
        out('Càmeres detectades: ' + cams.length);
        cams.forEach(function (c, i) {
            out(' #' + i + ' label="' + (c.label || '(sense label, no hi ha permis)') + '" id=' + c.deviceId.substring(0, 12));
        });
    });
}
</script>
