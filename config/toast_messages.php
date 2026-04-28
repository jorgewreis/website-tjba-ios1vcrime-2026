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
    ],
];
