# ACULTA Magazine — instruções para agentes

Herde o `AGENTS.md` raiz e o do `aculta_portal`.

- `aculta_magazine` é owner do conteúdo `revista`, fields, validações e workflow.
- Administração fica em `/painel-administrativo/revista`, purpose `main`,
  somente administradores. O submenu de publicações usa a View do owner; não
  devolva a listagem ao menu genérico `system.admin_content`.
- A jornada editorial de não administradores autorizados fica no ACULTA Studio
  Marley em `account:/marley`, sob `/marley/revista/*`. Escritas não podem
  atravessar purpose por POST, AJAX ou proxy.
- Combine capacidades configuradas com Entity Access, Revision Access, Field
  Access, Comment Access e o workflow. Nunca crie papel, storage, workflow,
  listagem ou editor paralelo.
- Discussões usam Comment `aculta_discussion_post` em
  `field_aculta_discussion`. Resolver sempre pelo
  `aculta_forum.discussion_context_resolver`; preserve o provider contextual,
  acesso ao Node/campo e isolamento de comentários Growroom.
- Atualize contratos, Config Sync, testes e changelog. Não escreva no Runtime nem
  importe configuração global sem autorização explícita.
