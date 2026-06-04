/**
 * LVScanner v4
 * - Chrome/Android: native BarcodeDetector via live camera stream
 * - Safari/iOS: file input with capture="environment" + Quagga2 image decode
 *   (avoids all iOS video autoplay / playsinline issues entirely)
 */
console.log('[LVScanner] v4 loaded');
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

    // ── Native BarcodeDetector (Chrome / Android) ────────────────────────────
    function startNative() {
        status('Starting camera...');
        var video = document.createElement('video');
        video.style.cssText = 'width:100%;border-radius:8px;background:#000;display:block';
        video.setAttribute('autoplay', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');
        video.muted = true;
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

    // ── Safari/iOS fallback: photo capture + Quagga2 image decode ───────────
    function startPhotoCapture() {
        container.innerHTML = '';

        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'padding:1rem; text-align:center';

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.setAttribute('capture', 'environment');
        input.style.display = 'none';
        input.id = 'lv-barcode-capture-' + Date.now();

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Open Camera';
        btn.style.cssText = 'padding:0.875rem 1.5rem; background:var(--blue,#378ADD); color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; width:100%';

        var preview = document.createElement('img');
        preview.style.cssText = 'display:none; width:100%; border-radius:8px; margin-top:0.75rem';

        btn.addEventListener('click', function() { input.click(); });

        input.addEventListener('change', function() {
            var file = input.files && input.files[0];
            if (!file || !active) return;

            status('Processing image...');

            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target.result;
                preview.src = dataUrl;
                preview.style.display = 'block';
                decodeImage(dataUrl);
            };
            reader.readAsDataURL(file);
        });

        wrapper.appendChild(input);
        wrapper.appendChild(btn);
        wrapper.appendChild(preview);
        container.appendChild(wrapper);
        status('Tap "Open Camera" and point at the barcode');
    }

    function decodeImage(dataUrl) {
        if (!window.Quagga) { status('Loading decoder...'); return; }

        Quagga.decodeSingle({
            decoder: {
                readers: ['ean_reader','ean_8_reader','upc_reader','upc_e_reader','code_128_reader','code_39_reader','qr_code_reader']
            },
            locate: true,
            src: dataUrl
        }, function(result) {
            if (!active) return;
            if (result && result.codeResult && result.codeResult.code) {
                onDetected(result.codeResult.code);
            } else {
                status('No barcode found — try again, ensure barcode is clearly visible');
                // Reset button so they can try again
                var btn = container.querySelector('button');
                if (btn) btn.textContent = 'Try Again';
            }
        });
    }

    function loadQuaggaThenStartPhoto() {
        if (window.Quagga) { startPhotoCapture(); return; }
        status('Loading...');
        var s = document.createElement('script');
        s.src = QUAGGA_CDN;
        s.onload = function() { if (active) startPhotoCapture(); };
        s.onerror = function() { status('Failed to load scanner library. Check your connection.'); };
        document.head.appendChild(s);
    }

    // ── Public API ───────────────────────────────────────────────────────────
    this.start = function() {
        active = true;
        if (useNative) { startNative(); } else { loadQuaggaThenStartPhoto(); }
    };

    this.stop = function() { cleanup(); };
}
