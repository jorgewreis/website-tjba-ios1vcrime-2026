(function (root, factory) {
    'use strict';
    var api = factory();
    if (root) {
        root.App = root.App || {};
        root.App.PdfIntelligencePdfJs = api;
    }
    if (typeof module === 'object' && module.exports) module.exports = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function itemBounds(item, viewport) {
        if (!item || !Array.isArray(item.transform) || typeof viewport.convertToViewportRectangle !== 'function') return null;
        var x = Number(item.transform[4]);
        var y = Number(item.transform[5]);
        var width = Number(item.width);
        var height = Math.abs(Number(item.height || item.transform[3]));
        if (![x, y, width, height].every(Number.isFinite)) return null;
        var points = viewport.convertToViewportRectangle([x, y, x + width, y + height]);
        if (!Array.isArray(points) || points.length < 4) return null;
        var left = Math.min(points[0], points[2]);
        var right = Math.max(points[0], points[2]);
        var top = Math.min(points[1], points[3]);
        var bottom = Math.max(points[1], points[3]);
        return {
            x: Math.max(0, Math.min(1, left / viewport.width)),
            y: Math.max(0, Math.min(1, top / viewport.height)),
            width: Math.max(0, Math.min(1, (right - left) / viewport.width)),
            height: Math.max(0, Math.min(1, (bottom - top) / viewport.height))
        };
    }

    function joinTextItems(items) {
        var rows = [];
        (items || []).forEach(function (item) {
            var text = String(item.str || '');
            if (!text.trim() && !item.hasEOL) return;
            var y = item.transform && Number(item.transform[5]);
            var height = Math.abs(Number(item.height || (item.transform && item.transform[3]) || 10));
            var row = rows.find(function (candidate) { return Number.isFinite(y) && Math.abs(candidate.y - y) <= Math.max(2, height * 0.35); });
            if (!row) {
                row = { y: Number.isFinite(y) ? y : (rows.length * -1), items: [] };
                rows.push(row);
            }
            row.items.push(item);
        });
        rows.sort(function (a, b) { return b.y - a.y; });
        return rows.map(function (row) {
            row.items.sort(function (a, b) { return Number(a.transform && a.transform[4] || 0) - Number(b.transform && b.transform[4] || 0); });
            var line = '';
            row.items.forEach(function (item) {
                var text = String(item.str || '');
                if (line && text && !/\s$/.test(line) && !/^\s/.test(text)) line += ' ';
                line += text;
            });
            return line.trim();
        }).filter(Boolean).join('\n');
    }

    async function extractNativePage(pdfPage, pageNumber, library) {
        var core = typeof globalThis !== 'undefined' && globalThis.App && globalThis.App.PdfIntelligenceCore;
        if (!core) throw new Error('Contrato de extração PDF Intelligence indisponível.');
        var content = await pdfPage.getTextContent();
        var viewport = pdfPage.getViewport({ scale: 1 });
        var version = library && library.version ? String(library.version) : null;
        var blocks = (content.items || []).map(function (item, index) {
            return core.createBlock({
                pageNumber: pageNumber,
                order: index,
                type: 'text',
                text: String(item.str || ''),
                coordinates: itemBounds(item, viewport),
                engine: 'pdf.js',
                engineVersion: version,
                confidence: null
            });
        }).filter(function (block) { return block.text.length > 0; });
        return core.createPageResult({
            pageNumber: pageNumber,
            engine: 'pdf.js',
            engineVersion: version,
            blocks: blocks,
            occurrences: blocks.length ? [] : [{ code: 'native-text-empty', severity: 'info', message: 'A página não possui texto nativo extraível.' }]
        });
    }

    return { extractNativePage: extractNativePage, itemBounds: itemBounds, joinTextItems: joinTextItems };
}));
