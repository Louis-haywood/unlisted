/**
 * LVScanner — unified barcode scanner
 * Uses native BarcodeDetector (Chrome/Android) with Quagga2 fallback (Safari/iOS)
 *
 * Usage:
 *   var s = new LVScanner('container-div-id', onDetected, onStatus);
 *   s.start();
 *   s.stop();
 */
function LVScanner(containerId, onDetected, onStatus) {
    var container = document.getElementById(containerId);
    var useNative = ('BarcodeDetector' in window);
    var active    = false;
    var stream    = null;

    function status(msg) { if (onStatus) onStatus(msg); }

    // ── Native BarcodeDetector (Chrome / Android) ────────────────────────────
    function startNative() {
        status('Starting camera...');
        var video = document.createElement('video');
        video.style.cssText = 'width:100%;border-radius:8px;background:#000;display:block';
        video.setAttribute('autoplay', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');
        container.innerHTML = '';
        container.appendChild(video);

        var detector = new BarcodeDetector({
            formats: ['ean_13','ean_8','upc_a','upc_e','code_128','code_39','qr_code','data_matrix','itf']
        });

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(s) {
                stream = s;
                video.srcObject = s;
                video.play();
                status('Camera open — point at barcode...');
                var frameCount = 0;
                (function scan() {
                    if (!active) return;
                    frameCount++;
                    detector.detect(video).then(function(results) {
                        if (!active) return;
                        status('Scanning... frame ' + frameCount + ' — ' + results.length + ' barcode(s) found');
                        if (results.length) {
                            onDetected(results[0].rawValue);
                        } else {
                            requestAnimationFrame(scan);
                        }
                    }).catch(function(e) {
                        status('detect() error: ' + e.message);
                        if (active) requestAnimationFrame(scan);
                    });
                })();
            })
            .catch(function(e) {
                status(e.name === 'NotAllowedError'
                    ? 'Camera permission denied — allow it in your browser settings.'
                    : 'Could not open camera: ' + e.message);
            });
    }

    function stopNative() {
        if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); stream = null; }
        container.innerHTML = '';
    }

    // ── Quagga2 fallback (Safari / iOS) ─────────────────────────────────────
    function startQuagga() {
        status('Starting camera...');
        container.innerHTML = '';

        Quagga.init({
            inputStream: {
                type: 'LiveStream',
                target: container,
                constraints: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
            },
            decoder: {
                readers: ['ean_reader','ean_8_reader','upc_reader','upc_e_reader','code_128_reader','code_39_reader']
            },
            locate: true,
            numOfWorkers: 0
        }, function(err) {
            if (err) {
                status('Camera error: ' + (err.message || err));
                return;
            }
            Quagga.start();
            status('Camera open — point at barcode...');

            // Require 2 consistent reads to reduce false positives
            var last = '', count = 0;
            Quagga.onDetected(function(result) {
                if (!active) return;
                var code = result.codeResult.code;
                if (code === last) { count++; } else { last = code; count = 1; }
                if (count >= 2) { onDetected(code); }
            });
        });
    }

    function stopQuagga() {
        try { Quagga.stop(); } catch(e) {}
        container.innerHTML = '';
    }

    // ── Public API ───────────────────────────────────────────────────────────
    this.start = function() {
        active = true;
        if (useNative) { startNative(); } else { startQuagga(); }
    };

    this.stop = function() {
        active = false;
        if (useNative) { stopNative(); } else { stopQuagga(); }
    };
}
