# Catálogo local de comarcas e varas do TJBA

Fonte: [API oficial de Transparência](https://www.tjba.jus.br/portal/api/): GET /comarcas e GET /unidades. Não é uma consulta processual e não há chamada ao DataJud. A comarca no seletor só filtra as varas ativas. Ilhéus tem ID 2146 no catálogo, diferente de 0103 (origem no número CNJ); 4701 identifica a 1ª Vara Criminal no catálogo. Não se presume equivalência com códigos de outro sistema ou a vara atual de um processo.

A ficha lê `storage/catalogo-tjba.sqlite`, um banco independente com o último catálogo validado e a data de conferência. O arquivo persiste entre deploys. A unidade escolhida apenas copia o nome para a edição de vara já existente; o servidor continua exigindo a autorização vigente e dando precedência ao DataJud. Sem catálogo, permanece a edição manual. A rotina não regrava fichas nem arquiva unidades no banco processual.

Após o deploy aprovado, execute na raiz como usuário do site:

```sh
bash scripts/install-cron-catalogo-tjba.sh
```

O instalador faz a primeira carga, preserva as demais entradas do crontab e instala uma única execução às 03h17 do dia 1 de cada mês (horário do cron do servidor). Requer PHP com cURL e PDO SQLite 3 e escrita em `storage`. Confirme a entrada e examine `storage/logs/catalogo-tjba.log` na primeira execução mensal. Operação manual: `php scripts/atualizar-catalogo-tjba.php`. O editor não faz chamadas externas. Falha de rede/lista inválida mantém o último banco válido; só troca o arquivo após validar os dois conjuntos. A reversão operacional é remover somente a linha marcada `# ios1vcrime-catalogo-tjba` do cron; não é preciso tocar em fichas.
