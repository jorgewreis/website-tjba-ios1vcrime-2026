# Regras de Sigilo e Visibilidade de Dados

## 1. Classificação da Informação

A segurança da informação no **WebApp | 1vCrimeIlheus** baseia-se em uma classificação rigorosa dos dados, dividindo-os em três níveis de sensibilidade: Público, Interno e Confidencial. Esta taxonomia orienta não apenas as regras de negócio, mas também a implementação técnica dos filtros de banco de dados e das interfaces de usuário. O objetivo é garantir a conformidade com a Lei Geral de Proteção de Dados (LGPD) e as normas da Corregedoria, evitando a exposição indevida de partes processuais e servidores.

Dados classificados como **Públicos** podem ser acessados sem autenticação. Dados **Internos** exigem autenticação e vínculo setorial. Já os dados **Confidenciais** possuem restrições severas, sendo acessíveis apenas por perfis de Chefia/Admin ou, no caso de senhas, sendo inacessíveis até mesmo para administradores.

### Níveis de Classificação

- **Público:** Pauta de audiências (sem sigilo), estatísticas gerais de produtividade, manuais de conduta, lista de servidores (apenas nomes).
- **Interno:** Detalhes de processos, movimentações, documentos anexos, dados cadastrais básicos de partes (endereço, telefone).
- **Confidencial:** Processos em segredo de justiça, logs de segurança, senhas (hashes), dados sensíveis de vítimas, anotações pessoais de magistrados.

### Dicas de Segurança e Boas Práticas

- **Princípio do Menor Privilégio:** Sempre classifique um novo dado como "Confidencial" por padrão até que se prove a necessidade de torná-lo "Interno" ou "Público".
- **Rotulagem:** Em relatórios impressos ou PDFs, inclua rodapés indicando o nível de sigilo (ex: "DOCUMENTO INTERNO - NÃO DIVULGAR").

## 2. Isolamento Lógico por Setor

O princípio fundamental de segurança interna é o **Isolamento por Setor**. Tecnicamente, isso significa que a autorização de acesso não é global, mas sim contextual. Um usuário com perfil de "Servidor" lotado na "Secretaria Criminal" não possui permissão, em nível de banco de dados, para realizar consultas (`SELECT`) em registros pertencentes ao "Gabinete do Juiz", a menos que possua uma permissão transversal explícita.

Esta regra impede a "navegação lateral" dentro do sistema. Mesmo que um usuário mal-intencionado tente manipular IDs na URL ou forçar chamadas de API, o sistema validará se o `id_setor` do registro solicitado coincide com o `id_setor` da sessão do usuário.

### Regras Técnicas de Implementação

- **Filtro SQL Obrigatório:** Toda consulta deve conter a cláusula `WHERE id_setor = :session_setor_id`.
- **Validação de API:** O *middleware* deve verificar se o token JWT pertence ao setor que está sendo consultado.
- **Bloqueio de IDs:** Tentar acessar `GET /api/processos/123` onde o processo 123 é de outro setor deve retornar `403 Forbidden`, e não `404 Not Found` (para evitar enumeração).

### Exemplo de Uso (SQL Seguro)

sql -- ERRADO (Vulnerável a acesso cruzado): SELECT * FROM tb_processos WHERE id_processo = 'PROC-100';

-- CORRETO (Isolamento garantido): SELECT * FROM tb_processos WHERE id_processo = 'PROC-100' AND id_setor = 5; -- ID do setor do usuário logado

## 3. Visibilidade na Área Pública

A Área Pública atua como uma vitrine de transparência, mas opera sob uma política de "Negação Padrão". Isso significa que nenhum dado interno é exposto publicamente, exceto aqueles explicitamente listados em uma *allowlist*. O Painel de Audiências em Tempo Real, por exemplo, exibe apenas processos que **não** estão marcados com a flag de "Segredo de Justiça".

Além da filtragem de processos sigilosos, a Área Pública não permite a busca ativa. Não existem campos de pesquisa por nome de parte ou CPF para usuários não autenticados. A visibilidade é passiva ou restrita ao próprio contexto do estudante logado.

### Restrições da Área Pública

- **Proibido:** Exibir nomes completos de vítimas em qualquer hipótese.
- **Proibido:** Exibir processos marcados com `segredo_justica = true`.
- **Proibido:** Ferramentas de busca livre (Google-like) para processos ou partes.
- **Permitido:** Listagem de audiências do dia (apenas horário, sala e iniciais das partes ou número do processo mascarado, conforme regra local).

### Dicas de Segurança

- **Sanitização de Saída:** Nunca envie o objeto completo do banco de dados para o *frontend* público. Crie DTOs (Data Transfer Objects) específicos que contenham apenas os campos seguros (ex: `PublicAudienciaDTO`).

## 4. Tratamento de Dados Sensíveis (Mascaramento)

Para proteger a privacidade dos indivíduos cadastrados, o sistema implementa técnicas de mascaramento visual em interfaces administrativas e públicas. O CPF, embora validado internamente pelos seus dígitos reais, deve ser sempre exibido com a máscara padrão dependendo do nível de permissão do visualizador.

No contexto do banco de dados, senhas e tokens de recuperação são tratados como dados de visibilidade nula. Senhas são armazenadas exclusivamente como *hashes* criptográficos irreversíveis.

### Regras de Mascaramento

- **CPF:** Exibir como `***.***.***-**` para perfis públicos/estudantes e `000.000.000-00` (completo) apenas para Chefias/Servidores no ato do cadastro.
- **E-mail:** Exibir como `usu***@email.com` em listagens públicas.
- **Senhas:** Nunca retornar o campo `senha` em consultas API, nem mesmo o hash.

### Exemplo de Implementação (PHP Helper)

<div class="widget code-container remove-before-copy"><div class="code-header non-draggable"><span class="iaf s13 w700 code-language-placeholder">php</span><div class="code-copy-button"><span class="iaf s13 w500 code-copy-placeholder">Copiar</span><img class="code-copy-icon" src="data:image/svg+xml;utf8,%0A%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2016%2016%22%20fill%3D%22none%22%3E%0A%20%20%3Cpath%20d%3D%22M10.8%208.63V11.57C10.8%2014.02%209.82%2015%207.37%2015H4.43C1.98%2015%201%2014.02%201%2011.57V8.63C1%206.18%201.98%205.2%204.43%205.2H7.37C9.82%205.2%2010.8%206.18%2010.8%208.63Z%22%20stroke%3D%22%23717C92%22%20stroke-width%3D%221.05%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%0A%20%20%3Cpath%20d%3D%22M15%204.42999V7.36999C15%209.81999%2014.02%2010.8%2011.57%2010.8H10.8V8.62999C10.8%206.17999%209.81995%205.19999%207.36995%205.19999H5.19995V4.42999C5.19995%201.97999%206.17995%200.999992%208.62995%200.999992H11.57C14.02%200.999992%2015%201.97999%2015%204.42999Z%22%20stroke%3D%22%23717C92%22%20stroke-width%3D%221.05%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%0A%3C%2Fsvg%3E%0A" /></div></div><pre id="code-w2sabdl67" style="color:white;font-family:Consolas, Monaco, &quot;Andale Mono&quot;, &quot;Ubuntu Mono&quot;, monospace;text-align:left;white-space:pre;word-spacing:normal;word-break:normal;word-wrap:normal;line-height:1.5;font-size:1em;-moz-tab-size:4;-o-tab-size:4;tab-size:4;-webkit-hyphens:none;-moz-hyphens:none;-ms-hyphens:none;hyphens:none;padding:8px;margin:8px;overflow:auto;background:#011627;width:calc(100% - 8px);border-radius:8px;box-shadow:0px 8px 18px 0px rgba(120, 120, 143, 0.10), 2px 2px 10px 0px rgba(255, 255, 255, 0.30) inset"><code class="language-php" style="white-space:pre;color:#d6deeb;font-family:Consolas, Monaco, &quot;Andale Mono&quot;, &quot;Ubuntu Mono&quot;, monospace;text-align:left;word-spacing:normal;word-break:normal;word-wrap:normal;line-height:1.5;font-size:1em;-moz-tab-size:4;-o-tab-size:4;tab-size:4;-webkit-hyphens:none;-moz-hyphens:none;-ms-hyphens:none;hyphens:none"><span class="token" style="color:rgb(127, 219, 202)">function</span><span> </span><span class="token function-definition" style="color:rgb(130, 170, 255)">mascararCpf</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(214, 222, 235)">$cpf</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(199, 146, 234)">{</span><span>
</span><span>    </span><span class="token" style="color:rgb(99, 119, 119);font-style:italic">// Transforma 12345678900 em ***.***.***-00</span><span>
</span><span>    </span><span class="token" style="color:rgb(127, 219, 202)">return</span><span> </span><span class="token double-quoted-string" style="color:rgb(173, 219, 103)">&quot;***.***.***-&quot;</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">.</span><span> </span><span class="token" style="color:rgb(130, 170, 255)">substr</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(214, 222, 235)">$cpf</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">-</span><span class="token" style="color:rgb(247, 140, 108)">2</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span class="token" style="color:rgb(199, 146, 234)">;</span><span>
</span><span></span><span class="token" style="color:rgb(199, 146, 234)">}</span><span>
</span></code></pre></div>

## 5. Hierarquia de Acesso a Logs e Auditoria

A visibilidade sobre as ações realizadas no sistema é um privilégio hierárquico restrito. Os **Logs de Auditoria** contêm informações sensíveis sobre a rotina de trabalho e possíveis vulnerabilidades exploradas. Portanto, o acesso a esses relatórios é bloqueado para perfis operacionais.

Esta segregação é vital para garantir a isenção em processos administrativos disciplinares. Um servidor não pode ter a capacidade de visualizar ou excluir os logs de suas próprias ações.

### Matriz de Acesso aos Logs

- **Chefia:** Visualiza logs apenas do seu próprio setor (quem fez o quê dentro do departamento).
- **Administrador:** Visualiza logs globais de todo o sistema (auditoria completa).
- **Servidor/Estagiário:** **Sem acesso** (menu oculto e API bloqueada).

### Boas Práticas de Auditoria

- **Imutabilidade:** A tabela de logs deve ter permissão `INSERT` e `SELECT`, mas `UPDATE` e `DELETE` devem ser revogados no banco de dados para o usuário da aplicação.
- **Registro Completo:** O log deve salvar o "Antes" e o "Depois" da alteração (snapshot JSON) para permitir *rollback* manual se necessário.
