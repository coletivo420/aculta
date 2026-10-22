# Catálogo visual de componentes ACULTA420

ACULTA420 mantém previews de seus SDCs na biblioteca do UI Patterns, usando
stories declarativas junto dos componentes. A interface usa o próprio Drupal e
não requer uma aplicação Storybook separada.

## Acesso

- Índice: `/admin/appearance/ui/components`
- Componentes do tema: `/admin/appearance/ui/components/aculta420`
- Cada componente apresenta seus stories no detalhe.
- A rota requer a permissão `access components page`. Ela é reservada a
  administradores; não conceder a papéis editoriais nem a usuários anônimos.

## Manutenção dos previews

- Cada SDC deve ter ao menos um arquivo `machine-name.exemplo.story.yml` no
  diretório do componente. Use variantes e estados vazios adicionais quando
  mudarem a apresentação ou ajudarem a revisão.
- Previews usam texto demonstrativo, links `#...` e placeholders CSS. Não usam
  conteúdo real de usuários, pedidos, cursos ou entidades com acesso restrito.
- Slots de mídia recebem placeholders de apresentação, nunca um `src` para
  contornar o catálogo/pipeline `aculta_image`.
- Componentes de formulário são demonstrados estaticamente: não devem submeter,
  enviar mensagem, alterar carrinho ou iniciar contribuição.
- Componentes com integração Core/contrib mantêm os nomes dos seus contratos;
  stories não alteram o presenter nem simulam autorização ou cache.
- Ao criar um SDC, atualizar o índice de componentes e seu story no mesmo
commit. O catálogo por provider lista os 34 SDCs atuais do ACULTA420.

## UI Styles

O catálogo está em `aculta420.ui_styles.yml`; `css/components/ui-styles.css`
implementa suas classes e usa tokens semânticos. As categorias atuais são
superfície, texto, borda e elevação. A elevação elevada tem um único nível
proposital, com valor adaptado pelo token ao modo claro/escuro.

Só adicionar um estilo quando existir uma necessidade editorial recorrente,
um nome compreensível para quem monta páginas e um token ACULTA420 que cubra a
função em todos os modos. Não expor valores livres, primitivas de paleta,
classes Bootstrap genéricas, espaçamentos ad hoc ou combinações que ocultem
contraste inadequado. Não disponibilizar texto inverso sem superfície compatível.

## Backlog do catálogo — padrões transversais ainda não implementados

O [roadmap visual próprio do tema](visual-cross-product-roadmap.md) define candidatos `context-header`, `context-tabs`, `empty-state`, `content-reader`, `resource-list`, `status-timeline` e outros. **Não listar como SDC existente nem contabilizar nos componentes disponíveis.** Quando qualquer padrão entrar no código, adicionar um story `.story.yml` real, atualizar contagem/inventário e criar demonstrações de mobile, desktop, claro/escuro e estados pertinentes. Preservar o shell global e priorizar variantes dos SDC atuais.

## Cobertura de stories — fotografia de 2026-10-22

O inventário do GitHub encontrou 38 SDCs e 55 stories antes da adequação M1. Foram adicionadas stories demonstrativas para `forum-category`, `qr-preview` e `qr-download`, totalizando **58 arquivos de stories** e nenhum SDC sem story. A verificação de renderização no UI Patterns Library permanece PENDENTE e a contagem histórica de catálogo deve ser distinguida desta fotografia da branch. Ver [inventário](sdc-ui-suite-inventory-2026-10-22.md).

## Novo candidato do catálogo — Context Header

O SDC experimental `aculta420:context-header` possui duas stories no repositório: `context-header.default.story.yml` e `context-header.editorial.story.yml`. São **fixtures estáticas**, não evidência de renderização no UI Patterns Library; verificar schema, HTML e tokens no Runtime antes de tornar o componente disponível a consumidores. Com a criação desse componente, a fotografia da árvore passa de 38 para **39** SDCs e de 58 para **60** arquivos stories, sujeito à verificação do HEAD.
