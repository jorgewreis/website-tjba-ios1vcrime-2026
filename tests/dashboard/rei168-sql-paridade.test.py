"""SELECT/VALUES somente em SQLite memória; compara SQL real com classificador PHP."""
import itertools
import json
import sqlite3
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
helper = root / 'app/helpers/CartasExpedidasEstadoFluxo.php'
query = subprocess.check_output(['php', '-r', 'require $argv[1]; echo CartasExpedidasEstadoFluxo::consultaEstados();', str(helper)], text=True, encoding="utf-8")
cases = []
for parent, send, back in itertools.product(['em_andamento', 'cancelada'], [None, 'pendente', 'em_andamento', 'concluida', ' Concluída ', 'invalido'], [None, 'pendente', 'em_andamento', 'concluida', 'invalido']):
    rows = []
    for kind, order, status in [('aguardando_envio', 1, send), ('aguardando_devolucao', 2, back)]:
        if status is not None:
            rows.append(dict(id=str(order), tipo=kind, ordem=order, status=status, updated_at='2026-11-20 12:00:00'))
    cases.append(dict(parent=parent, rows=rows))
for order in [None, 0, 2]:
    cases.append(dict(parent='em_andamento', rows=[dict(id='e',tipo='aguardando_envio',ordem=order,status='pendente',updated_at=None)]))
cases.append(dict(parent='em_andamento',rows=[dict(id=str(i),tipo='aguardando_envio',ordem=1,status='pendente',updated_at=None) for i in range(2)]))
php = 'require $argv[1]; $cases=json_decode(stream_get_contents(STDIN),true); echo json_encode(array_map(fn($c)=>CartasExpedidasEstadoFluxo::classificar($c["rows"],$c["parent"]),$cases));'
expected = json.loads(subprocess.check_output(['php','-r',php,str(helper)],input=json.dumps(cases),text=True, encoding="utf-8"))
with sqlite3.connect(':memory:') as conn:
    conn.row_factory = sqlite3.Row
    for case, result in zip(cases, expected):
        rows = case['rows'] or [dict(id='dummy',tipo='historico',ordem=8,status='pendente',updated_at=None)]
        prefix = 'WITH cartas_expedidas(id,status,prazo_dias) AS (VALUES (?,?,?)), cartas_expedidas_subtarefas(id,carta_expedida_id,tipo,ordem,status,updated_at) AS (VALUES '+','.join('(?,?,?,?,?,?)' for _ in rows)+') '
        args = ['c',case['parent'],5]
        for row in rows:
            args += [row['id'],'c',row['tipo'],row['ordem'],row['status'],row['updated_at']]
        actual = dict(conn.execute(prefix+query,args).fetchone())
        assert (actual['etapa'], actual['diagnostico']) == (result['etapa'],result['diagnostico']), (case,actual,result)
print(f'PASS REI168: {len(cases)} classificações PHP/SQL equivalentes; apenas SELECT/VALUES.')


# Execute the actual repository WHERE builder against a mixed synthetic set.
repository = root / 'app/repositories/CartasExpedidasRepository.php'
php_filters = 'require $argv[1]; $r=(new ReflectionClass(CartasExpedidasRepository::class))->newInstanceWithoutConstructor(); $m=new ReflectionMethod($r,"buildFilterClause"); $o=[]; foreach (["","aguardando_envio","aguardando_cumprimento","concluída","indeterminada"] as $status) {$o[$status]=$m->invoke($r,["status"=>$status],"ce",true);} echo json_encode($o);'
filters = json.loads(subprocess.check_output(['php','-r',php_filters,str(repository)],text=True,encoding='utf-8'))
parents = []
tasks = []
for index, case in enumerate(cases):
    letter = str(index)
    parents.append((letter,case['parent'],5))
    for row in case['rows']:
        tasks.append((letter+row['id'],letter,row['tipo'],row['ordem'],row['status'],row['updated_at']))
def quote(value):
    if value is None: return 'NULL'
    if isinstance(value,int): return str(value)
    return "'"+str(value).replace("'","''")+"'"
prefix = 'WITH cartas_expedidas(id,status,prazo_dias) AS (VALUES '+','.join('('+','.join(map(quote,row))+')' for row in parents)+'), cartas_expedidas_subtarefas(id,carta_expedida_id,tipo,ordem,status,updated_at) AS (VALUES '+','.join('('+','.join(map(quote,row))+')' for row in tasks)+') '
with sqlite3.connect(':memory:') as conn:
    for requested,(where,params) in filters.items():
        actual = set(row[0] for row in conn.execute(prefix+'SELECT ce.id FROM cartas_expedidas ce WHERE '+where,{k.lstrip(':'):v for k,v in (params or {}).items()}))
        wanted = set()
        for index,(case,state) in enumerate(zip(cases,expected)):
            stage=state['etapa']
            status={'envio':'em_andamento','devolucao':'aguardando cumprimento','indeterminada':'indeterminada'}.get(stage,'cancelada' if case['parent']=='cancelada' else 'concluída')
            selected={'aguardando_envio':'em_andamento','aguardando_cumprimento':'aguardando cumprimento'}.get(requested,requested)
            if (not requested and stage!='encerrada') or (requested and status==selected): wanted.add(str(index))
        assert actual==wanted,(requested,actual,wanted)
print('PASS REI168: repository filters select identical known/ambiguous/closed sets before pagination.')

