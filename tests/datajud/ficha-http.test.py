#!/usr/bin/env python3
"""Fronteira HTTP da ficha com dependências isoladas e dados sintéticos."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]

with tempfile.TemporaryDirectory(prefix='datajud-ficha-http-') as tmp:
    base = Path(tmp)

    def put(path, content):
        target = base / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')

    def copy(path):
        target = base / path
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / path, target)

    for path in ('public/api/processos/datajud-ficha.php', 'app/helpers/ProcessoFicha.php',
                 'app/helpers/CnjValidator.php', 'app/helpers/ProcessoNivelSigilo.php',
                 'app/DataJud/DataJudTribunalResolver.php', 'app/DataJud/DataJudUpstreamException.php'):
        copy(path)
    put('app/helpers/ApiAccessGuard.php', '''<?php
class ApiErrorReporter { static function register($x) {} static function logUnexpected($a,$b) {} }
class ApiAccessGuard { static function requireAccess($pages,$write) {
    if ($pages !== ['consultaprocessosedit'] || !$write || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_SERVER['HTTP_X_FIXTURE_AUTH'] ?? '') !== 'ok') { http_response_code(403); exit; }
} }
class Conection { static function getInstance() { return new self; } function getConnection() { return null; } }
''')
    put('app/repositories/ProcessosRepository.php', '''<?php
class ProcessosRepository {
    private int $reads = 0;
    function __construct($db) {}
    function findById($id) {
        $this->reads++;
        $scenario = $_SERVER['HTTP_X_FIXTURE_CASE'] ?? '';
        if ($scenario === 'missing' || ($scenario === 'deleted' && $this->reads === 2)) return null;
        return ['numero_processo' => $scenario === 'changed' && $this->reads === 2 ? 'other' : 'synthetic-number',
            'sigiloso' => $scenario === 'secret' || ($scenario === 'became-secret' && $this->reads === 2)];
    }
}
''')
    put('app/services/DataJudService.php', '''<?php
require_once __DIR__ . '/../DataJud/DataJudUpstreamException.php';
class DataJudAmbiguousResponseException extends RuntimeException {}
class DataJudService {
    function __construct($config) {}
    function consultarFichaPublica($numero) {
        $scenario = $_SERVER['HTTP_X_FIXTURE_CASE'] ?? '';
        if ($scenario === 'incomplete') throw new DataJudUpstreamException('incomplete');
        if ($scenario === 'ambiguous') throw new DataJudAmbiguousResponseException();
        return ['resultado_unico_verificado'=>true, 'sigilo_ausencia_verificada'=>true,
            'grau'=>'G1', 'classe'=>['descricao'=>'Classe fictícia'],
            'assuntos'=>[['descricao'=>'Assunto fictício']], 'orgao_julgador'=>['nome'=>'Vara fictícia'],
            'movimentos'=>[], 'polo_passivo'=>['PRIVATE_SENTINEL']];
    }
}
''')
    put('public/api/datajud/config.php', "<?php return ['api_key'=>'fixture'];")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {key: os.environ[key] for key in ('PATH', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(base / 'public')],
                              env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    def request(case='', auth='ok'):
        req = urllib.request.Request(f'http://127.0.0.1:{port}/api/processos/datajud-ficha.php',
            data=json.dumps({'processo_id':'SYNTHETIC'}).encode(),
            headers={'Content-Type':'application/json', 'X-Fixture-Auth':auth, 'X-Fixture-Case':case})
        try:
            with urllib.request.urlopen(req, timeout=4) as response:
                return response.status, json.loads(response.read())
        except urllib.error.HTTPError as err:
            return err.code, json.loads(err.read() or b'{}')

    try:
        for _ in range(60):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.2):
                    break
            except OSError:
                import time
                time.sleep(0.05)
        else:
            raise RuntimeError('Servidor de fixture não iniciou')
        status, body = request()
        assert status == 200 and body['estado'] == 'disponivel'
        assert (body['classe'], body['assuntos'], body['grau'], body['vara']) == (
            'Classe fictícia', ['Assunto fictício'], 'G1', 'Vara fictícia')
        assert 'PRIVATE_SENTINEL' not in json.dumps(body)
        assert request(auth='no')[0] == 403
        assert request('missing')[0] == 404
        assert request('secret') == (200, {'estado':'restrito'})
        for case in ('changed', 'deleted', 'became-secret'):
            assert request(case) == (200, {'estado':'indisponivel'}), case
        assert request('incomplete') == (503, {'estado':'erro', 'code':'datajud_incomplete', 'retry_after':None})
        assert request('ambiguous') == (409, {'estado':'erro', 'code':'datajud_ambiguous'})
        print('PASS: ficha HTTP isolada entrega campos e recusa alterações, sigilo e respostas incompletas.')
    finally:
        server.terminate()
        server.wait(timeout=10)
