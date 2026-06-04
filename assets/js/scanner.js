/**
 * LVScanner — unified barcode scanner
 * Uses native BarcodeDetector (Chrome/Android) with Quagga2 fallback (Safari/iOS).
 * Quagga2 is loaded lazily — only when the scanner is first opened.
 */
function LVScanner(containerId, onDetected, onStatus) {
    var QUAGGA_CDN = 'https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js';
    var container  = document.getElementById(containerId);
    var useNative  = ('BarcodeDetector' in window);
    var active     = false;
    var stream     = null;
    var self       = this;

    function status(msg) { if (onStatus) onStatus(msg); }

    // Stop everything and clear the container
    function cleanup() {
        active = false;
        if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); stream = null; }
        if (!useNative && window.Quagga) { try { Quagga.stop(); } catch(e) {} }
        if (container) container.innerHTML = '';
    }

    // ── Native BarcodeDetector ───────────────────────────────────────────────
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
                if (!active) { s.getTracks().forEach(function(t) { t.stop(); }); return; }
                stream = s;
                video.srcObject = s;
                video.play();
                status('Point camera at barcode...');
                var frameCount = 0;
                (function scan() {
                    if (!active) return;
                    frameCount++;
                    detector.detect(video).then(function(results) {
                        if (!active) return;
                        status('Scanning... frame ' + frameCount + ' — ' + results.length + ' found');
                        if (results.length) { onDetected(results[0].rawValue); }
                        else { requestAnimationFrame(scan); }
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
            if (!active) { try { Quagga.stop(); } catch(e) {} return; }
            if (err) { status('Camera error: ' + (err.message || err)); return; }
            Quagga.start();
            status('Point camera at barcode...');
            var last = '', count = 0;
            Quagga.onDetected(function(result) {
                if (!active) return;
                var code = result.codeResult.code;
                if (code === last) { count++; } else { last = code; count = 1; }
                if (count >= 2) { onDetected(code); }
            });
        });
    }

    // Lazy-load Quagga2 then call startQuagga
    function loadQuaggaThenStart() {
        if (window.Quagga) { startQuagga(); return; }
        status('Loading scanner...');
        var s = document.createElement('script');
        s.src = QUAGGA_CDN;
        s.onload = function() { if (active) startQuagga(); };
        s.onerror = function() { status('Failed to load scanner library. Check your connection.'); };
        document.head.appendChild(s);
    }

    // ── Public API ───────────────────────────────────────────────────────────
    this.start = function() {
        active = true;
        if (useNative) { startNative(); } else { loadQuaggaThenStart(); }
    };

    this.stop = function() { cleanup(); };

    // Stop camera if user navigates away mid-scan
    window.addEventListener('pagehide', cleanup);
    window.addEventListener('beforeunload', cleanup);
}
