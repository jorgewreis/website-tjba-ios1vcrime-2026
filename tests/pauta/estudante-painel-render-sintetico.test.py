"""Renderiza o painel do estudante com dados sintéticos, sem banco real."""
import json
import os
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

    statuses = ('designada', 'atrasada', 'em_andamento', 'realizada', 'finalizada', 'cancelada', 'redesignada')
    block_flags = ('processo_sigiloso', 'envolve_menor', 'qtd_menor', 'tem_prioridade_bloqueio')
    conditions = [
        ('privacy-' + '-'.join(flags), dict.fromkeys(flags, 1), 1)
        for flags in (
            ('processo_sigiloso',),
            ('envolve_menor',),
            ('qtd_menor',),
            ('tem_prioridade_bloqueio',),
            ('processo_sigiloso', 'envolve_menor'),
            ('envolve_menor', 'tem_prioridade_bloqueio'),
            block_flags,
        )
    ]
    conditions.extend(
        ('capacity-' + str(total), {}, total)
        for total in (1, 4, 5, 6, 10)
    )

    today_rows = []
    registered_rows = []
    expected = []
    for status in statuses:
        for condition_name, flags, total in conditions:
            for registered in (True, False):
                row_id = 'PAUTA-' + status.upper() + '-' + condition_name.upper() + ('-I' if registered else '-N')
                row = {
                    'id_pauta': row_id,
                    'pauta': row_id,
                    'processo_id': 'PROCESSO-SEGREDO',
                    'numero_processo': 'SEGREDO-CNJ',
                    'data_audiencia': '2034-03-04',
                    'hora_audiencia': '15:47',
                    'status': status,
                    'link_audiencia': 'https://segredo.invalid/' + row_id,
                    'processo_sigiloso': 0,
                    'envolve_menor': 0,
                    'qtd_menor': 0,
                    'tem_prioridade_bloqueio': 0,
                    'ja_inscrito': int(registered),
                    'total_inscritos_pauta': total,
                    'vagas_esgotadas': total >= 5,
                    'pode_inscrever': not registered and total < 5,
                }
                row.update(flags)
                today_rows.append(row)
                expected.append((row_id, status, condition_name, flags, total, registered))
                if registered:
                    registered_rows.append(row)

    assert len(conditions) == 12
    assert len(expected) == len(statuses) * len(conditions) * 2 == 168

    def php_array(rows):
        return '[' + ','.join(
            "json_decode('" + json.dumps(row, separators=(',', ':')) + "', true)"
            for row in rows
        ) + ']'

    today_php = php_array(today_rows)
    registered_php = php_array(registered_rows)
    stubs = {
        'app/helpers/Conection.php': '<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}',
        'app/models/Usuario.php': "<?php class Usuario {function listarPerfisUsuario($u){return ['ESTUDANTE'];}}",
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer {static function normalize($p){return $p;} static function hasEstudante($p){return true;}}',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper {static function isUsuarioExterno($p){return true;}}',
        'app/services/SalaAudienciasAtaService.php': "<?php class SalaAudienciasAtaService {const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=5; function __construct($db){} function listHojeParaInscricaoEstudante($u){return " + today_php + ";} function listMinhasInscricoesEstudante($u){return " + registered_php + ";}}",
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

    env = {key: os.environ[key] for key in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    today_pages = (len(today_rows) + 14) // 15
    minhas_pages = (len(registered_rows) + 14) // 15
    rendered_today = []
    rendered_minhas = []
    for page in range(1, today_pages + 1):
        page_env = dict(env, SYN_HOJE_PAGE=str(page), SYN_MINHAS_PAGE=str(min(page, minhas_pages)))
        result = subprocess.run(['php', str(base / 'render.php')], capture_output=True, env=page_env, check=True)
        html = result.stdout.decode('utf-8')
        today_start = html.index('id="card-audiencias-hoje-estudante"')
        minhas_start = html.index('id="card-minhas-inscricoes-estudante"')
        rendered_today.append(html[today_start:minhas_start])
        if page <= minhas_pages:
            scripts_start = html.index('<script src="/assets/js/modules/audiencias/atas-estudantes.js', minhas_start)
            rendered_minhas.append(html[minhas_start:scripts_start])
    today_html = '\n'.join(rendered_today)
    minhas_html = '\n'.join(rendered_minhas)

    outer_row = re.compile(
        r'<div class="display-flex col col-full register register-painel-estudante border-bottom" '
        r'data-pauta="([^"]+)"[^>]*>'
    )

    def rows_by_id(section):
        matches = list(outer_row.finditer(section))
        out = {}
        for index, match in enumerate(matches):
            end = matches[index + 1].start() if index + 1 < len(matches) else len(section)
            out[match.group(1)] = match.group(0) + section[match.end():end]
        return out

    today = rows_by_id(today_html)
    minhas = rows_by_id(minhas_html)
    assert len(today) == len(expected), (len(today), len(expected))
    assert len(minhas) == len(registered_rows), (len(minhas), len(registered_rows))

    terminal = {'realizada', 'finalizada', 'cancelada', 'redesignada'}
    for row_id, status, condition_name, flags, total, registered in expected:
        today_row = today.get(row_id)
        minhas_row = minhas.get(row_id)
        assert today_row is not None, (row_id, 'today missing')
        assert (minhas_row is not None) is registered, (row_id, 'my registrations membership')
        if flags:
            for section, rendered in (('today', today_row), ('minhas', minhas_row)):
                if rendered is None:
                    continue
                privacy_card = rendered.split('<div class="display-flex row row-full justify-between padding-x-md padding-y-md">', 1)[0]
                assert 'data-indisponivel="1"' in privacy_card, (row_id, section)
                assert privacy_card.count('Audiência indisponível.') == 1, (row_id, section)
                assert not re.search(r'<a\b|\bhref\s*=', privacy_card, re.IGNORECASE), (row_id, section)
                expected_card = (
                    '<div class="display-flex col col-full register register-painel-estudante border-bottom" '
                    f'data-pauta="{row_id}" data-indisponivel="1">'
                    '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p></div>'
                )
                assert expected_card in privacy_card, (row_id, section, privacy_card[:500])
                assert 'data-status-label-pauta' not in privacy_card, (row_id, section)
                for sensitive in ('PROCESSO-SEGREDO', 'SEGREDO-CNJ', '2034', '15:47', 'segredo.invalid'):
                    assert sensitive not in privacy_card, (row_id, section, sensitive)
            continue

        assert 'data-indisponivel="1"' not in today_row, (row_id, 'capacity must not redact today card')
        assert 'data-indisponivel="1"' not in (minhas_row or ''), (row_id, 'capacity must not redact registration')
        if status in terminal:
            assert 'href="https://segredo.invalid/' not in today_row, (row_id, 'terminal today link')
            if minhas_row:
                assert 'href="https://segredo.invalid/' not in minhas_row, (row_id, 'terminal registered link')
        else:
            assert 'href="https://segredo.invalid/' + row_id + '"' in today_row, (row_id, 'today link')
            if registered:
                assert minhas_row is not None and 'href="https://segredo.invalid/' + row_id + '"' in minhas_row, (row_id, 'registered link')

        if status in {'designada', 'atrasada'}:
            if registered:
                assert 'Voce esta inscrito.' in today_row, (row_id, 'registered label')
                assert 'mais nenhuma inscrição' not in today_row, (row_id, 'registered incorrectly shown as full')
            elif total >= 5:
                assert 'permitida mais nenhuma' in today_row, (row_id, 'full new registration notice')
                assert 'Inscrever-me' not in today_row, (row_id, 'new registration button at capacity')
            else:
                assert 'Inscrever-me' in today_row, (row_id, 'registration button below capacity')

    print('PASS: SSR 7 status × 12 privacidade/capacidade × inscrição existente/nova (168 casos)')
