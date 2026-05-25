<?php
declare(strict_types=1);

// Smoke local de parse + map v3. Apaga depois.
require_once __DIR__ . '/../app/Mni/MniClient.php';

$xml = <<<'XML'
<?xml version="1.0"?>
<soapenv:Envelope
    xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:tic="http://www.cnj.jus.br/mni/v300/tipos-servico-intercomunicacao"
    xmlns:pji="http://www.cnj.jus.br/mni/v300/intercomunicacao">
  <soapenv:Body>
    <tic:respostaConsultarProcesso>
      <tic:recibo>
        <pji:sucesso>true</pji:sucesso>
        <pji:mensagens>Consulta realizada com sucesso</pji:mensagens>
        <pji:reciboToken>
          <pji:token>abc-123-xyz</pji:token>
          <pji:dataExpiracao>2026-12-31T23:59:59</pji:dataExpiracao>
        </pji:reciboToken>
      </tic:recibo>
      <tic:processo>
        <pji:dadosBasicos>
          <pji:classeProcessual>1116</pji:classeProcessual>
          <pji:codigoLocalidade>BA-ILH</pji:codigoLocalidade>
          <pji:nivelSigilo>1</pji:nivelSigilo>
          <pji:dataAjuizamento>2024-03-15T10:00:00</pji:dataAjuizamento>
          <pji:prioridade>REU_PRESO</pji:prioridade>
          <pji:valorCausa>50000.00</pji:valorCausa>
          <pji:competencia>1</pji:competencia>
          <pji:processoFisico>false</pji:processoFisico>
          <pji:pedidoLiminarPendente>true</pji:pedidoLiminarPendente>
          <pji:intervencaoMP>true</pji:intervencaoMP>
          <pji:tamanhoProcesso>12345678</pji:tamanhoProcesso>
          <pji:magistradoAtuante>12345678901</pji:magistradoAtuante>
          <pji:assunto>
            <pji:codigoNacional>14848</pji:codigoNacional>
          </pji:assunto>
          <pji:polo polo="AT">
            <pji:parte>
              <pji:pessoa>
                <pji:dadosBasicos>
                  <pji:nome>Ministerio Publico</pji:nome>
                  <pji:tipoPessoa>JUR</pji:tipoPessoa>
                </pji:dadosBasicos>
              </pji:pessoa>
            </pji:parte>
          </pji:polo>
          <pji:polo polo="PA">
            <pji:parte>
              <pji:pessoa>
                <pji:dadosBasicos>
                  <pji:nome>Reu Teste</pji:nome>
                  <pji:tipoPessoa>FIS</pji:tipoPessoa>
                  <pji:numeroDocumentoPrincipal>99988877766</pji:numeroDocumentoPrincipal>
                </pji:dadosBasicos>
              </pji:pessoa>
            </pji:parte>
          </pji:polo>
          <pji:orgaoJulgador>
            <pji:codigo>OJ-001</pji:codigo>
            <pji:nome>1a Vara Criminal de Ilheus</pji:nome>
            <pji:instancia>ORIG</pji:instancia>
            <pji:codigoLocalidade>BA-ILH</pji:codigoLocalidade>
          </pji:orgaoJulgador>
        </pji:dadosBasicos>
      </tic:processo>
    </tic:respostaConsultarProcesso>
  </soapenv:Body>
</soapenv:Envelope>
XML;

$parsed = MniResponseParser::parseEnvelope($xml, '3.0.0');
echo "--- parser ---\n";
echo 'ok       : ' . ($parsed['ok'] ? 'true' : 'false') . "\n";
echo 'sucesso  : ' . ($parsed['sucesso'] ? 'true' : 'false') . "\n";
echo 'mensagem : ' . ($parsed['mensagem'] ?? '(null)') . "\n";
echo 'recibo_token: ' . json_encode($parsed['recibo_token']) . "\n";

$shape = MniProcessoMapper::mapToCommonShape($parsed['processo'], '00000000000000000000');

echo "\n--- shape topo ---\n";
echo 'prioridades   : ' . json_encode($shape['prioridades']) . "\n";
echo 'nivel_sigilo  : ' . ($shape['nivel_sigilo'] ?? '(null)') . "\n";
echo 'sigilo_restrito: ' . ($shape['sigilo_restrito'] ? 'true' : 'false') . "\n";
echo 'data_ajuizamento: ' . ($shape['data_ajuizamento'] ?? '(null)') . "\n";
echo 'polo_passivo  : ' . json_encode($shape['polo_passivo']) . "\n";
echo 'orgao_julgador: ' . json_encode($shape['orgao_julgador']) . "\n";

echo "\n--- shape[mni].cabecalho ---\n";
echo json_encode($shape['mni']['cabecalho'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n--- partes ---\n";
echo 'ativo   : ' . json_encode($shape['mni']['partes']['ativo'])   . "\n";
echo 'passivo : ' . json_encode($shape['mni']['partes']['passivo']) . "\n";
