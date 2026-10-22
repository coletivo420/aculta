# Wiki — instruções locais

Herde as regras da raiz e do Portal. Para trabalho na Wiki, consulte `README.md`,
`docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, somente nas seções pertinentes.

- O módulo existe em código desde 2026-10-22 — controlador, formulário de busca,
  rotas e testes unitários — e **não está habilitado**. Não habilitar, instalar
  configuração nem executar operações persistentes sem autorização do
  responsável. Enquanto estiver desabilitado, as rotas da Wiki não são
  registradas pelo Drupal; o Portal não as define mais.
- Testes unitários: `vendor/bin/phpunit -c web/core/phpunit.xml.dist
  web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Unit`.
- Nomes e caminhos de rota são contrato público: `aculta_portal.wiki_home`
  em `/wiki`, `aculta_portal.wiki_search` em `/wiki/busca` e
  `aculta_portal.wiki_search_preview` em `/wiki/busca/previa`. O prefixo
  `aculta_portal.` permanece mesmo com as rotas definidas neste submódulo.
- Domínio e purpose continuam no Portal (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`).
  A Wiki não redefine purpose nem host.
- Não importe classes de `aculta_statistics` neste módulo. A integração de
  métricas é feita por eventos e pelo `WikiProvider` do lado da estatística.
- Conteúdo sincronizado ou importado não gera métrica de publicação.
- Preserve UUIDs e IDs de `config/sync`; a propriedade da configuração ainda é
  decisão pendente do responsável.
- Teste com o tema ACULTA420 e também com o Olivero: o que é do módulo deve
  funcionar sem o tema.
- Use os gates pertinentes ao diff. Não deduza o estado do Runtime a partir
  desta documentação.
