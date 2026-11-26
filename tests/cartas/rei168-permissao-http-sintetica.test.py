#!/usr/bin/env python3
"""Proposta: HTTP local, Security/guard reais; identidade SQLite :memory: e serviço stub.

Não executa login, MySQL ou persistência do aplicativo. Sujeito a revisão antes de uso.
Depende de host/kernel confiáveis; verificações de caminho e PID têm janela TOCTOU.
"""
import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

def checked_path(path):
    absolute = Path(os.path.abspath(path))
    for item in (absolute, *absolute.parents):
        stat = item.lstat()
        if item.is_symlink() or getattr(stat, 'st_file_attributes', 0) & 0x400:
            raise RuntimeError('Link ou reparse point recusado')
    if os.path.normcase(str(absolute.resolve(strict=True))) != os.path.normcase(str(absolute)):
        raise RuntimeError('Caminho não canônico recusado')
    return absolute

class GuardedWorkspace:
    @staticmethod
    def _identity(path):
        st = path.lstat()
        if not path.is_dir() or not st.st_ino:
            raise RuntimeError('Identidade do diretório temporário indisponível')
        return st.st_dev, st.st_ino

    def _remove_verified(self):
        current = checked_path(self.root)
        if current != self.root or self._identity(current) != self.identity:
            raise RuntimeError('Identidade do diretório mudou; preservado')
        # Não seguir nem remover árvores com links introduzidos durante a execução.
        for directory, dirs, files in os.walk(current, followlinks=False):
            for name in dirs + files:
                checked_path(Path(directory) / name)
        if checked_path(current) != self.root or self._identity(current) != self.identity:
            raise RuntimeError('Identidade do diretório mudou; preservado')
        shutil.rmtree(current)
        if current.exists() or current.is_symlink():
            raise RuntimeError('Remoção do diretório não confirmada')

    def __enter__(self):
        parent = checked_path(tempfile.gettempdir())
        created = Path(tempfile.mkdtemp(prefix='rei168-auth-synthetic-', dir=parent))
        self.process = None
        try:
            self.root = checked_path(created)
            self.identity = self._identity(self.root)
            return self
        except Exception as error:
            # Sem identidade capturada e revalidada não se remove alvo algum.
            if hasattr(self, 'identity') and hasattr(self, 'root'):
                try: self._remove_verified()
                except Exception as cleanup_error:
                    raise RuntimeError('Inicialização e limpeza incertas; diretório preservado') from cleanup_error
                raise RuntimeError('Inicialização falhou; diretório removido') from error
            raise RuntimeError('Inicialização sem identidade confirmada; diretório preservado: ' + str(created)) from error

    def __exit__(self, exc_type, exc, tb):
        if self.process is not None and self.process.poll() is None:
            try:
                self.process.terminate()
                try: self.process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    self.process.kill()
                    self.process.wait(timeout=5)
            except Exception as error:
                raise RuntimeError('Término do servidor incerto; diretório preservado') from error
        if self.process is not None and self.process.poll() is None:
            raise RuntimeError('Servidor ainda ativo; diretório preservado')
        if getattr(self, 'log', None) is not None: self.log.close()
        self._remove_verified()

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
ROOT = checked_path(Path(__file__).absolute()).parents[2]
php_candidate = shutil.which('php')
if not php_candidate:
    raise RuntimeError('PHP indisponível')
PHP = str(checked_path(php_candidate))

# A lista limita os arquivos copiados; não copia .env, bancos, credenciais ou runners auth.
FILES = [
    'config/security.php', 'app/helpers/ApiErrorReporter.php', 'app/helpers/ApiAccessGuard.php',
    'app/helpers/BootstrapHelper.php', 'app/helpers/AutoloadHelper.php', 'app/helpers/UserAccessHelper.php',
    'app/helpers/UsuarioPerfisAutorizacao.php', 'app/helpers/EffectiveAccessProfiles.php',
    'app/helpers/CartorioSubtarefaUsuarioSessao.php', 'app/models/Usuario.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
    'app/access/AccessEvaluator.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
    'public/api/cartorio-cartas-expedidas/list.php',
    'public/api/cartorio-cartas-expedidas/concluir-subtarefa.php',
]
for rel in FILES:
    source = ROOT / rel
    checked_path(source)
    if not source.is_file():
        raise RuntimeError('Origem de fixture ausente ou link')

with GuardedWorkspace() as workspace:
    root = workspace.root
    for rel in FILES:
        dst = root / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / rel, dst)
    (root / 'config/config.php').write_text('''<?php
define('SESSION_IDLE_TIMEOUT',3600);
function bootstrapSession(){if(session_status()!==PHP_SESSION_ACTIVE) session_start();}
function appIsHttpsRequest(){return false;}
''', encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
require_once __DIR__ . '/../../config/config.php';
final class Conection {
 private static ?self $instance=null; private ?PDO $db=null;
 public static function getInstance(): self {return self::$instance ??= new self();}
 public function getConnection(): PDO {
  if ($this->db !== null) return $this->db;
  $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db->sqliteCreateFunction('NOW', fn()=>gmdate('Y-m-d H:i:s'), 0);
  $db->exec('CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER); CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT)');
  $users = ['admin'=>'ADMIN','cartorio'=>'SERVIDOR-CARTORIO','gabinete'=>'SERVIDOR-GABINETE','consultor'=>'CONSULTOR','estudante'=>'ESTUDANTE'];
  foreach ($users as $id=>$perfil) {
   $db->prepare('INSERT INTO usuarios VALUES (?,?,?,1)')->execute([$id,hash('sha256','rei168-synthetic-token'),'2999-01-01 00:00:00']);
   $db->prepare('INSERT INTO usuario_perfil VALUES (?,?)')->execute([$id,$perfil]);
  }
  return $this->db=$db;
 }
}
''', encoding='utf-8')
    # O stub mede apenas chamadas ao serviço, não prova SQL nem persistência de negócio.
    (root / 'app/services').mkdir(exist_ok=True)
    (root / 'app/services/CartasExpedidasService.php').write_text('''<?php
final class CartasExpedidasService {
 function __construct(PDO $p) {}
 private function trace(string $event): void { file_put_contents(getenv('REI168_TRACE'), $event."\\n", FILE_APPEND | LOCK_EX); }
 function listCartas(array $f, int $l, int $o): array { $this->trace('list'); return ['rows'=>[['IdCarta'=>'SYNTHETIC']], 'total'=>1]; }
 function getStatusCounts(array $f): array {return [];}
 function getClasseCounts(array $f): array {return [];}
 function concluirSubtarefa(string $id, ?string $data=null): array { $this->trace('write'); return ['repeticao'=>false]; }
}
''', encoding='utf-8')
    sessions = root / 'sessions'
    sessions.mkdir()
    trace = root / 'trace.txt'
    trace.write_text('', encoding='ascii')
    seed = root / 'seed.php'
    seed.write_text("<?php session_id($argv[1]); session_start(); $_SESSION=['id_usuario'=>$argv[2],'token'=>$argv[3],'last_activity_at'=>time()]; session_write_close();", encoding='utf-8')
    allowed_env = ('SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP')
    env = {k: os.environ[k] for k in allowed_env if k in os.environ}
    env['REI168_TRACE'] = str(trace)
    # O próprio PHP pede uma porta efêmera ao kernel; não existe reserva solta.
    runtime = subprocess.run([PHP, '-r', "if (PHP_VERSION_ID < 80200 || !extension_loaded('pdo_sqlite')) exit(2); $p=new PDO('sqlite::memory:'); if ($p->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite') exit(3);"], env=env, capture_output=True, timeout=15)
    if runtime.returncode != 0: raise RuntimeError('PHP/SQLite em memória indisponível')
    for user in ('admin','cartorio','gabinete','consultor','estudante','invalid'):
        account = 'cartorio' if user == 'invalid' else user
        token = 'wrong-token' if user == 'invalid' else 'rei168-synthetic-token'
        result = subprocess.run([PHP, '-d', 'session.save_path='+str(sessions), str(seed), 'fixture'+user, account, token], env=env, capture_output=True, timeout=15)
        if result.returncode != 0: raise RuntimeError('Falha ao criar sessão sintética')
    sentinel = hashlib.sha256(os.urandom(32)).hexdigest()
    (root / 'public' / (sentinel + '.txt')).write_text(sentinel, encoding='ascii')
    log = open(root / 'server.log', 'w+b')
    workspace.log = log
    proc = subprocess.Popen([PHP, '-d', 'session.save_path='+str(sessions), '-S', '127.0.0.1:0', '-t', str(root / 'public')], env=env, stdout=log, stderr=log)
    workspace.process = proc
    port = None
    for _ in range(100):
        if proc.poll() is not None: break
        log.flush()
        raw = (root / 'server.log').read_text(encoding='utf-8', errors='replace')
        found = re.search(r'Development Server \(http://127\.0\.0\.1:([0-9]{1,5})\) started', raw)
        if found:
            port = int(found.group(1))
            break
        time.sleep(0.05)
    if not port or not 1 <= port <= 65535: raise RuntimeError('Servidor não informou porta efêmera')
    def assert_listener_owned():
        if proc.poll() is not None: raise RuntimeError('Servidor temporário encerrado')
        if os.name == 'nt':
            command = f"$x=@(Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort {port} -State Listen -ErrorAction Stop); if($x.Count -ne 1 -or $x[0].OwningProcess -ne {proc.pid}){{exit 1}}"
            result = subprocess.run(['powershell.exe','-NoProfile','-NonInteractive','-Command',command], env=env, capture_output=True, timeout=10)
            if result.returncode != 0: raise RuntimeError('Socket não pertence ao servidor')
        elif os.name == 'posix' and Path(f'/proc/{proc.pid}/net/tcp').is_file():
            fds = set()
            for fd in Path(f'/proc/{proc.pid}/fd').iterdir():
                try: fds.add(os.readlink(fd))
                except OSError: continue
            found = []
            for line in Path(f'/proc/{proc.pid}/net/tcp').read_text().splitlines()[1:]:
                fields = line.split()
                if len(fields) >= 10 and fields[1].upper() == f'0100007F:{port:04X}' and fields[3] == '0A': found.append(fields[9])
            if len(found) != 1 or f'socket:[{found[0]}]' not in fds: raise RuntimeError('Socket não pertence ao servidor')
        else:
            raise RuntimeError('Verificação de PID/socket indisponível')
    def request(endpoint, user=None, method='GET', origin=None):
        headers = {'Cookie':'PHPSESSID=fixture'+user} if user else {}
        if origin is not None: headers['Origin'] = origin
        data = None
        if method == 'POST':
            headers['Content-Type'] = 'application/json'
            data = json.dumps({'subtarefa_id':'SYNTHETIC-SUB'}).encode()
        req = urllib.request.Request(f'http://127.0.0.1:{port}/api/cartorio-cartas-expedidas/{endpoint}', data=data, headers=headers, method=method)
        assert_listener_owned()
        try:
            with OPENER.open(req, timeout=4) as response: return response.status, response.read()
        except urllib.error.HTTPError as error: return error.code, error.read()
    try:
        ready = False
        for _ in range(50):
            if proc.poll() is not None: break
            try:
                assert_listener_owned()
                with OPENER.open(f'http://127.0.0.1:{port}/{sentinel}.txt', timeout=1) as response:
                    ready = response.read().decode() == sentinel
                if ready: break
            except (OSError, urllib.error.URLError): time.sleep(0.1)
        if not ready or proc.poll() is not None: raise RuntimeError('Servidor temporário não confirmado')
        for user, expected in [(None,401),('invalid',401),('estudante',403),('consultor',403),('gabinete',403),('admin',200),('cartorio',200)]:
            before = trace.read_bytes()
            status, payload = request('list.php?classe=classe-sintetica', user)
            data = json.loads(payload)
            if expected == 200 and data.get('data') != [{'IdCarta':'SYNTHETIC'}]: raise RuntimeError('JSON stub de leitura divergente')
            if expected != 200 and not isinstance(data.get('error'), str): raise RuntimeError('JSON de recusa inválido')
            if status != expected: raise RuntimeError('Autorização de leitura divergente: '+str(user))
            after = trace.read_bytes()
            if expected != 200 and after != before: raise RuntimeError('Leitura recusada chamou serviço')
            if expected == 200 and after != before+b'list\n': raise RuntimeError('Leitura autorizada não chamou stub esperado')
        for user, method, origin, expected in [
            (None,'POST',f'http://127.0.0.1:{port}',401),
            ('invalid','POST',f'http://127.0.0.1:{port}',401),
            ('estudante','POST',f'http://127.0.0.1:{port}',403),
            ('consultor','POST',f'http://127.0.0.1:{port}',403),
            ('gabinete','POST',f'http://127.0.0.1:{port}',403),
            ('cartorio','GET',None,403),
            ('cartorio','POST','http://outro.invalid',403),
            ('cartorio','POST',f'http://127.0.0.1:{port}',200),
            ('admin','POST',f'http://127.0.0.1:{port}',200),
        ]:
            before = trace.read_bytes()
            status, payload = request('concluir-subtarefa.php', user, method, origin)
            data = json.loads(payload)
            if expected == 200 and data.get('success') is not True: raise RuntimeError('JSON stub de escrita divergente')
            if expected != 200 and not isinstance(data.get('error'), str): raise RuntimeError('JSON de recusa inválido')
            if status != expected: raise RuntimeError('Autorização de escrita divergente: '+str(user))
            after = trace.read_bytes()
            if expected != 200 and after != before: raise RuntimeError('Escrita recusada chamou serviço')
            if expected == 200 and after != before+b'write\n': raise RuntimeError('Escrita autorizada não chamou stub esperado')
    finally:
        if proc.poll() is None: proc.terminate()
        try: proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
        if proc.poll() is None: raise RuntimeError('Servidor sem término confirmado')
        log.close()

print('REI168 HTTP: Security/guard reais, SQLite em memória, serviço stub e cleanup confirmado')
