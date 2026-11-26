const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');

module.exports = function render(root) {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'vara-mobile-operations-'));
    const put = (name, data) => {
        const file = path.join(dir, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, data);
    };
    const copy = name => put(name, fs.readFileSync(path.join(root, name)));
    const views = {dashboard:'dashboard',process:'consulta-processos-edit',create:'cartorio-guias-create',edit:'cartorio-guias-edit'};
    put('config/security.php', '<?php');
    try {
        for (const name of [...Object.values(views), '_internal_head_styles','_internal_head_css','_toast_script','_theme_script','_profile_saved_reload_parent','_cartorio_alocacao_direcionada_create','_cartorio_alocacao_direcionada_edit','_cartorio_timeline','_datajud_scripts']) copy('app/views/internal/'+name+'.php');
        for (const name of ['access/ProfileNormalizer','helpers/ProcessResponsibleSectorDefault','helpers/ParteDocumentoHelper']) copy('app/'+name+'.php');
        put('app/helpers/Conection.php', `<?php class Conection {
            static function getInstance(){return new self;}
            function getConnection(){
                $db=new PDO('sqlite::memory:');
                $db->exec("CREATE TABLE processos(id TEXT, id_setor_responsavel TEXT); CREATE TABLE setores(id_setor TEXT, nome TEXT, ativo INT, codigo_area TEXT); INSERT INTO processos VALUES ('P', NULL); INSERT INTO setores VALUES ('S', 'Cartório', 1, 'CARTORIO')");
                return $db;
            }
        }`);
        const stub = (folder, name, methods='') => put('app/'+folder+'/'+name+'.php', '<?php class '+name+' {function __construct(...$args){} '+methods+'}');
        stub('services','ConsultaProcessosService', `function get($id){return ['id'=>'P','numero_processo'=>'0000000-00.2026.0.00.0000','classe_nome'=>'Classe Sintética Com Nome Longo','sigiloso'=>0,'assuntos'=>[],'partes'=>[]];}`);
        stub('services','ProcessoPrioridadesService', 'function getEstadoEdicaoPorProcessoId(...$args){return []; }');
        stub('repositories','SalaAudienciasPautaParteRepository','function countPartesMenoresAtivasPorProcesso($id){return 0;}');
        for (const name of ['PartesDocumentosRepository','PartesInstituicoesRepository','ProcessosRepository','ClassesRepository']) stub('repositories',name);
        stub('repositories','AlocacoesRegrasRepository','function findByRegra($id){return null;}');
        stub('repositories','GuiasAlocadosRepository','function findByGuiaId($id){return [];}');
        stub('repositories','GuiasSubtarefasRepository', Array.from({length:8},(_,i)=>'const TIPO_ORDEM_'+(i+1)+'="TIPO_'+(i+1)+'";').join(' ')+' function ensureOitoSubtarefas($id){} function findByGuiaId($id){return [];}');
        stub('services','GuiasService', `function find($id){return ['id'=>'G','numero_processo'=>'0000000-00.2026.0.00.0000','status'=>'pendente','tipo'=>'Guia de Execução Definitiva','denunciado'=>'Pessoa Sintética','observacoes'=>'Observação sintética'];}`);
        stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return ["ADMIN"]; }');
        stub('helpers','AlocacaoTarefasPerfisHelper','static function usuarioAdminOuChefiaPodeAlocarOutrosCadastro($id){return true;}');
        stub('helpers','AlocacaoRegrasUsuariosHelper','static function listUsuariosInternos($db){return [["id"=>"S","name"=>"Pessoa sintética de teste"]];}');
        stub('helpers','CartorioAlocacaoChipHelper','static function ordenarAlocadosPorTipo($items){return $items;}');
        put('app/helpers/CartorioTimelineHelper.php', `<?php function cartorioTimelineBuildViewData(...$a){ return [
          'showProgress'=>true,'progressValue'=>1,'progressMax'=>8,'currentTaskTitle'=>'Aguardando a revisão e o preenchimento das informações da guia para liberar a próxima etapa',
          'segments'=>array_fill(0,8,['class'=>'pendente','title'=>'Etapa sintética']),
          'cadastro'=>['minorText'=>'Guia cadastrada','dateLabel'=>'26/11/2026 às 10:00'],
          'rows'=>[['minorText'=>'Aguardando expedição','majorText'=>'Detalhamento bastante longo da atividade em andamento, representando instruções operacionais','dateLabel'=>'26/11/2026 às 10:00']]
        ]; }`);
        const html = {};
        for (const [key,view] of Object.entries(views)) {
            put('render.php', `<?php class Security {static function requireAuthentication(){} static function generateCSRFToken(){return 'fixture';}} require_once __DIR__.'/app/helpers/Conection.php';
                $_SESSION=['id_usuario'=>'FIXTURE']; $_GET=['id'=>${JSON.stringify(key==='edit'?'G':'P')},'page'=>${JSON.stringify(key==='process'?'consultaprocessosedit':key==='create'?'guiascreate':key==='edit'?'guiasedit':'dashboard')}]; $userPerfils=['ADMIN'];
                require __DIR__.'/app/views/internal/${view}.php';`);
            html[key]=execFileSync(process.env.PHP_BIN || 'php',['-d','display_errors=stderr',path.join(dir,'render.php')],{encoding:'utf8'});
        }
        return html;
    } finally {
        fs.rmSync(dir,{recursive:true,force:true});
    }
};
