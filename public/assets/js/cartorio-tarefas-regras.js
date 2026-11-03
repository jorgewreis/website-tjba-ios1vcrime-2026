(function () {
    'use strict';
    const dados = JSON.parse(document.getElementById('tarefas-regras-dados').textContent);
    const form = document.getElementById('regra-form');
    const gruposEl = document.getElementById('regra-grupos');
    const acoesEl = document.getElementById('regra-acoes');
    const estado = dados.regra || { condicoes: [{}], acoes: [] };
    estado.condicoes = Array.isArray(estado.condicoes) && estado.condicoes.length ? estado.condicoes : [{}];
    estado.acoes = Array.isArray(estado.acoes) ? estado.acoes : [];
    const campos = [
        ['tipo', 'Tipo de tarefa', dados.tipos],
        ['tipo_documento', 'Tipo de documento', dados.documentos],
        ['especificacao_documento', 'Especificação do documento', dados.especificacoes],
        ['local_tarefa', 'Local', dados.locais]
    ];
    const tiposAcao = { alocar: 'Alocar usuário', criar_tarefa: 'Criar tarefa em sequência', alterar_status: 'Alterar status', definir_prazo: 'Definir prazo' };
    const inicios = { criacao: 'Criação da tarefa', andamento: 'Primeira entrada em andamento', alocacao: 'Alocação mais recente', finalizacao_anterior: 'Finalização da tarefa anterior' };

    function elemento(tag, classe, texto) {
        const item = document.createElement(tag);
        if (classe) item.className = classe;
        if (texto !== undefined) item.textContent = texto;
        return item;
    }
    function opcoes(select, valores, inicial, vazio) {
        if (vazio !== undefined) select.add(new Option(vazio, ''));
        Object.entries(valores).forEach(([valor, rotulo]) => select.add(new Option(rotulo, valor)));
        if (inicial !== undefined && inicial !== null) select.value = String(inicial);
        if (inicial && select.value !== String(inicial)) {
            const ausente = new Option(String(inicial) + ' (indisponível)', String(inicial));
            ausente.disabled = true;
            select.add(ausente);
            select.value = String(inicial);
        }
    }
    function campoSelect(pai, rotulo, valores, inicial, aoMudar, vazio) {
        const label = elemento('label', 'automacao-campo');
        label.append(elemento('span', '', rotulo));
        const select = elemento('select', 'height-md');
        opcoes(select, valores, inicial, vazio);
        select.addEventListener('change', () => aoMudar(select.value));
        label.append(select);
        pai.append(label);
        return select;
    }
    function campoNumero(pai, rotulo, valor, minimo, aoMudar) {
        const label = elemento('label', 'automacao-campo');
        label.append(elemento('span', '', rotulo));
        const input = elemento('input', 'height-md');
        input.type = 'number'; input.min = String(minimo); input.max = '36500'; input.step = '1'; input.required = true;
        input.value = String(valor === undefined ? minimo : valor);
        input.addEventListener('input', () => aoMudar(input.value));
        label.append(input); pai.append(label);
    }
    function botao(rotulo, acao) {
        const button = elemento('button', 'btn btn-secondary', rotulo);
        button.type = 'button'; button.addEventListener('click', acao); return button;
    }
    function desenharGrupos() {
        gruposEl.replaceChildren();
        estado.condicoes.forEach((grupo, indice) => {
            const box = elemento('fieldset', 'automacao-bloco');
            box.append(elemento('legend', '', 'Grupo ' + (indice + 1)));
            box.append(elemento('p', 'automacao-ajuda', 'Dentro do grupo, todos os campos escolhidos devem corresponder (E).'));
            const linha = elemento('div', 'automacao-campos');
            campos.forEach(([campo, rotulo, valores]) => campoSelect(linha, rotulo, valores, grupo[campo] || '', valor => {
                if (valor) grupo[campo] = valor; else delete grupo[campo];
            }, 'Qualquer'));
            box.append(linha);
            if (estado.condicoes.length > 1) box.append(botao('Remover grupo', () => { estado.condicoes.splice(indice, 1); desenharGrupos(); }));
            gruposEl.append(box);
            if (indice < estado.condicoes.length - 1) gruposEl.append(elemento('p', 'automacao-ou', 'OU'));
        });
    }
    function desenharAcoes() {
        acoesEl.replaceChildren();
        estado.acoes.forEach((acao, indice) => {
            const box = elemento('fieldset', 'automacao-bloco');
            box.append(elemento('legend', '', 'Ação ' + (indice + 1)));
            const linha = elemento('div', 'automacao-campos');
            campoSelect(linha, 'Ação', tiposAcao, acao.tipo, valor => { acao.tipo = valor; desenharAcoes(); });
            if (acao.tipo !== 'criar_tarefa') {
                const alvos = { original: 'Tarefa que acionou a regra' };
                estado.acoes.slice(0, indice).forEach((anterior, posicao) => {
                    if (anterior.tipo === 'criar_tarefa') alvos[anterior.id] = 'Tarefa criada na ação ' + (posicao + 1);
                });
                campoSelect(linha, 'Aplicar à', alvos, acao.alvo || 'original', valor => { acao.alvo = valor; });
            }
            if (acao.tipo === 'alocar') campoSelect(linha, 'Usuário', dados.usuarios, acao.usuario_id, valor => { acao.usuario_id = valor; }, 'Selecione');
            if (acao.tipo === 'criar_tarefa') {
                acao.alvo = 'original';
                campoSelect(linha, 'Tipo de tarefa', dados.tipos, acao.tipo_tarefa, valor => { acao.tipo_tarefa = valor; }, 'Selecione');
                campoSelect(linha, 'Tipo de documento', dados.documentos, acao.tipo_documento, valor => { acao.tipo_documento = valor; }, 'Selecione');
                campoSelect(linha, 'Especificação', dados.especificacoes, acao.especificacao_documento || '', valor => { acao.especificacao_documento = valor; }, 'Sem especificação');
                campoSelect(linha, 'Local', dados.locais, acao.local_tarefa, valor => { acao.local_tarefa = valor; }, 'Selecione');
            }
            if (acao.tipo === 'alterar_status') campoSelect(linha, 'Novo status', dados.status, acao.status, valor => { acao.status = valor; }, 'Selecione');
            if (acao.tipo === 'definir_prazo') {
                if (acao.dias === undefined) acao.dias = 1;
                if (acao.alerta_dias === undefined) acao.alerta_dias = 0;
                campoSelect(linha, 'Iniciar contagem em', inicios, acao.inicio, valor => { acao.inicio = valor; }, 'Selecione');
                campoNumero(linha, 'Prazo em dias corridos', acao.dias, 1, valor => { acao.dias = valor; });
                campoNumero(linha, 'Avisar com antecedência de dias', acao.alerta_dias, 0, valor => { acao.alerta_dias = valor; });
            }
            box.append(linha);
            const controles = elemento('div', 'automacao-controles');
            if (indice > 0) controles.append(botao('Subir', () => { [estado.acoes[indice - 1], estado.acoes[indice]] = [estado.acoes[indice], estado.acoes[indice - 1]]; desenharAcoes(); }));
            if (indice < estado.acoes.length - 1) controles.append(botao('Descer', () => { [estado.acoes[indice + 1], estado.acoes[indice]] = [estado.acoes[indice], estado.acoes[indice + 1]]; desenharAcoes(); }));
            controles.append(botao('Remover ação', () => { estado.acoes.splice(indice, 1); desenharAcoes(); }));
            box.append(controles); acoesEl.append(box);
        });
    }
    document.getElementById('adicionar-grupo').addEventListener('click', () => { estado.condicoes.push({}); desenharGrupos(); });
    document.getElementById('adicionar-acao').addEventListener('click', () => {
        estado.acoes.push({ id: 'a' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10), tipo: 'alocar', alvo: 'original' });
        desenharAcoes();
    });
    const evento = document.getElementById('regra-evento');
    const eventoStatus = document.getElementById('regra-evento-status');
    function atualizarEvento() { eventoStatus.parentElement.hidden = evento.value !== 'status_vinculada'; eventoStatus.required = evento.value === 'status_vinculada'; }
    evento.addEventListener('change', atualizarEvento);
    form.addEventListener('submit', event => {
        const acoes = estado.acoes.map(acao => {
            const copia = { ...acao };
            if (copia.tipo === 'definir_prazo') { copia.dias = Number(copia.dias); copia.alerta_dias = Number(copia.alerta_dias); }
            return copia;
        });
        if (!acoes.length) { event.preventDefault(); alert('Adicione ao menos uma ação.'); return; }
        document.getElementById('regra-json').value = JSON.stringify({
            nome: document.getElementById('regra-nome').value.trim(), evento: evento.value,
            evento_status: evento.value === 'status_vinculada' ? eventoStatus.value : null,
            prioridade: Number(document.getElementById('regra-prioridade').value),
            ativa: document.getElementById('regra-ativa').checked, condicoes: estado.condicoes, acoes
        });
    });
    desenharGrupos(); desenharAcoes(); atualizarEvento();
}());
