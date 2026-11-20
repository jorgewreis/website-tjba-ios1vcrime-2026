"""Renderiza o painel do estudante com bloqueio sintético, sem banco real."""
import os
import json
import pathlib
import subprocess
import tempfile

root = pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='pauta-painel-') as tmp:
    base = pathlib.Path(tmp)
    view = 'app/views/internal/sala-audiencias-painel-estudantes.php'
    helper = 'app/helpers/SalaAudienciasEstudantePrivacidade.php'
    for name in (view, helper):
        dest = base / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes((root / name).read_bytes())

    blocked = {
        'id_pauta': 'PAUTA-BLOQUEADA',
        'pauta': 'PAUTA-BLOQUEADA',
        'processo_id': 'PROCESSO-SEGREDO',
        'numero_processo': 'SEGREDO-CNJ',
        'data_audiencia': '2034-03-04',
        'hora_audiencia': '15:47',
        'status': 'designada',
        'link_audiencia': 'https://segredo.invalid/audiencia',
        'tem_prioridade_bloqueio': 1,
        'ja_inscrito': 1,
        'total_inscritos_pauta': 1,
    }
    blocked_unregistered = dict(blocked)
    blocked_unregistered.update({
        'id_pauta': 'PAUTA-NAO-INSCRITA',
        'pauta': 'PAUTA-NAO-INSCRITA',
        'ja_inscrito': 0,
        'qtd_menor': 1,
        'tem_prioridade_bloqueio': 0,
    })
    blocked_json = json.dumps(blocked, separators=(',', ':'))
    blocked_php = "json_decode('" + blocked_json + "', true)"
    unregistered_json = json.dumps(blocked_unregistered, separators=(',', ':'))
    unregistered_php = "json_decode('" + unregistered_json + "', true)"
    stubs = {
        'app/helpers/Conection.php': '<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}',
        'app/models/Usuario.php': "<?php class Usuario {function listarPerfisUsuario($u){return ['ESTUDANTE'];}}",
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer {static function normalize($p){return $p;} static function hasEstudante($p){return true;}}',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper {static function isUsuarioExterno($p){return true;}}',
        'app/services/SalaAudienciasAtaService.php': "<?php class SalaAudienciasAtaService {const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=5; function __construct($db){} function listHojeParaInscricaoEstudante($u){return [" + blocked_php + "," + unregistered_php + "]; } function listMinhasInscricoesEstudante($u){return [" + blocked_php + "]; }}",
        'app/views/internal/_internal_head_styles.php': '<?php',
        'app/views/internal/_toast_script.php': '<?php',
        'app/views/internal/_processo_bloco_listagem.php': '<?php echo htmlspecialchars((string)($row["numero_processo"] ?? ""), ENT_QUOTES, "UTF-8");',
        'app/views/internal/_copy_process_script.php': '<?php',
        'render.php': "<?php $_SESSION=['id_usuario'=>'estudante-sintetico']; include __DIR__ . '/" + view + "';",
    }
    for name, code in stubs.items():
        dest = base / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(code, encoding='utf-8')
    env = {k: os.environ[k] for k in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if k in os.environ}
    result = subprocess.run(['php', str(base / 'render.php')], capture_output=True, env=env, check=True)
    html = result.stdout.decode('utf-8')
    registered_rows = html.count('data-pauta="PAUTA-BLOQUEADA"')
    unregistered_rows = html.count('data-pauta="PAUTA-NAO-INSCRITA"')
    assert registered_rows == 2 and unregistered_rows == 1, (registered_rows, unregistered_rows)
    assert html.count('Audiência indisponível.') >= 2
    for secret in ('SEGREDO', '2034', '15:47', 'segredo.invalid', 'PROCESSO-SEGREDO', 'data-status-label-pauta="PAUTA-BLOQUEADA"'):
        assert secret not in html, secret
    print('PASS: painel SSR oculta status, dados, metadados e link de pauta bloqueada')
