/**
 * LVScanner v5
 * Chrome/Android  → native BarcodeDetector (live stream)
 * Safari/iOS      → getUserMedia + our own <video playsinline> + Quagga2 frame decode
 *                   We own the video element so iOS playsinline is set before play().
 */
console.log('[LVScanner] v5 loaded');

function LVScanner(containerId, onDetected, onStatus) {
    var QUAGGA_CDN = 'https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js';
    var container  = document.getElementById(containerId);
    var useNative  = ('BarcodeDetector' in window);
    var active     = false;
    var stream     = null;

    function status(msg) { if (onStatus) onStatus(msg); }

    function cleanup() {
        active = false;
        if (stream) { stream.getTracks().forEach(function(t) { t.stop(); }); stream = null; }
        if (container) container.innerHTML = '';
    }

    // ── Shared: build a <video> element that works on iOS ────────────────────
    function makeVideo() {
        var v = document.createElement('video');
        v.setAttribute('autoplay', '');
        v.setAttribute('playsinline', '');   // required on iOS
        v.setAttribute('muted', '');
        v.muted = true;
        v.style.cssText = 'width:100%;border-radius:8px;background:#000;display:block';
        return v;
    }

    // ── Native BarcodeDetector (Chrome / Android) ────────────────────────────
    function startNative() {
        status('Starting camera...');
        var video = makeVideo();
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
                var frame = 0;
                (function scan() {
                    if (!active) return;
                    frame++;
                    detector.detect(video).then(function(results) {
                        if (!active) return;
                        status('Scanning... frame ' + frame + ' — ' + results.length + ' found');
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

    // ── Safari / iOS: own video + Quagga frame decode ────────────────────────
    function startFallback() {
        status('Starting camera...');
        var video = makeVideo();
        container.innerHTML = '';
        container.appendChild(video);

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(s) {
                if (!active) { s.getTracks().forEach(function(t) { t.stop(); }); return; }
                stream = s;
                video.srcObject = s;
                return video.play();
            })
            .then(function() {
                if (!active) return;
                status('Point camera at barcode...');
                decodeLoop(video);
            })
            .catch(function(e) {
                status(e.name === 'NotAllowedError'
                    ? 'Camera permission denied — allow it in your browser settings.'
                    : 'Could not open camera: ' + e.message);
            });
    }

    function decodeLoop(video) {
        var canvas   = document.createElement('canvas');
        var ctx      = canvas.getContext('2d');
        var busy     = false;
        var attempts = 0;

        function tick() {
            if (!active) return;
            if (busy || !video.videoWidth) { setTimeout(tick, 150); return; }

            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            attempts++;
            status('Scanning... attempt ' + attempts);

            busy = true;
            Quagga.decodeSingle({
                decoder: {
                    readers: ['ean_reader','ean_8_reader','upc_reader','upc_e_reader',
                              'code_128_reader','code_39_reader']
                },
                locate: true,
                src: canvas.toDataURL('image/jpeg', 0.9)
            }, function(result) {
                busy = false;
                if (!active) return;
                if (result && result.codeResult && result.codeResult.code) {
                    onDetected(result.codeResult.code);
                } else {
                    setTimeout(tick, 300);
                }
            });
        }

        tick();
    }

    function loadQuaggaThenStart() {
        if (window.Quagga) { startFallback(); return; }
        status('Loading scanner...');
        var s = document.createElement('script');
        s.src = QUAGGA_CDN;
        s.onload  = function() { if (active) startFallback(); };
        s.onerror = function() { status('Failed to load scanner. Check your connection.'); };
        document.head.appendChild(s);
    }

    // ── Public API ───────────────────────────────────────────────────────────
    this.start = function() {
        active = true;
        if (useNative) { startNative(); } else { loadQuaggaThenStart(); }
    };

    this.stop = function() { cleanup(); };
}
