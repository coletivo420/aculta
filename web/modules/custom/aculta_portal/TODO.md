# TODO — aculta_portal

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-01/02 em andamento:** `SiteKey`, `DomainSiteManagerInterface/DomainSiteManager` e `ContentSiteKeyResolver` implementados; options de rota antigas removidas, sem fallback. `DomainSiteRequestSubscriber` e destinos de login/sessão já usam `site_key` direto; migrar consumidores restantes e provar paridade de URLs/alias, ACL e cache.
- **RN-03 State concluído:** `active_site_keys` substitui diretamente o State antigo. Kernel isolado 2/17; PHPCS PASS. Post-update está versionado, mas não executado no Runtime por exigir `updatedb`.
- **RN-00 parcial:** `scripts/inventory-site-key-contracts.php` registra 1.610 referências ainda sem triagem semântica; completar matriz HTTP, State, sessão e consumidores por owner.
- **US-02 UI Skins:** `DomainSkinResolver` mapeia dez Domain IDs a skin IDs e `AppearanceHooks` entrega `aculta_skin_id`/`aculta_site_key`; tema declara scopes oficiais UI Skins para dez × light/dark e remove config/form/tokens antigos. Runtime `ui_skins` continua Disabled; ativação, render real, baseline Links, contraste e captura visual pendentes.
- Depois, criar adapters de inventário para conteúdo, revisões e anexos e executor com backup/restauração, auditoria e revalidação por lote. Homologar em banco isolado; não executar limpeza nem `updatedb` sem autorização.

## Leitura e entrega

Leia este TODO, o código alvo e o trecho necessário do `CHANGELOG.md`. Ao concluir fase, atualize este arquivo e `docs/operations/status/aculta_portal.yml` no mesmo commit, sem histórico.
