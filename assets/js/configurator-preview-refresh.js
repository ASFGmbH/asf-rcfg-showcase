(function () {
    'use strict';

    const config = window.AsfRcfgShowcasePreview || {};
    const restRoutePart = String(config.restRoutePart || '/oneApi/rcfg/v3/api');
    const currentIdFromPhp = String(config.currentId || '').toUpperCase();
    const imageBaseUrl = String(config.imageBaseUrl || '');

    const debug = new URLSearchParams(window.location.search).get('asf_rcfg_debug') === '1';

    const previewSelector = '.save-load-wrapper .dbContent .dbRow .dbCol img.preview';
    const overlayClass = 'asf-rcfg-showcase-preview-overlay';

    const state = {
        currentId: currentIdFromPhp,
        latestImageUrl: null,
        mutationTimer: null,
        observerActive: false,
        refreshAttemptsLeft: 0
    };

    function log(message, data) {
        if (!debug || typeof console === 'undefined') {
            return;
        }

        if (typeof data === 'undefined') {
            console.log('[ASF RCFG Showcase]', message);
            return;
        }

        console.log('[ASF RCFG Showcase]', message, data);
    }

    function isRcfgApiUrl(url) {
        if (!url) {
            return false;
        }

        return String(url).indexOf(restRoutePart) !== -1;
    }

    function parseBody(body) {
        if (!body) {
            return null;
        }

        if (typeof FormData !== 'undefined' && body instanceof FormData) {
            return {
                rpc: body.get('rpc'),
                rpp: body.get('rpp')
            };
        }

        if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
            return {
                rpc: body.get('rpc'),
                rpp: body.get('rpp')
            };
        }

        if (typeof body === 'string') {
            const trimmed = body.trim();

            if (trimmed.startsWith('{')) {
                try {
                    const json = JSON.parse(trimmed);

                    return {
                        rpc: json.rpc || null,
                        rpp: json.rpp || null
                    };
                } catch (error) {
                    log('Body JSON konnte nicht gelesen werden');
                    return null;
                }
            }

            try {
                const params = new URLSearchParams(trimmed);

                return {
                    rpc: params.get('rpc'),
                    rpp: params.get('rpp')
                };
            } catch (error) {
                log('Body URLSearchParams konnte nicht gelesen werden');
                return null;
            }
        }

        return null;
    }

    function parseRpp(rpp) {
        if (!rpp) {
            return null;
        }

        if (Array.isArray(rpp)) {
            return rpp;
        }

        if (typeof rpp !== 'string') {
            return null;
        }

        try {
            const parsed = JSON.parse(rpp);

            return Array.isArray(parsed) ? parsed : null;
        } catch (error) {
            log('RPP konnte nicht gelesen werden');
            return null;
        }
    }

    function extractSavePresetPayload(body) {
        const parsedBody = parseBody(body);

        if (!parsedBody || parsedBody.rpc !== 'savePreset') {
            return null;
        }

        const args = parseRpp(parsedBody.rpp);

        if (!args || args.length < 1) {
            log('savePreset erkannt, aber rpp ist unvollständig');
            return null;
        }

        const id = String(args[0] || '').toUpperCase().trim();

        if (!id) {
            log('savePreset ohne ID erkannt');
            return null;
        }

        return {
            id: id
        };
    }

    function buildImageUrl(id) {
        if (!imageBaseUrl || !id) {
            return null;
        }

        return imageBaseUrl.replace(/\/$/, '') + '/' + encodeURIComponent(id) + '.png?asf_rcfg_ts=' + Date.now();
    }

    function getOverlayForPreview(preview) {
        const parent = preview.parentElement;

        if (!parent) {
            return null;
        }

        let overlay = parent.querySelector('img.' + overlayClass);

        if (overlay) {
            return overlay;
        }

        const parentPosition = window.getComputedStyle(parent).position;

        if (parentPosition === 'static') {
            parent.style.position = 'relative';
        }

        overlay = document.createElement('img');
        overlay.className = overlayClass;
        overlay.alt = preview.getAttribute('alt') || 'Aktuelle Ringkonfiguration';
        overlay.setAttribute('aria-hidden', 'true');

        overlay.style.position = 'absolute';
        overlay.style.inset = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.pointerEvents = 'none';
        overlay.style.background = 'transparent';

        const deleteButton = parent.querySelector('.delete-preset');

        if (deleteButton) {
            parent.insertBefore(overlay, deleteButton);
        } else {
            parent.appendChild(overlay);
        }

        return overlay;
    }

    function applyPreviewOverlay(id, imageUrl) {
        const previews = document.querySelectorAll(previewSelector);

        log('Preview-Overlay Versuch', {
            id: id,
            found: previews.length,
            imageUrl: imageUrl
        });

        if (!previews.length) {
            return false;
        }

        previews.forEach(function (preview) {
            const overlay = getOverlayForPreview(preview);

            if (!overlay) {
                return;
            }

            if (overlay.dataset.asfRcfgImageUrl === imageUrl) {
                return;
            }

            overlay.dataset.asfRcfgImageUrl = imageUrl;

            overlay.onload = function () {
                log('Preview-Overlay geladen', {
                    id: id,
                    src: imageUrl
                });
            };

            overlay.onerror = function () {
                log('Preview-Overlay konnte nicht geladen werden', {
                    id: id,
                    src: imageUrl
                });
            };

            overlay.src = imageUrl;
        });

        return true;
    }

    function refreshPreview(id) {
        const imageUrl = buildImageUrl(id);

        if (!imageUrl) {
            log('Keine Bild-URL erzeugbar', {
                id: id,
                imageBaseUrl: imageBaseUrl
            });
            return;
        }

        state.currentId = id;
        state.latestImageUrl = imageUrl;
        state.refreshAttemptsLeft = 20;

        runLimitedRefreshLoop();
    }

    function runLimitedRefreshLoop() {
        if (!state.currentId || !state.latestImageUrl || state.refreshAttemptsLeft <= 0) {
            return;
        }

        state.refreshAttemptsLeft--;

        applyPreviewOverlay(state.currentId, state.latestImageUrl);

        window.setTimeout(runLimitedRefreshLoop, 250);
    }

    function scheduleRefresh(id) {
        log('savePreset Payload erkannt', {
            id: id
        });

        window.setTimeout(function () {
            refreshPreview(id);
        }, 250);
    }

    function patchFetch() {
        if (typeof window.fetch !== 'function') {
            log('fetch nicht verfügbar');
            return;
        }

        const originalFetch = window.fetch;

        window.fetch = function (input, init) {
            const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            const body = init && init.body ? init.body : null;
            const isRcfg = isRcfgApiUrl(url);
            const payload = isRcfg ? extractSavePresetPayload(body) : null;

            if (isRcfg) {
                log('fetch RCFG Request erkannt', {
                    url: url,
                    payload: payload
                });
            }

            return originalFetch.apply(this, arguments).then(function (response) {
                if (payload) {
                    scheduleRefresh(payload.id);
                }

                return response;
            });
        };
    }

    function patchXmlHttpRequest() {
        if (typeof window.XMLHttpRequest !== 'function') {
            log('XMLHttpRequest nicht verfügbar');
            return;
        }

        const originalOpen = window.XMLHttpRequest.prototype.open;
        const originalSend = window.XMLHttpRequest.prototype.send;

        window.XMLHttpRequest.prototype.open = function (method, url) {
            this.__asfRcfgMethod = method;
            this.__asfRcfgUrl = url;

            return originalOpen.apply(this, arguments);
        };

        window.XMLHttpRequest.prototype.send = function (body) {
            const isRcfg = isRcfgApiUrl(this.__asfRcfgUrl);
            const payload = isRcfg ? extractSavePresetPayload(body) : null;

            if (isRcfg) {
                log('XHR RCFG Request erkannt', {
                    url: this.__asfRcfgUrl,
                    payload: payload
                });
            }

            if (payload) {
                this.addEventListener('loadend', function () {
                    scheduleRefresh(payload.id);
                });
            }

            return originalSend.apply(this, arguments);
        };
    }

    function observePreviewContainer() {
        if (typeof MutationObserver !== 'function') {
            return;
        }

        const observer = new MutationObserver(function () {
            if (!state.latestImageUrl || !state.currentId) {
                return;
            }

            if (state.mutationTimer) {
                window.clearTimeout(state.mutationTimer);
            }

            state.mutationTimer = window.setTimeout(function () {
                applyPreviewOverlay(state.currentId, state.latestImageUrl);
            }, 150);
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });

        state.observerActive = true;

        log('MutationObserver aktiv');
    }

    log('Script geladen', {
        restRoutePart: restRoutePart,
        currentId: state.currentId,
        previewSelector: previewSelector,
        imageBaseUrl: imageBaseUrl
    });

    patchFetch();
    patchXmlHttpRequest();
    observePreviewContainer();
})();