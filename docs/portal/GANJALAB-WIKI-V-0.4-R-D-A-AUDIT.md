# GanjaLab / Wiki420 — V-0.4-R, fase D-A: auditoria e contrato de integração

**Data:** 2026-10-22 · **Estado:** auditoria estática fundamentada nos arquivos da PR #297; nenhuma rota, permissão, tela de moderação ou teste Runtime implantados.
**Fonte de design:** [V-0.4-D](WE-SATIVA-V-0.4-D-GANJALAB-MODERATION.md).

## Descoberta central: NÃO criar outra aba Wiki

A classe **`web/modules/custom/aculta_portal/modules/aculta_studio/src/Plugin/StudioTab/WikiTab.php` já existe** e registra a área `wiki`, peso 25, com seções:
- `verbetes`: tabs `todos`, `rascunhos`, `em-revisao`, `publicados` com listing de Node `wiki_entry`/workflow `wiki_editorial`;
- `revisoes`: `todas` com listing de revisão `wiki_entry`.

A aba hoje é exposta quando existe bundle `wiki_entry` e a conta tem `edit any wiki_entry content` **OU** `create wiki_entry content`. Isso é apropriado para capacidade editorial genérica, **mas não autoriza por si só moderação**, pois `email_confirmed` pode criar verbetes sem publicar.

**Decisão D-A:** manter a área Wiki e adicionar uma seção `moderacao` acessível somente mediante autorização real de revisão/publicação do backend Wiki; preservar Verbetes/Revisões para usuários que já as possam ver, respeitando granularidade. Não criar novo plugin StudioTab, rota `/wiki/moderacao` pública ou UI dentro de WE SATIVA.

## Contratos Studio observados

| Arquivo | Comportamento real | Implicação |
| --- | --- | --- |
| `WikiTab.php` | Plugin existente, `isAvailable` verifica tipo de Node, `access()` combina `edit any` e `create`, `sections()` declara listings | filtrar/modular **seção de moderação separadamente**, não aplicar acesso da aba a todas seções |
| `StudioPresenter.php` | esconde aba sem seção visível; seção se mantém com listing ou action autorizada | novo listing requer provider de query/access; adicionar item por si só não prova autorização |
| `StudioController.php` | resolve links de área/seção/aba, retorna 404 para área fora do alcance e constrói root com cache contexts | revisar propagação de cache dos serviços de revisão e evitar listagem visível por `create` genérico |
| `aculta_studio.routing.yml` | `/ganjalab`, `/{aba}`, `/{aba}/{secao}`, `/{aba}/{secao}/{sub}`, purpose `account`; aliases /studio | proposta `/ganjalab/wiki/moderacao/aguardando` é **destino planejado**, não rota nova; usa estrutura existente após implementação |
| `js/studio-navigation.js` | AJAX drupal_ajax, pushState, popstate, aria-live, foco no heading, fallback à URL normal | manter enhancement progressivo; janela/modal não pode quebrar back/forward, teclado, erro e fallback |
| `aculta_wiki.routing.yml` | home, busca, prévia e settings administrativo; **não há operação canônica de revisão Wiki específica** | antes da janela funcional, criar contrato/rotas Wiki autorizadas para conteúdo e POST, sem publicar um endpoint inseguro |
| `workflows.workflow.wiki_editorial.yml` | draft, review, published, transições review_to_draft e review_to_published | ações na janela devem corresponder a transições reais do workflow |

## Contrato de ownership e camadas

- **aculta_wiki**: define service/provider da fila, revision-base, acesso por entidade/revisão, transições oficiais, validação de concorrência, formulário CSRF, cache/invalidação e persistência de devolutiva (somente quando houver campo/contrato).
- **aculta_studio**: usa a área Wiki e adiciona seção/aba de Moderação; mostra filtros e abre página/painel GanjaLab, sem recriar armazenamento, publicar diretamente ou substituir workflow.
- **aculta_user**: política de primeiro verbete e email_confirmed; não guardar fila.
- **aculta_messenger**: eventuais avisos de recebido/devolvido/publicado, via adapter idempotente, não caixa adicional.
- **aculta420**: composição estritamente Core SDC/UI Patterns/UI Styles; shell multidomínio preservado.

## Especificação concreta da seção de moderação

**Navegação conceitual**: GanjaLab → Wiki → Moderação → Aguardando. Não criar tab `moderacao` para qualquer `create wiki_entry content`; exigir capacidade editorial **real de transição** e acesso à fila do backend.

**Fila:** somente estados `review` e propostas que já tenham provider seguro; campos título, tipo de envio, autor conforme privacidade, data oficial, revisão e ação `Analisar` autorizada. `Primeiro verbete` é rótulo calculado pela política User; não um estado novo. Paginação e filtros de consulta com acesso e cache, sem exportar rascunho de terceiros.

**Janela:** desktop painel amplo ou modal acessível; mobile página completa (sem modal que tape teclado). Conteúdo publicado versus revisão proposta; se primeiro verbete, não mostrar “versão anterior” fictícia; fontes/media/histórico oficiais; campo de motivo de devolução só se backend persistir. Botões somente `Voltar`, `Devolver para rascunho`, `Publicar` quando os grants e estados permitirem. Toda decisão é POST do Wiki e revalida sessão, finalidade de domínio, conteúdo, revisão-base, permissão, workflow e concorrência.

**Dados de retorno:** após ação confirmada pelo Wiki, Studio atualiza lista e devolve sucesso/erro real; WE SATIVA lista contribuições próprias e ajustes solicitados, sem tela de moderação.

## Gaps bloqueadores de implementação

1. Distinguir **capacidade de criar conteúdo** de **capacidade de moderar** ao montar `WikiTab.sections()`; auditar `StudioListingBuilder` para alcance de revisions; nenhuma fila administrativa deve aparecer a contribuinte somente email_confirmed.
2. Mapear e/ou oferecer service de lista autorizada em `aculta_wiki` com `AccessResultInterface` e cache metadata. Não usar apenas `listing` do Studio como gate de segurança.
3. Definir rota/contrato de ação para revision específica e POST seguro no domínio de conta, encaminhando ao módulo Wiki sem bypass, sem revelar conteúdo por query.
4. Mapear campos de justificativa e histórico disponíveis antes de prometer notas de devolução persistidas.
5. Definir como propostas para verbetes de terceiros serão armazenadas/moderadas. Não tratar `edit own` como `edit any`.
6. Garantir que a instalação/habilitação real de `aculta_wiki` e do Studio estejam em conformidade com as configs antes de habilitar links.
7. Revisar acesso por Domain/purpose account (GanjaLab) versus wiki (conteúdo), cache contexts user/roles/permissions e invalidação por revisão.

## Plano de execução granular

| Marco | Atividade | Critério de saída |
| --- | --- | --- |
| **D-A.1** | inventário WikiTab/StudioPresenter/controller/rotas/JS e ownership | **DOCUMENTADO nesta fase** |
| **D-A.2** | examinar StudioListingBuilder e caches/grants com testes de caracterização | pendente |
| **D-B** | stories visual SDC de fila/janela em 320–1440px, sem criar backend fictício | pendente |
| **D-C** | integrar seção Wiki/Moderação à aba existente quando provider de acesso estiver pronto | pendente |
| **D-D** | formulário de transições/revisões delegadas ao aculta_wiki e prevenção de corrida | pendente |
| **D-E** | avisos Messenger e acompanhamento WE SATIVA | pendente |
| **D-R** | homelab: roles, domínio, revisões, HTTP/sem JS, a11y, AJAX, light/dark | pendente |

**V-0.4-R é aqui um gate de auditoria estática**, não homologação Runtime. Não houve smoke real, testes de navegador ou aprovação visual. Autosave Form segue futuro.
