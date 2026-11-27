<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/CartasExpedidasPrioridade.php';

function renderAcoes(string $etapa, string $actor, bool $privilegiado, bool $inconsistente = false, bool $semRegra = false): string
{
    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $idCarta = 'C1';
    $usuarioPiloto = $actor;
    $canDelete = $privilegiado;
    $fluxoInconsistente = $inconsistente;
    $prioridadesPiloto = $prioridadesTexto = $historicoLinhas = [];
    $row = ['EtapaEfetiva' => $etapa, 'CriadoPor' => 'autor'];
    $responsavelRegraEnvio = $semRegra ? null : ['id' => 'fila'];
    $numero = $destino = $finalidade = $prazo = $prazoRestanteTexto = $nomeResponsavelEnvio = '';
    $subtarefaAtivaId = $abrirHref = $whatsappTexto = $statusVisivelTexto = $classeTexto = $assuntoTexto = '';
    $isTerminal = $prazoRestanteAlerta = $sigiloso = $temPrioridadeVisual = $mostrarContinuar = $isAguardandoCumprimento = $mostrarCopiaWhatsapp = false;
    $abrirDisabled = true;
    $groupKey = $etapa;
    $showEdit = $etapa === 'envio';
    $mostrarReiniciar = $etapa === 'devolucao';
    $_SESSION = ['id_usuario' => $actor];
    ob_start();
    require __DIR__ . '/../../app/views/internal/partials/cartas-expedidas-piloto-row.php';
    $html = ob_get_clean();
    if (!preg_match('~<footer class="ce-detail-actions">(.*?)</footer>~s', $html, $m)) {
        throw new RuntimeException('Card não encontrado');
    }
    return $m[1];
}

foreach ([
    ['envio', 'fila', false, true],
    ['envio', 'autor', false, false],
    ['envio', 'admin', true, true],
    ['envio', 'chefia', true, true],
    ['devolucao', 'autor', false, true],
    ['devolucao', 'fila', false, false],
    ['devolucao', 'terceiro', false, false],
    ['devolucao', 'chefia', true, true],
] as [$etapa, $actor, $privilegiado, $esperado]) {
    $html = renderAcoes($etapa, $actor, $privilegiado);
    $acao = $etapa === 'envio' ? 'Editar' : 'Reiniciar envio';
    foreach ([$acao, 'Cancelar carta'] as $label) {
        if (!preg_match('~<(?:button|a)\b[^>]*>' . preg_quote($label, '~') . '</(?:button|a)>~', $html, $m)) {
            throw new RuntimeException("Controle ausente: $label");
        }
        if ((!str_contains($m[0], ' disabled')) !== $esperado) {
            throw new RuntimeException("Permissão inesperada: $etapa/$actor/$label");
        }
    }
    if (str_contains($html, 'Excluir carta') !== $privilegiado) {
        throw new RuntimeException('Permissão de exclusão alterada');
    }
}
if (str_contains(renderAcoes('envio', 'autor', false, false, true), 'data-ce-action="cancelar"')) {
    throw new RuntimeException('Sem regra ativa, não conceder permissão por suposição');
}
if (str_contains(renderAcoes('devolucao', 'autor', false, true), 'data-ce-action="cancelar"')) {
    throw new RuntimeException('Não inferir responsável em fluxo inconsistente');
}
echo "PASS: ações do card piloto respeitam responsável, etapa e perfis.\n";
