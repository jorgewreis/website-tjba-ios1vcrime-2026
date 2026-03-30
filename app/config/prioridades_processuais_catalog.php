<?php
declare(strict_types=1);

/**
 * Catálogo versionado de rótulos de prioridade processual (lista fechada).
 * Ordem aqui é irrelevante para a UI: a tela ordena alfabeticamente.
 *
 * A prioridade vinculada automaticamente a parte menor deve usar exatamente o mesmo texto
 * que em PrioridadesProcessuaisCatalog::prioridadeAutomaticaParteMenor().
 * Idem para réu preso e PrioridadesProcessuaisCatalog::prioridadeAutomaticaReuPreso().
 */
return [
    'Criança ou adolescente',
    'Réu preso',
    'Idoso',
    'Idoso (80 anos ou mais)',
    'Pessoa com deficiência',
    'Pessoa com doença grave',
    'Violência doméstica',
];
