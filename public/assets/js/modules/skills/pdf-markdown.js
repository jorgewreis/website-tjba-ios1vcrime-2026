(function () {
    'use strict';

    var filesState = [];
    var processing = false;
    var cancelRequested = false;
    var activeTask = null;
    var maxPdfBytes = 20 * 1024 * 1024;

    function notify(type, title, message) {
        var announcement = getById('skills-pdf-announcement');
        if (announcement) announcement.textContent = title + ': ' + message;
        if (typeof showToast === 'function') {
            showToast({ type: type, title: title, message: message });
            return;
        }
        console[type === 'error' ? 'error' : 'log'](title + ': ' + message);
    }

    function normalizeFileName(name) {
        return String(name || '')
            .replace(/\.[^.]+$/, '')
            .replace(/[^\w\- ]+/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .toLowerCase() || 'arquivo';
    }

    function formatBytes(bytes) {
        var value = Number(bytes || 0);
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        return (value / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function buildFileKey(file) {
        return [file.name, file.size, file.lastModified].join('::');
    }

    function getById(id) {
        return document.getElementById(id);
    }

    function renderList() {
        var list = getById('skills-pdf-list');
        var counter = getById('skills-pdf-counter');
        if (!list || !counter) return;

        counter.textContent = filesState.length + (filesState.length === 1 ? ' arquivo' : ' arquivos');
        var zipButton = getById('btn-skills-pdf-zip');
        if (zipButton) zipButton.disabled = !filesState.some(function (item) { return !!item.markdownBlob; });
        if (filesState.length === 0) {
            var hadFocus = list.contains(document.activeElement);
            list.innerHTML = '<p class="skills-pdf-empty">Os arquivos adicionados aparecerão aqui.</p>';
            if (hadFocus) getById('skills-pdf-dropzone').focus();
            return;
        }

        var focused = document.activeElement;
        var focusedRow = focused && focused.closest('.skills-pdf-item');
        var focusedKey = focusedRow && focusedRow.getAttribute('data-key');
        var focusedClass = focused && focused.classList.contains('btn-file-download') ? 'btn-file-download' : 'btn-file-remove';
        list.innerHTML = filesState.map(function (item) {
            var downloadDisabled = item.markdownBlob ? '' : ' disabled';
            return (
                '<div class="skills-pdf-item" data-key="' + escapeHtml(item.key) + '">' +
                    '<div class="skills-pdf-meta">' +
                        '<div class="skills-pdf-name">' + escapeHtml(item.file.name) + '</div>' +
                        '<div class="skills-pdf-status">' + escapeHtml(item.statusText) + ' · ' + escapeHtml(formatBytes(item.file.size)) + '</div>' +
                        (item.converting ? '<div class="skills-progress"><progress max="100"' + (item.progress === null ? '' : ' value="' + item.progress + '"') + ' aria-label="Progresso da conversão de ' + escapeHtml(item.file.name) + '"></progress><span>' + (item.progress === null ? 'Preparando…' : item.progress + '%') + '</span></div>' : '') +
                    '</div>' +
                    '<div class="skills-pdf-actions">' +
                        '<button type="button" class="btn btn-secondary-outline height-sm btn-file-download" aria-label="Baixar ' + escapeHtml(item.file.name) + ' em Markdown"' + downloadDisabled + '>Baixar .md</button>' +
                        '<button type="button" class="btn btn-danger-outline height-sm btn-file-remove" aria-label="Remover ' + escapeHtml(item.file.name) + '">Remover</button>' +
                    '</div>' +
                '</div>'
            );
        }).join('');
        if (focusedKey) {
            var replacement = Array.prototype.find.call(list.children, function (row) { return row.getAttribute('data-key') === focusedKey; });
            if (replacement) {
                var button = replacement.querySelector('.' + focusedClass);
                if (button && !button.disabled) button.focus();
            } else {
                getById('skills-pdf-dropzone').focus();
            }
        }
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function addFiles(fileList) {
        var incoming = Array.prototype.slice.call(fileList || []);
        var existingKeys = {};
        filesState.forEach(function (entry) {
            existingKeys[entry.key] = true;
        });

        var added = 0;
        var oversized = [];
        incoming.forEach(function (file) {
            if (!file || String(file.type || '').toLowerCase() !== 'application/pdf' && !/\.pdf$/i.test(file.name)) return;
            if (file.size > maxPdfBytes) {
                oversized.push(file.name);
                return;
            }
            var key = buildFileKey(file);
            if (existingKeys[key]) return;
            existingKeys[key] = true;
            filesState.push({
                key: key,
                file: file,
                statusText: 'Aguardando conversão',
                markdownBlob: null,
                converting: false,
                progress: null
            });
            added += 1;
        });

        if (added > 0) {
            processAll();
        }
        if (oversized.length) {
            notify('error', 'PDF acima do limite', 'Cada PDF pode ter no máximo 20 MB. Não adicionado(s): ' + oversized.join(', ') + '.');
        } else if (added === 0 && incoming.length) {
            notify('info', 'Nenhum arquivo adicionado', 'Selecione PDFs que ainda não estejam na lista.');
        }
        renderList();
    }

    function updateStatus(key, text) {
        filesState = filesState.map(function (entry) {
            if (entry.key === key) {
                entry.statusText = text;
            }
            return entry;
        });
        renderList();
    }

    function cancelled() { if (cancelRequested) { var e = new Error('cancelado'); e.name = 'CancelledError'; throw e; } }
    function yieldUi() { return new Promise(function (resolve) { setTimeout(resolve, 0); }); }
    function pageText(items) {
        var lines = [], current = '', lastY = null, lastHeight = 0;
        items.forEach(function (item) {
            var text = String(item.str || '');
            var y = item.transform ? item.transform[5] : null;
            var h = Math.abs(item.height || (item.transform && item.transform[3]) || 10);
            if (!current && lines.length && y !== null && lastY !== null && Math.abs(y - lastY) > Math.max(lastHeight, h) * 1.7 && lines[lines.length - 1] !== '') lines.push('');
            if (current && y !== null && lastY !== null && Math.abs(y - lastY) > Math.max(2, h * 0.5)) {
                lines.push(current.trim());
                if (Math.abs(y - lastY) > Math.max(lastHeight, h) * 1.7) lines.push('');
                current = '';
            }
            if (text) {
                if (current && !/\s$/.test(current) && !/^\s/.test(text)) current += ' ';
                current += text;
            }
            if (item.hasEOL && current) { lines.push(current.trim()); current = ''; }
            if (y !== null) lastY = y;
            lastHeight = h;
        });
        if (current.trim()) lines.push(current.trim());
        return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim();
    }
    async function extractMarkdownFromPdf(file, onProgress) {
        if (!window.pdfjsLib) throw new Error('A biblioteca de PDF não carregou. Verifique sua conexão e atualize a página.');
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        var data = await file.arrayBuffer(); cancelled();
        activeTask = window.pdfjsLib.getDocument({ data: data });
        var loadingTask = activeTask;
        try {
            var pdf = await loadingTask.promise; cancelled();
            var output = ['# ' + file.name, ''], emptyPages = [];
            for (var pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
                cancelled();
                var page = await pdf.getPage(pageNum);
                var content = await page.getTextContent(); cancelled();
                var text = pageText(content.items);
                if (!text) emptyPages.push(pageNum);
                output.push('## Página ' + pageNum, '', text, '');
                onProgress(pageNum, pdf.numPages);
                await yieldUi();
            }
            return { text: output.join('\n').trim() + '\n', emptyPages: emptyPages };
        } finally {
            try { await loadingTask.destroy(); } finally { if (activeTask === loadingTask) activeTask = null; }
        }
    }
    function errorMessage(error) {
        if (!error) return 'Não foi possível ler o PDF.';
        if (error.name === 'PasswordException') return 'PDF protegido por senha; a conversão não é compatível com arquivos protegidos.';
        if (error.name === 'InvalidPDFException' || error.name === 'MissingPDFException') return 'O arquivo PDF está inválido ou incompleto.';
        if (/biblioteca de PDF/i.test(error.message || '')) return error.message;
        return 'Não foi possível ler o PDF. Verifique se o arquivo está íntegro e tente novamente.';
    }

    function downloadBlob(filename, blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    async function processAll() {
        if (processing) return;
        processing = true;
        cancelRequested = false;
        getById('btn-skills-pdf-cancel').hidden = false;
        var entry;
        while ((entry = filesState.find(function (item) { return item.statusText === 'Aguardando conversão'; }))) {
            try {
                entry.converting = true;
                entry.progress = null;
                updateStatus(entry.key, 'Convertendo…');
                getById('skills-pdf-announcement').textContent = 'Convertendo ' + entry.file.name + '.';
                var markdown = await extractMarkdownFromPdf(entry.file, function (page, total) {
                    if (filesState.indexOf(entry) === -1) return;
                    entry.progress = Math.round(page * 100 / total);
                    var row = Array.prototype.find.call(getById('skills-pdf-list').children, function (element) { return element.getAttribute('data-key') === entry.key; });
                    if (row) {
                        row.querySelector('progress').value = entry.progress;
                        row.querySelector('.skills-progress span').textContent = entry.progress + '%';
                    }
                });
                cancelled();
                if (filesState.indexOf(entry) === -1) continue;
                entry.markdownBlob = new Blob([markdown.text], { type: 'text/markdown;charset=utf-8' });
                entry.statusText = markdown.emptyPages.length ? 'Convertido · sem texto nas páginas ' + markdown.emptyPages.join(', ') + ' · download solicitado' : 'Convertido · download solicitado';
                getById('skills-pdf-announcement').textContent = entry.file.name + ': ' + entry.statusText + '.';
                downloadBlob(normalizeFileName(entry.file.name) + '.md', entry.markdownBlob);
            } catch (error) {
                entry.markdownBlob = null;
                entry.statusText = cancelRequested || error.name === 'CancelledError' ? 'Conversão cancelada' : errorMessage(error);
                if (cancelRequested) getById('skills-pdf-announcement').textContent = 'Conversões canceladas.';
                else notify('error', 'Falha na conversão de ' + entry.file.name, entry.statusText);
            }
            entry.converting = false;
            renderList();
            if (cancelRequested) {
                filesState.forEach(function (item) { if (item.statusText === 'Aguardando conversão') item.statusText = 'Conversão cancelada'; });
                renderList();
                break;
            }
        }
        activeTask = null;
        processing = false;
        var cancelButton = getById('btn-skills-pdf-cancel');
        var restoreFocus = document.activeElement === cancelButton;
        cancelButton.hidden = true;
        if (restoreFocus) getById('skills-pdf-dropzone').focus();
    }

    async function downloadZip() {
        if (!window.JSZip) {
            notify('error', 'Erro', 'Biblioteca de ZIP indisponível.');
            return;
        }
        var ready = filesState.filter(function (entry) {
            return !!entry.markdownBlob;
        });
        if (ready.length === 0) {
            notify('info', 'Sem arquivos convertidos', 'Converta pelo menos um PDF antes de baixar o ZIP.');
            return;
        }

        var zip = new window.JSZip();
        var names = new Set();
        ready.forEach(function (entry) {
            var base = normalizeFileName(entry.file.name), candidate = base + '.md', suffix = 2;
            while (names.has(candidate.toLowerCase())) { candidate = base + '-' + suffix++ + '.md'; }
            names.add(candidate.toLowerCase());
            zip.file(candidate, entry.markdownBlob);
        });
        var blob = await zip.generateAsync({ type: 'blob' });
        downloadBlob('pdf-markdown.zip', blob);
    }

    function bindDropzone() {
        var input = getById('skills-pdf-input');
        var dropzone = getById('skills-pdf-dropzone');
        var btnZip = getById('btn-skills-pdf-zip');
        var list = getById('skills-pdf-list');

        if (!input || !dropzone || !btnZip || !list) return;

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function (e) {
            addFiles(e.target.files);
            input.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzone.classList.add('is-drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-drag-over');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            addFiles(e.dataTransfer ? e.dataTransfer.files : []);
        });

        getById('btn-skills-pdf-cancel').addEventListener('click', function () {
            cancelRequested = true;
            getById('skills-pdf-announcement').textContent = 'Cancelando conversões…';
            if (activeTask) activeTask.destroy().catch(function () {});
        });

        btnZip.addEventListener('click', function () {
            downloadZip().catch(function () {
                notify('error', 'Erro', 'Falha ao gerar ZIP.');
            });
        });

        list.addEventListener('click', function (e) {
            var row = e.target.closest('.skills-pdf-item');
            if (!row) return;
            var key = row.getAttribute('data-key') || '';
            if (e.target.classList.contains('btn-file-remove')) {
                filesState = filesState.filter(function (item) { return item.key !== key; });
                renderList();
                return;
            }
            if (e.target.classList.contains('btn-file-download')) {
                var current = filesState.find(function (item) { return item.key === key; });
                if (!current || !current.markdownBlob) return;
                downloadBlob(normalizeFileName(current.file.name) + '.md', current.markdownBlob);
            }
        });

        renderList();
    }

    document.addEventListener('DOMContentLoaded', bindDropzone);
})();
