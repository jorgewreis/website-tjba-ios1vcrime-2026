'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const core = require('../../public/assets/js/modules/skills/pdf-intelligence-core.js');
const tesseractAdapter = require('../../public/assets/js/modules/skills/pdf-intelligence-tesseract.js');
const pdfJsAdapter = require('../../public/assets/js/modules/skills/pdf-intelligence-pdfjs.js');

test('classifica texto nativo e página que deve ser candidata a OCR', () => {
    assert.equal(core.classifyPage({ pageNumber: 1, text: 'Decisão judicial com conteúdo nativo suficiente para leitura.' }).route, 'native-text');
    const result = core.classifyPage({ pageNumber: 2, text: ' ' });
    assert.equal(result.route, 'ocr-candidate');
    assert.equal(result.reason, 'insufficient-native-text');
});

test('normaliza contrato e descarta coordenadas e confiança inválidas', () => {
    const result = core.createPageResult({
        pageNumber: 1,
        engine: 'pdfjs',
        blocks: [{ type: 'text', text: 'Conteúdo', coordinates: { x: 0.9, y: 0.1, width: 0.2, height: 0.2 }, confidence: 1.5 }]
    });
    assert.equal(result.blocks[0].pageNumber, 1);
    assert.equal(result.blocks[0].engine, 'pdfjs');
    assert.equal(result.blocks[0].coordinates, null);
    assert.equal(result.blocks[0].confidence, null);
});

test('mantém as saídas dos motores e registra divergência em identificador processual', () => {
    const merged = core.mergePageResults([
        { pageNumber: 1, engine: 'pdfjs', blocks: [{ order: 0, text: 'Processo 0001234-56.2026.8.05.0001' }] },
        { pageNumber: 1, engine: 'tesseract', blocks: [{ order: 0, text: 'Processo 0001234-56.2026.8.05.0002' }] }
    ]);
    assert.equal(merged.pages.length, 2);
    assert.ok(merged.occurrences.some(item => item.code === 'critical-field-divergence:process-number'));
    assert.match(merged.occurrences[0].message, /Revise a página original/);
});

test('gera Markdown paginado e sinaliza páginas sem conteúdo sem completar texto', () => {
    const markdown = core.normalizeMarkdown({
        title: 'Autos fictícios',
        pages: [
            { pageNumber: 2, blocks: [] },
            { pageNumber: 1, blocks: [{ order: 0, text: 'Texto preservado.' }] }
        ]
    });
    assert.ok(markdown.indexOf('## Página 1') < markdown.indexOf('## Página 2'));
    assert.match(markdown, /\[Trecho ilegível ou não extraído\./);
    assert.match(markdown, /Texto preservado\./);
    assert.equal((markdown.match(/^# /gm) || []).length, 1);
});

test('cria relatório agregado sem calcular qualidade inexistente', () => {
    const report = core.createQualityReport({
        pageCount: 2,
        pages: [{ engine: 'pdfjs', blocks: [{ text: 'Amostra' }] }, { engine: 'pdfjs', blocks: [] }],
        occurrences: [{ severity: 'warning' }],
        durationMs: 120
    });
    assert.equal(report.pagesWithText, 1);
    assert.equal(report.pagesWithoutText, 1);
    assert.equal(report.warningCount, 1);
    assert.equal(Object.hasOwn(report, 'wer'), false);
});

test('adaptador OCR mapeia linhas e coordenadas do motor para o contrato comum', async () => {
    const originalDocument = globalThis.document;
    const originalTesseract = globalThis.Tesseract;
    globalThis.App = globalThis.App || {};
    globalThis.App.PdfIntelligenceCore = core;
    globalThis.document = {
        createElement() {
            return {
                width: 0,
                height: 0,
                getContext() { return { fillRect() {} }; }
            };
        }
    };
    globalThis.Tesseract = {
        OEM: { LSTM_ONLY: 1 },
        async createWorker(language, oem, options) {
            assert.equal(language, 'por');
            assert.equal(oem, 1);
            assert.equal(options.workerBlobURL, false);
            assert.match(options.workerPath, /^\/assets\//);
            return {
                async recognize() {
                    return { data: { blocks: [{ paragraphs: [{ lines: [{ text: 'Conteúdo fictício.', confidence: 82, bbox: { x0: 20, y0: 10, x1: 100, y1: 30 } }] }] }] } };
                },
                async terminate() {}
            };
        }
    };
    try {
        const adapter = tesseractAdapter.createAdapter();
        const result = await adapter.recognizePage({
            getViewport({ scale }) { return { width: 200 * scale, height: 100 * scale }; },
            render() { return { promise: Promise.resolve(), cancel() {} }; }
        }, 3);
        assert.equal(result.engine, 'tesseract.js');
        assert.equal(result.engineVersion, '7.0.0');
        assert.equal(result.blocks[0].text, 'Conteúdo fictício.');
        assert.deepEqual(result.blocks[0].coordinates, { x: 0.05, y: 0.05, width: 0.2, height: 0.1 });
        assert.equal(result.blocks[0].confidence, 0.82);
        await adapter.terminate();
    } finally {
        if (originalDocument === undefined) delete globalThis.document;
        else globalThis.document = originalDocument;
        if (originalTesseract === undefined) delete globalThis.Tesseract;
        else globalThis.Tesseract = originalTesseract;
    }
});

test('adaptador OCR respeita cancelamento antes de rasterizar a página', async () => {
    const controller = new AbortController();
    controller.abort();
    const adapter = tesseractAdapter.createAdapter();
    await assert.rejects(
        adapter.recognizePage({}, 1, { signal: controller.signal }),
        error => error.name === 'AbortError'
    );
});

test('adaptador PDF.js registra blocos nativos e coordenadas sem estimar confiança', async () => {
    globalThis.App = globalThis.App || {};
    globalThis.App.PdfIntelligenceCore = core;
    const result = await pdfJsAdapter.extractNativePage({
        async getTextContent() { return { items: [{ str: 'Amostra', width: 35, height: 10, transform: [1, 0, 0, 10, 20, 80] }] }; },
        getViewport() { return { width: 200, height: 100, convertToViewportRectangle(rect) { return [rect[0], 100 - rect[1], rect[2], 100 - rect[3]]; } }; }
    }, 2, { version: '3.11.174' });
    assert.equal(result.engine, 'pdf.js');
    assert.equal(result.engineVersion, '3.11.174');
    assert.equal(result.blocks[0].text, 'Amostra');
    assert.deepEqual(result.blocks[0].coordinates, { x: 0.1, y: 0.1, width: 0.175, height: 0.1 });
    assert.equal(result.blocks[0].confidence, null);
});

test('adaptador PDF.js organiza fragmentos nativos em linhas por posição vertical', () => {
    const lines = pdfJsAdapter.joinTextItems([
        { str: 'segunda', transform: [1, 0, 0, 10, 10, 80], height: 10 },
        { str: 'linha', transform: [1, 0, 0, 10, 10, 100], height: 10 },
        { str: 'primeira', transform: [1, 0, 0, 10, 40, 100], height: 10 }
    ]);
    assert.equal(lines, 'linha primeira\nsegunda');
});
