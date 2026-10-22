# aculta_magazine (Revista)

Auditoria estrutural F0.1.0: [estado do código, Config Sync, Runtime e plano de
limpeza](AUDITORIA-F0.1.0.md).

Documentação normativa vigente:

- [Arquitetura](ARCHITECTURE.md) — propriedade, modelo de conteúdo, transições
  exatas do workflow `revista`, hooks, configuração e invariantes.
- [Matriz de dependências](DEPENDENCY-MATRIX.md) — consumidores, fornecedores,
  dependências declaradas e bloqueadores.
- [Auditoria MAG 0.5-A](AUDIT-MAG-0.5-A.md) — inventário, achados A-01 a A-12 e
  relatório de Config Sync.
- [Contrato de integração Studio ↔ Magazine](STUDIO-INTEGRATION-CONTRACT.md) —
  inventário real das rotas Core e dos contratos do Magazine e do Studio,
  lacunas e decisão sobre adaptador.
- [Matriz de acesso editorial](EDITORIAL-ACCESS-MATRIX.md) — atores, operações,
  estados e transições reais do `revista`, coautoria e cacheability.
- [Matriz ator × estado × ação](EDITORIAL-ACTOR-STATE-MATRIX.md) — leitura,
  edição, transições e demais ações por estado, verificadas no workflow real.
- [Contrato de rotas e domínios editoriais](ROUTES-DOMAIN-CONTRACT.md) —
  purpose de cada rota do `revista`, redirecionamento `302`/`404`, URL canônica
  para o Studio e evidência de Runtime (MAG 0.5-B.7).

> **Discussão contextual:** `field_aculta_discussion` é um campo Comment hospedado
> pelo Node `revista`; não cria tópicos no Fórum público. A sincronização automática
> de tópicos foi removida. A desinstalação do Drupal Forum contrib e a retirada de
> consumidores restantes seguem a sequência segura descrita em
> [`MIGRATION-REMOVAL.md`](../../aculta_forum/docs/MIGRATION-REMOVAL.md).

Submódulo do Portal ACULTA (`aculta_portal`). Centraliza tudo que é da Revista: publicações, chamada, resumo,
SEO, caixa de autores e coautoria. Depende de `aculta_user` (perfil, privacidade e redes), de `node_co_authors`
(coautores, contrib), de `metatag` (SEO) e de `pathauto` (URL por sessão).

## Regras (valem para todas as publicações da Revista)

| Regra | Como | Onde |
| --- | --- | --- |
| Publicações da Revista | `revista` é o único bundle; `article` e `blog` foram migrados e aposentados | `src/Revista/RevistaPublications.php` |
| Chamada | campo `field_subtitle` (rótulo "Chamada") | exibição do tipo, acima do corpo |
| Resumo | campo `field_summary` (rótulo "Resumo"); descrição e Open Graph via `[node:editorial-summary]` | metatag `node__revista` |
| SEO | metatag `node__revista` e campo `field_meta_tags` | config do metatag |
| URL por sessão | padrão Pathauto `/[node:session]/[node:title]` (`aculta_article`) | config do pathauto e token do aculta_portal |
| Listagens por sessão | view `revista_sessoes` (filtro de tipo com todos os tipos da Revista) | config da view |
| Caixa de autores | `node_view`: autor (`node.uid`) e coautores (`co_authors`); identity/avatar/bio/redes vêm do cartão `author_editorial` de `aculta_user`; Magazine fornece só Autor/Coautor | `src/Hook/ArticleAuthorsHooks.php` |
| Coautores | `co_authors` (Node Co-Authors): coautores recebem os direitos de conteúdo do autor | módulo contrib |

## Discussão atual da publicação

A caixa de autoria editorial usa o `AuthorCardBuilder` de `aculta_user` com a
variante `author_editorial`. O Magazine mantém somente a designação Autor ou
Coautor; apelido/nome, avatar, biografia e redes públicas são preparados pelo
User e apresentados pelo SDC compartilhado do ACULTA420. Bio e redes respeitam
o opt-in do perfil público, e o Magazine não lê campos de perfil diretamente.

`revista` recebe `field_aculta_discussion`, do tipo Comment `aculta_discussion_post`, diretamente no Node.
O full display usa o formatter Core `comment_default`, e o `aculta_user` constrói o cartão `author_comment`; autoria vinculada ao acervo usa `author_growroom_historico` sem conta ativa. A política
configurável de primeiro comentário do `aculta_user` aplica-se aqui como nos demais contextos públicos; LMS fica
expressamente fora dessa moderação global. As discussões contextuais continuam hospedadas no campo Comment do Node `revista`; a migração preservou as entidades hospedeiras e os campos disponíveis.

A configuração está em `config/sync` e `config/install`; as duas instâncias e displays full foram aplicados no
Runtime de teste em importação parcial. Para Nodes de publicação anteriores à instalação do campo,
`PublicationDiscussionHooks` aplica em memória o default
definido na configuração do campo (fallback aberto), sem gravar o Node. Status editorial explícito fechado ou
oculto e estatísticas de comentários carregadas pelo Core permanecem intactos. A visibilidade efetiva e a submissão
de comentários via HTTP/browser continuam pendentes de homologação.

Smoke em memória no Runtime de teste confirmou o field/display e renderizou o
formulário Comment para um participante de teste com `post comments`. Nenhum
formulário foi enviado, e a contagem de Comments permaneceu zero.

## Categorias editoriais

As categorias em `editorial_category` classificam o conteúdo: Editorial, Blog/Opinião, Notícias, Reportagens,
Entrevistas e outras seções editoriais. Elas não criam bundles de Node. A decisão anterior que associava `blog` à
categoria Editorial está superada pelo modelo canônico `revista`.

- Novas publicações usam o bundle `revista` e uma categoria editorial apropriada.
- Publicações existentes e futuras usam `revista`; Blog/Opinião é uma classificação editorial.
- O modelo de publicação (`aculta420:revista-publication`) atende o bundle `revista`.

## Migração concluída

Os 16 Nodes inventariados no Runtime de teste foram migrados pelo comando
`aculta-magazine:migrate-publications` via Entity API, preservando NID, UUID,
VID, campos e aliases. `article` e `blog` não existem mais como bundles; a
configuração mantém somente `revista`. Backup, verificação e rollback estão em
[`MIGRATION-ARTICLE-BLOG-TO-REVISTA.md`](MIGRATION-ARTICLE-BLOG-TO-REVISTA.md).

## Privacidade

A caixa de autores usa somente `PublicProfilePolicy` (aculta_user): link para o perfil só com perfil público,
foto e bio só quando exibidas, ícones só das redes preenchidas e exibidas. E-mail não entra na caixa.

## Classificações editoriais

Termos como Editorial, Blog/Opinião, Notícias, Reportagens e Entrevistas são
classificações do vocabulário `editorial_category`. Não criar bundles Node para
separar formatos; todas as publicações usam `revista`.

## Gates e verificação

- `php scripts/validate-aculta-portal-drupal11.php`, `validate-module-theme-boundary.php`,
  `validate-public-slugs.php`, `validate-environment-addresses.php`.
- Navegador: publicação de teste (`revista`) com chamada, resumo, corpo e coautora; caixa de autores; descrição de SEO.

## Pendências

- Vídeo: não há um campo ou contrato editorial específico; usar incorporação
  compatível no corpo quando necessário. Galerias existentes são incorporadas
  pelo CKEditor 5/Entity Embed e continuam sob propriedade de `aculta_gallery`.
- O comando de migração de galeria ainda não foi executado. Depois da importação
  da configuração, revisar a prévia em lotes; resolver manualmente os NIDs
  reportados com mais de uma galeria antes de completar o backfill.
- Content Index: `field_editorial_gallery` mantém uma referência estruturada
  única e o provider expõe `editorial_gallery`. O comando
  `aculta-magazine:migrate-gallery-references` faz prévia por padrão e migra em
  lotes apenas embeds únicos resolvidos; casos com várias galerias são
  reportados para revisão. O corpo e a apresentação Entity Embed permanecem
  inalterados. Consulte [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).
- A captura pública em Firefox não teve overflow em mobile; com sessão editorial,
  a barra administrativa gera 78 px de overflow em 390 px. Revisar essa
  apresentação antes de RC.
- Resolver as diferenças de Config Sync depois que o workflow concorrente do
  Fórum estiver disponível no Runtime. Diferenças de hostname Domain e campos
  virtuais Entity Print devem permanecer separadas desse ajuste.
- O formulário de comentários foi confirmado via HTTP autenticado; envio de
  comentário de teste e revisão visual permanecem pendentes de homologação.

## Função x tema (separação)

| Parte | Dono | Onde |
| --- | --- | --- |
| Tipos, campos, SEO, URL por sessão, listagens e caixa de autores (dados e marcação com tokens) | `aculta_magazine` (função) | `src/`, `config/sync` (displays, metatag, pathauto, view) |
| Aparência da postagem (largura de leitura, imagem, chamada, resumo, vídeo 16:9, caixa de autores) | ACULTA420 (tema) | `css/components/revista-post.css`, `portal-account.css` |

O submódulo não tem CSS nem classes de design: todo elemento sai com `data-portal-ui` e o tema traduz.
Os displays (`core.entity_view_display.node.*.full`) definem quais campos aparecem e em que ordem; a aparência é do tema.
Referência de leitura usada: largura de linha entre 60 e 75 caracteres (Piccalilli, "Limit line lengths to increase readability").

## Cabeçalho da postagem e compartilhamento (função)

- `src/Presentation/PostPresentation.php` (serviço, chamado pelo hook `node_view` de `ArticleAuthorsHooks`):
  faixa com autor, data e hora (fuso do site) e os botões de compartilhamento (WhatsApp, Telegram, Facebook,
  Reddit e e-mail), com links montados a partir da URL canônica da publicação.
- Só um hook `node_view` por módulo: novas funções de exibição entram como serviço chamado por esse hook.
- Aparência (círculos, cores por rede, título e faixa) é do ACULTA420 (`revista-post.css`), com tokens semânticos.
- Data: `field_published_at` é guardado em UTC; ao preencher manualmente, use o horário UTC (ex.: 19:32 de Brasília = 22:32 UTC).

## Registro histórico: Drupal Forum contribuído

Registro histórico: a versão anterior criava um tópico Drupal Forum por publicação. Essa decisão foi substituída: cada publicação mantém a discussão dentro da própria entidade via Comment compartilhado; novos tópicos públicos automáticos estão proibidos.

- Pastas históricas e tópicos automáticos por publicação foram aposentados. Os
  updates 10001/10002 mantêm seus números, sem criar o campo editorial antigo
  ou termos no vocabulário contrib.
- Em 2026-10-20, os 30 Nodes antigos, oito termos e 12 referências da Revista
  foram removidos no Runtime de teste; o inventário tinha zero Comments
  `comment_forum`. O campo `field_discussao`, o leitor legado e a apresentação
  da publicação como abertura de Node `forum` foram retirados do código atual.
- `field_aculta_discussion` é a discussão contextual da publicação `revista`; Drupal Comment mantém autoria, estado e encadeamento.
- A desinstalação oficial do módulo contrib e remoção do pacote Composer ainda
  dependem da migração dos consumidores restantes e revisão da configuração.
- Moderação atual: comentários diretos seguem a política configurável do primeiro comentário público, fora do LMS.
  A regra específica do LMS permanece sob Educadores com acesso contextual.
- Nova categoria editorial: adicionar termo em `editorial_category`; o bundle
  único `revista` continua inalterado.
- Categoria nova: não cria pasta no vocabulário antigo do Forum.
- Não há criação manual de tópico para publicação. Tópicos de outros tipos (verbete, atividade) seguem seus contratos contextuais.

## Arquitetura editorial vigente

A Revista é **consumidora**, nunca proprietária, dos subkernels `aculta_gallery` (álbuns independentes incorporados como entidades existentes pelo CKEditor 5/Entity Embed) e `aculta_forum` (provider contextual de Comments na publicação). Identidade e cartões pertencem a `aculta_user`; imagens a `aculta_image`; leitura de documentos ao `aculta_document_viewer` conforme disponibilidade. O `aculta_studio` planejado fornece somente interface de autoria/revisão, sem storage próprio de publicações.

O bundle `revista`, seus campos, displays, Metatag, Pathauto, Views e workflow próprio estão ativos no Runtime de teste. `article` e `blog` foram migrados e aposentados. O workflow `editorial` existente permanece com `aculta_notice`. O provider contextual do Fórum reconhece `revista` e combina acesso do Node e do campo Comment com permissões de escrita/moderação; não cria tópicos públicos. Procedimento, salvaguardas e rollback estão em [`MIGRATION-ARTICLE-BLOG-TO-REVISTA.md`](MIGRATION-ARTICLE-BLOG-TO-REVISTA.md). Projetos continuam no domínio principal, Eventos pertencem ao futuro `aculta_eventos`, e `editorial_highlight` é curadoria Home a auditar separadamente.

O papel `content_editor` é concedido manualmente e concentra criação, edição e
transições do workflow `revista`. O papel `email_confirmed`, sozinho, não tem
permissão para criar/publicar conteúdo da Revista. O workflow distingue
rascunho, revisão, publicado e arquivado; a UI autenticada e a moderação de
comentários ainda precisam de homologação no navegador.

Norma: [Kernel ACULTA](../../../../../../../docs/architecture/aculta-kernels.md). Planejamento: [Studio e Revista](../../../../../../../docs/portal/STUDIO-MAGAZINE-ROADMAP.md).
