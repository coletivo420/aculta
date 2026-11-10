# TODO — aculta_portal

- **ADR-054/055:** status do carrinho compõe `status-messages`; preserva Render API, papéis, link e contrato JS. Fallback do módulo é neutro.

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-01/02:** SiteKey, DomainSiteManager e ContentSiteKeyResolver implementados; options antigas removidas sem fallback. Migrar consumidores restantes e provar paridade de URLs, ACL e cache.
- **RN-03 State concluído:** `active_site_keys` substitui diretamente o State antigo. Kernel isolado 2/17; PHPCS PASS. Post-update está versionado, mas não executado no Runtime por exigir `updatedb`.
- **RN-00:** inventário reproduzível registra 1.610 referências sem triagem; completar matriz HTTP, State, sessão e owners.
- **US-02 UI Skins:** `DomainSkinResolver` mapeia dez Domain IDs a skin IDs e `AppearanceHooks` entrega `aculta_skin_id`/`aculta_site_key`; tema declara scopes oficiais UI Skins para dez × light/dark e remove config/form/tokens antigos. Runtime `ui_skins` está ativo; mapa/scopes ainda não chegaram à configuração ativa. Import parcial autorizado aguarda execução do post-update Portal; render real, baseline Links e captura visual pendentes.
- **ADR-054/055 visual:** Portal mantém dados; tema compõe `layout-section` e `surface`. UI Skins ativo; mapa/import e baseline Links pendentes.
- Depois, criar adapters de inventário para conteúdo, revisões e anexos e executor com backup/restauração, auditoria e revalidação por lote. Homologar em banco isolado; não executar limpeza nem `updatedb` sem autorização.

## Leitura e entrega

Leia este TODO, o código alvo e o trecho necessário do `CHANGELOG.md`. Ao concluir fase, atualize este arquivo e `docs/operations/status/aculta_portal.yml` no mesmo commit, sem histórico.
