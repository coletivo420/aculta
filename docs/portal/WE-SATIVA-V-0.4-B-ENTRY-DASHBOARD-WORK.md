# WE SATIVA — V-0.4-B: entrada, início e Meu trabalho

**Data:** 2026-10-22 · **Estado:** especificação visual mobile-first, sem implementação de Runtime.
**Cobertura:** V01 convite no verbete, V02 seletor, V03 início, V04 Meu trabalho.
**Dependências:** [arquitetura V-0.4](WE-SATIVA-VISUAL-SCREENS-0.4.md), [C01](WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md), [moderação Wiki](../../web/modules/custom/aculta_portal/modules/aculta_wiki/docs/WE-SATIVA-MODERATION-PLAN.md).

## Sistema visual e layout compartilhados

- Institution Bar e Domain Header multidomínio **sempre presentes e não clonados**. Subcabeçalho WE SATIVA começa dentro do main, abaixo dos headers.
- Coluna 320–390px: padding lateral 16px, gap de blocos 16–24px, um H1 e texto de orientação curto, cartões largura integral. São intenções de layout, não novos tokens codificados.
- Desktop 1024px+: conteúdo principal com largura máxima de leitura aproximada de 880–1040px; cards de escolha podem passar a duas colunas se a ordem lógica se mantiver.
- Reutilização somente de Core SDC/UI Patterns/UI Styles existentes. Confirmados por metadata: `context-header` (título + slots `back_link`, `actions`), `context-tabs` (links do provider), `card` (slots conteúdo/cabeçalho/rodapé).
- Botões/links são render arrays fornecidos por backend. Não gerar URLs ou decisões de permissão dentro de Twig.
- Claro/escuro compartilham geometria, espaçamentos, hierarquia e foco; apenas tokens semânticos mudam.

## V01 — convite Contribuir no verbete público

**Propósito:** aproximar leitura e edição, sem converter o artigo em dashboard.

Layout mobile: breadcrumb → H1 do verbete → síntese → CTA `Contribuir` → operações Core de leitura. **Posicionamento técnico candidato:** slot `context-header.actions`, visualmente adjacente ao título/contexto. O slot não altera o H1 e deve receber link previamente autorizado. O `wiki-entry.actions` mantém links Core existentes e **não repete Contribuir**.

**Estados:** (a) usuário com e-mail confirmado e rota de contribuição funcional: mostrar CTA; (b) usuário sem confirmação: orientar confirmação por destino real, caso exista; (c) verbete sem acesso ou sem backend: não gerar CTA; (d) rotas/permissões revogadas: request negada no servidor. Acesso de terceiros por sugestão exige entidade/proposta segura antes de ser habilitado.

**Storyboard:** leitor do artigo → identifica Contribuir → abre V02 sem JS → retorno seguro ao artigo.

**Gate:** um H1, ausência de CTA duplicado, link acessível com foco e touch target confortável, backend revalida view/propose/update e cache context.

## V02 — seletor de intenção

**Propósito:** permitir contribuição pequena, com linguagem comum e 1 decisão por vez.

Estrutura visual: retornar ao verbete; eyebrow `WE SATIVA`; H1 `Como você quer contribuir?`; título do verbete como contexto, não outro H1; texto `Sua sugestão será analisada antes de aparecer publicamente`; cards de intenção.

**Cards condicionais:** corrigir informação, adicionar referência, melhorar trecho, sugerir imagem, editar verbete completo. Cada card recebe título, descrição de 1 linha, seta e href do provider. **Jamais** exibir ação sem endpoint funcional e acesso efetivo. Para verbete próprio, editar completo pode ser primeira intenção implementada; em verbete de terceiro, microtarefas aguardam o modelo de proposta moderada.

**Estados:** uma opção, muitas opções, nenhuma opção (orientação segura), acesso revogado, título longo. Não oferecer CTA fictício em empty state. UX navegável sem JS.

## V03 — Início do WE SATIVA

**Propósito:** valorizar a colaboração, não emular painel de administração.

Primeira dobra mobile: H1 `Seu espaço de contribuição`, descrição de 1–2 linhas, CTA primário `Criar verbete` (apenas email_confirmed com create access); abaixo `Continuar de onde parou` se houver rascunho autorizado; cartão editorial de ajuda/guia. No desktop, duas zonas opcionais, sem modificar a sequência do teclado.

**Estados:** primeiro acesso sem trabalho (boas-vindas e CTA), usuário com rascunho, usuário com item em revisão (acompanhar, não editar sem transição), usuário com e-mail ainda não confirmado (orientar confirmação pela rota real), falha do provider (feedback sem inventar itens), lista vazia. Métricas e gamificação ficam fora da primeira entrega.

## V04 — Meu trabalho

**Propósito:** organizar próprias contribuições sem vazar filas editoriais ou versões privadas de terceiros.

Cabeçalho com H1 `Meu trabalho` e resumo curto. Filtros por links HTTP reais: `Em andamento`, `Em revisão`, `Publicados`, `Todos`, usando `context-tabs` somente se provider das rotas tiver acesso/cache correto. Lista de cards com nome do verbete, status oficial, data de alteração real, ação única contextual (continuar, acompanhar, ver publicado). Itens de proposta e verbete devem ser distinguidos, sem expor texto privado.

**Estados:** vazio geral, vazio em filtro, rascunhos próprios, revisão pendente, publicado, dados paginados, acesso removido, erro. Não exibir opções de revisão editorial aqui: `Fila editorial` é destino separado, exclusivo para content_editor. Para conteúdos sem permissão de update, somente acompanhar.

**A11y:** status não representado apenas por cor, aria-current em filtro ativo, h2 para títulos de cards quando apropriado, datas legíveis, links específicos (`Continuar verbete X`) e foco conservado após filtros.

## Stories de aceitação propostas (não são arquivos criados)

| Story | Telas | Cenário |
| --- | --- | --- |
| WES-B-01 | V01 | verbete próprio editável: um Contribuir real |
| WES-B-02 | V01 | sem permissão / outro Domain: nenhuma URL proibida |
| WES-B-03 | V02 | apenas uma intenção ativa e título longo |
| WES-B-04 | V02 | múltiplas intenções reais, cards legíveis a 320px |
| WES-B-05 | V03 | primeiro acesso email_confirmed sem rascunho |
| WES-B-06 | V03 | rascunho próprio + item em revisão |
| WES-B-07 | V04 | filtros, card por status e paginação |
| WES-B-08 | V04 | usuário A nunca vê rascunho B |
| WES-B-09 | V01–V04 | modo claro/escuro com mesmo layout |
| WES-B-10 | V01–V04 | sem JS, teclado, zoom 400%, título único, 320px |

## Gates para implementar

1. Auditar rotas reais `aculta_wiki`/Portal, links Core e Entity Access. Não existem rotas WE SATIVA comprovadas nesta especificação.
2. Revisar a matriz de papéis `email_confirmed` e content_editor; primeiro verbete moderado, nenhum botão Publicar ao colaborador.
3. Garantir que o provider devolva listas e URLs cacheáveis, sob Purpose/Domain e revisão.
4. História visual somente a partir de SDC confirmados, não reutilizar Twig não-SDC. Formular links/call-to-actions pelo Drupal Form/Link APIs.
5. Testar desktop/mobile (320/360/390/768/1024/1280/1440), zoom, contraste, teclado, leitor de tela, no-JS e estados de erro.

## Próxima fase

**V-0.4-C:** microtarefas, criação de verbete, editor completo e prévia; dependência do backend de proposta moderada no caso de verbetes alheios. Autosave Form segue backlog futuro.
