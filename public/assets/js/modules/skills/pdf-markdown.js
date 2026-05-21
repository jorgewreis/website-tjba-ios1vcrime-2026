(function () {
    'use strict';

    var filesState = [];

    function notify(type, title, message) {
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

        counter.textContent = filesState.length + ' arquivo(s)';
        if (filesState.length === 0) {
            list.innerHTML = '';
            return;
        }

        list.innerHTML = filesState.map(function (item) {
            var downloadDisabled = item.markdownBlob ? '' : ' disabled';
            return (
                '<div class="skills-pdf-item" data-key="' + item.key + '">' +
                    '<div class="skills-pdf-meta">' +
                        '<div class="skills-pdf-name">' + escapeHtml(item.file.name) + '</div>' +
                        '<div class="skills-pdf-status">' + escapeHtml(item.statusText) + ' - ' + escapeHtml(formatBytes(item.file.size)) + '</div>' +
                    '</div>' +
                    '<div class="skills-pdf-actions">' +
                        '<button type="button" class="btn btn-secondary-outline height-sm btn-file-download"' + downloadDisabled + '>Baixar .md</button>' +
                        '<button type="button" class="btn btn-danger-outline height-sm btn-file-remove">Remover</button>' +
                    '</div>' +
                '</div>'
            );
        }).join('');
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
        incoming.forEach(function (file) {
            if (!file || String(file.type || '').toLowerCase() !== 'application/pdf') return;
            var key = buildFileKey(file);
            if (existingKeys[key]) return;
            existingKeys[key] = true;
            filesState.push({
                key: key,
                file: file,
                statusText: 'Aguardando conversão',
                markdownBlob: null
            });
            added += 1;
        });

        if (added > 0) {
            notify('success', 'Arquivos adicionados', added + ' PDF(s) pronto(s) para conversão.');
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

    async function extractMarkdownFromPdf(file) {
        if (!window.pdfjsLib) {
            throw new Error('Biblioteca de PDF indisponível.');
        }
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        var data = await file.arrayBuffer();
        var pdf = await window.pdfjsLib.getDocument({ data: data }).promise;
        var output = ['# ' + file.name, ''];

        for (var pageNum = 1; pageNum <= pdf.numPages; pageNum += 1) {
            var page = await pdf.getPage(pageNum);
            var content = await page.getTextContent();
            var lines = content.items.map(function (it) {
                return String(it.str || '').trim();
            }).filter(Boolean);
            output.push('## Página ' + pageNum);
            output.push(lines.join(' '));
            output.push('');
        }

        return output.join('\n').trim() + '\n';
    }

    function downloadBlob(filename, blob) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    async function processAll() {
        if (filesState.length === 0) {
            notify('info', 'Sem arquivos', 'Adicione pelo menos um PDF para converter.');
            return;
        }

        for (var i = 0; i < filesState.length; i += 1) {
            var entry = filesState[i];
            try {
                updateStatus(entry.key, 'Convertendo...');
                var markdown = await extractMarkdownFromPdf(entry.file);
                entry.markdownBlob = new Blob([markdown], { type: 'text/markdown;charset=utf-8' });
                entry.statusText = 'Convertido com sucesso';
            } catch (error) {
                entry.markdownBlob = null;
                entry.statusText = 'Falha na conversão';
            }
            renderList();
        }

        notify('success', 'Conversão finalizada', 'Processamento concluído.');
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
        ready.forEach(function (entry) {
            zip.file(normalizeFileName(entry.file.name) + '.md', entry.markdownBlob);
        });
        var blob = await zip.generateAsync({ type: 'blob' });
        downloadBlob('pdf-markdown.zip', blob);
    }

    function bindDropzone() {
        var input = getById('skills-pdf-input');
        var dropzone = getById('skills-pdf-dropzone');
        var btnAdd = getById('btn-skills-pdf-add');
        var btnProcess = getById('btn-skills-pdf-process');
        var btnZip = getById('btn-skills-pdf-zip');
        var list = getById('skills-pdf-list');

        if (!input || !dropzone || !btnAdd || !btnProcess || !btnZip || !list) return;

        btnAdd.addEventListener('click', function () { input.click(); });
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

        btnProcess.addEventListener('click', processAll);
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
