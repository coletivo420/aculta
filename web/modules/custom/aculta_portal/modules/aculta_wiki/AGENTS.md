# Wiki — regras locais vigentes

Herde as regras da raiz (`AGENTS.md`) e do Portal (`web/modules/custom/aculta_portal/AGENTS.md`). Para trabalho na Wiki, consulte `README.md`, `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, somente nas seções pertinentes.

Caminhos das Suites e residência da WE SATIVA: `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md` (retificado em 2026-10-30). Não repita a tabela aqui. `/studio` e `/studio/*` respondem 404.

Histórico de versões e rodadas (dev.12 a dev.117): `docs/history/modules/aculta_wiki-AGENTS-ARQUIVO-2026-11-05.md`. Não carregue por padrão.

## Contratos públicos e propriedade

- Rotas são contrato público pelos IDs `aculta_portal.wiki_home` (caminho interno `/wiki`), `aculta_portal.wiki_search` (`/busca-wiki`) e `aculta_portal.wiki_search_preview` (`/busca-wiki/previa`). No domínio `wiki420`, a entrada pública é `/` e `/wiki` responde 404. O prefixo `aculta_portal.` permanece.
- Domínio e purpose ficam no Portal (`DomainPurposeManager`, `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`). A Wiki não define purpose nem host.
- Wiki é dona de verbetes, propostas e permissões. Rotas de moderação que pertencem à Marley continuam em `account:/marley/...`; não mova rotas automaticamente.
- Não crie segunda fila, aba ou camada de autorização no Studio. Moderadores de propostas não recebem grants de Node por causa de gate de seção.
- Acesso ao CTA usa `createAccess('wiki_entry')`, com acesso efetivo e confirmação compartilhada de e-mail (`EmailConfirmationPolicy`). Não crie bypass para conta não confirmada.
- Nenhuma propriedade de Entity Access ou de purpose é concedida por papel de nome. Use `view`, `update`, `view revision` e permissões reais.

## Propostas editoriais (leitura sob demanda)

- Fluxo, cotas, idempotência, concorrência, retirada, fila e histórico: `docs/AGENT-PROPOSTAS.md`.

## Mídia, busca e configuração

- Imagem de destaque: `private://wiki/imagens`. A Media acompanha o estado publicado do verbete (`WikiEntryImageHooks`) e fica publicada enquanto outro Node publicado a referenciar. Download é negado quando só restam referências em revisões históricas.
- Upload revertido por erro ou rebuild: invalide caches de File e Media e limpe a referência temporária do FormState.
- Busca: `wiki_entry` está no índice `aculta_conteudo` (`config/sync`). Verbetes já publicados entram no índice só depois de `drush search-api:index` no ambiente. As três rotas públicas seguem como contrato.
- Preserve UUIDs e IDs de `config/sync`. A propriedade dessa configuração é decisão pendente do responsável.
- `aculta_wiki.settings` não tem arquivo em `config/sync` nem em `config/install`. Os padrões (desligado, sem destinatários) vivem no código.
- Administração: `/painel-administrativo/aculta/wiki`, permissão `administer aculta_wiki`. Liga o aviso de revisão e cadastra caixas editoriais.

## Aviso de revisão e LGPD

- `WikiReviewHooks` (`entity_update`) usa `getOriginal()` e avisa só na entrada em revisão.
- O contexto do e-mail tem título, estado e link de edição. **Não inclua o nome de quem editou** sem atualizar o pacote LGPD.
- Fila do `aculta_mailer` e URL pública são serviços opcionais (`@?…`). O módulo não depende do mailer em `info.yml`.

## Integrações

- Messenger: a Wiki publica eventos editoriais versionados em fila persistente (Queue API). O núcleo Messenger controla avisos, preferências e e-mail. Não crie notificador ou transporte paralelo na Wiki. Contrato: `docs/MESSENGER-EVENT-CONTRACT.md`.
- Status: sem subscriber do Messenger não há aviso de site funcional, e os e-mails continuam. Não declare aviso Messenger funcional antes do subscriber. Notificação em caixas editoriais é decisão de produto separada, ainda em análise.
- Estatística: não importe classes de `aculta_statistics`. A integração de métricas é feita por eventos e pelo `WikiProvider` do lado da estatística. Conteúdo sincronizado ou importado não gera métrica de publicação.
- GanjaLab: use a `WikiTab` existente. Revisões consomem `entryRevisions()`; edição segue o formulário Node/Content Moderation; propostas consomem `proposals()` e `WikiProposalDecisionForm`.

## Testes e Runtime

- Unit: `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Unit`.
- Teste contratos e renderização do módulo sem dependência do tema ACULTA420.
- Use os gates pertinentes ao diff (catálogo em `docs/operations/GATES.md`).
- Kernel MariaDB isolada e fixtures de grants em `tests/modules`. UID 1 não prova acesso.
- Não declare cobertura multiprocesso PHP sem executar um worker independente.
- Runtime não está homologado integralmente. Não instale configuração nem execute operação persistente sem autorização aplicável. Não deduza o estado do Runtime a partir desta documentação.
- O Portal não define mais as rotas da Wiki.
