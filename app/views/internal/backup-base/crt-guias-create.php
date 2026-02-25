<?php
declare(strict_types=1);

const DATAJUD_API_KEY = 'cDZHYzlZa0JadVREZDJCendQbXY6SkJlTzNjLV9TRENyQk1RdnFKZGRQdw==';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/../class/Connect.php';

if (!$connection instanceof mysqli) {
  http_response_code(500);
  exit('Conexão invlida');
}
$connection->set_charset('utf8mb4');
$connection->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
$connection->query("SET collation_connection = 'utf8mb4_unicode_ci'");

$isAjax    = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function send_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

function gerarIdGuia(): string {
  static $ultimoTempo = 0;
  static $sequencia = 0;

  $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $base = strlen($alfabeto);
  $atual = (int) floor(microtime(true) * 1_000_000);

  if ($atual === $ultimoTempo) {
    $sequencia++;
    if ($sequencia >= 1024) {
      do { usleep(1); $atual = (int) floor(microtime(true) * 1_000_000); }
      while ($atual <= $ultimoTempo);
      $sequencia = 0;
    }
  } else {
    $sequencia = 0;
  }

  $ultimoTempo = $atual;
  $combinado = ($atual << 10) | $sequencia;

  $cod = '';
  do { $cod = $alfabeto[$combinado % $base] . $cod; $combinado = intdiv($combinado, $base); }
  while ($combinado > 0);

  $len = strlen($cod);
  if ($len < 20) {
    for ($i=0, $max=strlen($alfabeto)-1; $i < 20-$len; $i++) {
      $cod .= $alfabeto[random_int(0, $max)];
    }
  } elseif ($len > 20) {
    $cod = substr($cod, -20);
  }
  return $cod;
}

// Gera id de tamanho customizavel (ex.: 30 chars) para guia_servidores
function gerarIdLong(int $len = 30): string {
  $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
  $max = strlen($alfabeto) - 1;
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $out .= $alfabeto[random_int(0, $max)];
  }
  return $out;
}

$user     = trim((string)filter_input(INPUT_POST, 'doc-user', FILTER_UNSAFE_RAW));
$processo = trim((string)filter_input(INPUT_POST, 'doc-process', FILTER_UNSAFE_RAW));
$tipoIn   = trim((string)filter_input(INPUT_POST, 'doc-class', FILTER_UNSAFE_RAW));
$prio     = trim((string)filter_input(INPUT_POST, 'doc-prio', FILTER_UNSAFE_RAW));
$reu      = trim((string)filter_input(INPUT_POST, 'doc-denunciado', FILTER_UNSAFE_RAW));
// servidores e modo de alocação
$serversRaw = $_POST['doc-servers'] ?? [];
$allocationMode = trim((string)filter_input(INPUT_POST, 'allocation-mode', FILTER_UNSAFE_RAW)) ?: 'direct';

// --- debug logs (NDJSON) ---
// debug instrumentation removed

$proc = $processo;

$apiKey = (string)(DATAJUD_API_KEY ?: getenv('DATAJUD_API_KEY'));
$endpoint = 'https://api-publica.datajud.cnj.jus.br/api_publica_tjba/_search';

$digits = preg_replace('/\D/', '', $proc);

function consultarDadosProcesso(string $endpoint, string $apiKey, string $numeroProcesso, string $grauFiltro = ''): array {
  $query = [
    'query' => [
      'match' => [
        'numeroProcesso' => $numeroProcesso
      ]
    ]
  ];
  
  if ($grauFiltro !== '') {
    $query['query'] = [
      'bool' => [
        'must' => [
          ['match' => ['numeroProcesso' => $numeroProcesso]]
        ],
        'filter' => [
          ['term' => ['grau' => $grauFiltro]]
        ]
      ]
    ];
  }
  
  $query['_source'] = ['numeroProcesso', 'classe', 'classe.codigo', 'classe.nome', 'grau'];
  $query['size'] = 1;
  
  $payload = json_encode($query, JSON_UNESCAPED_UNICODE);
  
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: APIKey ' . $apiKey,
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
  ]);
  
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  
  if ($http === 200 && $res) {
    $j = json_decode($res, true) ?: [];
    $src = $j['hits']['hits'][0]['_source'] ?? [];
    if (!empty($src)) {
      return [
        'codigo' => $src['classe']['codigo'] ?? null,
        'nome' => $src['classe']['nome'] ?? null,
        'grau' => $src['grau'] ?? ''
      ];
    }
  }
  
  return ['codigo' => null, 'nome' => null, 'grau' => ''];
}

// Tenta primeiro no primeiro grau (G1)
$resultado = consultarDadosProcesso($endpoint, $apiKey, $digits, 'G1');

$codigo = $resultado['codigo'];
$nome = $resultado['nome'];
$grau = $resultado['grau'];

// Se não encontrou em G1, tenta em G2
if ($codigo === null || $nome === null) {
  $resultado = consultarDadosProcesso($endpoint, $apiKey, $digits, 'G2');
  $codigo = $resultado['codigo'];
  $nome = $resultado['nome'];
  $grau = $resultado['grau'];
  
  // Se encontrou em G2, marca como tal
  if ($codigo !== null && $nome !== null) {
    $nome = $nome . ' (Processo no Segundo Grau)';
    $codigo = 'G2-' . (string)$codigo;
  }
}

// Se ainda não encontrou, define valores padrão
if ($codigo === null || $nome === null) {
  $codigo = 'Classe não encontrada';
  $nome = 'Classe não encontrada';
}


if ($processo === '' || $tipoIn === '') {
    exit;
}

$etapa = $status = $tipo = $msg_prod = '';
$tipoNorm = mb_strtolower($tipoIn, 'UTF-8');

switch ($tipoNorm) {
    case 'guia - tipo não informado':
        $etapa   = 'Secretaria/Triagem';
        $status  = 'Aguardando detalhamento';
        $tipo    = 'Guia - Tipo não informado';
        $msg_prod= 'Cadastro de nova guia para expedição';
        break;

    case 'guia (corrigir)':
        $etapa   = 'Reviso/Secretaria';
        $status  = 'Aguardando correção';
        $tipo    = 'Guia (corrigir)';
        $msg_prod= 'Cadastro de guia devolvida para refazer';
        break;

    case 'guia (reenviar)':
        $etapa   = 'Expedição';
        $status  = 'Aguardando reenvio';
        $tipo    = 'Guia (reenviar)';
        $msg_prod= 'Cadastro de guia devolvida para reenviar';
        break;

    default:
        $etapa   = 'Secretaria/Triagem';
        $status  = 'Aguardando detalhamento';
        $tipo    = 'Guia - Tipo não informado';
        $msg_prod= 'Cadastro de nova guia para expedição';
}

mysqli_select_db($connection, 'ios1_users');

$agora  = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
$novoId = gerarIdGuia();

try {
  $connection->begin_transaction();

  $sql1 = "INSERT INTO guia
           (id_guia, processo, denunciado, tipo, `status`, etapa, urgencia, data_cadastro, user_cadastro)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt1 = $connection->prepare($sql1);
  $stmt1->bind_param("sssssssss", $novoId, $processo, $reu, $tipo, $status, $etapa, $prio, $agora, $user);
  $stmt1->execute();
  $stmt1->close();

  // inserir alocações em guia_servidores conforme selection/mode
  $servers = is_array($serversRaw) ? array_values($serversRaw) : [];
  if (!empty($servers)) {
      // Se draw: fazer sorteio balanceado usando histórico de 60 dias na tabela guia_sorteios
      if ($allocationMode === 'draw' && count($servers) > 0) {
          // limpar registros antigos (mais de 60 dias)
          $connection->query("DELETE FROM guia_sorteios WHERE allocated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");

          // montar lista para IN
          $escaped = array_map(function($v) use ($connection) {
              return "'" . $connection->real_escape_string((string)$v) . "'";
          }, $servers);
          $inList = implode(',', $escaped);

          $counts = [];
          if ($inList !== '') {
              $sqlCounts = "SELECT id_user, COUNT(*) AS cnt FROM guia_sorteios WHERE id_user IN ($inList) AND allocated_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) GROUP BY id_user";
              $resCounts = $connection->query($sqlCounts);
              if ($resCounts) {
                  while ($r = $resCounts->fetch_assoc()) {
                      $counts[$r['id_user']] = (int)$r['cnt'];
                  }
                  $resCounts->free();
              }
          }
          // garantir 0 para quem não aparecer
          foreach ($servers as $s) {
              $s = (string)$s;
              if (!isset($counts[$s])) $counts[$s] = 0;
          }
          $min = min($counts);
          $candidates = [];
          foreach ($counts as $uid => $cnt) {
              if ($cnt === $min) $candidates[] = $uid;
          }
          if (!empty($candidates)) {
              $chosen = $candidates[array_rand($candidates)];
              $serversToInsert = [$chosen];
          } else {
              $serversToInsert = [$servers[array_rand($servers)]];
          }
      } else {
          // direct ou selected (inserir todos)
          $serversToInsert = $servers;
      }

      $sqlInsAlloc = "INSERT INTO guia_servidores (id_guia_serv, id_guia, id_user) VALUES (?, ?, ?)";
      $stmtAlloc = $connection->prepare($sqlInsAlloc);
      if ($stmtAlloc) {
          foreach ($serversToInsert as $srvValue) {
              $uid = (string)$srvValue;
              $idAlloc = gerarIdLong(30);
              $stmtAlloc->bind_param("sss", $idAlloc, $novoId, $uid);
              $stmtAlloc->execute();

              // se foi sorteio, registrar na tabela de histórico guia_sorteios
              if ($allocationMode === 'draw') {
                  $idS = gerarIdLong(30);
                  $sqlS = "INSERT INTO guia_sorteios (id_sorteio, id_guia, id_user, allocated_at, created_at) VALUES (?, ?, ?, ?, NOW())";
                  $stmtS = $connection->prepare($sqlS);
                  if ($stmtS) {
                      $stmtS->bind_param("ssss", $idS, $novoId, $uid, $agora);
                      $stmtS->execute();
                      $stmtS->close();
                  }
              }
          }
          $stmtAlloc->close();
      }
  }
  $sql2 = "INSERT INTO Produtividade
           (IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd)
           VALUES (?, ?, ?, '1', ?, ?)";
  $stmt2 = $connection->prepare($sql2);
  $stmt2->bind_param("sssss", $novoId, $processo, $msg_prod, $agora, $user);
  $stmt2->execute();
  $stmt2->close();

  if($nome !== 'Classe não encontrada' && strpos($nome ?? '', 'Processo no Segundo Grau') === false) {
    if ($codigo !== null && strpos($codigo ?? '', 'Classe não encontrada') === false && strpos($codigo ?? '', 'G2-') === false) {
        $sql3 = "SELECT cod FROM classes WHERE cod = ? LIMIT 1";
        $stmt3 = $connection->prepare($sql3);
        $stmt3->bind_param("s", $codigo);
        $stmt3->execute();
        $stmt3->store_result();
        if ($stmt3->num_rows === 0) {
          $novoClasseId = gerarIdGuia();
            $sql4 = "INSERT INTO classes (id_classe, cod, descricao) VALUES (?, ?, ?)";
            $stmt4 = $connection->prepare($sql4);
            $stmt4->bind_param("sss", $novoClasseId, $codigo, $nome);
            $stmt4->execute();
            $stmt4->close();
        }
        $stmt3->close();

        $sql5 = "SELECT classe FROM processos WHERE numero = ? LIMIT 1";
        $stmt5 = $connection->prepare($sql5);
        $stmt5->bind_param("s", $processo);
        $stmt5->execute();
        $stmt5->store_result();
        if ($stmt5->num_rows === 0) {
        $novoProcId = gerarIdGuia();
            $sql6 = "INSERT INTO processos (id_proc, numero, classe, data_cadastro) VALUES (?, ?, ?, ?)";
            $stmt6 = $connection->prepare($sql6);
            $stmt6->bind_param("ssss", $novoProcId, $processo, $codigo, $agora);
            $stmt6->execute();
            $stmt6->close();
            }
            $stmt5->close();
        }
    }

    

  $connection->commit();
  $payload = ['ok'=>true, 'id'=>$novoId, 'status'=>'Guia cadastrada com sucesso'];

  // JSON para AJAX. Redireciona para navegadores diretos para a nova página guias2.php.
  if ($isAjax || $wantsJson) {
    send_json($payload, 201);
  } else {
    // PRG evita reenvio no refresh.
    header('Location: ./../pages/guias.php?ok=1&id=' . urlencode($novoId), true, 303);
    exit;
  }

} catch (Throwable $e) {
  $connection->rollback();
  if ($isAjax || $wantsJson) {
    send_json(['ok'=>false, 'id'=>'', 'status'=>'error'], 500);
  } else {
    // Redirect back to guias2 with error flag
    header('Location: ./../pages/guias.php?error=1', true, 303);
    exit;
  }
}