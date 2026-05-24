<?php
declare(strict_types=1);

require_once __DIR__ . '/ClasseProcessualListagemHelper.php';
require_once __DIR__ . '/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../repositories/ProcessoAssuntosRepository.php';

/**
 * Dados agregados para o partial `_processo_bloco_listagem.php` em listagens (ex.: sala de mídias).
 *
 * Contrato visual e de integração: docs/00-foundation/bloco-processo-listagem.md
 */
final class ProcessoBlocoListagemHelper
{
    private const ICON_SIGILOSO = '/assets/icons/Lock-6--Streamline-Ultimate.png';
    private const ICON_REU_PRESO = '/assets/icons/Crime-Man-Inmate--Streamline-Ultimate.png';
    private const ICON_VD_ALERTA = '/assets/icons/Police-Rotating-Light-1--Streamline-Ultimate.png';
    private const ICON_DEFAULT = '/assets/icons/Road-Sign-Warning-Alternate--Streamline-Ultimate.png';

    /**
     * Garante `numero_processo` e `classe_processo` esperados por {@see appendBlocoData()}
     * quando a listagem usa aliases (`Processo`, `ClasseNome`, `Classe`).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeRowKeysForBloco(array $row): array
    {
        $numero = trim((string) ($row['numero_processo'] ?? ''));
        if ($numero === '') {
            $disp = trim((string) ($row['Processo'] ?? ''));
            if ($disp !== '' && $disp !== '—' && $disp !== '–') {
                $row['numero_processo'] = $disp;
            }
        }
        if (trim((string) ($row['classe_processo'] ?? '')) === '') {
            foreach (['ClasseNome', 'Classe'] as $k) {
                $c = trim((string) ($row[$k] ?? ''));
                if ($c !== '') {
                    $row['classe_processo'] = $c;
                    break;
                }
            }
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $rows */
    public static function appendBlocoData(PDO $connection, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $rows = array_map([self::class, 'normalizeRowKeysForBloco'], $rows);

        $processosRepo = new ProcessosRepository($connection);
        $classesRepo = new ClassesRepository($connection);
        $prioridadesRepo = new ProcessoPrioridadesRepository($connection);
        $assuntosRepo = new ProcessoAssuntosRepository($connection);

        $idsFromColumn = [];
        $digitosPorLinha = [];
        foreach ($rows as $idx => $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid !== '') {
                $idsFromColumn[$pid] = true;
            }
            $digits = preg_replace('/\D+/', '', (string) ($row['numero_processo'] ?? ''));
            $digitosPorLinha[$idx] = strlen($digits) === 20 ? $digits : '';
        }

        $processoById = [];
        foreach (array_keys($idsFromColumn) as $pid) {
            $p = $processosRepo->findById($pid);
            if ($p !== null) {
                $processoById[$pid] = $p;
            }
        }

        $digitosUnicos = [];
        foreach ($digitosPorLinha as $d) {
            if ($d !== '') {
                $digitosUnicos[$d] = true;
            }
        }
        foreach (array_keys($digitosUnicos) as $d) {
            $p = $processosRepo->findByNumero($d);
            if ($p !== null) {
                $id = trim((string) ($p['id'] ?? ''));
                if ($id !== '') {
                    $processoById[$id] = $p;
                }
            }
        }

        $classeIds = [];
        foreach ($processoById as $p) {
            $cid = trim((string) ($p['classe_id'] ?? ''));
            if ($cid !== '') {
                $classeIds[$cid] = true;
            }
        }
        $classePorId = [];
        foreach (array_keys($classeIds) as $cid) {
            $c = $classesRepo->findById($cid);
            if ($c !== null) {
                $classePorId[$cid] = $c;
            }
        }

        $resolvedIds = [];
        foreach ($rows as $idx => $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid !== '' && isset($processoById[$pid])) {
                $resolvedIds[$idx] = $pid;
                continue;
            }
            $d = $digitosPorLinha[$idx] ?? '';
            if ($d !== '') {
                $found = $processosRepo->findByNumero($d);
                $rid = $found !== null ? trim((string) ($found['id'] ?? '')) : '';
                $resolvedIds[$idx] = $rid;
                continue;
            }
            $resolvedIds[$idx] = '';
        }

        $idsParaPrioridade = array_values(array_unique(array_filter($resolvedIds, static function (string $id): bool {
            return $id !== '';
        })));
        $prioridadesPorProcesso = $prioridadesRepo->findByProcessoIds($idsParaPrioridade);
        $assuntosPorProcesso = $assuntosRepo->findByProcessoIds($idsParaPrioridade);

        $out = [];
        foreach ($rows as $idx => $row) {
            $rid = $resolvedIds[$idx] ?? '';
            $proc = $rid !== '' && isset($processoById[$rid]) ? $processoById[$rid] : null;

            $classeTexto = '';
            $classeNot = true;
            if ($proc !== null) {
                $cid = trim((string) ($proc['classe_id'] ?? ''));
                if ($cid !== '' && isset($classePorId[$cid])) {
                    $cl = $classePorId[$cid];
                    $cod = trim((string) ($cl['codigo'] ?? ''));
                    $nom = trim((string) ($cl['nome'] ?? ''));
                    if ($nom === '' || !ClasseProcessualListagemHelper::temDestaqueListagem($nom)) {
                        $classeTexto = 'Classe não cadastrada';
                        $classeNot = true;
                    } elseif ($cod !== '') {
                        $classeTexto = $cod . ' - ' . $nom;
                        $classeNot = false;
                    } else {
                        $classeTexto = $nom;
                        $classeNot = false;
                    }
                } elseif ($cid !== '') {
                    $classeTexto = 'Classe não cadastrada';
                    $classeNot = true;
                } else {
                    $classeTexto = 'Classe não cadastrada';
                    $classeNot = true;
                }
            } else {
                $rawClasse = (string) ($row['classe_processo'] ?? '');
                if (ClasseProcessualListagemHelper::temDestaqueListagem($rawClasse)) {
                    $classeTexto = trim($rawClasse);
                    $classeNot = false;
                } else {
                    $classeTexto = 'Classe não cadastrada';
                    $classeNot = true;
                }
            }

            $priorRows = $rid !== '' ? ($prioridadesPorProcesso[$rid] ?? []) : [];
            $codigos = [];
            foreach ($priorRows as $pr) {
                $c = trim((string) ($pr['codigo'] ?? ''));
                if ($c !== '') {
                    $codigos[$c] = true;
                }
            }
            $codigosLista = array_keys($codigos);
            self::ordenarCodigosPrioridade($codigosLista);
            $prioridadesUi = self::montarPrioridadesUiAgrupadas($codigosLista);

            $podeAbrir = $rid !== '';
            $hrefAbrir = $podeAbrir ? ('/frame.php?page=consultaprocessosedit&id=' . rawurlencode($rid)) : '';

            $sigilosoMesclado = self::rawProcessoSigilosoFlag($row) || isset($codigos['sigiloso']);
            $assuntoRows = $rid !== '' ? ($assuntosPorProcesso[$rid] ?? []) : [];
            $assuntoItens = [];
            foreach ($assuntoRows as $assuntoRow) {
                $assuntoCodigo = trim((string) ($assuntoRow['assunto_codigo'] ?? ''));
                $assuntoDescricao = trim((string) ($assuntoRow['assunto_descricao'] ?? ''));
                if ($assuntoCodigo === '' && $assuntoDescricao === '') {
                    continue;
                }
                $texto = $assuntoCodigo !== '' ? $assuntoCodigo : $assuntoDescricao;
                if ($assuntoDescricao !== '' && $assuntoDescricao !== $assuntoCodigo) {
                    $texto = $assuntoCodigo !== '' ? ($assuntoCodigo . ' - ' . $assuntoDescricao) : $assuntoDescricao;
                }
                $assuntoItens[$texto] = true;
            }
            $assuntoTexto = implode('; ', array_keys($assuntoItens));
            $assuntoNot = $assuntoTexto === '';
            if ($assuntoNot) {
                $assuntoTexto = 'Assunto não cadastrado';
            }

            $out[$idx] = [
                'bloco_processo_id_resolvido' => $rid,
                'bloco_processo_classe_texto' => $classeTexto,
                'bloco_processo_classe_not' => $classeNot,
                'bloco_processo_assunto_texto' => $assuntoTexto,
                'bloco_processo_assunto_not' => $assuntoNot,
                'bloco_processo_prioridades' => $prioridadesUi,
                'bloco_processo_abrir_href' => $hrefAbrir,
                'bloco_processo_abrir_disabled' => !$podeAbrir,
                'processo_sigiloso' => $sigilosoMesclado,
            ];
        }

        $merged = [];
        foreach ($rows as $idx => $row) {
            $merged[] = array_merge($row, $out[$idx] ?? []);
        }

        return $merged;
    }

    /**
     * Indica sigilo a partir da coluna `processos.sigiloso` / `processo_sigiloso` na linha
     * (antes ou depois do merge), sem considerar prioridades.
     */
    public static function rawProcessoSigilosoFlag(array $row): bool
    {
        if (!array_key_exists('processo_sigiloso', $row)) {
            return false;
        }
        $v = $row['processo_sigiloso'];
        if ($v === true) {
            return true;
        }
        if ($v === false || $v === null) {
            return false;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v !== 0;
        }
        if (is_string($v)) {
            $t = trim($v);

            return $t !== '' && $t !== '0';
        }

        return (bool) $v;
    }

    /**
     * Listagens de estudantes: mascarar número quando o processo for sigiloso na base
     * ou houver prioridade que bloqueia visibilidade/inscrição externa.
     */
    public static function deveMascararNumeroNaVarianteEstudantes(array $row): bool
    {
        if (self::rawProcessoSigilosoFlag($row)) {
            return true;
        }
        $pps = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
        $bloqueantes = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
        foreach ($pps as $p) {
            if (!is_array($p)) {
                continue;
            }
            if (in_array(trim((string) ($p['codigo'] ?? '')), $bloqueantes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prioridades exibidas com o ícone de alerta (giroflex): uma única entrada na UI,
     * com rótulos concatenados no tooltip. Réu preso e sigiloso permanecem separados.
     *
     * @return list<string>
     */
    private static function codigosGrupoIconeAlertaVulneravel(): array
    {
        return [
            'violencia_domestica',
            'crianca_adolescente',
            'idoso',
            'idoso_80_anos_ou_mais',
            'pessoa_deficiencia',
            'pessoa_doenca_grave',
        ];
    }

    /**
     * @param list<string> $codigosLista já ordenados
     * @return list<array{codigo: string, label: string, icon: string}>
     */
    private static function montarPrioridadesUiAgrupadas(array $codigosLista): array
    {
        $grupoAlerta = self::codigosGrupoIconeAlertaVulneravel();
        $alertaCodigos = [];
        $outrosCodigos = [];

        foreach ($codigosLista as $codigo) {
            if (in_array($codigo, $grupoAlerta, true)) {
                $alertaCodigos[] = $codigo;
                continue;
            }
            $outrosCodigos[] = $codigo;
        }

        $ui = [];
        foreach ($outrosCodigos as $codigo) {
            $ui[] = [
                'codigo' => $codigo,
                'label' => PrioridadesProcessuaisCatalog::labelForCodigo($codigo),
                'icon' => self::iconForCodigoPrioridade($codigo),
            ];
        }

        if ($alertaCodigos !== []) {
            $labels = [];
            foreach ($alertaCodigos as $codigo) {
                $labels[] = PrioridadesProcessuaisCatalog::labelForCodigo($codigo);
            }
            $ui[] = [
                'codigo' => $alertaCodigos[0],
                'label' => implode('; ', $labels),
                'icon' => self::ICON_VD_ALERTA,
            ];
        }

        usort($ui, static function (array $a, array $b) use ($grupoAlerta): int {
            $codA = (string) ($a['codigo'] ?? '');
            $codB = (string) ($b['codigo'] ?? '');
            $peso = static function (string $codigo) use ($grupoAlerta): int {
                if ($codigo === 'sigiloso') {
                    return 10;
                }
                if ($codigo === 'reu_preso') {
                    return 20;
                }
                if (in_array($codigo, $grupoAlerta, true)) {
                    return 30;
                }

                return 500;
            };

            return $peso($codA) <=> $peso($codB);
        });

        return $ui;
    }

    /** @param list<string> $codigos mutável */
    private static function ordenarCodigosPrioridade(array &$codigos): void
    {
        $weight = [
            'sigiloso' => 10,
            'reu_preso' => 20,
            'violencia_domestica' => 30,
            'crianca_adolescente' => 40,
            'idoso' => 50,
            'idoso_80_anos_ou_mais' => 51,
            'pessoa_deficiencia' => 60,
            'pessoa_doenca_grave' => 70,
        ];
        usort($codigos, static function (string $a, string $b) use ($weight): int {
            $wa = $weight[$a] ?? 500;
            $wb = $weight[$b] ?? 500;
            if ($wa !== $wb) {
                return $wa <=> $wb;
            }

            return strcmp($a, $b);
        });
    }

    private static function iconForCodigoPrioridade(string $codigo): string
    {
        if (in_array($codigo, self::codigosGrupoIconeAlertaVulneravel(), true)) {
            return self::ICON_VD_ALERTA;
        }

        return match ($codigo) {
            'sigiloso' => self::ICON_SIGILOSO,
            'reu_preso' => self::ICON_REU_PRESO,
            default => self::ICON_DEFAULT,
        };
    }

    /**
     * Mascara o número CNJ preservando ano, segmento, tribunal e unidade
     * (`*******-**.AAAA.J.TR.OOOO`); usado em listagens de estudantes para
     * processos sigilosos. O segredo é o identificador sequencial.
     */
    public static function mascararNumeroProcessoSigiloso(string $numero): string
    {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        if (strlen($digits) !== 20) {
            return '*******-**.****.*.**.****';
        }

        return '*******-**.'
            . substr($digits, 9, 4) . '.'
            . substr($digits, 13, 1) . '.'
            . substr($digits, 14, 2) . '.'
            . substr($digits, 16, 4);
    }
}
