<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function jsonResponse(bool $success, string $message, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require __DIR__ . '/../class/Connect.php';

    if (!isset($connection) || !($connection instanceof mysqli)) {
        jsonResponse(false, 'Conexão com o banco de dados indisponível.', 500);
    }
        
        // Gera um ID curto (compatível com gerarIdGuia usado em outros handlers)
        function gerarIdGuia(): string {
            static $ultimoTempo = 0;
            static $sequencia = 0;
            $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $base = strlen($alfabeto);
            $atual = (int) floor(microtime(true) * 1_000_000);
            if ($atual === $ultimoTempo) {
                $sequencia++;
                if ($sequencia >= 1024) {
                    do { usleep(1); $atual = (int) floor(microtime(true) * 1_000_000); } while ($atual <= $ultimoTempo);
                    $sequencia = 0;
                }
            } else {
                $sequencia = 0;
            }
            $ultimoTempo = $atual;
            $combinado = ($atual << 10) | $sequencia;
            $cod = '';
            do { $cod = $alfabeto[$combinado % $base] . $cod; $combinado = intdiv($combinado, $base); } while ($combinado > 0);
            $len = strlen($cod);
            if ($len < 20) {
                for ($i = 0, $max = strlen($alfabeto) - 1; $i < 20 - $len; $i++) $cod .= $alfabeto[random_int(0, $max)];
            } elseif ($len > 20) {
                $cod = substr($cod, -20);
            }
            return $cod;
        }

    // gera id aleatório legível para id_guia_serv (30 chars)
    function gerarIdLong(int $len = 30): string {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
        $max = strlen($alfabeto) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alfabeto[random_int(0, $max)];
        }
        return $out;
    }

    $connection->set_charset('utf8mb4');
    $connection->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $connection->query("SET collation_connection = 'utf8mb4_unicode_ci'");

    if (!$connection->select_db('ios1_users')) {
        throw new RuntimeException('Banco ios1_users indisponível.');
    }

    $idGuia     = trim((string)filter_input(INPUT_POST, 'mov-process', FILTER_UNSAFE_RAW));
    $statusNovo = trim((string)filter_input(INPUT_POST, 'mov-type',    FILTER_UNSAFE_RAW));
    $tipoGuia   = trim((string)filter_input(INPUT_POST, 'guia',        FILTER_UNSAFE_RAW));
    $changeTipo = trim((string)filter_input(INPUT_POST, 'change_tipo', FILTER_UNSAFE_RAW));
    $user       = trim((string)filter_input(INPUT_POST, 'mov-user',    FILTER_UNSAFE_RAW));

    if ($idGuia === '') {
        jsonResponse(false, 'ID da guia ausente.', 400);
    }
    if ($user === '') {
        jsonResponse(false, 'Usuário ausente.', 400);
    }

    if (mb_strtolower($statusNovo, 'UTF-8') === 'a fazer') {
        $statusNovo = '';
    }

    $connection->begin_transaction();

    try {
        $proc = null;
        $statusAtual = null;
        $request = $connection->prepare("SELECT `processo`, `status` FROM guia WHERE id_guia = ?");
        $request->bind_param('s', $idGuia);
        $request->execute();
        $request->bind_result($proc, $statusAtual);
        $found = $request->fetch();
        $request->close();

        if (!$found) {
            jsonResponse(false, 'Guia não encontrada.', 404);
        }

        $tz   = new DateTimeZone('America/Bahia');
        $now  = (new DateTime('now', $tz))->format('Y-m-d H:i:s');
        $novo = substr((new DateTime('now', $tz))->format('YmdHisu'), 0, 15);

        $statusToEtapa = [
            'Aguardando detalhamento'        => 'Triagem',
            'Aguardando expedição'           => 'Expedição',
            'Aguardando assinatura da juíza' => 'Gabinete',
            'Aguardando correção'            => 'Secretaria',
            'Aguardando envio'               => 'Expedição',
            'Aguardando reenvio'             => 'Expedição',
            // accept both variants and map to Secretaria
            'Aguardando juntada de termo'    => 'Secretaria',
            'Aguardando juntada do termo'    => 'Secretaria',
        ];

        $allowedStatuses = array_keys($statusToEtapa);
        if ($statusNovo !== '' && !in_array($statusNovo, $allowedStatuses, true)) {
            $statusNovo = '';
        }

        $allowedTipos = [
            'Guia - Tipo não informado',
            'Guia (corrigir)',
            'Guia (reenviar)',
            'Guia de Execução Definitiva',
            'Guia de Recolhimento Provisória',
            'Guia de Recolhimento Definitiva',
        ];

        if ($tipoGuia !== '' && !in_array($tipoGuia, $allowedTipos, true)) {
            $tipoGuia = '';
        }

        // handle change_tipo action: update tipo and set status to Aguardando expedição
        if ($changeTipo !== '' && in_array($changeTipo, ['Guia de Execução Definitiva','Guia de Recolhimento Provisória','Guia de Recolhimento Definitiva'], true)) {
            $stmtUp = $connection->prepare("UPDATE guia SET tipo = ?, status = 'Aguardando expedição', etapa = 'Expedição', data_atualizacao = ? WHERE id_guia = ?");
            if ($stmtUp) {
                $stmtUp->bind_param('sss', $changeTipo, $now, $idGuia);
                $stmtUp->execute();
                $stmtUp->close();
                // registro de produtividade
                $descChange = 'Guia - Tipo alterado para ' . $changeTipo;
                $stmtP = $connection->prepare("INSERT INTO Produtividade (IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmtP) {
                    $idProd = gerarIdGuia();
                    $pts = '1';
                    $stmtP->bind_param('ssssss', $idProd, $proc, $descChange, $pts, $now, $user);
                    $stmtP->execute();
                    $stmtP->close();
                }
                // marca que já registramos produtividade para evitar duplicação
                $skipProdEntry = true;
                // commit handled later
            } else {
                throw new RuntimeException('Falha ao preparar atualização de tipo: ' . $connection->error);
            }
        }
        // Se changeTipo foi recebido, garantir que as flags de update reflitam a alteração
        if (!empty($changeTipo)) {
            $tipoGuia = $changeTipo;
            $statusNovo = 'Aguardando expedição';
        }

        $willUpdateStatus = ($statusNovo !== null && $statusNovo !== '');
        $willUpdateDesc   = ($tipoGuia !== null && $tipoGuia !== '');

        if (!$willUpdateStatus && !$willUpdateDesc) {
            jsonResponse(false, 'Nenhuma alteração foi solicitada (tipo ou status vazio).', 400);
        }

        $etapaNova = $statusToEtapa[$statusNovo] ?? null;

        // Special handling: if requested status is 'juntada de/do termo', delete the guia and its allocations
        $statusLower = mb_strtolower((string)$statusNovo, 'UTF-8');
        if ($statusLower === 'aguardando juntada de termo' || $statusLower === 'aguardando juntada do termo') {
            // delete allocations
            $delAlloc = $connection->prepare("DELETE FROM guia_servidores WHERE id_guia = ?");
            if ($delAlloc) {
                $delAlloc->bind_param('s', $idGuia);
                $delAlloc->execute();
                $delAlloc->close();
            }
            // delete guia
            $delGuia = $connection->prepare("DELETE FROM guia WHERE id_guia = ?");
            if ($delGuia) {
                $delGuia->bind_param('s', $idGuia);
                $delGuia->execute();
                $delGuia->close();
            }
            // produtividade
            $desc = 'Guia - Termo de envio juntado aos autos';
            $idProd = gerarIdGuia();
            $stmtP2 = $connection->prepare("INSERT INTO Produtividade (IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd) VALUES (?, ?, ?, '1', ?, ?)");
            if ($stmtP2) {
                $stmtP2->bind_param('sssss', $idProd, $proc, $desc, $now, $user);
                $stmtP2->execute();
                $stmtP2->close();
            }
            $connection->commit();
            // respond and exit
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isAjax || $wantsJson) {
                jsonResponse(true, 'Guia excluída após juntada de termo', 200);
            } else {
                header('Location: ./../pages/guias2.php?deleted=1&id=' . urlencode($idGuia), true, 303);
                exit;
            }
        }

        $updateGuiaSql = "UPDATE guia SET data_atualizacao = ?";
        $updateGuiaTypes = 's';
        $updateGuiaParams = [$now];

        if ($willUpdateStatus) {
            $updateGuiaSql .= ", status = ?";
            $updateGuiaTypes .= 's';
            $updateGuiaParams[] = $statusNovo;
        }
        if ($etapaNova !== null) {
            $updateGuiaSql .= ", etapa = ?";
            $updateGuiaTypes .= 's';
            $updateGuiaParams[] = $etapaNova;
        }
        if ($willUpdateDesc) {
            $updateGuiaSql .= ", tipo = ?";
            $updateGuiaTypes .= 's';
            $updateGuiaParams[] = $tipoGuia;
        }

        $updateGuiaSql .= " WHERE id_guia = ?";
        $updateGuiaTypes .= 's';
        $updateGuiaParams[] = $idGuia;

        $stmt = $connection->prepare($updateGuiaSql);
        $stmt->bind_param($updateGuiaTypes, ...$updateGuiaParams);
        $stmt->execute();
        $stmt->close();

        $desc = null;
        $pts = '1';

        if ($willUpdateStatus) {
            switch ($statusNovo) {
                case 'Aguardando detalhamento':
                    $desc='Guia - Documento cadastrado';
                    $pts='2';
                    break;
                case 'Aguardando expedição':
                    $desc='Guia - Tipo de documento alterado';
                    $pts='1';
                    break;
                case 'Aguardando assinatura da juíza':
                    $desc='Guia - Documento expedido no BNMP';
                    $pts='4';
                    break;
                case 'Aguardando correção':
                    $desc='Guia - Documento devolvido para correção';
                    $pts='2';
                    break;
                case 'Aguardando envio':
                    $desc='Guia - Documento assinado pela Juíza';
                    $pts='1';
                    break;
                case 'Aguardando reenvio':
                    $desc='Guia - Documento cadastrado para reenvio';
                    $pts='2';
                    break;
                default:
                    $desc='Guia - Movimentada';
                    $pts='2';
                    break;
            }
        } elseif ($willUpdateDesc) {
            $desc='Guia - Tipo de documento alterado';
            $pts='1';
        }

        if ($desc !== null && empty($skipProdEntry)) {
            $stmt = $connection->prepare("
                INSERT INTO Produtividade (IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssss', $novo, $proc, $desc, $pts, $now, $user);
            $stmt->execute();
            $stmt->close();
        }
        // Verifica se o usurio que executou a movimentaço já está alocado na guia.
        // Se não estiver, aloca imediatamente.
        $checkAlloc = $connection->prepare("SELECT 1 FROM guia_servidores WHERE id_guia = ? AND id_user = ? LIMIT 1");
        if ($checkAlloc) {
            // tentar obter UserId a partir do nome do usurio
            $sqlGetUid = "SELECT UserId FROM Usuarios WHERE UserNome = ? LIMIT 1";
            $stmtGetUid = $connection->prepare($sqlGetUid);
            $foundUserId = null;
            if ($stmtGetUid) {
                $stmtGetUid->bind_param("s", $user);
                $stmtGetUid->execute();
                $resG = $stmtGetUid->get_result();
                if ($resG && ($rowG = $resG->fetch_assoc())) {
                    $foundUserId = $rowG['UserId'] ?? null;
                }
                $stmtGetUid->close();
            }

            if ($foundUserId === null) {
                // não foi possível resolver UserId a partir do nome; não alocar
                $checkAlloc->close();
            } else {
                $checkAlloc->bind_param('ss', $idGuia, $foundUserId);
                $checkAlloc->execute();
                $checkAlloc->store_result();
                $hasAlloc = $checkAlloc->num_rows > 0;
                $checkAlloc->close();
                if (!$hasAlloc) {
                    // insere alocação
                    $idAlloc = gerarIdLong(30);
                    $insAlloc = $connection->prepare("INSERT INTO guia_servidores (id_guia_serv, id_guia, id_user) VALUES (?, ?, ?)");
                    if ($insAlloc) {
                        $insAlloc->bind_param('sss', $idAlloc, $idGuia, $foundUserId);
                        $insAlloc->execute();
                        $insAlloc->close();
                    }
                }
            }
        }

        $connection->commit();
        // resposta padronizada: JSON para AJAX, redirect PRG para navegadores
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($isAjax || $wantsJson) {
            jsonResponse(true, 'ok', 200);
        } else {
            header('Location: ./../pages/guias2.php?ok=1&id=' . urlencode($idGuia), true, 303);
            exit;
        }

    } catch (Throwable $e) {
        $connection->rollback();
        jsonResponse(false, 'Erro interno ao processar a movimentação: ' . $e->getMessage(), 500);
    }

} catch (Throwable $e) {
    jsonResponse(false, 'Erro fatal no servidor: ' . $e->getMessage(), 500);
}
?>
