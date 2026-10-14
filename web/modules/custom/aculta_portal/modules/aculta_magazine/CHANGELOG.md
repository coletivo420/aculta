## 0.1.0-dev.3 — cabeçalho da publicação declara a dependência de tema; Blog é Editorial documentado — 2026-10-14

Classificação: PATCH (correção de cache; sem mudança de contrato).

- Correção: publicação da Revista sem imagem de capa falhava na renderização a frio com `LogicException` do
  `VariationCache` (contexto `theme` ausente na raiz). O cabeçalho (`PostPresentation`) passa a declarar o contexto
  `theme`, de que depende (elementos `aculta_portal_element`).
- Documentação: regra "Blog é sinônimo de Editorial" em `docs/README.md`.
- Gates: Portal Drupal 11+ PASS, fronteira módulo/tema PASS, slugs PASS, endereços de ambiente PASS.
- RUNTIME STATUS: verificado no Homelab por renderização a frio (em memória) das publicações. Inspeção visual humana pendente.

# Changelog — aculta_magazine

## 0.1.0-dev.2 — Fórum da Revista: tópico automático por publicação e abertura da discussão — 2026-10-13

- Pastas do Fórum espelham as sessões da Revista: pasta-mãe "Revista" com uma pasta por categoria editorial
  (`ForumSectionResolver`). A ligação é `field_categoria_revista` no vocabulário `forums`.
- Cada publicação (article e blog) cria um tópico do Fórum com o mesmo autor, na pasta da sua categoria
  (`ForumTopicSync`, `ForumTopicHooks`). O vínculo é `field_discussao`, que já existia na publicação.
- Tópico sem cópia: a abertura (resumo, imagem e link para a publicação) é lida da publicação. A imagem é o mesmo
  arquivo da mídia, renderizado no estilo `large` (`ForumOpeningPost`).
- Título, autor, estado e pasta seguem a publicação. Excluir a publicação despublica o tópico e preserva a discussão.
- Comentários: o tópico usa `comment_forum`. A página da publicação mostra o trecho e o link para a discussão
  completa no propósito FORUM (plano E3); a resposta fica só no Fórum.
- URL do tópico: `/topico/<título>` (padrão `aculta_forum_topic`), conforme docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md.
- Permissão `access comments` para anônimo e autenticado (leitura da discussão). Moderação de comentários segue
  o padrão do Core: comentário de quem não tem `skip comment approval` entra na fila do `content_editor`.
- Atualizações: `aculta_magazine_update_10001` (campo de categoria e tópicos das publicações existentes),
  `_10002` (uma pasta por categoria) e `_10003` (padrão de URL e alias dos tópicos).
- Dependência nova: `forum:forum`.
- RUNTIME STATUS: verificado no Homelab (ciclo de vida da publicação, HTTP no host do Fórum e da Revista,
  comentário aprovado aparecendo nas duas páginas). Inspeção visual humana pendente.

## 0.1.0-dev.1 — submódulo da Revista (centraliza publicações, chamada, resumo, SEO e caixa de autores) — 2026-10-13

- Novo submódulo do `aculta_portal`. Centraliza a Revista: `RevistaPublications` (tipos de publicação) e
  `ArticleAuthorsHooks` (caixa de autores), antes em `aculta_user`.
- Tipo "blog" da Revista com chamada (`field_subtitle`) e resumo (`field_summary`), SEO (`node__blog`),
  URL por sessão e listagens por sessão.
- Chamada aparece na exibição da Notícia e do blog.
- Documentação em `docs/README.md` (regras e passo a passo para tipos novos: vídeo, galeria).
- RUNTIME STATUS: verificado no Homelab (navegador e HTTP). Inspeção visual humana pendente.
