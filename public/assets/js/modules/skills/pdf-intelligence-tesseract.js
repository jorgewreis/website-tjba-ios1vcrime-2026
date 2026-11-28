(function (root, factory) {
    'use strict';

    var api = factory(root);
    if (root) {
        root.App = root.App || {};
        root.App.PdfIntelligenceTesseract = api;
    }
    if (typeof module === 'object' && module.exports) module.exports = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function (root) {
    'use strict';

    var ENGINE = 'tesseract.js';
    var VERSION = '7.0.0';

    function abortError() {
        var error = new Error('Processamento cancelado.');
        error.name = 'AbortError';
        return error;
    }

    function isAborted(signal) {
        return Boolean(signal && signal.aborted);
    }

    function extractLines(data) {
        if (Array.isArray(data && data.lines) && data.lines.length) return data.lines;
        var lines = [];
        function visit(container) {
            (container || []).forEach(function (item) {
                if (Array.isArray(item.lines) && item.lines.length) {
                    item.lines.forEach(function (line) {
                        lines.push({ text: line.text, bbox: line.bbox || item.bbox, confidence: line.confidence });
                    });
                } else if (Array.isArray(item.paragraphs) && item.paragraphs.length) {
                    visit(item.paragraphs);
                } else if (Array.isArray(item.blocks) && item.blocks.length) {
                    visit(item.blocks);
                }
            });
        }
        visit(data && data.blocks);
        if (lines.length) return lines;
        return String(data && data.text || '').split(/\r?\n/).map(function (text) { return { text: text }; }).filter(function (line) { return line.text.trim(); });
    }

    function normalizeBbox(bbox, width, height) {
        if (!bbox || !Number.isFinite(Number(width)) || !Number.isFinite(Number(height)) || width <= 0 || height <= 0) return null;
        var left = Number(bbox.x0 !== undefined ? bbox.x0 : bbox.left);
        var top = Number(bbox.y0 !== undefined ? bbox.y0 : bbox.top);
        var right = Number(bbox.x1 !== undefined ? bbox.x1 : bbox.right);
        var bottom = Number(bbox.y1 !== undefined ? bbox.y1 : bbox.bottom);
        if (![left, top, right, bottom].every(Number.isFinite) || right < left || bottom < top) return null;
        return {
            x: Math.max(0, Math.min(1, left / width)),
            y: Math.max(0, Math.min(1, top / height)),
            width: Math.max(0, Math.min(1, (right - left) / width)),
            height: Math.max(0, Math.min(1, (bottom - top) / height))
        };
    }

    function createAdapter(options) {
        options = options || {};
        var basePath = String(options.basePath || '/assets/ocr/tesseract/7.0.0/').replace(/\/?$/, '/');
        var languagePath = basePath + 'lang/4.0.0_best_int';
        var coreApi = function () { return root.App && root.App.PdfIntelligenceCore; };
        var workerPromise = null;
        var worker = null;
        var activeRender = null;

        async function getWorker(onProgress) {
            if (worker) return worker;
            if (workerPromise) return workerPromise;
            if (!root.Tesseract || typeof root.Tesseract.createWorker !== 'function') {
                throw new Error('OCR indisponível: a biblioteca local Tesseract.js não carregou.');
            }
            workerPromise = root.Tesseract.createWorker('por', 1, {
                workerPath: basePath + 'worker.min.js',
                corePath: basePath + 'core/tesseract-core-lstm.wasm.js',
                langPath: languagePath,
                workerBlobURL: false,
                gzip: true,
                logger: function (status) {
                    if (typeof onProgress === 'function' && status && Number.isFinite(status.progress)) {
                        onProgress({ stage: String(status.status || 'ocr'), progress: Math.max(0, Math.min(1, status.progress)) });
                    }
                }
            }).then(function (instance) {
                worker = instance;
                workerPromise = null;
                return instance;
            }).catch(function (error) {
                workerPromise = null;
                throw error;
            });
            return workerPromise;
        }

        async function recognizePage(pdfPage, pageNumber, settings) {
            settings = settings || {};
            var signal = settings.signal;
            if (isAborted(signal)) throw abortError();
            var core = coreApi();
            if (!core || typeof core.createPageResult !== 'function') throw new Error('Contrato de extração PDF Intelligence indisponível.');
            if (!root.document || typeof root.document.createElement !== 'function') throw new Error('OCR indisponível neste ambiente de navegador.');
            var startedAt = Date.now();
            var scale = Number.isFinite(settings.scale) ? Math.max(1, Math.min(4, settings.scale)) : 2;
            var viewport = pdfPage.getViewport({ scale: scale });
            var canvas = root.document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            var context = canvas.getContext('2d', { alpha: false });
            if (!context) throw new Error('Não foi possível preparar a página para OCR.');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            activeRender = pdfPage.render({ canvasContext: context, viewport: viewport });
            var abortListener = function () {
                if (activeRender && typeof activeRender.cancel === 'function') activeRender.cancel();
                if (worker && typeof worker.terminate === 'function') {
                    var currentWorker = worker;
                    worker = null;
                    currentWorker.terminate();
                }
            };
            if (signal) signal.addEventListener('abort', abortListener, { once: true });
            try {
                await activeRender.promise;
                if (isAborted(signal)) throw abortError();
                var currentWorker = await getWorker(settings.onProgress);
                if (isAborted(signal)) throw abortError();
                var recognition = await currentWorker.recognize(canvas, {}, { blocks: true });
                if (isAborted(signal)) throw abortError();
                var data = recognition && recognition.data || {};
                var lines = extractLines(data);
                var blocks = lines.map(function (line, index) {
                    var confidence = Number.isFinite(Number(line.confidence)) ? Number(line.confidence) / 100 : null;
                    return core.createBlock({
                        pageNumber: pageNumber,
                        order: index,
                        type: 'text',
                        text: String(line.text || ''),
                        coordinates: normalizeBbox(line.bbox, canvas.width, canvas.height),
                        engine: ENGINE,
                        engineVersion: VERSION,
                        confidence: confidence
                    });
                }).filter(function (block) { return block.text.trim(); });
                var occurrences = [];
                if (!blocks.length) {
                    occurrences.push({ code: 'ocr-no-text', severity: 'warning', message: 'O OCR não identificou texto nesta página; confira o PDF original.' });
                }
                var confidences = blocks.map(function (block) { return block.confidence; }).filter(function (value) { return value !== null; });
                if (confidences.length && confidences.reduce(function (sum, value) { return sum + value; }, 0) / confidences.length < 0.6) {
                    occurrences.push({ code: 'ocr-low-confidence', severity: 'warning', message: 'O OCR reportou confiança baixa; revise o texto contra a página original.' });
                }
                return core.createPageResult({
                    pageNumber: pageNumber,
                    engine: ENGINE,
                    engineVersion: VERSION,
                    durationMs: Date.now() - startedAt,
                    blocks: blocks,
                    occurrences: occurrences
                });
            } catch (error) {
                if (isAborted(signal) || error.name === 'AbortError') throw abortError();
                throw new Error('Falha ao processar OCR local na página ' + pageNumber + '.');
            } finally {
                if (signal) signal.removeEventListener('abort', abortListener);
                if (activeRender) activeRender = null;
                canvas.width = 0;
                canvas.height = 0;
            }
        }

        async function terminate() {
            if (activeRender && typeof activeRender.cancel === 'function') activeRender.cancel();
            if (!worker && workerPromise) {
                try { worker = await workerPromise; } catch (error) { workerPromise = null; }
            }
            if (worker && typeof worker.terminate === 'function') await worker.terminate();
            worker = null;
            workerPromise = null;
        }

        return {
            name: ENGINE,
            version: VERSION,
            language: 'por',
            recognizePage: recognizePage,
            terminate: terminate
        };
    }

    return { createAdapter: createAdapter, extractLines: extractLines, normalizeBbox: normalizeBbox };
}));
