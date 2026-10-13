# aculta_magazine (Revista)

Submódulo do Portal ACULTA (`aculta_portal`). Centraliza tudo que é da Revista: publicações, chamada, resumo,
SEO, caixa de autores e coautoria. Depende de `aculta_user` (perfil, privacidade e redes), de `node_co_authors`
(coautores, contrib), de `metatag` (SEO) e de `pathauto` (URL por sessão).

## Regras (valem para todas as publicações da Revista)

| Regra | Como | Onde |
| --- | --- | --- |
| Publicações da Revista | lista `RevistaPublications::BUNDLES` (hoje `article` e `blog`) | `src/Revista/RevistaPublications.php` |
| Chamada | campo `field_subtitle` (rótulo "Chamada" no blog) | exibição do tipo, acima do corpo |
| Resumo | campo `field_summary` (rótulo "Resumo" no blog); descrição e Open Graph via `[node:editorial-summary]` | metatag `node__<tipo>` |
| SEO | metatag do tipo (`node__article`, `node__blog`) e campo `field_meta_tags` | config do metatag |
| URL por sessão | padrão Pathauto `/[node:session]/[node:title]` (`aculta_article`, inclui o blog) | config do pathauto e token do aculta_portal |
| Listagens por sessão | view `revista_sessoes` (filtro de tipo com todos os tipos da Revista) | config da view |
| Caixa de autores | `node_view`: autor (`node.uid`) e coautores (`co_authors`), foto, nome, bio, ícones das redes | `src/Hook/ArticleAuthorsHooks.php` |
| Coautores | `co_authors` (Node Co-Authors): coautores recebem os direitos de conteúdo do autor | módulo contrib |

## Privacidade

A caixa de autores usa somente `PublicProfilePolicy` (aculta_user): link para o perfil só com perfil público,
foto e bio só quando exibidas, ícones só das redes preenchidas e exibidas. E-mail não entra na caixa.

## Como adicionar um tipo da Revista (vídeo, galeria, outro)

1. Criar o tipo de conteúdo com os campos `field_subtitle` (rótulo "Chamada"), `field_summary` (rótulo
   "Resumo"), `field_editorial_category`, `field_media_image` e `field_meta_tags`.
2. Adicionar o machine name em `RevistaPublications::BUNDLES`.
3. Criar o metatag `node__<tipo>` copiando o de `node__article` (o resumo entra por `[node:editorial-summary]`).
4. Incluir o tipo no padrão Pathauto `aculta_article` (critério de bundle) e no filtro de tipo da view `revista_sessoes`.
5. Exibir chamada e resumo na exibição `node.<tipo>.default` (view display).
6. Testar: página na Revista com chamada, descrição de SEO, caixa de autores e coautoria.

## Gates e verificação

- `php scripts/validate-aculta-portal-drupal11.php`, `validate-module-theme-boundary.php`,
  `validate-public-slugs.php`, `validate-environment-addresses.php`.
- Navegador: publicação de teste (blog) com chamada, resumo, corpo e coautora; caixa de autores; descrição de SEO.

## Pendências

- Vídeo e galeria: tipos ainda não criados; seguem o passo a passo acima.
- Inspeção visual humana.

## Função x tema (separação)

| Parte | Dono | Onde |
| --- | --- | --- |
| Tipos, campos, SEO, URL por sessão, listagens e caixa de autores (dados e marcação com tokens) | `aculta_magazine` (função) | `src/`, `config/sync` (displays, metatag, pathauto, view) |
| Aparência da postagem (largura de leitura, imagem, chamada, resumo, vídeo 16:9, caixa de autores) | ACULTA420 (tema) | `css/components/revista-post.css`, `portal-account.css` |

O submódulo não tem CSS nem classes de design: todo elemento sai com `data-portal-ui` e o tema traduz.
Os displays (`core.entity_view_display.node.*.full`) definem quais campos aparecem e em que ordem; a aparência é do tema.
Referência de leitura usada: largura de linha entre 60 e 75 caracteres (Piccalilli, "Limit line lengths to increase readability").
