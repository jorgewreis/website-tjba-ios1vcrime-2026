(function () {
    'use strict';
    var form = document.getElementById('split-form');
    if (!form) return;
    var fileInput = document.getElementById('split-file');
    var sizeInput = document.getElementById('split-size');
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
        status.textContent = 'Selecione um PDF e informe o tamanho máximo para começar.';
    }
    fileInput.addEventListener('change', resetForInput);
    sizeInput.addEventListener('input', resetForInput);
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        var file = fileInput.files[0];
        if (!file || (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf')) {
            status.textContent = 'Selecione um arquivo PDF válido.';
            return;
        }
        if (!window.PDFLib || !window.JSZip) {
            status.textContent = 'Não foi possível carregar as bibliotecas. Verifique sua conexão e recarregue a página.';
            return;
        }
        var limit = Math.floor(Number(sizeInput.value) * 1024 * 1024);
        if (!Number.isFinite(limit) || limit < 1) { status.textContent = 'Informe um tamanho máximo válido.'; return; }
        clearResults();
        baseName = file.name.replace(/\.pdf$/i, '').replace(/[\\/:*?"<>|\x00-\x1f]/g, '_') || 'documento';
        submit.disabled = fileInput.disabled = sizeInput.disabled = true;
        progressWrap.hidden = false;
        progress.removeAttribute('value');
        progressText.textContent = 'Preparando…';
        status.textContent = 'Preparando o PDF para divisão.';
        try {
            var source = await PDFLib.PDFDocument.load(await file.arrayBuffer());
            var total = source.getPageCount();
            if (!total) throw new Error('O PDF não contém páginas.');
            var output = await PDFLib.PDFDocument.create();
            var first = 1, currentBytes = null;
            var generated = [];
            var announcedPercent = 0;
            for (var i = 0; i < total; i++) {
                var copied = await output.copyPages(source, [i]);
                output.addPage(copied[0]);
                var candidate = await output.save();
                if (candidate.length > limit) {
                    if (!currentBytes) throw new Error('A página ' + (i + 1) + ' sozinha excede o tamanho máximo. Aumente o limite e tente novamente.');
                    generated.push({bytes: currentBytes, first: first, last: i});
                    output = await PDFLib.PDFDocument.create();
                    copied = await output.copyPages(source, [i]);
                    output.addPage(copied[0]);
                    candidate = await output.save();
                    if (candidate.length > limit) throw new Error('A página ' + (i + 1) + ' sozinha excede o tamanho máximo. Aumente o limite e tente novamente.');
                    first = i + 1;
                }
                currentBytes = candidate;
                var percent = Math.round((i + 1) * 100 / total);
                progress.value = percent;
                progressText.textContent = percent + '%';
                if (percent >= announcedPercent + 25 && percent < 100) {
                    announcedPercent = Math.floor(percent / 25) * 25;
                    status.textContent = 'Divisão em andamento: ' + percent + '%.';
                }
                await new Promise(function (resolve) { requestAnimationFrame(resolve); });
            }
            generated.push({bytes: currentBytes, first: first, last: total});
            parts = generated;
            parts.forEach(addResult);
            downloadAll.disabled = false;
            status.textContent = parts.length + (parts.length === 1 ? ' parte gerada.' : ' partes geradas.') + ' Escolha como baixar.';
        } catch (error) {
            clearResults();
            status.textContent = error && /^A página |^O PDF não contém/.test(error.message) ? error.message : 'Não foi possível dividir o PDF. Verifique se o arquivo é válido e não está protegido por senha.';
        } finally {
            progressWrap.hidden = true;
            submit.disabled = fileInput.disabled = sizeInput.disabled = false;
        }
    });
    downloadAll.addEventListener('click', async function () {
        if (!parts.length) return;
        downloadAll.disabled = true;
        submit.disabled = fileInput.disabled = sizeInput.disabled = true;
        try {
            var zip = new JSZip();
            parts.forEach(function (part, i) { zip.file(partName(i), part.bytes); });
            saveBlob(await zip.generateAsync({type: 'blob'}), baseName + '-partes.zip');
            status.textContent = 'Download do ZIP iniciado.';
        } catch (error) {
            status.textContent = 'Não foi possível gerar o ZIP. Baixe as partes individualmente.';
        } finally {
            downloadAll.disabled = false;
            submit.disabled = fileInput.disabled = sizeInput.disabled = false;
        }
    });
})();
