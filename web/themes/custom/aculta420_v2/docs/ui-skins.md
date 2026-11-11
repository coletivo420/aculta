# UI Skins — ACULTA420 V2

## Contrato

- O Portal resolve `domain_id -> skin_id` em `aculta_portal.visual_skins`; o tema recebe `aculta_skin_id` e aplica `data-skin`.
- `aculta_site_key` vira `data-site-key` para regras funcionais/de layout. Esse atributo não escolhe cores.
- `aculta_color_mode` vira `data-theme`, preservando a preferência pessoal/default resolvida pelo Portal.
- `aculta420_v2.ui_skins.css_variables.yml` declara cinco variáveis CSS do UI Skins. Os scopes por skin/modo ficam em `third_party_settings.ui_skins.css_variables` no Config Sync. UI Skins `PageTop` imprime esses valores; nenhuma configuração global do tema é alterada por request.
- `--color-accent`, `--color-on-accent` e `--color-accent-sub-1..3` são tokens semânticos consumidos pelo tema.

## Estado e gates

O domínio e as dez skins estão mapeados. Testes estáticos cobrem definições e scopes; `ui_skins` está habilitado no Config Sync e Runtime como 1.2.1. A matriz Firefox ESR passou 300/300 nos dez domínios em cinco larguras e claro/escuro, sem overflow, com H1/main únicos e contraste mínimo 7,16:1. Links resolve Ruderalis; os outros nove Skunk. Cache-hit, zoom 200%, teclado, perfis e aceite visual humano aguardam homologação. Evidências: `tests/browser/capturas/ui-skins-ten-domains/2026-11-11/manifest.json`.

A configuração `aculta_purpose_palette`, os controles/schema, `PurposePaletteSchemes`, `purposePaletteStyle()`, os seletores `data-purpose-scheme` e tokens `--aculta-purpose-*` foram removidos. Busca em tema, Portal e Config Sync não encontra consumidores nem aliases/dual-read/fallback. A preferência de modo do usuário e `DomainPurposeManager` funcional permanecem preservados.
