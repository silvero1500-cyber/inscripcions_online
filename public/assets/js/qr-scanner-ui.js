/**
 * Escàner QR compartit (recollida + check-in) basat en html5-qrcode.
 *
 * Millores respecte a l'arrencada bàsica:
 *  - Selector de càmera amb noms (els mòbils amb més d'una lent solen obrir la
 *    gran angular SENSE autofocus i el QR es veu borrós).
 *  - Recorda la càmera triada (localStorage) per a les properes vegades.
 *  - Demana resolució alta i enfocament continu quan el dispositiu ho suporta.
 *  - Control de zoom (si la càmera l'exposa) per enfocar QR petits.
 *
 * Ús: WerunQrScanner.init({ onToken: fn, extract: fn })
 */
window.WerunQrScanner = (function () {
    var STORAGE_KEY = 'werun_scanner_cam';

    function init(opts) {
        var statusEl  = document.getElementById('reader-status');
        var btnStop   = document.getElementById('btn-stop');
        var camSelect = document.getElementById('cam-select');
        var zoomWrap  = document.getElementById('zoom-wrap');
        var zoomInput = document.getElementById('zoom-input');

        function setStatus(msg, cls) {
            statusEl.textContent = msg;
            statusEl.className = 'scanner-status' + (cls ? ' ' + cls : '');
        }

        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Llibreria no carregada.', 'err');
            return;
        }

        var reader = new Html5Qrcode('reader');
        var stopped = false;
        var cameras = [];

        var config = {
            fps: 10,
            qrbox: function (w, h) {
                var min = Math.min(w, h);
                var size = Math.floor(min * 0.7);
                return { width: size, height: size };
            },
            aspectRatio: 1.7777778
        };

        function onSuccess(decodedText) {
            if (stopped) return;
            var token = opts.extract(decodedText);
            if (!token) {
                setStatus('QR no vàlid: ' + decodedText.substring(0, 40), 'err');
                return;
            }
            stopped = true;
            setStatus('QR llegit, redirigint…', 'ok');
            reader.stop().then(function () { opts.onToken(token); });
        }

        function onError(_e) { /* frames sense QR */ }

        // Constraints d'arrencada: resolució alta ajuda a llegir QR petits/borrosos
        function buildConstraints(source) {
            var c = { width: { ideal: 1920 }, height: { ideal: 1080 } };
            if (typeof source === 'string') c.deviceId = { exact: source };
            else c.facingMode = { ideal: 'environment' };
            return c;
        }

        function startWith(source) {
            var cfg = Object.assign({}, config, { videoConstraints: buildConstraints(source) });
            var camArg = (typeof source === 'string') ? source : { facingMode: 'environment' };
            return reader.start(camArg, cfg, onSuccess, onError);
        }

        // Últim recurs: primera càmera que hi hagi, sense constraints extra
        function startAny() {
            return Html5Qrcode.getCameras().then(function (cams) {
                if (!cams || cams.length === 0) throw new Error('Cap càmera disponible');
                return reader.start(cams[0].id, config, onSuccess, onError);
            });
        }

        // Millores post-arrencada: enfocament continu + slider de zoom si existeix
        function tuneRunningTrack() {
            try {
                reader.applyVideoConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(function () {});
            } catch (e) { /* no suportat */ }

            try {
                var caps = reader.getRunningTrackCapabilities();
                if (caps && caps.zoom && caps.zoom.max > caps.zoom.min) {
                    zoomInput.min = caps.zoom.min;
                    zoomInput.max = Math.min(caps.zoom.max, 8);
                    zoomInput.step = caps.zoom.step || 0.1;
                    zoomInput.value = caps.zoom.min;
                    zoomWrap.style.display = '';
                    zoomInput.oninput = function () {
                        try {
                            reader.applyVideoConstraints({ advanced: [{ zoom: parseFloat(zoomInput.value) }] }).catch(function () {});
                        } catch (e) { /* ignorar */ }
                    };
                } else {
                    zoomWrap.style.display = 'none';
                }
            } catch (e) { zoomWrap.style.display = 'none'; }
        }

        function onStarted(label) {
            setStatus(label ? 'Càmera: ' + label : 'Apunta al QR', 'ok');
            btnStop.style.display = 'inline-block';
            tuneRunningTrack();
            populateSelector();
        }

        // Selector de càmeres pel nom (un cop hi ha permís, els labels són visibles)
        function populateSelector() {
            Html5Qrcode.getCameras().then(function (cams) {
                cameras = cams || [];
                if (cameras.length < 2) return;
                camSelect.innerHTML = '';
                cameras.forEach(function (c, i) {
                    var o = document.createElement('option');
                    o.value = c.id;
                    o.textContent = c.label || ('Càmera ' + (i + 1));
                    camSelect.appendChild(o);
                });
                var saved = localStorage.getItem(STORAGE_KEY);
                if (saved && cameras.some(function (c) { return c.id === saved; })) camSelect.value = saved;
                camSelect.style.display = '';
            }).catch(function () {});
        }

        camSelect.addEventListener('change', function () {
            var id = camSelect.value;
            localStorage.setItem(STORAGE_KEY, id);
            var p = reader.isScanning ? reader.stop() : Promise.resolve();
            p.then(function () {
                zoomWrap.style.display = 'none';
                startWith(id).then(function () {
                    var cam = cameras.find(function (c) { return c.id === id; });
                    onStarted(cam ? cam.label : null);
                }).catch(function (err) {
                    setStatus('Error obrint la càmera triada: ' + (err && err.message ? err.message : err), 'err');
                });
            });
        });

        btnStop.addEventListener('click', function () {
            if (reader.isScanning) {
                reader.stop().then(function () {
                    setStatus('Pausat. Recarrega per tornar a escanejar.', '');
                    btnStop.style.display = 'none';
                });
            }
        });

        // ── Arrencada ────────────────────────────────────────────
        // 1) càmera recordada; 2) darrere (environment); 3) el que hi hagi.
        setStatus('Obrint càmera…', '');
        var saved = localStorage.getItem(STORAGE_KEY);
        var boot = saved
            ? startWith(saved).then(function () { onStarted(null); })
                .catch(function () { localStorage.removeItem(STORAGE_KEY); return startDefault(); })
            : startDefault();

        function startDefault() {
            return startWith(null).then(function () { onStarted(null); })
                .catch(function () {
                    return startAny().then(function () { onStarted(null); });
                })
                .catch(function (err) {
                    setStatus('Error iniciant càmera: ' + (err && err.name ? err.name + ' - ' + err.message : err), 'err');
                });
        }

        return boot;
    }

    return { init: init };
})();
