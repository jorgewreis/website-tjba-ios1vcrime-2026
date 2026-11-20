"""Renderiza view copiada com serviço fake, sem abrir banco ou executar serviço de atas."""
import os, pathlib, shutil, subprocess, tempfile
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
         function listMinhasInscricoesEstudante($u){$rows=[];foreach(['processo_sigiloso','qtd_menor','tem_prioridade_bloqueio'] as $flag){$rows[]=['pauta'=>'BLOQUEADA-'.$flag,$flag=>1,'numero_processo'=>'SEGREDO-'.$flag,'data_audiencia'=>'2034-03-04','hora_audiencia'=>'15:47','arquivo_relativo'=>'SEGREDO.pdf','id_ata'=>'SEGREDO','expires_at'=>'2099-01-01','link_audiencia'=>'https://segredo.invalid'];}return $rows;}}
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
    assert html.count('Audiência indisponível.')==3
    for secret in ('SEGREDO','2034','15:47','segredo.invalid','download.php','BLOQUEADA-'): assert secret not in html, secret
    print('PASS: view sintética de atas não expõe dados nem links bloqueados')
