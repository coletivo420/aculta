# Wiki420 / WE SATIVA — Moderação editorial e primeiro verbete

**Versão:** WES-MOD 0.1.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento antes da inspeção visual; sem alteração de Runtime.
**Arquitetura central:** [serviços compartilhados ACULTA](../../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).

## Retificação de produto vigente - 21/11/2026 (WES-MOD 0.2)

**Decisão expressa do responsável:** a moderação editorial e as denúncias da
Wiki pertencem ao **WE Sativa no domínio Wiki**, e NÃO ao Studio Marley,
GanjaLab, ou ao Fórum. Esta decisão substitui explicitamente a orientação
V-0.4-D, de 22/10/2026, que posicionava a fila Wiki no GanjaLab/Marley,
preservada abaixo apenas como histórico. Não implementar uma segunda fila:
o WE Sativa **consome os providers existentes do aculta_wiki**.

**Código publicado na feat/forum-paridade-discourse, sem branch nova:**

- Rotas Wiki: `/we-sativa/moderacao`, `/propostas`, `/verbetes`,
  `/denuncias` e `/denuncias/{aculta_cgu_report}/decidir` sob o prefixo.
- Acesso: `WikiModerationAccess` aplica domínio Wiki, grants reais por
  tarefa, conteúdo e Group; a equipe apenas Fórum recebe negação.
- Revisões: `WikiEditorialQueue::entryRevisions()`, com última revisão,
  `view revision`, `update` e transições do workflow `wiki_editorial`.
  O botão examina a revisão; publicação ou retorno ocorre somente pelo
  formulário/Content Moderation nativo.
- Propostas: `WikiEditorialQueue::proposals()` com `view` e `update`.
  A decisão preserva `WikiProposalDecisionForm`, o serviço
  `WikiProposalDecision` e o lifecycle `aculta_cgu`, sem gravar duas vezes.
- Denúncias: `CguReportQueue` recebe explicitamente purpose `wiki`.
  O filtro por alvo `wiki_entry`/comentário Wiki, `field_domain_source`,
  acesso `view` e `CguReport::access('view')` precede rótulos/pareceres.
  `CguReportDecisionForm` e `ReportService` permanecem o **único motor**
  de decisão de denúncia. Decidir denúncia não publica nem despublica verbete.
- SDC/UI Patterns: `wiki-moderation-workspace`,
  `wiki-moderation-case` (entry/proposal/report) e
  `wiki-moderation-decision` com stories, CSS tokens e slots Form API.
  Reutilizar ícones do tema Phosphor e shell institucional sem duplicá-lo.
- Integração Studio: `WikiContributorWorkspace` oferece link somente após
  `Url::access` cacheável e permite entrar na experiência ao usuário que só
  tenha grants de moderação Wiki. Não conceder `update` de Node a moderador CGU.

### Incremento independente - filtros de denúncias (21/11/2026)

A fila de denúncias utiliza o novo SDC `wiki-moderation-filters`, no
WE Sativa. Ele recebe por slot apenas os links já construídos pelo
`WikiModerationController`, com status validado em allowlist, GET sem
mutação e `aria-current=page` definido pelo backend. Não faz queries,
não expõe contagens de casos e não cria permissões. A navegação é
horizontal responsiva, possui foco visível e funciona sem JavaScript.
A story é estática e não contém nomes ou denúncias reais.

O contrato Unit de SDC verifica a presença do metadata, do Twig, do CSS
e da story. **Gates Unit/Kernel/Browser, PHPStan e revisão visual
permanecem DEFERRED** até execução isolada na worktree do agente; não
declarar homologação a partir deste commit.

### Matriz de aceite WES-MOD 0.2

| Conta / alvo | Comportamento obrigatório |
| --- | --- |
| Anônimo e colaborador sem grants | Todas as rotas de moderação negadas |
| Moderador apenas Fórum | Não acessa /we-sativa/moderacao nem decisões Wiki |
| Propostas Wiki (moderate+view any) | Apenas propostas; não verbetes nem denúncias |
| Editor de verbetes (edit any + transição) | Apenas revisões autorizadas; sem CGU |
| Moderador CGU Wiki | Apenas denúncias Wiki cujos alvos ele pode ler |
| Papéis Fórum+Wiki combinados | Nenhum parecer ou decisão do purpose Fórum pelas rotas Wiki |
| Alvo de Group/domínio não autorizado | Sem título, UID, histórico ou parecer; 403/404 |
| POST de proposta ou parecer | Form API CSRF, escopo e versão/estado revalidados |
| Decisão CGU procedente | Nunca despublicar automaticamente o Node |
| Stories UI Patterns | Render de 3 SDCs, responsividade 320px+, light/dark, teclado |
| Cache | Variação segura por usuário/domínio, sem vazamento entre contas |

**Gates de homologação pendentes:** Unit na feature, Kernel em banco
`simpletest` descartável, Playwright autenticado com fixture sintética,
PHPStan nível 10 no diff, PHPCS/SCSS/Stylelint e revisão visual UI Patterns.
Sem acesso ao Homelab daqui, **não declarar PASS local**. A suite de navegador
é `tests/browser/qa-wiki-moderation/`; SKIP por falta de fixture não é PASS.
Nenhum Runtime ou migração de dados foi executado por este pacote.

### Próxima fase WES-MOD 0.2-R

1. Executar PHPUnit Unit de `WikiModerationAccessTest` e
   `WikiModerationRoutingContractTest` na worktree EXISTENTE da feature.
2. Kernel: criar reports Wiki Node e Comment, conferir Domain/Group, mutação
   negativa pós-revogação, proposta dual-store e workflow de revisão.
3. Browser real: papéis separados/combinados, GET/POST com CSRF, foco, zoom,
   desktop/mobile e contraste dark/light, sem usar banco Runtime compartilhado.
4. Revisar o fluxo em `WikiContributorWorkspace` para não exibir duplicatas de
   listas editoriais após homologar a página própria de moderação.
5. Só após gates, aprovar integração de feature no destino `dev` conforme
   fluxo do agente; esta entrega não cria branch nem mexe no Runtime.


## Política aprovada

1. **Todo usuário com papel `email_confirmed` pode criar verbetes.** A confirmação é determinada pela política confiável de conta no servidor. O papel exportado já tem `create wiki_entry content`, `edit own wiki_entry content` e transições de rascunho/revisão.
2. **O primeiro verbete criado por cada colaborador é moderado**, seguindo o princípio existente no Fórum de primeira participação analisada. O sinal que encerra a condição de primeira contribuição é **a existência comprovada de um verbete previamente publicado do autor**, nunca envio à fila ou criação de rascunho.
3. **Somente os `content_editor` com acesso efetivo (e administradores segundo política vigente) aprovam/publicam**; `email_confirmed` não recebe a transição `review_to_published`.
4. **A política geral de sugestões continua moderada.** A publicação do primeiro verbete não libera publicação automática de novos verbetes ou revisões no fluxo atual. O marco de primeira moderação caracteriza uma primeira participação, mas não é atalho de publicação.
5. **Edição de verbete de terceiro:** permitir sugerir correções sem `edit any wiki_entry content`, em um modelo seguro de proposta moderável ainda a decidir. Não mudar o Node público antes da análise.
6. **Fórum e Wiki compartilham princípios e serviços neutros através do subkernel `aculta_user`, não por dependência direta entre submódulos.** Dados, workflows, contadores e grants de cada domínio permanecem com os proprietários.

## Fluxo editorial — verbete novo

```text
E-mail confirmado → WE SATIVA → Novo verbete
  → rascunho Node wiki_entry (revisão não publicada)
  → Enviar para revisão (`draft_to_review`)
  → fila Wiki (aculta_wiki, acesso de content_editor)
  → Verificar texto, fontes, direitos e duplicatas
     ├─ Devolver para ajustes (`review_to_draft`)
     └─ Publicar (`review_to_published`)
  → atualizar Meu trabalho + verbete público somente após transição real
```

`create_new_draft` is permitted from both `published` and `draft`. The second
source keeps the initial Node form's default state selectable as `draft`, so
contributors can save a private draft and later choose the separate
`draft_to_review` transition. The role `email_confirmed` still has no
`review_to_published` permission.

**Primeiro verbete:** identificar revisão inicial pela consulta canônica à história de publicações pertencentes à conta; essa decisão será derivada do serviço compartilhado de aculta_user + lookup Wiki, sem consultar comentários do Fórum. Testar criação de dois rascunhos simultâneos, primeira aprovação tardia, mudança de autor e edição concorrente. Para evitar condição de corrida, a fila não pode liberar publicação por UI apenas porque outro verbete foi publicado.

## Estrutura da moderação interna da Wiki

| Área | Funcionalidade | Responsável |
| --- | --- | --- |
| **Fila** | revisão de verbetes novos, sugestões em terceiros e revisões pendentes (indicadas separadamente) | `aculta_wiki` usando View/query com grants |
| **Item em revisão** | título, contexto, versão-base, proposta, fontes, conteúdo anterior e status | `aculta_wiki`, Form API, revision API |
| **Comparação** | original × sugestão × diferenças acessíveis, apenas se dados existirem | Core revision/diff quando disponível; não inventar comparador |
| **Decisão** | publicar, devolver para ajustes e orientar autor com motivo | Content Moderation / Form API / Entity Access |
| **Histórico** | trilha de decisões, revisores e timestamp autorizados; privacidade na Wiki pública | entidade/revision e logs oficiais |
| **Avisos** | confirmação ao autor, ajustes solicitados, aprovação e recusa, se serviço existente suportar | `aculta_messenger` por integração real |
| **Antispam** | limites, verificações de fonte, abuso e proteção da fila | serviço compartilhado de aculta_user neutro + políticas Wiki |

Na UI mobile-first, itens se apresentam como cartões enxutos e o revisor toma **uma decisão por vez**, sem exigir tabelas estreitas. Em desktop a fila pode usar filtros e colunas adicionais; todos os dados continuam permissionados. O shell global multidomínio permanece intacto.

## Diferença entre primeiro verbete e primeiras discussões

- `ParticipationRules::requiresFirstDiscussionModeration()` em `aculta_user` já analisa a primeira participação publicada em discussões, incluindo posts do Fórum; **não chamar esse método para Wiki**.
- Criar contrato genérico no `aculta_user` com adaptadores e lookup de participações publicadas por domínio somente após ADR/gates.
- Não migrar para o Portal os detalhes de Entity Query, workflow, menu, classes de tema, config ou UI do Fórum.
- Revisão de primeiro verbete e moderação de comentários no verbete são assuntos distintos; comentários usam contrato de discussão contextual do Fórum quando habilitado.

## Testes e critérios

- Usuário A com e-mail confirmado e nenhum verbete publicado: cria múltiplos rascunhos, envia primeiro e segundo à fila; nenhum é público antes da decisão do editor.
- Usuário B com e-mail não confirmado: não recebe ações nem POST habilitado.
- Content editor: recebe fila e consegue somente transições autorizadas; conta comum não consegue publicar por rota direta.
- Usuário já com verbete publicado: pode criar/editar próprio e enviar revisões; ainda depende de moderação para publicação.
- Tentativa de modificar verbete de outro autor: negar `update`; aceitar sugestão apenas após implementação de proposta moderável própria.
- Revisão pendente de Node publicado: preserva versão pública anterior.
- Fila respeita Domain/purpose, cache contexts/tags, acesso por entidade; sem vazamento entre usuários.
- 320–1440px, zoom 200/400%, teclado, sem JS, light/dark idênticos em geometria, H1 único, acessibilidade WCAG 2.2 AA.
- Nenhum teste foi executado neste marco documental.

## Implementação faseada

| Fase | Entrega |
| --- | --- |
| **WES-MOD 0.1-A** | auditoria estática do Fórum (`ParticipationRules`), papéis e workflow Wiki; decisões documentadas |
| **0.1-B** | ADR e contratos compartilhados no `aculta_user`, riscos de migração e isolamento |
| **0.1-C** | implementação e testes dos serviços centrais com adaptação do Fórum sem regressão |
| **0.1-D** | resolver primeiro verbete no adapter Wiki, rever acesso e mostrar fila real |
| **0.1-E** | proposta moderável para conteúdo de terceiros, com segurança e revisão-base |
| **0.1-F** | UI WE SATIVA da moderação e feedback, com SDC/UI Suite |
| **0.1-R** | homelab e testes reais de roles, domínio, revisão e acessibilidade |

**Dependência de planejamento:** concluir a fundação de moderação e a política central antes da próxima inspeção visual. Autosave Form continua futuro.

## Retificação de ownership (2026-10-22)

**A política compartilhada de moderação e primeira participação reside em `aculta_user`**, estendendo `ParticipationRules`, sem migração para `aculta_portal`. Wiki e Fórum permanecem donos de filas/workflows e entidades próprias. Esta decisão substitui indicações anteriores de centralizar essa política no Portal. [Contrato revisado](../../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).


## Interface da moderação — decisão V-0.4-D

**Fila, análise, devolução e publicação são exibidas em GanjaLab (`aculta_studio`)**, consumindo providers e Form API/Content Moderation de `aculta_wiki`. WE SATIVA exibe apenas contribuições próprias e retornos de moderação. [Contrato de moderação de contribuições](../../../../../../../docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md). Não desenvolver fila de content editors no WE SATIVA, ainda que seções de planejamento anteriores usem o termo “moderação interna da Wiki”: ele descreve backend/ownership, não frontend separado.
