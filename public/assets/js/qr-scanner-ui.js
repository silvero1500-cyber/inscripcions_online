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
    var RES_KEY     = 'werun_scanner_res';

    // Presets de resolució seleccionables (més resolució = QR petits més nítids,
    // però algunes càmeres/mòbils van més lents o no ho suporten)
    var RES_PRESETS = {
        'auto': null,
        '720':  { w: 1280, h: 720 },
        '1080': { w: 1920, h: 1080 },
        '1440': { w: 2560, h: 1440 },
        '2160': { w: 3840, h: 2160 }
    };

    function init(opts) {
        var statusEl  = document.getElementById('reader-status');
        var btnStop   = document.getElementById('btn-stop');
        var camSelect = document.getElementById('cam-select');
        var resSelect = document.getElementById('res-select');
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
        function currentRes() {
            var k = (resSelect && resSelect.value) || localStorage.getItem(RES_KEY) || '1080';
            return RES_PRESETS.hasOwnProperty(k) ? RES_PRESETS[k] : RES_PRESETS['1080'];
        }

        function buildConstraints(source, exact) {
            var c = {};
            var r = currentRes();
            if (r) {
                c.width  = exact ? { exact: r.w } : { ideal: r.w };
                c.height = exact ? { exact: r.h } : { ideal: r.h };
            }
            if (typeof source === 'string') c.deviceId = { exact: source };
            else c.facingMode = { ideal: 'environment' };
            return c;
        }

        function startWith(source) {
            var camArg = (typeof source === 'string') ? source : { facingMode: 'environment' };
            // Primer amb resolució EXACTA (si el navegador la ignorés amb "ideal",
            // el vídeo quedaria en baixa qualitat); si la càmera no la suporta, "ideal".
            var cfgExact = Object.assign({}, config, { videoConstraints: buildConstraints(source, true) });
            var cfgIdeal = Object.assign({}, config, { videoConstraints: buildConstraints(source, false) });
            if (!currentRes()) return reader.start(camArg, config, onSuccess, onError);
            return reader.start(camArg, cfgExact, onSuccess, onError)
                .catch(function () { return reader.start(camArg, cfgIdeal, onSuccess, onError); });
        }

        // Resolució REAL que ha donat la càmera (per mostrar-la a l'estat)
        function actualRes() {
            try {
                var v = document.querySelector('#reader video');
                if (v && v.videoWidth) return v.videoWidth + '×' + v.videoHeight;
            } catch (e) {}
            return null;
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
            btnStop.style.display = 'inline-block';
            if (resSelect) resSelect.style.display = '';
            tuneRunningTrack();
            populateSelector();
            // La resolució real triga uns ms a estar disponible al <video>
            setTimeout(function () {
                var res = actualRes();
                var txt = (label ? 'Càmera: ' + label : 'Apunta al QR') + (res ? ' · ' + res : '');
                setStatus(txt, 'ok');
            }, 600);
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

        function restartWithCurrent() {
            var id = camSelect.style.display !== 'none' && camSelect.value
                ? camSelect.value
                : (localStorage.getItem(STORAGE_KEY) || null);
            var p = reader.isScanning ? reader.stop() : Promise.resolve();
            p.then(function () {
                zoomWrap.style.display = 'none';
                startWith(id).then(function () {
                    var cam = cameras.find(function (c) { return c.id === id; });
                    onStarted(cam ? cam.label : null);
                }).catch(function (err) {
                    setStatus('Error obrint la càmera: ' + (err && err.message ? err.message : err), 'err');
                });
            });
        }

        camSelect.addEventListener('change', function () {
            localStorage.setItem(STORAGE_KEY, camSelect.value);
            restartWithCurrent();
        });

        if (resSelect) {
            var savedRes = localStorage.getItem(RES_KEY);
            if (savedRes && RES_PRESETS.hasOwnProperty(savedRes)) resSelect.value = savedRes;
            resSelect.addEventListener('change', function () {
                localStorage.setItem(RES_KEY, resSelect.value);
                restartWithCurrent();
            });
        }

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
