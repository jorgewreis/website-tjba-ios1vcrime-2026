# Retenção de PDFs de atas

Listagens, downloads e uploads não executam retenção global. O prazo de download continua sendo validado nos serviços consumidores; após o vencimento, a ata não fica disponível para download mesmo que o arquivo ainda aguarde a próxima manutenção.

## Execução controlada

A retenção é uma operação exclusiva de CLI e exige confirmação textual:

```powershell
php scripts/purge-expired-ata-pdfs.php --execute --confirm=purge-expired-atas
```

Argumentos ausentes ou diferentes são recusados antes de carregar a configuração do banco. O script não instala cron nem foi executado em ambiente com dados reais.

Antes de remover arquivos, o processo registra um evento `ATA_RETENTION_AUDIT` no `error_log`; se o handler de log recusar o evento, a operação aborta. Também tenta gravar no `audit_log` pelo helper existente, que mantém o fallback operacional para `error_log`.

## Semântica e falhas

- Cada execução examina até 100 referências expiradas, em ordem de vencimento.
- Cada item é relido sob `SELECT ... FOR UPDATE`; a limpeza só prossegue se a linha ainda estiver vencida e apontar para o mesmo arquivo.
- O caminho precisa corresponder a um PDF diretamente em `storage/sala-audiencias-atas`; caminhos fora desse formato são preservados para correção manual.
- A referência e o tamanho só são limpos após remoção bem-sucedida ou confirmação de que o arquivo já está ausente. A linha da ata e seus demais metadados permanecem.
- Falha por item é registrada com identificador pseudonimizado; o item fica elegível para nova tentativa e os demais do lote continuam. O CLI registra resultado parcial e retorna código 2.
- Falha global retorna código 1. Um lote sem falhas retorna código 0. Uma execução parcial recorrente requer inspeção dos eventos no `error_log` e correção controlada do storage ou da referência.

O lote de 100 limita o trabalho de uma execução. Se mais de 100 itens antigos falharem persistentemente, as execuções seguintes podem encontrar os mesmos candidatos; monitore o resultado parcial e faça triagem antes de repetir em ambiente operacional.
