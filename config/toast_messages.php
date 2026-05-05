<?php
declare(strict_types=1);

/**
 * Fonte unica de verdade das mensagens de toast.
 * Estrutura:
 * - type: success|error|info
 * - title: titulo padrao
 * - message: template com placeholders {variavel}
 * - vars: variaveis esperadas pelo template
 */
return [
    'meta' => [
        'version' => '2026.04.28',
        'locale' => 'pt-BR',
    ],
    'messages' => [
        // =========================
        // CARTORIO
        // =========================

        // Cartorio - subtarefas
        'cartorio.subtask.conclude.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Subtarefa concluida com sucesso.',
            'vars' => [],
        ],
        'cartorio.subtask.delete.success' => [
            'type' => 'info',
            'title' => 'Subtarefa excluida',
            'message' => '{mensagem}',
            'vars' => ['mensagem'],
        ],
        'cartorio.task.main.message' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => '{mensagem}',
            'vars' => ['mensagem'],
        ],
        'cartorio.guias.detalhamento.cancel.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Subtarefa ocultada sem conclusão',
            'vars' => [],
        ],
        'cartorio.guias.first-subtask.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Subtarefa criada com sucesso',
            'vars' => [],
        ],
        'cartorio.guias.edit.save.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Alteracoes salvas.',
            'vars' => [],
        ],
        'cartorio.guias.edit.cancel.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Guia cancelada.',
            'vars' => [],
        ],
        'cartorio.ceapa.update.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Cadastro atualizado com sucesso.',
            'vars' => [],
        ],
        'cartorio.ceapa.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Cadastro realizado com sucesso.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.id.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Identificador do cadastro não informado.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.process.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe o número do processo.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.datajud.wait' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Aguarde a consulta ao DataJud terminar antes de cadastrar.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.datajud.wait.edit' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Aguarde a consulta ao DataJud terminar.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.reu.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe o nome do réu.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.tipo_suspensao.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe o tipo de suspensão.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.decisao.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe a decisão a cumprir.',
            'vars' => [],
        ],
        'cartorio.ceapa.validation.data_decisao.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe a data da decisão.',
            'vars' => [],
        ],
        'cartorio.ceapa.create.progress.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'Cadastrando...',
            'vars' => [],
        ],
        'cartorio.ceapa.edit.progress.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'Salvando alterações...',
            'vars' => [],
        ],
        'cartorio.ceapa.delete.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Cadastro excluído com sucesso.',
            'vars' => [],
        ],
        'cartorio.ceapa.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.',
            'vars' => [],
        ],
        'cartorio.allocation.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Alocacao removida.',
            'vars' => [],
        ],
        'cartorio.allocation.remove.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao remover alocacao.',
            'vars' => [],
        ],
        'cartorio.allocation.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Servidor alocado.',
            'vars' => [],
        ],
        'cartorio.allocation.add.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao alocar.',
            'vars' => [],
        ],
        'oficios.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente para confirmar a exclusão.',
            'vars' => [],
        ],
        'cartorio.editais.publication_date.required' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'A data da publicação é obrigatória para concluir esta subtarefa.',
            'vars' => [],
        ],
        'cartorio.medidas.quantity.required' => [
            'type' => 'error',
            'title' => 'Campo obrigatório',
            'message' => 'Informe a quantidade.',
            'vars' => [],
        ],
        'cartorio.medidas.cejusc.id.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Identificador da tarefa do Cejusc não encontrado.',
            'vars' => [],
        ],
        'cartorio.guias.validation.data_fato.window.error' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'A data do fato deve ser anterior aos últimos 10 dias.',
            'vars' => [],
        ],
        'cartorio.guias.validation.juntada.required' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'Selecione o tipo de juntada (automática ou manual).',
            'vars' => [],
        ],
        'cartorio.guias.validation.tipo_guia.required' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'Selecione o tipo da guia expedida.',
            'vars' => [],
        ],
        'cartorio.guias.validation.docs.min.error' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'Informe quantidade de documentos gerados.',
            'vars' => [],
        ],
        'cartorio.guias.validation.envio.required' => [
            'type' => 'error',
            'title' => 'Atenção',
            'message' => 'Selecione o tipo de envio e o destinatário.',
            'vars' => [],
        ],
        'cartorio.guias.validation.regime.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Regime inválido. Selecione Aberto, Semiaberto ou Fechado.',
            'vars' => [],
        ],
        'cartorio.guias.validation.data_fato.before.today' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'A data do fato deve ser anterior à data de hoje.',
            'vars' => [],
        ],
        'cartorio.guias.validation.dias_multa.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe quantidade de dias de multa.',
            'vars' => [],
        ],
        'cartorio.cartas.validation.required.fields' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Finalidade, cidade e estado são obrigatórios.',
            'vars' => [],
        ],
        'cartorio.cartas.validation.comarca.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Selecione uma cidade válida para que a comarca seja identificada.',
            'vars' => [],
        ],
        'cartorio.cartas.update.progress.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'Atualizando dados...',
            'vars' => [],
        ],

        // =========================
        // OFICIOS
        // =========================

        'oficios.cancel.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Ofício {numero_oficio} cancelado.',
            'vars' => ['numero_oficio'],
        ],
        'oficios.delete.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Ofício {numero_oficio} excluído.',
            'vars' => ['numero_oficio'],
        ],
        'oficios.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Ofício {numero_oficio} cadastrado.',
            'vars' => ['numero_oficio'],
        ],

        // =========================
        // GME
        // =========================
        'gme.monitoring.cancel.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Monitoramento Eletronico cancelado.',
            'vars' => [],
        ],
        'gme.monitoring.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Monitoramento Eletronico cadastrado.',
            'vars' => [],
        ],
        'gme.monitoring.datajud.wait.info' => [
            'type' => 'info',
            'title' => 'Aguarde',
            'message' => 'Aguarde a consulta ao DataJud terminar antes de registrar.',
            'vars' => [],
        ],
        'gme.monitoring.validation.process.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe o número do processo.',
            'vars' => [],
        ],
        'gme.monitoring.validation.reu.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe o nome completo do réu.',
            'vars' => [],
        ],
        'gme.monitoring.validation.start_date.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe a data de início.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.name.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe o nome do réu.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.single_process_principal' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'O único processo vinculado deve ser o principal.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.define_other_principal_first' => [
            'type' => 'info',
            'title' => 'Validação',
            'message' => 'Defina outro processo como principal antes de marcar este como secundário.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.process.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe o número do processo.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.movement.date.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe a data da movimentação.',
            'vars' => [],
        ],
        'gabinete.reus_presos.validation.movement.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Movimentação inválida.',
            'vars' => [],
        ],

        // =========================
        // PRODUTIVIDADE
        // =========================
        'produtividade.manual.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Produtividade registrada com sucesso.',
            'vars' => [],
        ],
        'produtividade.manual.cancel.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Produtividade cancelada com sucesso.',
            'vars' => [],
        ],
        'produtividade.badge.unlock.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Badge desbloqueado: {badge_nome}',
            'vars' => ['badge_nome'],
        ],
        'produtividade.rule.required.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Selecione uma regra válida.',
            'vars' => [],
        ],
        'produtividade.datajud.wait.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Aguarde a consulta ao DataJud terminar antes de cadastrar.',
            'vars' => [],
        ],
        'produtividade.manual.cancel.reason.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Motivo da anulação é obrigatório.',
            'vars' => [],
        ],
        'produtividade.filter.period.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Período inválido. Use datas válidas para filtrar.',
            'vars' => [],
        ],
        'produtividade.filter.period.start_after_end' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'A data inicial não pode ser maior que a data final.',
            'vars' => [],
        ],
        'produtividade.filter.period.max_range.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'O intervalo entre data inicial e data final não pode ultrapassar 60 dias.',
            'vars' => [],
        ],

        // Produtividade automática — fluxos internos (consultas, audiência, prioridades)
        'interno.produtividade.cep.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'Esta consulta de CEP registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.consulta_partes.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'Esta consulta de partes registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.consulta_processos_lista.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'Esta consulta de processos (listagem) registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.consulta_processos_detalhe.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'Esta consulta de processo (detalhe) registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.audiencia_parte_cadastro.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'O cadastro da parte na audiência registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.audiencia_parte_edicao.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'A alteração da parte na audiência registrou +1 ponto de produtividade.',
            'vars' => [],
        ],
        'interno.produtividade.prioridade_add.ponto' => [
            'type' => 'info',
            'title' => 'Produtividade',
            'message' => 'A inclusão da prioridade processual registrou +1 ponto de produtividade.',
            'vars' => [],
        ],

        // =========================
        // AUDIENCIAS
        // =========================
        'audiencias.pauta.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Audiência cadastrada.',
            'vars' => [],
        ],
        'audiencias.pauta.update.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Alterações salvas.',
            'vars' => [],
        ],
        'audiencias.pauta.update.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao salvar.',
            'vars' => [],
        ],
        'audiencias.pauta.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente em Excluir audiência em até 5 segundos para confirmar.',
            'vars' => [],
        ],
        'audiencias.pauta.auto_save.info' => [
            'type' => 'info',
            'title' => 'Atualização automática',
            'message' => 'Dados da audiência salvos com sucesso.',
            'vars' => [],
        ],
        'audiencias.pauta.part.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Parte adicionada.',
            'vars' => [],
        ],
        'audiencias.pauta.part.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Parte removida.',
            'vars' => [],
        ],
        'audiencias.pauta.server.allocate.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Servidor alocado.',
            'vars' => [],
        ],
        'audiencias.pauta.status.reiniciar.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Status atualizado para Designada.',
            'vars' => [],
        ],
        'audiencias.pauta.status.iniciar.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Status atualizado para Em andamento.',
            'vars' => [],
        ],
        'audiencias.pauta.status.encerrar.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Status atualizado para Finalizada.',
            'vars' => [],
        ],
        'audiencias.ata.upload.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Ata enviada com sucesso.',
            'vars' => [],
        ],
        'audiencias.ata.subscribe.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Inscrição realizada.',
            'vars' => [],
        ],
        'audiencias.midias.task.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Tarefa de mídia cadastrada com sucesso.',
            'vars' => [],
        ],
        'audiencias.midias.task.update.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Alterações salvas com sucesso.',
            'vars' => [],
        ],
        'audiencias.midias.task.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente em Excluir tarefa em até 5 segundos para confirmar a exclusão desta tarefa e de todas as subtarefas vinculadas.',
            'vars' => [],
        ],
        'audiencias.midias.subtask.conclude.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Subtarefa concluída com sucesso.',
            'vars' => [],
        ],
        'audiencias.prioridade.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Prioridade adicionada.',
            'vars' => [],
        ],
        'audiencias.prioridade.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Prioridade removida.',
            'vars' => [],
        ],
        'audiencias.validation.required.field' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe: {campo}.',
            'vars' => ['campo'],
        ],
        'audiencias.validation.process.invalid' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Número do processo inválido. Informe no padrão CNJ.',
            'vars' => [],
        ],
        'audiencias.validation.date.before.today' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'A data da audiência não pode ser anterior a hoje.',
            'vars' => [],
        ],
        'audiencias.validation.class.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe: Classe (aguarde o DataJud finalizar o preenchimento automático).',
            'vars' => [],
        ],
        'audiencias.validation.server.required' => [
            'type' => 'info',
            'title' => 'Alocação',
            'message' => 'Selecione um servidor.',
            'vars' => [],
        ],
        'audiencias.validation.hearing.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Audiência inválida.',
            'vars' => [],
        ],
        'audiencias.schedule.load.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao carregar horários.',
            'vars' => [],
        ],
        'audiencias.schedule.unavailable.info' => [
            'type' => 'info',
            'title' => 'Horários',
            'message' => 'Não há horários livres nesta data.',
            'vars' => [],
        ],
        'audiencias.date.past.not.allowed' => [
            'type' => 'info',
            'title' => 'Data',
            'message' => 'Datas anteriores a hoje não são permitidas.',
            'vars' => [],
        ],
        'audiencias.part.remove.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao remover parte.',
            'vars' => [],
        ],
        'audiencias.part.update.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao atualizar parte.',
            'vars' => [],
        ],
        'audiencias.part.add.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha ao adicionar parte.',
            'vars' => [],
        ],
        'audiencias.ata.filepicker.open.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Não foi possível abrir o seletor de arquivos.',
            'vars' => [],
        ],
        'audiencias.ata.file.invalid.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Selecione um arquivo PDF válido.',
            'vars' => [],
        ],
        'audiencias.ata.file.size.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'O arquivo deve ter no máximo 2 MB.',
            'vars' => [],
        ],
        'audiencias.ata.upload.http.error' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Falha no envio (HTTP {status}).',
            'vars' => ['status'],
        ],
        'audiencias.midias.schedule.empty.info' => [
            'type' => 'info',
            'title' => 'Sem audiências',
            'message' => 'Não há audiências finalizadas para os filtros informados.',
            'vars' => [],
        ],
        'audiencias.midias.create.required.error' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Preencha processo, classe, data e audiência finalizada.',
            'vars' => [],
        ],
        'audiencias.midias.create.date.future.error' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'A data da audiência não pode ser futura.',
            'vars' => [],
        ],
        'audiencias.midias.edit.record.invalid.update' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Registro inválido para atualização.',
            'vars' => [],
        ],
        'audiencias.midias.edit.record.invalid.delete' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Registro inválido para exclusão.',
            'vars' => [],
        ],
        'audiencias.midias.subtask.quantity.invalid' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe uma quantidade de mídias válida para o download.',
            'vars' => [],
        ],
        'audiencias.prioridade.required.error' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Selecione uma prioridade no catálogo.',
            'vars' => [],
        ],

        // =========================
        // DATAJUD
        // =========================
        'datajud.process.class.updated.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'Classe do processo atualizada no sistema.',
            'vars' => [],
        ],
        'datajud.process.class.created.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'Processo e classe registrados no sistema.',
            'vars' => [],
        ],

        // =========================
        // GENERICO (COMPARTILHADO)
        // =========================
        'generic.operation.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Operacao concluida com sucesso.',
            'vars' => [],
        ],
        'generic.record.save.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Dados salvos.',
            'vars' => [],
        ],
        'generic.task.cancel.success' => [
            'type' => 'info',
            'title' => 'Informacao',
            'message' => 'Tarefa cancelada com sucesso.',
            'vars' => [],
        ],
        'generic.record.delete.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Registro excluido.',
            'vars' => [],
        ],
        'generic.record.archive.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Registro arquivado.',
            'vars' => [],
        ],
        'generic.process.link.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Processo vinculado.',
            'vars' => [],
        ],
        'generic.link.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Vínculo removido com sucesso.',
            'vars' => [],
        ],
        'generic.link.update.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Vínculo atualizado.',
            'vars' => [],
        ],
        'generic.link.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Vínculo criado.',
            'vars' => [],
        ],
        'generic.movement.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Movimentação registrada.',
            'vars' => [],
        ],
        'generic.movement.delete.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Movimentação excluída.',
            'vars' => [],
        ],
        'generic.phone.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Telefone adicionado com sucesso.',
            'vars' => [],
        ],
        'generic.phone.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Telefone removido com sucesso.',
            'vars' => [],
        ],
        'generic.address.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Endereço adicionado com sucesso.',
            'vars' => [],
        ],
        'generic.address.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Endereço removido com sucesso.',
            'vars' => [],
        ],
        'generic.department.add.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Setor adicionado com sucesso.',
            'vars' => [],
        ],
        'generic.department.remove.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Setor removido com sucesso.',
            'vars' => [],
        ],
        'generic.validation.review.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Revise os campos obrigatórios e corrija os dados informados.',
            'vars' => [],
        ],
        'generic.user.create.pending.info' => [
            'type' => 'info',
            'title' => 'Informação',
            'message' => 'O cadastro será criado com status Pendente.',
            'vars' => [],
        ],
        'generic.institution.create.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Instituição cadastrada.',
            'vars' => [],
        ],
        'generic.validation.name.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Nome é obrigatório.',
            'vars' => [],
        ],
        'generic.validation.cnpj.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'CNPJ inválido. Verifique os dígitos.',
            'vars' => [],
        ],
        'generic.validation.phone.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe o número.',
            'vars' => [],
        ],
        'generic.validation.department.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe o nome do setor.',
            'vars' => [],
        ],
        'generic.validation.address.minimum.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Informe ao menos logradouro ou cidade.',
            'vars' => [],
        ],
        'generic.validation.person.institution.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Selecione a pessoa e a instituição.',
            'vars' => [],
        ],
        'generic.validation.role.required' => [
            'type' => 'error',
            'title' => 'Validação',
            'message' => 'Informe o cargo ou função.',
            'vars' => [],
        ],
        'generic.validation.inclusion_date.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Data de inclusão é obrigatória.',
            'vars' => [],
        ],
        'generic.validation.link.fields.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Preencha parte, tipo e polo.',
            'vars' => [],
        ],
        'generic.validation.link.type_polo.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Preencha tipo e polo.',
            'vars' => [],
        ],
        'generic.validation.cpf.invalid' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'CPF inválido. Verifique os dígitos.',
            'vars' => [],
        ],
        'generic.validation.entity.id.required' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'ID da pessoa não encontrado. Recarregue a página.',
            'vars' => [],
        ],
        'generic.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente para confirmar a exclusão.',
            'vars' => [],
        ],
        'generic.cep.lookup.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'CEP encontrado.',
            'vars' => [],
        ],
        'generic.confirmation.required' => [
            'type' => 'info',
            'title' => 'Confirmacao',
            'message' => '{mensagem}',
            'vars' => ['mensagem'],
        ],
        'generic.error.unexpected' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Ocorreu um erro inesperado. Tente novamente.',
            'vars' => [],
        ],
        'generic.error.connection' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Erro de conexao.',
            'vars' => [],
        ],
        'generic.error.save' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Erro ao salvar.',
            'vars' => [],
        ],
        'generic.error.cancel' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Erro ao cancelar.',
            'vars' => [],
        ],
        'generic.error.delete' => [
            'type' => 'error',
            'title' => 'Erro',
            'message' => 'Erro ao excluir.',
            'vars' => [],
        ],
        'cartorio.guias.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente em Excluir para confirmar a exclusão.',
            'vars' => [],
        ],
        'cartorio.cartas.delete.confirm' => [
            'type' => 'info',
            'title' => 'Confirmação',
            'message' => 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão desta tarefa.',
            'vars' => [],
        ],
    ],
];
