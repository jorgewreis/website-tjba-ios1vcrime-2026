(function () {
    'use strict';
    var form = document.getElementById('split-form');
    if (!form) return;
    var fileInput = document.getElementById('split-file');
    var sizeInput = document.getElementById('split-size');
    var mode = document.getElementById('split-mode');
    var countInput = document.getElementById('split-count');
    var rangesInput = document.getElementById('split-ranges');
    var cancelButton = document.getElementById('split-cancel');
    var summary = document.getElementById('split-summary');
    var cancelRequested = false;
    var submit = document.getElementById('split-submit');
    var downloadAll = document.getElementById('split-download-all');
    var status = document.getElementById('split-status');
    var resultList = document.getElementById('split-results');
    var progressWrap = document.getElementById('split-progress-wrap');
    var progress = document.getElementById('split-progress');
    var progressText = document.getElementById('split-progress-text');
    var parts = [];
    var baseName = '';

    function formatBytes(bytes) {
        return bytes >= 1024 * 1024 ? (bytes / 1024 / 1024).toFixed(2) + ' MB' : (bytes / 1024).toFixed(1) + ' KB';
    }
    function saveBlob(blob, name) {
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }
    function partName(index) { return baseName + '-parte-' + String(index + 1).padStart(3, '0') + '.pdf'; }
    function clearResults() {
        parts = [];
        summary.hidden = true;
        summary.textContent = '';
        resultList.replaceChildren();
        downloadAll.disabled = true;
    }
    function addResult(part, index) {
        var row = document.createElement('div');
        row.className = 'skills-pdf-item';
        var info = document.createElement('div');
        info.className = 'skills-pdf-meta';
        var name = document.createElement('div');
        name.className = 'skills-pdf-name';
        name.textContent = partName(index);
        var details = document.createElement('div');
        details.className = 'skills-pdf-status';
        details.textContent = 'Páginas ' + part.first + '–' + part.last + ' · ' + formatBytes(part.bytes.length);
        info.append(name, details);
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary-outline height-sm';
        button.textContent = 'Baixar PDF';
        button.setAttribute('aria-label', 'Baixar PDF ' + partName(index));
        button.addEventListener('click', function () { saveBlob(new Blob([part.bytes], {type: 'application/pdf'}), partName(index)); });
        row.append(info, button);
        resultList.appendChild(row);
    }
    function resetForInput() {
        clearResults();
        status.textContent = 'Selecione um PDF e configure a divisão para começar.';
    }
    function syncMode() {
        ['size', 'count', 'ranges'].forEach(function (name) {
            var input = document.getElementById('split-' + name);
            var active = mode.value === name;
            document.getElementById('split-' + name + '-wrap').hidden = !active;
            input.disabled = !active;
            input.required = active;
        });
        resetForInput();
    }
    function checkCancel() { if (cancelRequested) { var e = new Error('Divisão cancelada.'); e.name = 'CancelledError'; throw e; } }
    function yieldUi() { return new Promise(function (resolve) { setTimeout(resolve, 0); }); }
    function parseRanges(value, total) {
        var pieces = value.split(',');
        var ranges = [], previous = 0;
        pieces.forEach(function (piece) {
            var match = piece.trim().match(/^(\d+)\s*(?:[-–]\s*(\d+))?$/);
            if (!match) throw new Error('Informe páginas ou intervalos válidos, separados por vírgulas. Ex.: 1-10, 11-20.');
            var first = Number(match[1]), last = Number(match[2] || match[1]);
            if (!Number.isSafeInteger(first) || !Number.isSafeInteger(last) || first < 1 || last < first || last > total)
                throw new Error('Os intervalos devem conter páginas de 1 a ' + total + ', com início menor ou igual ao fim.');
            if (first <= previous) throw new Error('Informe os intervalos em ordem crescente, sem sobreposição.');
            ranges.push({first:first, last:last}); previous = last;
        });
        return ranges;
    }
    async function makePart(source, first, last) {
        checkCancel();
        var doc = await PDFLib.PDFDocument.create();
        var indices = Array.from({length:last - first + 1}, function (_, i) { return first - 1 + i; });
        var pages = await doc.copyPages(source, indices);
        checkCancel();
        pages.forEach(function (page) { doc.addPage(page); });
        var bytes = await doc.save();
        checkCancel();
        return {first:first, last:last, bytes:bytes};
    }
    async function findSizePart(source, first, total, limit) {
        var best = await makePart(source, first, first);
        if (best.bytes.length > limit) {
            var mb = Math.ceil(best.bytes.length / (1024 * 1024) * 10) / 10;
            var guidance = mb <= 500 ? 'Aumente o limite para pelo menos ' + mb.toFixed(1) + ' MB ou compacte o PDF antes de tentar novamente.' : 'O tamanho excede o limite máximo permitido pela ferramenta; compacte o PDF antes de tentar novamente.';
            throw new Error('A página ' + first + ' sozinha ocupa ' + formatBytes(best.bytes.length) + '. ' + guidance);
        }
        var span = 2, upper = first + 1;
        while (upper <= total) {
            await yieldUi(); checkCancel();
            var candidate = await makePart(source, first, upper);
            if (candidate.bytes.length > limit) break;
            best = candidate;
            span *= 2;
            upper = Math.min(total + 1, first + span - 1);
        }
        if (upper > total && best.last < total) {
            var tail = await makePart(source, first, total);
            if (tail.bytes.length <= limit) return tail;
            upper = total;
        }
        var low = best.last + 1, high = Math.min(upper - 1, total);
        while (low <= high) {
            await yieldUi(); checkCancel();
            var middle = Math.floor((low + high) / 2);
            var probe = await makePart(source, first, middle);
            if (probe.bytes.length <= limit) { best = probe; low = middle + 1; }
            else high = middle - 1;
        }
        return best;
    }
    function lockForm(locked) {
        submit.disabled = fileInput.disabled = mode.disabled = locked;
        ['size', 'count', 'ranges'].forEach(function (name) {
            document.getElementById('split-' + name).disabled = locked || mode.value !== name;
        });
    }
    fileInput.addEventListener('change', resetForInput);
    [sizeInput, countInput, rangesInput].forEach(function (input) { input.addEventListener('input', resetForInput); });
    mode.addEventListener('change', syncMode);
    cancelButton.addEventListener('click', function () { cancelRequested = true; status.textContent = 'Cancelando divisão…'; });
    syncMode();
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        var file = fileInput.files[0];
        if (!file || (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf')) { status.textContent = 'Selecione um arquivo PDF válido.'; return; }
        if (!window.PDFLib) { status.textContent = 'A biblioteca de PDF não carregou. Verifique sua conexão e atualize a página.'; return; }
        var limit = Math.floor(Number(sizeInput.value) * 1024 * 1024);
        if (mode.value === 'size' && (!Number.isFinite(limit) || limit < 1)) { status.textContent = 'Informe um tamanho máximo válido.'; return; }
        clearResults(); cancelRequested = false;
        baseName = file.name.replace(/\.pdf$/i, '').replace(/[\\/:*?"<>|\x00-\x1f]/g, '_') || 'documento';
        lockForm(true); cancelButton.hidden = false;
        progressWrap.hidden = false; progress.removeAttribute('value'); progressText.textContent = 'Preparando…';
        status.textContent = 'Preparando o PDF para divisão.';
        try {
            await yieldUi(); checkCancel();
            var data = await file.arrayBuffer(); checkCancel();
            var source = await PDFLib.PDFDocument.load(data); checkCancel();
            var total = source.getPageCount();
            if (!total) throw new Error('O PDF não contém páginas.');
            var ranges = [];
            if (mode.value === 'ranges') ranges = parseRanges(rangesInput.value, total);
            if (mode.value === 'count') {
                var count = Number(countInput.value);
                if (!Number.isSafeInteger(count) || count < 1) throw new Error('Informe uma quantidade inteira de páginas maior que zero.');
                for (var p = 1; p <= total; p += count) ranges.push({first:p, last:Math.min(total, p + count - 1)});
            }
            var generated = [], processed = 0, next = 1, announcedPercent = 0;
            var target = mode.value === 'ranges' ? ranges.reduce(function (sum, r) { return sum + r.last - r.first + 1; }, 0) : total;
            while (mode.value === 'size' ? next <= total : generated.length < ranges.length) {
                await yieldUi(); checkCancel();
                var part;
                if (mode.value === 'size') part = await findSizePart(source, next, total, limit);
                else { var range = ranges[generated.length]; part = await makePart(source, range.first, range.last); }
                generated.push(part); next = part.last + 1; processed += part.last - part.first + 1;
                var percent = Math.round(processed * 100 / target);
                progress.value = percent; progressText.textContent = percent + '%';
                if (percent >= announcedPercent + 25 && percent < 100) { announcedPercent = Math.floor(percent / 25) * 25; status.textContent = 'Divisão em andamento: ' + percent + '%.'; }
            }
            checkCancel(); parts = generated; parts.forEach(addResult);
            summary.hidden = false;
            summary.textContent = 'PDF original: ' + total + (total === 1 ? ' página' : ' páginas') + ' · Selecionadas: ' + processed + ' · ' + parts.length + (parts.length === 1 ? ' parte.' : ' partes.');
            downloadAll.disabled = !window.JSZip;
            status.textContent = parts.length + (parts.length === 1 ? ' parte gerada.' : ' partes geradas.') + (window.JSZip ? ' Escolha como baixar.' : ' Baixe individualmente; a biblioteca ZIP não carregou.');
        } catch (error) {
            clearResults();
            if (cancelRequested || error.name === 'CancelledError') status.textContent = 'Divisão cancelada. Nenhuma parte foi gerada.';
            else if (/encrypted|encrypt|password/i.test(error.message || '')) status.textContent = 'PDF protegido por senha: não é possível dividi-lo nesta ferramenta.';
            else if (/^A página |^O PDF não contém|^Informe |^Os intervalos /.test(error.message || '')) status.textContent = error.message;
            else status.textContent = 'Não foi possível ler o PDF. Verifique se o arquivo é válido e está completo.';
        } finally {
            var restoreFocus = document.activeElement === cancelButton;
            progressWrap.hidden = true; cancelButton.hidden = true; lockForm(false);
            if (restoreFocus) submit.focus();
        }
    });
    downloadAll.addEventListener('click', async function () {
        if (!parts.length) return;
        downloadAll.disabled = true;
        lockForm(true);
        try {
            var zip = new JSZip();
            parts.forEach(function (part, i) { zip.file(partName(i), part.bytes); });
            saveBlob(await zip.generateAsync({type: 'blob'}), baseName + '-partes.zip');
            status.textContent = 'Download do ZIP iniciado.';
        } catch (error) {
            status.textContent = 'Não foi possível gerar o ZIP. Baixe as partes individualmente.';
        } finally {
            downloadAll.disabled = false;
            lockForm(false);
        }
    });
})();
