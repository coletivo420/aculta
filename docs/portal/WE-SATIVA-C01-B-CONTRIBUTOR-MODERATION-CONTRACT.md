# WE SATIVA — C01-B: contribuição por e-mail confirmado e publicação moderada

**Versão:** WES-UX 0.3-C01-B / 2026-10-22 · **Estado:** decisão aprovada e auditoria de configuração exportada; Runtime não executado.
**Decisão do produto:** **conta com e-mail confirmado pode contribuir para a Wiki420. Toda publicação resultante das sugestões passa pela moderação dos content editors.** WE SATIVA continua independente de GanjaLab/GanjaLab420, mobile-first e baseado em SDC/UI Suite.

## 1. Evidência: o projeto já contém grande parte da fundação

| Configuração | O que já existe na branch | Implicação |
| --- | --- | --- |
| `user.role.email_confirmed.yml` | `create wiki_entry content`; `edit own wiki_entry content`; `use text format wiki`; `view own unpublished content` | **contas com papel email_confirmed já podem criar e editar seus próprios verbetes no modelo exportado**; não criar outro papel sem necessidade |
| Mesmo papel | `use wiki_editorial transition create_new_draft`, `draft_to_review`, `review_to_draft`; **não** possui `review_to_published` | fluxo de rascunho e envio à revisão já está previsto; publicação não concedida |
| `user.role.content_editor.yml` | `edit any wiki_entry content`, `use wiki_editorial transition review_to_published`, além das transições anteriores | content editors são os revisores/publicadores; não delegar estas capacidades a email_confirmed |
| `workflows.workflow.wiki_editorial.yml` | estados `draft`, `review`, `published`; transições criar rascunho de nova revisão, enviar, devolver e publicar | aproveitar Content Moderation existente, sem mecanismo paralelo de aprovação |
| `core.entity_form_display.node.wiki_entry.default.yml` | campos `title`, `body`, síntese, fontes, imagem, categorias e relacionados | Form API pode sustentar o editor completo; reavaliar campos expostos por capacidade |
| `aculta_wiki.routing.yml` | somente home, busca e preview | **WE SATIVA ainda não tem rota específica**; não publicar CTA fictício |

**Correção explícita de planejamento:** a C01-A anterior examinou `authenticated` e `content_editor`, mas não o papel `email_confirmed`; portanto sua afirmação de que era necessária uma capacidade nova para criar/editar conteúdo próprio estava **incompleta**. Para o próprio conteúdo, aproveitar permissões já exportadas; para edição colaborativa de verbetes de terceiros, ainda há uma lacuna real.

## 2. Regra editorial para as duas modalidades

### A. Criar ou editar verbete próprio — suporte previsto na configuração

1. Conta tem e-mail confirmado **e mantém a capacidade efetiva no servidor** (não bastam classe CSS, rótulo de perfil nem flag enviada pelo navegador).
2. O usuário abre WE SATIVA, preenche a Form API e salva em `draft`, quando a transição real é válida.
3. Envia a versão a `review` via transição oficial.
4. Content editor verifica fontes e qualidade, devolve a rascunho ou publica por `review_to_published`.
5. Usuário acompanha apenas seus próprios rascunhos, alterações e resultados, respeitando Entity Access e cache.

### B. Sugerir alteração em verbete de outra pessoa — **não está habilitado pelo papel atual**

A permissão `edit own wiki_entry content` **não autoriza atualizar qualquer Node de terceiros**, inclusive um verbete publicado. Não adicionar `edit any wiki_entry content` ao papel email_confirmed para criar facilidade de contribuição.

**Decisão de desenho:** a UX oferecerá microtarefas sobre verbetes existentes, mas o backend deve receber propostas sem alterar o Node publicado. Uma proposta moderável precisa estar vinculada ao Node, à revisão-base, ao autor da sugestão, à intenção, ao trecho/alteração e às fontes, sem divulgação pública antes de aprovada. Após a decisão editorial, o content editor é quem incorpora a mudança em revisão oficial e publica conforme workflow. Avaliar primeiro os serviços/entidades nativos já existentes no repositório; se não houver mecanismo compatível, propor entidade própria **mínima no aculta_wiki**, documentada em ADR e testes, antes de implementação. Não presumir que `Content Moderation` por si só cria propostas sem Node edit access.

## 3. Matriz de capacidades proposta para C01 e WE SATIVA

| Usuário | Criar verbete | Editar próprio | Sugerir alteração em outro verbete | Publicar |
| --- | --- | --- | --- | --- |
| Anônimo | Não | Não | Não (sem fluxo próprio aprovado) | Não |
| Autenticado sem e-mail confirmado | Não | Não | Não | Não |
| E-mail confirmado | Sim, conforme Entity Access | Sim, conforme Entity Access | **Sim, após implementar provider seguro de propostas** | **Não** |
| Content editor | Sim | Sim | Sim / revisar propostas | Sim, conforme transição oficial |
| Conta revogada/bloqueada | Revalidar e negar | Revalidar e negar | Revalidar e negar | Revalidar e negar |

**Autorização efetiva:** validar papel/capacidade `email_confirmed` em cada GET/POST, estado da conta, grants, Domain/purpose e acesso por entidade. Conferir no módulo Mail Confirmer o momento em que o papel é concedido/revogado; a exportação sozinha não prova essa transição em Runtime. Não confundir `authenticated` com `email_confirmed`.

## 4. UI da porta de contribuição (C01)

- No verbete, `Contribuir` é um único CTA no `context-header.actions`, recebendo URL e `AccessResult` cacheáveis do kernel; `wiki-entry.actions` retém operações Core sem segundo `Contribuir`.
- **E-mail confirmado:** mostra `Corrigir informação`, `Adicionar fonte`, `Melhorar trecho`, `Sugerir imagem` **somente depois** de existir rota de proposta válida; `Editar verbete completo` aparece se o Node for efetivamente editável por aquela conta. Para conteúdo próprio, o editor completo pode ser a primeira entrega.
- **Contribuição sem permissão/rota:** nenhuma ação enganosa; uma orientação clara e segura pode explicar como confirmar o e-mail.
- Formulário sempre informa `Sua contribuição será analisada antes de aparecer publicamente`. Após envio real, mostrar estado `Em revisão`, com retorno ao verbete e acompanhamento em `Meu trabalho`.
- Nenhum `Publicar` acessível a `email_confirmed`; não esconder controles de segurança por CSS: controlar no backend/Form API.
- Mobile-first desde 320px, teclado, leitor de tela, fallback HTTP, zoom, contraste, light/dark com geometria idêntica e Institution Bar + Domain Header intactos.

## 5. Contrato mínimo futuro de proposta

**Não criar tabela ainda.** Documentar como possível entidade/registro: `source_node`, `base_revision`, `suggestion_type` (allowlist), `requested_change`, `citations`, `proposer_uid`, `created/changed`, `status` (`submitted/needs_changes/accepted/rejected` quando aprovado), e referência à revisão aplicada. Estudar versionamento, idempotência, limites de tamanho, moderação, logs auditáveis, privacidade, antiabuso, expiração/retention e conflitos (publicação concorrente). Form API/CSRF e AccessResult no backend; render arrays cacheáveis. As propostas **não** são os estados do workflow `wiki_editorial` e não podem ser representadas como revisões publicadas sem aplicação editorial.

## 6. Ordem de implementação e gates

| Etapa | Escopo | Situação |
| --- | --- | --- |
| **C01-B0** | auditar email_confirmed, content_editor e workflow; ajustar planejamento | **DOCUMENTADO** |
| **C01-B1** | auditar integração Mail Confirmer e acessos Node por perfil e propósito de domínio | Pendente |
| **C01-B2** | ADR: decidir persistência mínima de propostas para verbetes de terceiros + antiabuso/retention | Pendente |
| **C01-C1** | entregar editor completo autorizado para verbete próprio via Form API, revisão e CTA único | Bloqueado por Runtime/access e rotas |
| **C01-C2** | implementar primeiro tipo de proposta moderável, com tests e UI mobile | Bloqueado por B2 |
| **C01-D** | demais microtarefas, fila de content editors, status e feedback | Futuro |
| **C01-R** | Drupal homelab: acesso, domínio, publicação, revisões e WCAG 2.2 AA | Pendente |

**Autosave Form fica apenas no backlog futuro.** Não instalar, não depender e não afirmar autosave. Este documento não muda permissões, workflows, rotas, storage ou código de apresentação.
