# Matriz de compatibilidade contrib — Drupal 12 (verificada em 2026-11-03)

Fonte: páginas de projeto em drupal.org (campo "Works with Drupal" da release estável atual). Não é prova de compatibilidade: a resolução real deve ser feita com `composer require drupal/core-recommended:^12 --dry-run` em worktree.

Legenda: ✅ D12 listado na estável atual · ⚠️ D12 só em beta/alpha · ❌ D12 não listado · ❔ não verificado / resultado inconsistente

## Pacotes diretos (lock → estável atual → status)

| Pacote | Lock | Estável atual | Compat. | Status |
|---|---|---|---|---|
| drupal/bootstrap | 5.0.2 | 5.x (linha do lock) | ^10.3 \|\| ^11 | ❌ |
| drupal/captcha | 2.0.10 | 2.0.11 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/ckeditor5_plugin_pack | 1.5.6 | 1.5.6 | ^10.3 \|\| ^11 | ⚠️ (2.0.0-beta1 com ^12) |
| drupal/commerce (+ cart, checkout, etc.) | 3.3.10 | 3.3.13 | ^10.3 \|\| ^11 | ❌ |
| drupal/content_lock | 3.0.0 | 3.0.0 | ^10.2 \|\| ^11 | ❌ |
| drupal/crop | 2.6.0 | ❔ resultado inconsistente | — | ❔ |
| drupal/csp | 2.2.4 | 2.2.4 | ^10.2 \|\| ^11 | ❌ |
| drupal/ctools | 4.1.1 | 4.1.1 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/diff | 2.1.1 | 2.1.1 | ^10.5 \|\| ^11 \|\| ^12 | ✅ |
| drupal/domain | 3.0.1 | 3.0.2 | ^10.2 \|\| ^11 | ⚠️ (4.0.0-alpha2 com ^11.4 \|\| ^12) |
| drupal/entity_embed | 1.7.0 | 1.7 | ^10.3 \|\| ^11 | ❌ |
| drupal/entity_print | 2.19.0 | 2.19.0 | ^9.4 \|\| ^10 \|\| ^11 | ❌ |
| drupal/entity_usage | 5.0.1 | 5.0.1 | ^11.4 \|\| ^12 | ✅ |
| drupal/flag | 5.1.1 | ❔ página ambígua | — | ❔ |
| drupal/flood_control | 3.0.1 | 3.0.1 | ^10.2 \|\| ^11 | ❌ |
| drupal/freelinking | 4.0.3 | 4.0.2 | ^10.3 \|\| ^11 | ⚠️ (5.0.0-alpha2 com ^11.4 \|\| ^12) |
| drupal/geofield | 1.67.0 | 10.3.5 | ^10.3 \|\| ^11 \|\| ^12 | ✅ na linha atual; conferir se 1.67.0 é a mesma linha |
| drupal/google_tag | 2.0.9 | ❔ página não corresponde ao módulo | — | ❔ |
| drupal/group | 3.3.5 | 3.3.5 | ^10.3 \|\| ^11 | ⚠️ (4.0.0-alpha2 com ^11.4 \|\| ^12) |
| drupal/history | 1.0.0 | 1.0.0 | >11.3 | ❌ (validar: módulo habilitado como `history`) |
| drupal/image_widget_crop | 3.0.0 | 3.0.0 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/imagemagick | 4.0.2 | 5.0.1 | ^11.3 | ❌ (exige upgrade para 5.x; sem D12) |
| drupal/imageapi_optimize_binaries | 1.2.0-beta1 | ❔ não verificado | — | ❔ |
| drupal/key | 1.22.0 | 1.22 | ^9.1 \|\| ^10 \|\| ^11 | ❌ |
| drupal/login_emailusername | 3.0.1 | ❔ não lido (erro de servidor) | — | ❔ |
| drupal/media_duplicates | 2.0.4 | ❔ não confirmado | — | ❔ |
| drupal/media_gallery | 3.3.0 | 3.3.0 | ^10 \|\| ^11 \|\| ^12 | ✅ |
| drupal/message | 1.9.0 | ❔ página errada | — | ❔ |
| drupal/message_notify | 1.5.0 | ❔ não lido (erro de servidor) | — | ❔ |
| drupal/message_subscribe | 2.1.0 | 2.1.1 | ^10.2 \|\| ^11 \|\| ^12 | ✅ (atualizar para 2.1.1) |
| drupal/metatag | 2.2.0 | 2.2.5 | ^10.3 \|\| ^11 \|\| ^12 | ✅ (atualizar para 2.2.5) |
| drupal/node_co_authors | 1.2.3 | ❔ não verificado | — | ❔ |
| drupal/pathauto | 1.15.0 | 1.15 | ^10.2 \|\| ^11 | ❌ |
| drupal/pdfpreview | 1.2.0 | 1.2 | ^10 \|\| ^11 | ❌ |
| drupal/photoswipe | 5.0.12 | 5.0.12 | ^10.3 \|\| ^11 | ❌ |
| drupal/physical | 1.5.0 | ❔ não verificado | — | ❔ |
| drupal/private_message | 3.0.7 | 3.0.7 | ^10 \|\| ^11 | ❌ |
| drupal/profile | 1.14.0 | ❔ não verificado | — | ❔ |
| drupal/redirect | 1.13.0 | 1.13 | ^10 \|\| ^11 | ❌ |
| drupal/rename_admin_paths | 3.1.0 | 3.1.0 | ^10.2 \|\| ^11 | ❌ |
| drupal/reporting | 2.2.0 | 2.2.0 | ^10.1 \|\| ^11 \|\| ^12 | ✅ |
| drupal/role_hierarchy | 4.0.0 | 4.0.0 | ^8 … ^11 | ❌ |
| drupal/schema_metatag | 3.0.4 | ❔ não verificado | — | ❔ |
| drupal/search_api | 1.41.0 | 1.43 | ^10.3 \|\| ^11 \|\| ^12 | ✅ (atualizar para 1.43) |
| drupal/section_library | 2.0.1 | 2.0.1 | ^10.3 \|\| ^11 | ❌ |
| drupal/security_review | 3.1.3 | 3.1.3 | ^10.3 \|\| ^11 | ❌ |
| drupal/simple_oauth | 6.1.3 | 6.1.3 | ^10.3 \|\| ^11 | ❌ |
| drupal/simple_sitemap | 4.2.3 | 4.2.3 | ^10.3 \|\| ^11 | ❌ |
| drupal/site_alert | 1.4.0 | 1.4 | ^8 … ^11 | ❌ |
| drupal/social_auth | 4.1.2 | 4.1.2 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/social_auth_google | 4.0.3 | 4.0.3 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/tool | 1.0.0-beta12 | ❔ erro de servidor | — | ❔ |
| drupal/turnstile | 1.2.0 | 1.2.0 | ^10.6 \|\| ^11 \|\| ^12 | ✅ |
| drupal/ui_patterns | 2.0.23 | ❔ página sem informação | — | ❔ |
| drupal/ui_patterns_field_group | 2.0.0-beta2 | ❔ não verificado | — | ❔ |
| drupal/ui_patterns_layouts | 2.0.23 | ❔ erro do site | — | ❔ |
| drupal/ui_styles | 1.22.0 | 1.22 | ^10.3 \|\| ^11 \|\| ^12 | ✅ |
| drupal/username_enumeration_prevention | 1.4.0 | 1.4 | ^9.5 \|\| ^10 \|\| ^11 | ❌ |
| drupal/views_bulk_operations | 4.4.9 | ❔ página sem informação | — | ❔ |
| drupal/vvja | 2.0.1 | 2.0.0 | ^11.3 \|\| ^12 | ✅ |
| drupal/vvjb | 2.0.0 | 2.0.2 | ^11.3 \|\| ^12 | ✅ (atualizar para 2.0.2) |
| drupal/vvjf | 2.0.1 | 2.0.1 | ^11.3 \|\| ^12 | ✅ |
| drupal/vvjh | 2.0.1 | ❔ sem releases listadas | — | ❔ |
| drupal/vvjl | 2.0.2 | ❔ sem releases listadas | — | ❔ |
| drupal/vvjr | 2.0.1 | ❔ sem releases listadas, sem D12 | — | ❌ provável |
| drupal/vvjs | 2.0.2 | 2.0.2 | ^11.3 \|\| ^12 | ✅ |
| drupal/vvjt | 2.0.1 | 2.0.0 | ^11.3 \|\| ^12 | ✅ |
| drupal/webform | 6.3.1 | 6.3.1 | ^10.3 \|\| ^11 | ❌ **crítico** |
| drupal/webform_score | 2.4.0 | 2.4.0 | ^8.8 … ^11 | ❌ |

## MCP (sem D12 em nenhuma release)

| Pacote | Lock | Release mais recente | Compat. | Status |
|---|---|---|---|---|
| drupal/mcp_server | 2.0.0-beta7 | beta7 (29/10/2026), sem estável | ^10 \|\| ^11 | ❌ **crítico** |
| drupal/mcp_server_oauth | 1.0.0-alpha2 | alpha1 (29/07/2026) | ^11 | ❌ **crítico** |
| drupal/mcp_server_tool_bridge | 1.0.0-beta5 | beta5 (21/10/2026) | ^10 \|\| ^11 | ❌ **crítico** |
| drupal/mcp_server_ui | 1.0.0-beta2 | beta2 (02/10/2026) | ^10 \|\| ^11 | ❌ **crítico** |

## Não é módulo contrib de Drupal (ou nome não confere)

- `e0ipso/simple_oauth_21` v1.13.1: sem verificação — depende de `simple_oauth` 6.x.
- Bibliotecas JS (photoswipe, pdf.js, codemirror, select2, inputmask, intl-tel-input, rateit, timepicker, textcounter, popperjs, tabby, tippyjs, signature_pad, progress-tracker): não dependem da versão do Drupal; manter pins, validar apenas nos testes de navegador.

## Conclusões

1. **Bloqueadores de decisão (sem D12 estável e sem release alvo):** `webform`, `commerce`, `group` (só alpha), `mcp_server` e família, `pathauto`, `simple_oauth`, `simple_sitemap`, `redirect`, `private_message`, `social_auth` e `social_auth_google`, `csp`, `ctools`, `entity_print`, `flood_control`, `freelinking` (só alpha), `imagemagick` (sem D12 mesmo na 5.x), `bootstrap` (tema base).
2. **Ausência de D12 na página não prova incompatibilidade:** muitos mantenedores só atualizam o campo depois de testar. Só a resolução do Composer e os testes dizem o real.
3. **Já compatíveis ou quase:** `diff`, `entity_usage`, `media_gallery`, `reporting`, `turnstile`, `ui_styles`, `search_api` (1.43), `metatag` (2.2.5), `message_subscribe` (2.1.1), `vvja`, `vvjb`, `vvjf`, `vvjs`, `vvjt`. Atualizar a versão no composer.
4. **Verificação pendente (não lida ou inconsistente):** `login_emailusername`, `message`, `message_notify`, `tool`, `ui_patterns`, `ui_patterns_layouts`, `ui_patterns_field_group`, `views_bulk_operations`, `vvjh`, `vvjl`, `vvjr`, `crop`, `flag`, `google_tag`, `imageapi_optimize_binaries`, `media_duplicates`, `node_co_authors`, `physical`, `profile`, `schema_metatag`. Repetir com acesso estável ou direto ao Composer.

## Próxima ação recomendada

No worktree, rodar `composer require drupal/core-recommended:^12 --dry-run --ignore-platform-req=php+` e, em seguida, com `--with-all-dependencies`, para obter a resolução real. Isso substitui esta matriz por um resultado objetivo.
