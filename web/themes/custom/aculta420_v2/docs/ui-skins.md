# UI Skins — ACULTA420 V2

## Contrato

- O Portal resolve `domain_id -> skin_id` em `aculta_portal.visual_skins`; o tema recebe `aculta_skin_id` e aplica `data-skin`.
- `aculta_site_key` vira `data-site-key` para regras funcionais/de layout. Esse atributo não escolhe cores.
- `aculta_color_mode` vira `data-theme`, preservando a preferência pessoal/default resolvida pelo Portal.
- `aculta420_v2.ui_skins.css_variables.yml` declara cinco variáveis CSS do UI Skins. Os scopes por skin/modo ficam em `third_party_settings.ui_skins.css_variables` no Config Sync. UI Skins `PageTop` imprime esses valores; nenhuma configuração global do tema é alterada por request.
- `--color-accent`, `--color-on-accent` e `--color-accent-sub-1..3` são tokens semânticos consumidos pelo tema.

## Estado e gates

O domínio e as dez skins estão mapeados. Testes estáticos cobrem definições e scopes. O snapshot de Runtime de 2026-11-10 registrou `ui_skins` Disabled; descoberta real, ativação no Runtime, render CSS, cache entre domínios, contraste, preferência de modo, browser captures e aceite visual continuam DEFERRED.

A configuração, formulário, classe, tokens e seletores antigos por purpose foram removidos diretamente. Não há aliases, dual-read ou fallback de dados visuais antigos. A reversão é feita por revert do commit.
