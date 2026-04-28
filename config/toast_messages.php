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

        // Oficios
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

        // Genericos compartilhados
        'generic.operation.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Operacao concluida com sucesso.',
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
    ],
];
