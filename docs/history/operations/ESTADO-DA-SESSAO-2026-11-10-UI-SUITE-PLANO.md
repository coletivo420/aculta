# ADR-054 + ADR-055 - Migracao integral visual e UI Skins - 2026-11-10

- **Objetivo:** complementar as ADR-054 e ADR-055 para substituir a camada visual antiga por UI Skins e migrar TODO o visual legado para UI Suite, com classificacao obrigatoria deprecado/obsoleto.
- **Decisoes:** UI Skins passa a ser fonte visual unica por dominio; `DomainPurposeManager` continua apenas para roteamento, acesso, URLs, dados e cache. Preferir `ui_patterns_field_group:2.0.x-dev@dev` com gates de seguranca/Drupal 12. `ui_patterns_ckeditor5` expressamente fora desta rodada; `ui_styles_ckeditor5` permanece.
- **Entregas documentais:** `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`, plano US-00 a US-14 revisto, ADR-054/055 reconciliadas, politica de catalogo/deprecacao e prompt `docs/architecture/ACULTA-ADR054-055-PROMPT-AGENTE-EXECUCAO.md`; TODO, README e status do tema sincronizados.
- **Evidencias:** leitura do codigo de `DomainPurposeManager`, `SurfaceSettingsHooks`, `AppearanceHooks`, `theme-settings.php`, manifestos e Config Sync, mais pesquisas upstream UI Skins e Field Group. Nao houve instalacao, mudanca de configuracao ativa ou alteracao de codigo funcional.
- **Gates:** auditoria documental apenas, testes Runtime/Drupal/Browser **DEFERRED**, nao PASS.
- **Proximo passo:** executar US-00 inventario real, US-02 ponte UI Skins/domínio e US-12 classificacao integral, depois migracoes por area e encerramento US-14 com aceite visual e validações.
