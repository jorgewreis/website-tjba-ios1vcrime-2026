(function (root, factory) {
    'use strict';

    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) {
        root.App = root.App || {};
        root.App.PdfIntelligenceCore = api;
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    var BLOCK_TYPES = ['text', 'heading', 'paragraph', 'list', 'table', 'image', 'stamp', 'signature', 'unknown'];
    var CRITICAL_PATTERNS = [
        { type: 'process-number', regex: /\b\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}\b/g },
        { type: 'date', regex: /\b\d{2}[/.]\d{2}[/.]\d{4}\b/g },
        { type: 'currency', regex: /\bR\$\s?\d[\d.]*,\d{2}\b/g }
    ];

    function finiteOrNull(value) {
        if (value === null || value === undefined || value === '') return null;
        return Number.isFinite(Number(value)) ? Number(value) : null;
    }

    function normalizeCoordinates(value) {
        if (!value || typeof value !== 'object') return null;
        var x = finiteOrNull(value.x);
        var y = finiteOrNull(value.y);
        var width = finiteOrNull(value.width);
        var height = finiteOrNull(value.height);
        if ([x, y, width, height].some(function (part) { return part === null; })) return null;
        if (x < 0 || y < 0 || width < 0 || height < 0 || x + width > 1.0001 || y + height > 1.0001) return null;
        return { x: x, y: y, width: width, height: height };
    }

    function createBlock(input) {
        input = input || {};
        var pageNumber = Math.floor(Number(input.pageNumber));
        var order = Math.floor(Number(input.order));
        var type = BLOCK_TYPES.indexOf(input.type) >= 0 ? input.type : 'unknown';
        var confidence = finiteOrNull(input.confidence);
        if (!Number.isInteger(pageNumber) || pageNumber < 1) throw new TypeError('pageNumber deve ser inteiro positivo.');
        if (!Number.isInteger(order) || order < 0) throw new TypeError('order deve ser inteiro não negativo.');
        if (confidence !== null && (confidence < 0 || confidence > 1)) confidence = null;
        return {
            id: String(input.id || ('p' + pageNumber + '-b' + order)),
            pageNumber: pageNumber,
            order: order,
            type: type,
            text: String(input.text || ''),
            coordinates: normalizeCoordinates(input.coordinates),
            engine: String(input.engine || 'unknown'),
            engineVersion: input.engineVersion ? String(input.engineVersion) : null,
            confidence: confidence
        };
    }

    function createOccurrence(input) {
        input = input || {};
        return {
            code: String(input.code || 'processing-note'),
            severity: ['info', 'warning', 'error'].indexOf(input.severity) >= 0 ? input.severity : 'warning',
            pageNumber: Number.isInteger(Number(input.pageNumber)) && Number(input.pageNumber) > 0 ? Number(input.pageNumber) : null,
            blockId: input.blockId ? String(input.blockId) : null,
            engines: Array.isArray(input.engines) ? input.engines.map(String) : [],
            message: String(input.message || 'Ocorrência de processamento sem descrição.')
        };
    }

    function createPageResult(input) {
        input = input || {};
        var pageNumber = Math.floor(Number(input.pageNumber));
        if (!Number.isInteger(pageNumber) || pageNumber < 1) throw new TypeError('pageNumber deve ser inteiro positivo.');
        var engine = String(input.engine || 'unknown');
        var durationMs = finiteOrNull(input.durationMs);
        return {
            pageNumber: pageNumber,
            engine: engine,
            engineVersion: input.engineVersion ? String(input.engineVersion) : null,
            durationMs: durationMs !== null && durationMs >= 0 ? durationMs : null,
            blocks: (Array.isArray(input.blocks) ? input.blocks : []).map(function (block, index) {
                return createBlock(Object.assign({}, block, {
                    pageNumber: pageNumber,
                    order: block.order === undefined ? index : block.order,
                    engine: block.engine || engine,
                    engineVersion: block.engineVersion || input.engineVersion
                }));
            }),
            occurrences: (Array.isArray(input.occurrences) ? input.occurrences : []).map(function (occurrence) {
                return createOccurrence(Object.assign({ pageNumber: pageNumber }, occurrence));
            })
        };
    }

    function classifyPage(input, thresholds) {
        input = input || {};
        thresholds = thresholds || {};
        var text = String(input.text || '');
        var minCharacters = Number.isFinite(thresholds.minCharacters) ? thresholds.minCharacters : 24;
        var minWords = Number.isFinite(thresholds.minWords) ? thresholds.minWords : 4;
        var words = text.trim().split(/\s+/).filter(Boolean).length;
        var characters = text.replace(/\s/g, '').length;
        var needsOcr = characters < minCharacters || words < minWords;
        return {
            pageNumber: Math.max(1, Math.floor(Number(input.pageNumber) || 1)),
            route: needsOcr ? 'ocr-candidate' : 'native-text',
            reason: needsOcr ? 'insufficient-native-text' : 'native-text-available',
            nativeCharacters: characters,
            nativeWords: words
        };
    }

    function criticalValues(text, pattern) {
        return (String(text || '').match(pattern.regex) || []).map(function (value) {
            return value.normalize('NFKC').replace(/\s+/g, '').toLocaleLowerCase('pt-BR');
        }).sort();
    }

    function findCriticalDivergences(results) {
        var byPage = new Map();
        (results || []).forEach(function (result) {
            var current = byPage.get(result.pageNumber) || [];
            current.push(result);
            byPage.set(result.pageNumber, current);
        });
        var occurrences = [];
        byPage.forEach(function (pageResults, pageNumber) {
            if (pageResults.length < 2) return;
            var engines = Array.from(new Set(pageResults.map(function (result) { return result.engine; })));
            CRITICAL_PATTERNS.forEach(function (pattern) {
                var values = pageResults.map(function (result) {
                    return criticalValues(result.blocks.map(function (block) { return block.text; }).join('\n'), pattern);
                });
                var serialized = new Set(values.map(function (entries) { return JSON.stringify(entries); }));
                if (serialized.size > 1) {
                    occurrences.push(createOccurrence({
                        code: 'critical-field-divergence:' + pattern.type,
                        severity: 'warning',
                        pageNumber: pageNumber,
                        engines: engines,
                        message: 'Resultados dos motores divergem em um campo potencialmente crítico (' + pattern.type + '). Revise a página original.'
                    }));
                }
            });
        });
        return occurrences;
    }

    function mergePageResults(results) {
        var normalized = (results || []).map(createPageResult).sort(function (a, b) {
            return a.pageNumber - b.pageNumber || a.engine.localeCompare(b.engine);
        });
        return {
            pages: normalized,
            occurrences: normalized.reduce(function (all, result) { return all.concat(result.occurrences); }, [])
                .concat(findCriticalDivergences(normalized))
        };
    }

    function normalizeMarkdown(input) {
        input = input || {};
        var title = String(input.title || 'Documento PDF').replace(/[\r\n]+/g, ' ').trim() || 'Documento PDF';
        var pages = (input.pages || []).slice().sort(function (a, b) { return a.pageNumber - b.pageNumber; });
        var lines = ['# ' + title, ''];
        pages.forEach(function (page) {
            lines.push('## Página ' + page.pageNumber, '');
            var blocks = (page.blocks || []).slice().sort(function (a, b) { return a.order - b.order; });
            var pageText = blocks.map(function (block) { return String(block.text || '').trim(); }).filter(Boolean);
            if (pageText.length) lines.push(pageText.join('\n\n'));
            else lines.push('[Trecho ilegível ou não extraído. Consulte a página original.]');
            lines.push('');
        });
        return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim() + '\n';
    }

    function createQualityReport(input) {
        input = input || {};
        var pages = input.pages || [];
        var occurrences = input.occurrences || [];
        var successful = pages.filter(function (page) { return page.blocks && page.blocks.some(function (block) { return Boolean(block.text.trim()); }); }).length;
        return {
            pageCount: Number(input.pageCount || pages.length),
            pagesWithText: successful,
            pagesWithoutText: Math.max(0, Number(input.pageCount || pages.length) - successful),
            engines: Array.from(new Set(pages.map(function (page) { return page.engine; }))),
            durationMs: finiteOrNull(input.durationMs),
            occurrenceCount: occurrences.length,
            warningCount: occurrences.filter(function (item) { return item.severity === 'warning'; }).length,
            errorCount: occurrences.filter(function (item) { return item.severity === 'error'; }).length
        };
    }

    return {
        version: '0.1.0',
        createBlock: createBlock,
        createOccurrence: createOccurrence,
        createPageResult: createPageResult,
        classifyPage: classifyPage,
        mergePageResults: mergePageResults,
        normalizeMarkdown: normalizeMarkdown,
        createQualityReport: createQualityReport
    };
}));
