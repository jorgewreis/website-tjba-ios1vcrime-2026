import pathlib, tempfile, shutil, subprocess
root=pathlib.Path.cwd()
for endpoint, service, method in [('instituicoes-publicas/update.php','InstituicoesPublicasService','update'),('pessoas/vinculos-instituicoes/update.php','PessoasService','updateVinculoInstituicao')]:
  for exists in (False,True):
    with tempfile.TemporaryDirectory() as tmp:
      base=pathlib.Path(tmp); target=base/'public/api'/endpoint; target.parent.mkdir(parents=True); shutil.copy(root/'public/api'/endpoint,target)
      for d in ('app/helpers','app/services'): (base/d).mkdir(parents=True,exist_ok=True)
      (base/'app/helpers/ApiAccessGuard.php').write_text('<?php class ApiAccessGuard {static function requireAccess($a,$b) {}}',encoding='utf8')
      (base/'app/helpers/Conection.php').write_text('<?php class Conection {static function getInstance(){return new self;} function getConnection(){return new stdClass;}}',encoding='utf8')
      (base/'app/helpers/BootstrapHelper.php').write_text('<?php',encoding='utf8')
      (base/f'app/services/{service}.php').write_text(f'<?php class {service} {{function __construct($x){{}} function {method}($id,$p){{return '+('true' if exists else 'false')+';}}',encoding='utf8')
      runner=base/'runner.php'; runner.write_text("<?php $_POST=['id'=>'synthetic']; register_shutdown_function(function(){fwrite(STDERR,'status='.(http_response_code() ?: 200));}); require "+repr(str(target).replace('\\','/'))+';',encoding='utf8')
      r=subprocess.run(['php',str(runner)],capture_output=True,text=True); assert r.returncode == 0, r.stderr; assert ('status=200' if exists else 'status=404') in r.stderr,(endpoint,r.stdout,r.stderr); assert ('"success":true' if exists else '"error"') in r.stdout,(endpoint,r.stdout)
print('REI-149 endpoint tests passed')
