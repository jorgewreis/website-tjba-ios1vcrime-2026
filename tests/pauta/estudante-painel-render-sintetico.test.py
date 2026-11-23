"""Renderiza o painel do estudante com bloqueio sintético, sem banco real."""
import os
import json
import pathlib
import re
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
    statuses = ('designada', 'atrasada', 'em_andamento', 'realizada', 'finalizada', 'cancelada', 'redesignada')
    block_flags = ('processo_sigiloso', 'envolve_menor', 'qtd_menor', 'tem_prioridade_bloqueio')
    flag_sets = [(flag,) for flag in block_flags] + [
        ('processo_sigiloso', 'envolve_menor'),
        ('envolve_menor', 'tem_prioridade_bloqueio'),
        tuple(block_flags),
    ]
    blocked_rows = []
    registered_rows = []
    expected_ids = []
    for index, (status, flags) in enumerate(((status, flags) for status in statuses for flags in flag_sets), start=1):
        for registered in (True, False):
            row_id = 'PAUTA-BLOQUEADA-' + str(index).zfill(2) + ('-I' if registered else '-N')
            row = dict(blocked, id_pauta=row_id, pauta=row_id, status=status, ja_inscrito=int(registered),
                       processo_sigiloso=0, envolve_menor=0, qtd_menor=0, tem_prioridade_bloqueio=0,
                       link_audiencia='https://segredo.invalid/' + row_id)
            for flag in flags:
                row[flag] = 1
            blocked_rows.append(row)
            expected_ids.append((row_id, registered))
            if registered:
                registered_rows.append(row)
    blocked_php = "[" + ','.join(
        "json_decode('" + json.dumps(row, separators=(',', ':')) + "', true)" for row in blocked_rows
    ) + "]"
    registered_php = "[" + ','.join(
        "json_decode('" + json.dumps(row, separators=(',', ':')) + "', true)" for row in registered_rows
    ) + "]"
    stubs = {
        'app/helpers/Conection.php': '<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}',
        'app/models/Usuario.php': "<?php class Usuario {function listarPerfisUsuario($u){return ['ESTUDANTE'];}}",
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer {static function normalize($p){return $p;} static function hasEstudante($p){return true;}}',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper {static function isUsuarioExterno($p){return true;}}',
        'app/services/SalaAudienciasAtaService.php': "<?php class SalaAudienciasAtaService {const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=5; function __construct($db){} function listHojeParaInscricaoEstudante($u){return " + blocked_php + "; } function listMinhasInscricoesEstudante($u){return " + registered_php + "; }}",
        'app/views/internal/_internal_head_styles.php': '<?php',
        'app/views/internal/_toast_script.php': '<?php',
        'app/views/internal/_processo_bloco_listagem.php': '<?php echo htmlspecialchars((string)($row["numero_processo"] ?? ""), ENT_QUOTES, "UTF-8");',
        'app/views/internal/_copy_process_script.php': '<?php',
        'render.php': "<?php $_SESSION=['id_usuario'=>'estudante-sintetico']; $_GET=['hoje_page'=>(int)(getenv('SYN_HOJE_PAGE') ?: 1),'minhas_page'=>(int)(getenv('SYN_MINHAS_PAGE') ?: 1)]; include __DIR__ . '/" + view + "';",
    }
    for name, code in stubs.items():
        dest = base / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(code, encoding='utf-8')
    env = {k: os.environ[k] for k in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if k in os.environ}
    rendered_today = []
    rendered_minas = []
    today_pages = (len(blocked_rows) + 14) // 15
    minhas_pages = (len(registered_rows) + 14) // 15
    assert len(expected_ids) == len(statuses) * len(flag_sets) * 2
    for page in range(1, today_pages + 1):
        page_env = dict(env, SYN_HOJE_PAGE=str(page), SYN_MINHAS_PAGE=str(min(page, minhas_pages)))
        result = subprocess.run(['php', str(base / 'render.php')], capture_output=True, env=page_env, check=True)
        page_html = result.stdout.decode('utf-8')
        hoje_start = page_html.index('id="card-audiencias-hoje-estudante"')
        minhas_start = page_html.index('id="card-minhas-inscricoes-estudante"')
        rendered_today.append(page_html[hoje_start:minhas_start])
        if page <= minhas_pages:
            rendered_minas.append(page_html[minhas_start:])
    today_html = '\n'.join(rendered_today)
    minhas_html = '\n'.join(rendered_minas)

    def blocked_cards(section_html, row_id):
        escaped_id = re.escape(row_id)
        pattern = re.compile(
            r'<div(?=[^>]*class="[^"]*\bregister-painel-estudante\b[^"]*")'
            r'(?=[^>]*data-pauta="' + escaped_id + r'")'
            r'[^>]*>(.*?)</div>', re.DOTALL
        )
        return [(match.group(0), match.group(1).strip()) for match in pattern.finditer(section_html)]

    for row_id, registered in expected_ids:
        today_cards = blocked_cards(today_html, row_id)
        minhas_cards = blocked_cards(minhas_html, row_id)
        assert len(today_cards) == 1, (row_id, 'today', len(today_cards))
        assert len(minhas_cards) == (1 if registered else 0), (row_id, 'minhas', len(minhas_cards))
        for section_name, cards in (('today', today_cards), ('minhas', minhas_cards)):
            for rendered_row, content in cards:
                assert 'data-indisponivel="1"' in rendered_row, (row_id, section_name)
                assert content == '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>', (row_id, section_name, content)
                assert not re.search(r'<a\b|\bhref\s*=', rendered_row, re.IGNORECASE), (row_id, section_name)
                assert all(status not in rendered_row for status in statuses), (row_id, section_name)
                assert all(secret not in rendered_row for secret in ('SEGREDO', '2034', '15:47', 'segredo.invalid', 'PROCESSO-SEGREDO')), (row_id, section_name)
    html = today_html + '\n' + minhas_html
    assert html.count('Audiência indisponível.') >= len(statuses) * len(block_flags) * 3
    for secret in ('SEGREDO', '2034', '15:47', 'segredo.invalid', 'PROCESSO-SEGREDO',
                   'data-status-label-pauta="PAUTA-BLOQUEADA-'):
        assert secret not in html, secret
    print('PASS: painel SSR redige 7 status × 7 combinações de bloqueio × inscrição existente/nova (98 casos)')
