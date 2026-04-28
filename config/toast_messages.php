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
            'message' => 'Oficio cancelado.',
            'vars' => [],
        ],
        'oficios.delete.success' => [
            'type' => 'success',
            'title' => 'Sucesso',
            'message' => 'Oficio excluido.',
            'vars' => [],
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
