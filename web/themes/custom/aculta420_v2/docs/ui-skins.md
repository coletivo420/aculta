# UI Skins — ACULTA420 V2

## Contrato

- O Portal resolve `domain_id -> skin_id` em `aculta_portal.visual_skins`; o tema recebe `aculta_skin_id` e aplica `data-skin`.
- `aculta_site_key` vira `data-site-key` para regras funcionais/de layout. Esse atributo não escolhe cores.
- `aculta_color_mode` vira `data-theme`, preservando a preferência pessoal/default resolvida pelo Portal.
- `aculta420_v2.ui_skins.css_variables.yml` declara cinco variáveis CSS do UI Skins. Os scopes por skin/modo ficam em `third_party_settings.ui_skins.css_variables` no Config Sync. UI Skins `PageTop` imprime esses valores; nenhuma configuração global do tema é alterada por request.
- `--color-accent`, `--color-on-accent` e `--color-accent-sub-1..3` são tokens semânticos consumidos pelo tema.

## Estado e gates

O domínio e as dez skins estão mapeados. Testes estáticos cobrem definições e scopes; `ui_skins` está habilitado no Config Sync e instalado no Runtime como 1.2.1. O plugin manager descobriu as cinco definições, cada uma com vinte scopes. O Runtime ainda não tem o mapa `aculta_portal.visual_skins` nem valores ativos em `third_party_settings.ui_skins.css_variables`; render CSS, cache entre domínios, preferência de modo, capturas e aceite visual aguardam a configuração importada.

A configuração `aculta_purpose_palette` foi removida do Runtime em 2026-11-10, após confirmar ausência de leitores no código. O código e o Config Sync não têm aliases, dual-read ou fallback de dados visuais antigos. Não há cópia runtime da paleta removida; o cutover ainda precisa importar UI Skins e o mapa atual antes do aceite visual.
