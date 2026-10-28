# Changelog — aculta_content_admin

## 2026-10-28 — painel geral por acesso de moderação (ADM5)

- Criado `/painel-administrativo/conteudo` com descoberta de Views editoriais e allowlist de coleções servidas por controllers.
- O painel só apresenta rotas cujo acesso nativo está permitido; sem uma coleção visível, o dashboard também nega acesso.
- Kernel verifica a área FAQ oculta/negada para usuário comum e disponível ao usuário com permissão editorial.

