# TODO — aculta_portal

- **Runtime Sync v2 (F0-F6):** sincronizador v2, classificador `R0-R4`/`RX`, `structural-plan`, `isolation-plan` e leitura somente leitura no painel do Deployer implementados; suíte isolada 24/24 PASS.
- **ADR-054/055:** status do carrinho compõe `status-messages`; preserva Render API, papéis, link e contrato JS. Fallback do módulo é neutro.

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-01/02:** SiteKey, DomainSiteManager e ContentSiteKeyResolver implementados; options antigas removidas sem fallback. Migrar consumidores restantes e provar paridade de URLs, ACL e cache.
- **RN-03 State concluído:** `active_site_keys` substitui diretamente o State antigo. Kernel isolado 2/17; PHPCS PASS. Post-update executado no Runtime após snapshot autorizado.
- **RN-00:** inventário reproduzível registra 1.610 referências sem triagem; completar matriz HTTP, State, sessão e owners.
- **US-02 UI Skins:** `DomainSkinResolver` entrega `aculta_skin_id`/`aculta_site_key`; tema declara scopes oficiais para dez skins × dois modos e remove o motor visual antigo. Runtime ativo; mapa limitado ao piloto `links_aculta_org: ruderalis`, demais domínios usam `skunk` até aceite visual.
- **ADR-054/055 visual:** Portal mantém dados; tema compõe `layout-section` e `surface`. UI Patterns Library autenticada voltou a abrir após rebuild do container; `locale/translations` sai do agregado quebrado, 11/11 scripts responderam 200 e `text/javascript`. CSP inline segue com violações report-only que não bloqueiam execução. Piloto Links, cache por domínio e aceitação visual ainda pendentes.
- Depois, criar adapters de inventário para conteúdo, revisões e anexos e executor com backup/restauração, auditoria e revalidação por lote. Homologar em banco isolado; não executar limpeza nem `updatedb` sem autorização.

## Leitura e entrega

Leia este TODO, o código alvo e o trecho necessário do `CHANGELOG.md`. Ao concluir fase, atualize este arquivo e `docs/operations/status/aculta_portal.yml` no mesmo commit, sem histórico.
