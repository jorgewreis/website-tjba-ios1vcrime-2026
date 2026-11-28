(function () {
    'use strict';

    var state = [];
    var nextId = 1;
    var running = false;
    var cancelled = false;
    var activeController = null;
    var activeLoadingTask = null;
    var activeOcr = null;
    var maxBytes = 20 * 1024 * 1024;

    function el(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function fileStem(name) { return String(name || 'documento').replace(/\.pdf$/i, '').replace(/[^\p{L}\p{N}._ -]+/gu, '').trim() || 'documento'; }
    function mode() { var selected = document.querySelector('input[name="pdfi-mode"]:checked'); return selected ? selected.value : 'fast'; }
    function say(message) { el('pdfi-announcement').textContent = message; }
    function download(filename, blob) {
        var url = URL.createObjectURL(blob);
        var anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
    }
    function escapeMarkdownText(text) { return String(text || '').trim(); }

    function renderList() {
        var list = el('pdfi-list');
        var ready = state.filter(function (item) { return item.markdownBlob; });
        el('pdfi-count').textContent = state.length + (state.length === 1 ? ' arquivo' : ' arquivos');
        el('pdfi-zip').disabled = !ready.length || !window.JSZip;
        if (!state.length) {
            list.innerHTML = '<p class="pdfi-empty">Os PDFs adicionados aparecerão aqui.</p>';
            return;
        }
        list.innerHTML = state.map(function (item) {
            var progress = item.progress === null ? '' : '<div class="pdfi-row-progress"><progress max="100" value="' + item.progress + '"></progress><span>' + item.progress + '% · página ' + item.page + ' de ' + (item.totalPages || '?') + '</span></div>';
            return '<article class="pdfi-file" data-id="' + item.id + '"><div class="pdfi-file-info"><strong>' + esc(item.file.name) + '</strong><span>' + esc(item.status) + (item.engineText ? ' · motor: ' + esc(item.engineText) : '') + (item.processingStage ? ' · ' + esc(item.processingStage) : '') + '</span>' + progress + '</div><div class="pdfi-file-actions"><button type="button" data-action="preview" data-id="' + item.id + '"' + (!item.markdownBlob ? ' disabled' : '') + '>Prévia</button><button type="button" data-action="md" data-id="' + item.id + '"' + (!item.markdownBlob ? ' disabled' : '') + '>Baixar .md</button><button type="button" data-action="json" data-id="' + item.id + '"' + (!item.jsonBlob ? ' disabled' : '') + '>Baixar JSON</button><button type="button" data-action="remove" data-id="' + item.id + '"' + (item.converting ? ' disabled' : '') + '>Remover</button></div></article>';
        }).join('');
    }

    function currentItem(id) { return state.find(function (item) { return item.id === id; }); }

    function showResult(item) {
        if (!item || !item.markdown) return;
        el('pdfi-results').hidden = false;
        el('pdfi-result-file').textContent = item.file.name;
        el('pdfi-original').src = item.pdfUrl;
        el('pdfi-markdown').textContent = item.markdown;
        el('pdfi-download-md').onclick = function () { download(fileStem(item.file.name) + '.md', item.markdownBlob); };
        el('pdfi-download-json').onclick = function () { if (item.jsonBlob) download(fileStem(item.file.name) + '.json', item.jsonBlob); };
        var report = item.report;
        el('pdfi-report').innerHTML = [
            ['Páginas', report.pageCount],
            ['Com texto', report.pagesWithText],
            ['Sem texto', report.pagesWithoutText],
            ['Motores', report.engines.join(', ') || 'nenhum'],
            ['Tempo', (report.durationMs / 1000).toFixed(2) + ' s'],
            ['Alertas', report.warningCount]
        ].map(function (entry) { return '<div><dt>' + esc(entry[0]) + '</dt><dd>' + esc(entry[1]) + '</dd></div>'; }).join('');
        var warnings = item.occurrences || [];
        el('pdfi-warnings').hidden = !warnings.length;
        el('pdfi-warnings').innerHTML = warnings.length ? '<h3>Ocorrências para revisão</h3><ul>' + warnings.map(function (warning) { return '<li>Página ' + esc(warning.pageNumber || '—') + ': ' + esc(warning.message) + '</li>'; }).join('') + '</ul>' : '';
    }

    function chooseFile(input) {
        var files = Array.prototype.slice.call(input || []);
        var added = 0;
        var oversized = 0;
        files.forEach(function (file) {
            if (!file || (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name))) return;
            if (file.size > maxBytes) { oversized += 1; return; }
            if (state.some(function (item) { return item.file.name === file.name && item.file.size === file.size && item.file.lastModified === file.lastModified; })) return;
            state.push({ id: String(nextId++), file: file, status: 'Aguardando processamento', converting: false, progress: null, page: 0, totalPages: 0, markdown: null, markdownBlob: null, jsonBlob: null, pages: [], occurrences: [], report: null, pdfUrl: null, engineText: '', processingStage: '' });
            added += 1;
        });
        renderList();
        if (oversized) say(oversized + ' arquivo(s) excedem o limite de 20 MB.');
        else if (!added && files.length) say('Nenhum PDF novo foi adicionado.');
        else if (added) processQueue();
    }

    function withOccurrence(result, occurrence) {
        return window.App.PdfIntelligenceCore.createPageResult({
            pageNumber: result.pageNumber,
            engine: result.engine,
            engineVersion: result.engineVersion,
            durationMs: result.durationMs,
            blocks: result.blocks,
            occurrences: result.occurrences.concat([occurrence])
        });
    }

    function checkCancelled() { if (cancelled || (activeController && activeController.signal.aborted)) { var error = new Error('cancelado'); error.name = 'AbortError'; throw error; } }

    async function extractPage(pdfPage, pageNumber, selectedMode) {
        checkCancelled();
        var nativeResult = await window.App.PdfIntelligencePdfJs.extractNativePage(pdfPage, pageNumber, window.pdfjsLib);
        checkCancelled();
        if (selectedMode === 'fast') return nativeResult;
        if (selectedMode === 'hybrid') {
            var nativeText = nativeResult.blocks.map(function (block) { return block.text; }).join(' ');
            var classification = window.App.PdfIntelligenceCore.classifyPage({ pageNumber: pageNumber, text: nativeText });
            if (classification.route === 'native-text') return nativeResult;
        }
        try {
            return await activeOcr.recognizePage(pdfPage, pageNumber, {
                signal: activeController.signal,
                onProgress: function (progress) {
                    var item = state.find(function (candidate) { return candidate.converting; });
                    if (item) { item.processingStage = String(progress.stage || 'OCR local'); renderList(); }
                }
            });
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            return withOccurrence(nativeResult, {
                code: 'ocr-failed-native-fallback',
                severity: 'warning',
                message: 'OCR falhou nesta página; foi mantido apenas o texto nativo disponível. Revise a página original.'
            });
        }
    }

    async function processItem(item, selectedMode) {
        if (!window.pdfjsLib || !window.App || !window.App.PdfIntelligenceCore) throw new Error('Bibliotecas locais de processamento indisponíveis.');
        if (selectedMode === 'oxr') throw new Error('A instância privada OXR não está configurada.');
        if (selectedMode !== 'fast' && (!window.Tesseract || !window.App.PdfIntelligenceTesseract)) throw new Error('Assets locais de OCR indisponíveis.');
        var started = Date.now();
        activeController = new AbortController();
        var buffer = await item.file.arrayBuffer();
        checkCancelled();
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        activeLoadingTask = window.pdfjsLib.getDocument({ data: buffer });
        var loadingTask = activeLoadingTask;
        var pdf;
        try {
            pdf = await loadingTask.promise;
            item.totalPages = pdf.numPages;
            item.pdfUrl = URL.createObjectURL(item.file);
            if (selectedMode !== 'fast') activeOcr = window.App.PdfIntelligenceTesseract.createAdapter();
            for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                checkCancelled();
                item.page = pageNumber;
                var page = await pdf.getPage(pageNumber);
                var pageResult;
                try { pageResult = await extractPage(page, pageNumber, selectedMode); }
                finally { if (page && typeof page.cleanup === 'function') page.cleanup(); }
                item.pages.push(pageResult);
                item.occurrences = item.occurrences.concat(pageResult.occurrences);
                item.engineText = Array.from(new Set(item.pages.map(function (entry) { return entry.engine; })).values()).join(', ');
                item.progress = Math.round(pageNumber * 100 / pdf.numPages);
                var total = Math.max(1, state.length);
                el('pdfi-progress').value = Math.round(((item.progress / 100 + state.filter(function (entry) { return entry.markdownBlob; }).length) / total) * 100);
                el('pdfi-progress-label').textContent = el('pdfi-progress').value + '%';
                renderList();
                await new Promise(function (resolve) { window.setTimeout(resolve, 0); });
            }
            checkCancelled();
            item.markdown = window.App.PdfIntelligenceCore.normalizeMarkdown({ title: item.file.name.replace(/\.pdf$/i, ''), pages: item.pages });
            item.report = window.App.PdfIntelligenceCore.createQualityReport({ pageCount: pdf.numPages, pages: item.pages, occurrences: item.occurrences, durationMs: Date.now() - started });
            item.markdownBlob = new Blob([item.markdown], { type: 'text/markdown;charset=utf-8' });
            item.structured = {
                schemaVersion: '1.0',
                source: { name: item.file.name, sizeBytes: item.file.size, pageCount: pdf.numPages },
                processedAt: new Date().toISOString(),
                mode: selectedMode,
                pages: item.pages,
                occurrences: item.occurrences,
                quality: item.report
            };
            item.jsonBlob = new Blob([JSON.stringify(item.structured, null, 2)], { type: 'application/json;charset=utf-8' });
            item.status = 'Concluído · ' + item.report.pagesWithText + '/' + item.report.pageCount + ' páginas com texto · ' + item.report.warningCount + ' alerta(s)';
            item.processingStage = '';
        } finally {
            if (activeOcr) { await activeOcr.terminate(); activeOcr = null; }
            if (loadingTask && activeLoadingTask === loadingTask) {
                try { await loadingTask.destroy(); } catch (error) { /* tarefa já encerrada */ }
                activeLoadingTask = null;
            }
        }
    }

    async function processQueue() {
        if (running) return;
        running = true;
        cancelled = false;
        el('pdfi-overall').hidden = false;
        el('pdfi-cancel').hidden = false;
        var selectedMode = mode();
        while (!cancelled) {
            var item = state.find(function (candidate) { return candidate.status === 'Aguardando processamento'; });
            if (!item) break;
            item.converting = true;
            item.progress = 0;
            item.page = 0;
            item.status = 'Preparando';
            renderList();
            try {
                item.status = selectedMode === 'fast' ? 'Extraindo texto nativo' : selectedMode === 'ocr' ? 'Executando OCR local' : 'Classificando e processando páginas';
                await processItem(item, selectedMode);
                showResult(item);
                say('Processamento concluído. Confira alertas e compare o resultado com o original.');
            } catch (error) {
                item.status = error.name === 'AbortError' ? 'Processamento cancelado' : 'Falha · confira o PDF e os recursos locais';
                if (error.name !== 'AbortError') say('Não foi possível processar o PDF. Confira os assets e tente novamente.');
            }
            item.converting = false;
            item.progress = item.markdownBlob ? 100 : null;
            renderList();
        }
        if (activeOcr) { await activeOcr.terminate(); activeOcr = null; }
        activeController = null;
        activeLoadingTask = null;
        running = false;
        el('pdfi-cancel').hidden = true;
        el('pdfi-overall').hidden = true;
        if (cancelled) say('Processamento cancelado.');
    }

    async function downloadZip() {
        if (!window.JSZip) { say('Biblioteca ZIP indisponível.'); return; }
        var converted = state.filter(function (item) { return item.markdownBlob; });
        if (!converted.length) return;
        var zip = new window.JSZip();
        converted.forEach(function (item) { zip.file(fileStem(item.file.name) + '.md', item.markdown); });
        download('pdf-intelligence-markdown.zip', await zip.generateAsync({ type: 'blob' }));
    }

    function removeItem(id) {
        var item = currentItem(id);
        if (!item || item.converting) return;
        if (item.pdfUrl) URL.revokeObjectURL(item.pdfUrl);
        state = state.filter(function (entry) { return entry.id !== id; });
        renderList();
    }

    function bind() {
        el('pdfi-dropzone').addEventListener('click', function () { el('pdfi-input').click(); });
        el('pdfi-input').addEventListener('change', function (event) { chooseFile(event.target.files); event.target.value = ''; });
        el('pdfi-dropzone').addEventListener('dragover', function (event) { event.preventDefault(); el('pdfi-dropzone').classList.add('is-dragover'); });
        el('pdfi-dropzone').addEventListener('dragleave', function () { el('pdfi-dropzone').classList.remove('is-dragover'); });
        el('pdfi-dropzone').addEventListener('drop', function (event) { event.preventDefault(); el('pdfi-dropzone').classList.remove('is-dragover'); chooseFile(event.dataTransfer.files); });
        el('pdfi-zip').addEventListener('click', downloadZip);
        el('pdfi-cancel').addEventListener('click', function () {
            cancelled = true;
            if (activeController) activeController.abort();
            if (activeLoadingTask) Promise.resolve(activeLoadingTask.destroy()).catch(function () {});
            if (activeOcr) activeOcr.terminate();
        });
        el('pdfi-list').addEventListener('click', function (event) {
            var button = event.target.closest('button[data-action]');
            if (!button) return;
            var item = currentItem(button.dataset.id);
            if (!item) return;
            if (button.dataset.action === 'preview') showResult(item);
            if (button.dataset.action === 'md' && item.markdownBlob) download(fileStem(item.file.name) + '.md', item.markdownBlob);
            if (button.dataset.action === 'json' && item.jsonBlob) download(fileStem(item.file.name) + '.json', item.jsonBlob);
            if (button.dataset.action === 'remove') removeItem(item.id);
        });
    }

    document.addEventListener('DOMContentLoaded', bind);
}());
