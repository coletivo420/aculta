# ACULTA Magazine (`aculta_magazine`)

Submódulo editorial do ACULTA Portal. Drupal continua como fonte de verdade para
conteúdo, publicação e mídia.

## Estado

**0.5.0-dev.16:** provider v3 registra `embedded_video` para Media `remote_video`
explicitamente embutido na revisão atual da publicação. Acesso ao alvo é
revalidado pelo Content Index. Esta entrega não está homologada em
Runtime nem em Firefox/X11. Testes e pendências constam do CHANGELOG.

Revalidação 2026-10-31: Unit/Kernel completos passaram com 54 testes / 712
assertions; PHP lint 32/32 e PHPCS Drupal/DrupalPractice sem erros ou avisos.
O formulário de capacidades agora carrega os papéis por Entity Storage
injetado. Configuração Runtime lida via MCP: zero diferenças com o Sync do
checkout servido; a árvore `config/sync` da branch coincide com a desse
checkout. O módulo ativo no Runtime está em `0.5.0-dev.14`, enquanto a branch
contém `.16`; não houve import nem escrita. Revisão Firefox/X11 e homologação
funcional no código candidato continuam pendentes.

**Base 0.5.0-dev.13:** PHPCS Drupal/DrupalPractice integral PASS; Unit + Kernel
39/610 PASS sem depreciações; PHP lint 2/2 e `node --check` PASS. A revisão
visual móvel do rótulo de categoria colorido ainda depende de servir o
candidato atual. O gate global de fronteira módulo–tema continua vermelho por
arquivos de Calendar, Events e Maps, sem violações do Magazine.

## Documentação

- [Índice documental](docs/README.md)
- [Arquitetura normativa](docs/ARCHITECTURE.md)
- [Matriz de dependências](docs/DEPENDENCY-MATRIX.md)
- [Auditoria MAG 0.5-A](docs/AUDIT-MAG-0.5-A.md)
- [Contrato de integração Studio ↔ Magazine](docs/STUDIO-INTEGRATION-CONTRACT.md)
- [Matriz de acesso editorial](docs/EDITORIAL-ACCESS-MATRIX.md)
- [Matriz ator × estado × ação](docs/EDITORIAL-ACTOR-STATE-MATRIX.md)
- [Contrato de rotas e domínios editoriais](docs/ROUTES-DOMAIN-CONTRACT.md)
- [Registro histórico da migração `article`/`blog` → `revista`](docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md)
- [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md) — o provider declara o contexto canônico `magazine`; Entity Access e Revision Access continuam nos módulos de origem. `aculta_portal` é owner do Media compartilhado `remote_video`; `filter.format.revista_editorial` permite o embed e o provider v3 registra a aresta `embedded_video` apenas quando `entity_usage` aponta ao corpo da revisão atual. Não lê nem projeta URL oEmbed ou corpo; o consumidor revalida o acesso ao Media.
- `entity_usage` é dependência direta do adapter de Index do Magazine para resolver embeds explícitos de mídia por revisão.
- [CHANGELOG](CHANGELOG.md)

Para políticas editoriais transversais, consulte a
[documentação de módulos](../../../../../../docs/modules/EDITORIAL-MEDIA.md)
e o [mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Contratos editoriais para o Studio

O Magazine fornece adaptadores de Form API em `/marley/revista/*`, no purpose
`account`, para que o trabalho editorial fique na Suite sem POST cross-domain.
Criação e edição usam o Entity Form Builder; transições consultam
`StateTransitionValidation` e salvam uma revisão pela Entity API. Entity
Access, Revision Access e permissões Drupal continuam obrigatórias. A central
administrativa fica em `/painel-administrativo/revista`, no purpose `main`.
Os detalhes estão em
[STUDIO-INTEGRATION-CONTRACT.md](docs/STUDIO-INTEGRATION-CONTRACT.md) e
[ROUTES-DOMAIN-CONTRACT.md](docs/ROUTES-DOMAIN-CONTRACT.md); a matriz de acesso
e a matriz ator × estado × ação descrevem quem pode o quê, sem alterar estados,
transições ou permissões.

## Administração editorial

O único bundle de publicação é `revista`. Os Nodes legados foram migrados com
preservação de NID, UUID, revisão e aliases; os bundles `article` e `blog` foram
aposentados. O migrador e o comando Drush foram removidos após a execução; o
documento de migração é apenas um registro histórico.

`/painel-administrativo/revista` concentra visão geral e configuração; as
publicações administrativas ficam em `/painel-administrativo/revista/publicacoes`
e a fila em `/painel-administrativo/revista/comentarios`. A View
`aculta_magazine_admin_comments` e seu bloco estão em Config Sync e
`config/install`. Studio Marley apresenta fila contextual no purpose `account`,
revalidando acesso ao Node, ao campo e ao Comment. `aculta_magazine.settings`
seleciona papéis para autoria, revisão/publicação e comentários/moderação; os
padrões são `content_editor`, `content_editor` e `administrator`. A capacidade
ACULTA não substitui permissões Drupal, Entity/Revision/Field Access ou workflow.
Somente administradores alteram esses controles.

## Avisos da Revista

`aculta_magazine` publica eventos de publicação/republicação e atualização no
contrato central `aculta_messenger.notice_event_publisher`. O núcleo persiste o
aviso como `Message`, agrega a origem Revista na central e coordena e-mail pelo
Mailer conforme preferência da conta. `seguir_publicacao` é Flag opcional por
publicação; acompanhar não altera acesso e não inicia conversa. A administração
do Messenger pode habilitar separadamente publicação e atualização. O módulo
também publica avisos de envio para revisão (`draft` → `review`) e devolução
para ajustes (`review` → `draft`), com controles próprios no Messenger. Esses
avisos editoriais são destinados a autores e coautores que ainda podem acessar
a revisão, excluindo quem realizou a transição. Aprovação/publicação segue o
evento público de publicação; comentários e arquivamento não geram avisos.
Consulte a [arquitetura](docs/ARCHITECTURE.md#11-integração-com-avisos-do-aculta-messenger)
para audiência, autorização e evidências de teste.

Comentários contextuais continuam como entidades Comment no Node `revista`.
`aculta_forum` fornece o contexto compartilhado de leitura, resposta e moderação;
essa integração não cria tópico público. Galerias são entidades `media_gallery`
propriedade de `aculta_gallery`, incorporadas no corpo editorial pelo CKEditor 5
e Entity Embed; imagens continuam sob a política de `aculta_image`.
