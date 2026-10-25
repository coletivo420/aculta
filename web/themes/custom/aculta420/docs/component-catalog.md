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

## Context Tabs — novo componente experimental

`aculta420:context-tabs` acrescenta um SDC e duas stories estáticas para navegação contextual de GanjaLab e Wiki. **Inventário nominal esperado:** 40 SDCs, 62 arquivos story, sujeito à confirmação da árvore do HEAD. Renderização UI Patterns Library, UI Styles e consumidores reais permanecem pendentes. [Plano B2](theme-ux-0.8-b2-context-tabs.md).

## Quatro SDCs de Meu Perfil — pré-S-R4 (0.7.0-dev.1)

O tema ganhou `profile-editor`, `profile-identity`, `profile-bio`
e `profile-social` (todos experimentais), com **8 stories estáticas**:
`profile-editor.full/empty`, `profile-identity.avatar/fallback`,
`profile-bio.filled/empty`, `profile-social.filled/empty`, todas
no padrão `<nome>.<variante>.story.yml` do catálogo. Há teste Unit
estático de schema/slots/assets/fixtures e ausência de lógica
de dados no Twig, escrito mas não executado.

As contagens históricas anteriores permanecem vinculadas à data de
inventário e **não** são totais verificados da branch atual. Os
SDCs novos ainda não possuem consumidor, nem validação no UI Patterns
Library ou Firefox. O catálogo jamais deve usar dados privados reais.

## Integração dos SDCs privados (S-R4, Studio dev.21)

Os quatro SDCs experimentais `profile-editor`, `profile-identity`,
`profile-bio` e `profile-social` passaram a ser consumidos em
`/studio/perfil` pelo render element Core `#type: component`.
O Studio entrega os slots autorizados dos serviços ACULTA User e
`aculta_image`. O componente não faz consulta a entidades nem decide
política de dados. O perfil público mantém sua filtragem original;
a foto própria exibida no editor é privada à sessão titular.

A mudança foi no consumidor Studio; as APIs do tema (Twig, CSS,
props e slots) não foram alteradas e a versão fica `0.7.0-dev.1`.
Sinais de código não equivalem a render validado: Unit, Kernel,
UI Patterns Library e Firefox ESR/X11 seguem pendentes.
