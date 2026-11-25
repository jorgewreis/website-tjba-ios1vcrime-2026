"""Renderiza view copiada com serviço fake, sem abrir banco ou executar serviço de atas."""
import os, pathlib, re, shutil, subprocess, tempfile
root=pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='pauta-render-') as tmp:
    base=pathlib.Path(tmp)
    for name in ('app/views/internal/sala-audiencias-atas-estudantes.php','app/helpers/SalaAudienciasEstudantePrivacidade.php'):
        dest=base/name; dest.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(root/name,dest)
    stubs={
      'app/helpers/Conection.php':'<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}',
      'app/models/Usuario.php':"<?php class Usuario {function listarPerfisUsuario($u){return ['ESTUDANTE'];}}",
      'app/access/ProfileNormalizer.php':'<?php class ProfileNormalizer {static function normalize($p){return $p;} static function hasEstudante($p){return true;}}',
      'app/helpers/UserAccessHelper.php':'<?php class UserAccessHelper {static function isUsuarioExterno($p){return true;}}',
      'app/services/SalaAudienciasAtaService.php':'''<?php class SalaAudienciasAtaService {
         function __construct($db){} static function ocultarDaListagemAtasPorPrazoSemDownload($r){return false;}
         function listMinhasInscricoesEstudante($u){$rows=[];if(getenv('REI161_LARGE')) {for($i=0;$i<20;$i++) $rows[]=['pauta'=>'BLOQUEADA-'.$i,'processo_sigiloso'=>1];return $rows;} foreach(['processo_sigiloso','qtd_menor','tem_prioridade_bloqueio'] as $flag){$rows[]=['pauta'=>'BLOQUEADA-'.$flag,$flag=>1,'numero_processo'=>'SEGREDO-'.$flag,'data_audiencia'=>'2034-03-04','hora_audiencia'=>'15:47','arquivo_relativo'=>'SEGREDO.pdf','id_ata'=>'SEGREDO','expires_at'=>'2099-01-01','link_audiencia'=>'https://segredo.invalid'];}return $rows;}}
      ''',
      'app/views/internal/_internal_head_styles.php':'<?php',
      'app/views/internal/_toast_script.php':'<?php',
      'app/views/internal/_copy_process_script.php':'<?php',
      'app/views/internal/_processo_bloco_listagem.php':"<?php echo htmlspecialchars((string)($row['numero_processo']??''));",
      'render.php':"<?php $_SESSION=['id_usuario'=>'SINTETICO']; include __DIR__.'/app/views/internal/sala-audiencias-atas-estudantes.php';"
    }
    for name,code in stubs.items():
        dest=base/name; dest.parent.mkdir(parents=True,exist_ok=True); dest.write_text(code,encoding='utf-8')
    env={k:os.environ[k] for k in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if k in os.environ}
    result=subprocess.run(['php',str(base/'render.php')],capture_output=True,env=env,check=True)
    html=result.stdout.decode('utf-8')
    rendered_html=html.split('<script>',1)[0]
    assert len(re.findall(r'<p class="padding-x-lg padding-y-md">Audiência indisponível\.</p>', rendered_html))==3
    assert rendered_html.count('data-inicial-indisponivel="1"')==3
    assert re.search(r'data-observacao="[a-f0-9]{32}"', rendered_html)
    assert 'id="rei161-lista-alterada" role="status" aria-live="polite" hidden' in rendered_html
    for secret in ('SEGREDO','2034','15:47','segredo.invalid','download.php','BLOQUEADA-'): assert secret not in html, secret
    # Exercise pagination and the 16-token session bound without a real server.
    (base/'render.php').write_text('''<?php
$_SESSION=['id_usuario'=>'SINTETICO']; $tokens=[]; $counts=[];
for ($i=0; $i<17; $i++) {
    $_GET['page_number']=$i===1 ? 2 : 1;
    ob_start(); include __DIR__.'/app/views/internal/sala-audiencias-atas-estudantes.php'; $html=ob_get_clean();
    preg_match('/data-observacao="([a-f0-9]{32})"/', $html, $match);
    if (!isset($match[1]) || str_contains($html,'BLOQUEADA-')) throw new RuntimeException('unexpected disclosure');
    $tokens[]=$match[1]; $counts[]=count($_SESSION['rei161_observacoes'][$match[1]]['ids']);
}
echo json_encode(['counts'=>$counts,'total'=>count($_SESSION['rei161_observacoes']),'first_present'=>isset($_SESSION['rei161_observacoes'][$tokens[0]]),'second_present'=>isset($_SESSION['rei161_observacoes'][$tokens[1]])]);
''',encoding='utf-8')
    env['REI161_LARGE']='1'
    large=subprocess.run(['php',str(base/'render.php')],capture_output=True,env=env,check=True)
    import json
    summary=json.loads(large.stdout.decode('utf-8'))
    assert summary['counts'][0] == 15 and summary['counts'][1] == 5
    assert summary['total'] == 16 and not summary['first_present'] and summary['second_present']
    print('PASS: view sintética de atas não expõe dados nem links bloqueados')
