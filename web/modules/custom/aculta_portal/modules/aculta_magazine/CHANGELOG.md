# Changelog — aculta_magazine

## 0.1.0-dev.1 — submódulo da Revista (centraliza publicações, chamada, resumo, SEO e caixa de autores) — 2026-10-13

- Novo submódulo do `aculta_portal`. Centraliza a Revista: `RevistaPublications` (tipos de publicação) e
  `ArticleAuthorsHooks` (caixa de autores), antes em `aculta_user`.
- Tipo "blog" da Revista com chamada (`field_subtitle`) e resumo (`field_summary`), SEO (`node__blog`),
  URL por sessão e listagens por sessão.
- Chamada aparece na exibição da Notícia e do blog.
- Documentação em `docs/README.md` (regras e passo a passo para tipos novos: vídeo, galeria).
- RUNTIME STATUS: verificado no Homelab (navegador e HTTP). Inspeção visual humana pendente.
