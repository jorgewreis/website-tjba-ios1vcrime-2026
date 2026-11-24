# REI-168: MySQL descartável no CI

O workflow `rei168-mysql.yml` exercita o código real de classificação, repositório e transição em MySQL 8.4 com `lower_case_table_names=0/1`. A fixture cria apenas tabelas sintéticas mínimas, depois de conferir banco, versão, marcador exclusivo e ausência de tabelas alheias.

## Cobertura

- Paridade da classificação SQL/PHP, envio ainda não realizado e devolução executável pendente.
- Prazos próximo/vencido, lista filtrada, count e paginação sem alteração dos dados.
- Conclusão de envio/devolução, repetição sem alteração de timestamp/dados/eventos, rejeição de devolução antecipada.
- Falhas MySQL `SIGNAL` em atualização do pai e inserção de evento: rollback integral e nova tentativa válida.
- Criação atômica da devolução ausente e recusa de fluxo com duplicatas.
- Renderização da partial real por `tests/ui/rei168-row.test.php`, sem banco.

- Cadastro pelo service real, recarga por nova conexao, mesmo ID antes/depois de envio e transferencia exata de contadores.
- Recusa MyISAM real antes de gravar e restauracao de lock_wait_timeout apos recusa/sucesso.

## Isolamento

`run-rei168-mysql-isolated.ps1` recusa execução fora do CI Linux antes de chamar Docker. Imagens base são fixadas por digest; runtime é construído sem rede e conferido por ID. Containers não têm rede nem portas publicadas. O banco usa datadir tmpfs; o único compartilhamento gravável é o volume de socket exclusivo. Código e fixture são montados somente leitura. Nenhuma configuração de aplicação, `.env` ou credencial de produção é montada. Containers e volume são identificados por rótulo/ID e a ausência após limpeza é conferida; falha de limpeza reprova o job.

## Limites

A fixture não é cópia do schema de produção, não executa navegador autenticado, não comprova implantação e não simula perda real de conexão durante commit. As falhas `false`/commit incerto são cobertas separadamente pelo teste FakePDO de transição. Esta suite precisa de resultado do CI remoto para alegar integração MySQL; lint/parser local não são evidência de integração.
