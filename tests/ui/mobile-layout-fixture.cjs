const fs=require('node:fs'), path=require('node:path'),cp=require('node:child_process'),os=require('node:os');
module.exports=(src)=>{
 const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'vara-mobile-fixture-'));
 const put=(f,t)=>{const q=path.join(tmp,f);fs.mkdirSync(path.dirname(q),{recursive:true});fs.writeFileSync(q,t)};
 const copy=f=>put(f,fs.readFileSync(path.join(src,f)));
 for (const name of ['alterar-dados-pessoais','sala-audiencias-painel-estudantes','_processo_bloco_listagem','_internal_head_styles','_internal_head_css','_theme_script','_toast_script','_copy_process_script'])copy('app/views/internal/'+name+'.php');
 for(const f of ['helpers/UserAccessHelper','helpers/SexoHelper','helpers/SalaAudienciasEstudantePrivacidade','access/ProfileNormalizer'])copy('app/'+f+'.php');
 put('app/helpers/Conection.php','<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}');
 put('app/models/Usuario.php',`<?php class Usuario {
 function listarPerfisUsuario($id){return $id==='PROFILE' ? ['SERVIDOR-CARTORIO'] : ['ESTUDANTE'];}
 function getDadosPessoais($id){return ['nome'=>'Pessoa Sintética','sobrenome'=>'Nome Comprido de Teste Responsivo','cpf'=>'000.000.000-00','email'=>'fixture.test@example.invalid','telefone'=>'0000','sexo'=>'Outro','cargo'=>'Servidor','instituicao'=>'Cartório'];}
 }`);
 put('app/repositories/AlocacoesRegrasRepository.php','<?php class AlocacoesRegrasRepository {function __construct($db){} function findByUsuarioId($id){return [];}}');
 put('app/helpers/ProcessoBlocoListagemHelper.php','<?php class ProcessoBlocoListagemHelper {static function deveMascararNumeroNaVarianteEstudantes($row){return false;}}');
 put('app/services/SalaAudienciasAtaService.php',`<?php class SalaAudienciasAtaService {
 const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=5;
 function __construct($pdo){}
 static function statusIndisponivelParaEstudanteNaoInscrito($row){return false;}
 function listHojeParaInscricaoEstudante($id){
  $item=['id_pauta'=>'FIXTURE','data_audiencia'=>date('Y-m-d',time()+86400),'hora_audiencia'=>'13:30:00','status'=>'designada','tipo_audiencia'=>'instrução e julgamento','numero_processo'=>'0000000-00.2026.0.00.0000','bloco_processo_classe_texto'=>'Classe processual sintética com rótulo longo','bloco_processo_assunto_texto'=>'Um assunto longo de exemplo para validar quebra responsiva sem rolagem lateral','bloco_processo_polo_passivo_texto'=>'Pessoa sintética de teste','pode_inscrever'=>true,'link_audiencia'=>'https://audiencia.example.invalid/sala'];
  return [$item,array_merge($item,['id_pauta'=>'OFF','indisponivel'=>true])];
 }
 function listMinhasInscricoesEstudante($id){return [];}
 }`);
 put('profile.php',`<?php require __DIR__.'/app/helpers/Conection.php'; require __DIR__.'/app/models/Usuario.php'; class Security{static function generateCSRFToken(){return 'synthetic';}} $_SERVER['REQUEST_METHOD']='GET'; $_SESSION=['id_usuario'=>'PROFILE']; $usuario=new Usuario; require __DIR__.'/app/views/internal/alterar-dados-pessoais.php';`);
 put('student.php',`<?php $_SERVER['REQUEST_METHOD']='GET'; $_SESSION=['id_usuario'=>'SYNTHETIC']; require __DIR__.'/app/views/internal/sala-audiencias-painel-estudantes.php';`);
 const php=process.env.PHP_BIN || 'php';
 try {return Object.fromEntries(['profile','student'].map(n=>[n,cp.execFileSync(php,['-d','display_errors=stderr',path.join(tmp,n+'.php')],{encoding:'utf8'})]));}
 finally{fs.rmSync(tmp,{recursive:true,force:true});}
};
