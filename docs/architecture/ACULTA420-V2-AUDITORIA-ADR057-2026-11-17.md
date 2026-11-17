# Auditoria técnica aprofundada - ACULTA420 V2 para ADR-057

**Data:** 2026-11-17  
**Repositório:** [coletivo420/aculta](https://github.com/coletivo420/aculta)  
**Ref inspecionada:** dev @ **5c496047509f43831de2a6996cbc6ba95d867749**  
**ADR resultante:** [ADR-057](../decisions/ADR-057-consolidacao-final-aculta420-v2-ui-suite.md)  
**Método:** leitura da árvore GitHub completa, arquivos fonte/configuração, documentação operacional, histórico declarado, check-runs CI e amostra de logs Kernel; pesquisa de páginas upstream.  
**Limitação central:** sem checkout executável nem acesso ao Runtime/BD e sem execução local de testes nesta auditoria. PASS histórico é rotulado como histórico, não como teste executado em 2026-11-17.

## 1. Sumário executivo

O problema principal **não é falta de um tema base**: a fundação SDC/UI Patterns/UI Skins foi construída. As maiores lacunas estão no acabamento de consumidores reais, reconciliação entre plano e código, fechamento formal da migração Domain/site_key, integração editorial, visual regression mais determinística, qualidade Kernel, performance medida e segurança de Field Group.

**Conclusão arquitetural:** consolidar e estender. Não reconstruir shell, reimplementar UI Patterns, substituir a Library oficial, recriar Bootstrap ou eliminar todos os Twig por métrica.

### Estado por dimensão

| Frente | Constatação | Status de auditoria |
| --- | --- | --- |
| SDC e catálogo | 150 componentes, 244 stories; ADR-056 reporta render isolado integral | Implementado; runtime atual DEFERRED |
| Layout Builder | ADR-054 descreve 22/24 displays elegíveis, 2 exclusões por perda de conteúdo | Parcialmente homologado; revalidar |
| Site_key | Código atual contém DomainSiteManager/Registry e testes, contradiz capa antiga do plano RN | Migração de API implementada; RN-05 residual |
| UI Skins | Dez skins e duas modalidades; cinco variáveis CSS; config ativa versionada | Implementado; aceite multidomínio residual |
| UI Styles/Icons | Módulos e packs habilitados/definidos; uso editorial desigual | Parcial, consumidor a consumidor |
| Field Group | Ausente do core.extension versionado; ADR-055 relata Runtime antigo habilitado | Desalinhamento histórico; segurança BLOCKED |
| Regressão visual | Playwright, Axe, 140 snapshots, 50 manifests de rota | Infraestrutura existente; ampliar confiabilidade |
| Performance | CSS audit e CSS scope existentes; sem orçamento Web Vitals identificado | Baseline de campo/laboratório DEFERRED |
| CI PHPStan | Check PHPStan level 10 gate success no HEAD observado | PASS remoto específico |
| CI Kernel | 12 check-runs de shards fail em dois eventos; continue-on-error true | FAIL remoto; triagem necessária |
| Runtime/BD | ADR-056 relata doctor 0 drift/updates em 2026-11-14 | Histórico, não leitura atual |
| Templates | 17 overrides de tema restantes | Não presumir obsolescência |

## 2. Inventário Git exato do snapshot

Árvore GitHub obtida por endpoint Git Trees recursivo: **sem truncamento**.

| Objeto/área | Quantidade |
| --- | ---: |
| Arquivos do tema | 3.980 |
| Entradas da árvore do tema, inclusive diretórios | 4.165 |
| Manifests *.component.yml | 150 |
| Stories *.story.yml | 244 |
| CSS do tema | 161 arquivos |
| Bytes somados de fontes CSS versionadas | 433.593 bytes |
| JavaScript do tema | 14 arquivos |
| SVGs do tema | 3.117 arquivos |
| Overrides em templates/ do tema | 17 arquivos |
| Testes PHP *Test.php dentro do tema | 10 |
| Baselines PNG em tests/browser/__snapshots__ | 140 |
| Arquivos tests/browser/**/routes.json | 50 |

**Interpretação:** os 433.593 bytes são soma de **fontes**, não CSS transferido por request. Os 3.117 SVGs são ativos disponíveis, não 3.117 downloads por página. Os 140 snapshots não provam cobertura de todos os dez domínios e perfis; é necessário mapear manifest/rota versus snapshot.

Maiores CSS por origem: tokens.css (21.698 B), forum-post-card.css (21.390 B), multidomain-bar.css (20.846 B), messenger-trigger.css (18.631 B), account-shell.css (11.250 B), revista-publication.css (11.208 B). **Priorizar medição de consumo, não corte cego dos maiores.**

Fontes e assets: [aculta420_v2.libraries.yml](../../web/themes/custom/aculta420_v2/aculta420_v2.libraries.yml) carrega Inter e Oswald via Google Fonts externo na biblioteca global. Há motivo concreto para benchmark de fontes, mas nenhuma conclusão de economia ainda.

## 3. Consolidação ADR-054/055/056 e contradições

### A-01 - Documentos RN estão defasados em relação ao código [ALTO]

A capa de [ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md](ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md) afirma planejamento não executado em 2026-11-10. Já o capítulo "Estado reconciliado no checkout" de [ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md](ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md), atualizado em 2026-11-14, declara a API nova existente e DomainPurposeManager ausente.

A árvore atual confirma:
- web/modules/custom/aculta_portal/src/Domain/DomainSiteManager.php
- web/modules/custom/aculta_portal/src/Domain/DomainSiteManagerInterface.php
- web/modules/custom/aculta_portal/src/Domain/SiteKeyRegistry.php
- web/modules/custom/aculta_portal/src/Domain/ActiveSiteKeysState.php
- web/modules/custom/aculta_portal/src/EventSubscriber/DomainSiteRequestSubscriber.php
- web/modules/custom/aculta_portal/src/PathProcessor/SiteFrontPathProcessor.php
- Testes Unit e Kernel correlatos.

A fonte DomainSiteManager já implementa getCurrentSiteKey(), getSiteKeyForDomainId() e runInSite(). Não há arquivo DomainPurposeManager.php na árvore atual. O que falta é demonstrar **RN-05** com cobertura de consumidores, cache, segurança, sessão, route options, URLs, fail-closed, perfis e Runtime. Não refazer RN-01/02 por pressuposto documental.

**Ação ADR-057:** reconciliar statuses e gerar inventário por contrato funcional; apenas diferenças demonstradas viram trabalho de código.

### A-02 - ADR-056 concluída, mas estados históricos misturados [MÉDIO]

[Tema TODO](../../web/themes/custom/aculta420_v2/TODO.md) declara ADR-056 P0-P4 concluída, 150 SDCs renderizados, Library oficial 200 e preflight doctor zero drift/updates em 2026-11-14. Versões anteriores da ADR-054 e do roadmap citam 145, 148, 150 e flags DEFERRED de momentos diferentes. A métrica estática correta neste snapshot é 150/244. A descoberta Runtime anterior não permite afirmar seu estado na data desta auditoria.

**Ação:** manter ADR-056 fechada, carimbar snapshots por data/SHA e atualizar descrições contraditórias nas fases executivas apropriadas.

### A-03 - US-14 e aceite multidomínio continuam importantes [ALTO]

ADR-054 contém evidência histórica de 300/300 checagens Firefox nos dez domínios, além de 70/70 verificações posteriores de foco/zoom/cache anônimo. Porém a mesma ADR registra perfis editor/admin e aceite humano pendentes; relata falha em persistência de sessão de teste na sequência de login em 2026-11-11. Páginas públicas consultadas eram private/no-cache e UNCACHEABLE: exigir HIT nesse caso seria gate inválido.

**Ação:** retestar sessão com auth-login.mjs, acesso por perfil, URLs e cache contexts por host. Distinguir cache correto sem HIT de cache incorreto entre domínios.

### A-04 - Configuração Views ainda precisa comparação real [MÉDIO]

O TODO do tema lista importações de estilo UI Patterns Views pendentes para Revista e Cursos, enquanto o preflight de 2026-11-14 relata zero drift. Isso não prova nem erro atual nem reconciliação atual: são afirmações de momentos ou escopos diferentes.

**Ação:** conferir config ativa e exportada no Runtime mediante doctor read-only e export focal; import exige R3 e autorização por operação.

### A-05 - Twig remanescente não é lixo [MÉDIO]

17 arquivos em web/themes/custom/aculta420_v2/templates/, entre eles:
- layout/html.html.twig e layout/page.html.twig;
- page-title.html.twig, status-messages.html.twig, breadcrumb.html.twig, pager.html.twig;
- input--submit.html.twig e aculta-mailer-wrap.html.twig;
- dois adaptadores Comment;
- três overrides da UI Patterns Library oficial para overview/stories;
- quatro overrides Views para Fórum e Revista.

A ADR-056 contém triagem específica de hooks, slots, attributes, forms, responses e fallbacks. **Não** eliminar genericamente; manter ou substituir somente após prova de paridade funcional dos consumidores, incluindo pager, headers, anexos, threading e cache.

## 4. UI Suite, componentes e área editorial

### A-06 - UI Patterns e Layout Builder [MÉDIO]

core.extension ativa ui_patterns, ui_patterns_blocks, ui_patterns_field, ui_patterns_field_formatters, ui_patterns_layouts, ui_patterns_library e ui_patterns_views. O tema é baseless, Drupal ^11.5 || ^12, schema SDC estrito, com cinco regiões: institution, header, content, sidebar_first, footer.

O inventário foi regenerado em 2026-11-17 a partir do checkout executável e tem 459 registros: 150 SDCs, 159 displays de Views, 52 placements, 22 displays Layout Builder, 22 templates de módulos, 17 overrides do tema, 36 integrações UI Suite e um engine visual. Status: 249 UI_SUITE_NATIVE, 165 FUNCTIONAL_NATIVE_EXCEPTION, 21 MIGRATION_IN_PROGRESS, 2 LEGACY_OBSOLETE e 22 BLOCKED_UPSTREAM. O campo `runtime_status` segue `not_observed` em 88 registros; **UI_SUITE_NATIVE não equivale a demonstração do Runtime**.

**Ação:** ampliar o inventário para presets/controls e distinguir native, adapter, candidate, blocked, no-consumer e não observado.

### A-07 - Presets editoriais [NOVO, ALTO POTENCIAL]

Não foi identificado um catálogo versionado de composições reutilizáveis por dez site_keys equivalente ao que a ADR-057 pretende. Já existem SDCs de landing, layout-page, layout-section, surface, headings, cards e dashboards. Os presets devem compor esses elementos e não duplicá-los.

**Ação:** mapear APIs nativas de composições e reuso no UI Patterns/Drupal instalado; especificar configuração exportável, permissões, versões, rollback, revisão e consumidores. Criar no mínimo dez exemplos reais, não uma nova taxonomia visual obrigatória. O primeiro catálogo com dez fontes reais por `site_key` está em [ADR-057-F2-presets-2026-11-17.md](../operations/testing/ADR-057-F2-presets-2026-11-17.md); ele identifica quais exemplos são YAML exportado e quais ainda são adaptadores de código. F2 continua parcial até confirmar edição, permissões, persistência e rollback por API instalada.

### A-08 - UI Styles já é amplo [MÉDIO]

[aculta420_v2.ui_styles.yml](../../web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml) declara estilos para button, card_accent, revista_accent, unpublished_preview, heading_accent, heading_surface, spacing_section, surface_style, layout_section_surface, surface_accent_style, surface_elevation_style, surface_radius_style. Inclui molduras tricolores, green-soft, radius e elevação.

core.extension habilita ui_styles, ui_styles_block, ui_styles_ckeditor5, ui_styles_entity_status, ui_styles_layout_builder, ui_styles_page, ui_styles_ui_patterns e ui_styles_views.

A [documentação UI Styles](../../web/themes/custom/aculta420_v2/docs/ui-styles.md) confirma pilotos e mantém homologação de edição real, página/region, layout section e CKEditor 5 pendente. O settings exportado não mostrou ui_styles_page.regions na inspeção focal; **isso exige verificação da API/config instalada**, não inventar valor de configuração.

**Ação:** priorizar consumidores, papéis e persistência real sobre criação indiscriminada de opções novas.

### A-09 - UI Icons já tem packs e módulos [MÉDIO]

[aculta420_v2.icons.yml](../../web/themes/custom/aculta420_v2/aculta420_v2.icons.yml) define Phosphor Regular/Duotone, ACULTA Brócolis e Fluent Emoji com sources SVG; outros packs/variantes devem ser auditados no manifest completo. Config Sync ativa ui_icons, ui_icons_field, ui_icons_menu, ui_icons_patterns, ui_icons_picker e ui_icons_popover. [ui_icons_patterns.settings.yml](../../config/sync/ui_icons_patterns.settings.yml) usa icon_selector: icon_picker.

A ADR-055 registra validação parcial de packs e import de settings, mas não prova todas as formas, menus, patterns e campos em Runtime autenticado.

**Ação:** inventariar páginas de formulário consumidoras, campos compatíveis, icon objects, acessibilidade, persistência de menus/presets, seleção de emojis e separação de reações funcionais.

### A-10 - Field Group requer segurança e reconciliação [CRÍTICO]

[core.extension.yml](../../config/sync/core.extension.yml) **não** lista ui_patterns_field_group habilitado. A ADR-055 registra snapshot em 2026-11-11 de módulo preexistente ativado no Runtime e ausente no Sync. Registra também o lock de drupal/ui_patterns_field_group dev-2.0.x SHA 4403e7b0e27679f47113e96fcdc5349c95e164d1 e Field Group 4.0.0. Isso é **evidência histórica**, não leitura do lock/Runtime hoje.

A página [UI Patterns Field Group](https://www.drupal.org/project/ui_patterns_field_group) confirma que o projeto **não está coberto pela política de avisos de segurança** e a release beta2 não declara Drupal 12. A página do [UI Patterns](https://www.drupal.org/project/ui_patterns/releases) aponta correções e compatibilidade no módulo base, mas compatibilidade deste não se transfere automaticamente ao submódulo Field Group. A segurança de UI Patterns 2 exige no mínimo a correção do advisory SA-CONTRIB-2026-075, resolvido em 2.0.17.

**Ação:** conferir lock e Runtime atuais; auditar source/formatter; teste isolado Drupal 11 e preparação Drupal 12; plano de migração sem perda de fields; revisão R2/R3 e autorização para habilitar/importar. Não declarar PASS só porque composer audit --locked não encontrou advisory para pacote fora da cobertura.

### A-11 - UI Skins [MÉDIO]

[aculta420_v2.ui_skins.css_variables.yml](../../web/themes/custom/aculta420_v2/aculta420_v2.ui_skins.css_variables.yml) declara cinco variáveis semânticas com seletores explícitos por dez skins e dois modos. [docs/ui-skins.md](../../web/themes/custom/aculta420_v2/docs/ui-skins.md) confirma o desenho Domain ID -> skin_id, data-skin/data-theme, sem motor de paletas antigo. O mapa atual de skins usa Skunk por default e Ruderalis em Links no snapshot documental.

**Ação:** validar identidade e cache para dez domínios, estados de preferências e alterações de skin com isolamento entre hosts; não reintroduzir CSS purpose.

## 5. Qualidade, CI, navegador e acessibilidade

### A-12 - CI está parcialmente vermelho [CRÍTICO PARA ACEITE]

No commit base, a API GitHub de check-runs retornou **38 checks: 19 success, 12 failure e 7 skipped**. As 12 falhas correspondem a seis shards Kernel vistos em dois eventos de workflow; não afirmar que existem 12 causas distintas. Check "PHPStan level 10 gate" success. O workflow [.github/workflows/php-quality.yml](https://github.com/coletivo420/aculta/blob/dev/.github/workflows/php-quality.yml) define continue-on-error: true no job phpunit-kernel, de modo que sucesso do workflow agregado não prova a suíte Kernel verde.

Exemplo verificado do [job 133980919811](https://github.com/coletivo420/aculta/actions/runs/44065410069/job/133980919811): **184 testes, 3.891 assertions, 3 failures e 2 warnings**. As falhas são:
1. ActivityContentIndexProviderKernelTest::testActivityFormHooksAreDispatchedByPortal - rótulo "Conta responsável pelo cadastro" esperado, obtido vazio;
2. aculta_H5PCourseAccessIntegrationTest::testPlaybackRequiresCurrentCourseMembershipAndFieldReference - render esperado do PDF viewer ausente;
3. MapSettingsFormKernelTest::testSaveDoesNotExportCredential - assert de texto inesperado.
Os warnings citam include_once de aculta_messenger/private_message.theme.inc ausente. Isso indica integração/retrato de teste de múltiplos módulos; **não há evidência aqui de que o CSS/SDC V2 causou as falhas**.

Há um check de descoberta de browser **skipped**; não houve execução browser contra Runtime comprovada pelo CI deste HEAD. A auditoria não executou PHPUnit nem PHPStan localmente.

**Ação:** classificar falhas por ownership e tornar bloqueantes as suites comprovadamente estáveis antes de definir RC.

### A-13 - Regressão visual já existe, com lacunas [ALTO]

[playwright.visual.spec.mjs](../../tests/browser/playwright.visual.spec.mjs) já percorre public_routes dos routes.json, duas modalidades, larguras **320, 375, 768, 1024, 1440**, reduz motion, espera fontes e captura screenshot. [playwright.visual.config.mjs](../../tests/browser/playwright.visual.config.mjs) controla snapshots, updateSnapshots none por padrão, modo missing somente sob opt-in e outputs SUITE_OUT. Os snapshots guardados no Git são 140 PNGs.

Lacunas arquiteturais identificáveis:
- Não há garantia de que public_routes e snapshots cubram dez domínios/skins e principais estados autenticados.
- A suíte depende de variáveis de origem; testes podem ficar skipped sem host.
- A imagem é nomeada por front/tema/largura; revisar risco de colisões quando uma mesma frente lista mais de uma rota.
- A configuração impõe maxDiffPixelRatio: 0 e precisa de tratamento consciente para ruído do ambiente, sem afrouxamento indiscriminado.
- A matriz já usa 375, não 390; mudança demanda aprovação de novas referências.
- Screenshots comprovam aparência e overflow, não acesso, eventos nem cache.

**Ação:** ampliar cobertura no runner existente, adicionar estabilidade de fixtures, capturas por contexto/rota/skin/papel e gates de revisão humana com orçamento de diffs.

### A-14 - Axe e testes funcionais já existem [ALTO]

[playwright.a11y.spec.mjs](../../tests/browser/playwright.a11y.spec.mjs) executa @axe-core/playwright com tags WCAG2A/AA, WCAG21A/AA e WCAG22AA, bloqueando violações serious/critical, além de reflow a 320. Falhas moderate/minor são ignoradas no gate corrente, portanto "verde" **não prova WCAG 2.2 AA integral**. Não cobre revisão manual de teclado e leitor de tela. [playwright.functional.spec.mjs](../../tests/browser/playwright.functional.spec.mjs) tem testes de acesso anônimo e login com contas de teste quando há origens/credenciais.

**Ação:** definir trilha WCAG 2.2 AA por severidade e impacto, auditoria manual de foco e leitor de tela, zoom/reflow 200%, hit targets e estado de validação de formulários, com bloqueio para falhas críticas.

## 6. Performance e dívida de assets

### A-15 - CSS existe em escopo global/contextual [MÉDIO]

O tema carrega apenas uma seleção reduzida de CSS no global-styling, enquanto SDCs e bibliotecas de contexto agregam folhas específicas. Os scripts de css:audit/css:verify e validators de CSS scope já estão presentes. O volume bruto de CSS não é o payload de rede.

**Ação:** coletar por rota transferSize, tamanho comprimido, CSS coverage, duplicação, FOUC, custo de render e assets de SDC carregados; comparar com budgets reais e não remover um CSS funcional apenas por estar entre os maiores.

### A-16 - Web Vitals sem instrumento dedicado identificado [ALTO]

Na árvore versionada não foi encontrado runner nomeado Lighthouse, Web Vitals ou performance budget (busca por nome; ausência não descarta código genérico noutros scripts). Existem checks gerais de CSS, responsividade e visual. Não foram obtidas métricas de campo ou laboratório para LCP/INP/CLS.

**Ação:** instrumentar baseline das rotas críticas antes de estabelecer budgets. Usar limiares públicos de "good" no p75 como referência: LCP <= 2,5 s, INP <= 200 ms, CLS <= 0,1; separar RUM/CrUX de Lighthouse/Playwright e não estimar INP a partir de TBT.

### A-17 - Fontes externas são candidato concreto [MÉDIO]

global-styling inclui Google Fonts (Inter e Oswald, várias espessuras), com display=swap. Proposta de self-host deve comparar custo de conexão, cache, total transferido, subsets, pesos usados e comportamento do fallback. **Sem dado de browser, não há ganho comprovado.**

## 7. Registro de riscos priorizado

| Código | Severidade | Fato gerador | Primeiro gate |
| --- | --- | --- | --- |
| AUD57-01 | CRÍTICO | Field Group fora Security Advisory e sem Drupal 12 declarado | Perícia + sandbox antes de habilitar |
| AUD57-02 | CRÍTICO | CI Kernel com falhas e continue-on-error | Triagem independente e gate estabilizado |
| AUD57-03 | ALTO | RN-05 sessão/ACL/cache e perfis não fechados | Auth + multihost + fail-closed |
| AUD57-04 | ALTO | Revisão humana/Runtime dos dez domínios incompleta | US-14 |
| AUD57-05 | ALTO | Presets editoriais inexistentes como catálogo homologado | P03 piloto e config reproduzível |
| AUD57-06 | ALTO | CI browser skipped e visual com lacunas | P06 suites executadas |
| AUD57-07 | ALTO | Core Web Vitals não medidos | P07 baseline |
| AUD57-08 | MÉDIO | Documentação RN antiga contradiz API nova | P00 reconciliação |
| AUD57-09 | MÉDIO | Reports 2026-11-14 não são estado Runtime de 2026-11-17 | P00 doctor read-only |
| AUD57-10 | MÉDIO | UI Styles/Page/Icons ainda sem prova completa de consumidores | P04 inventário e perfis |
| AUD57-11 | MÉDIO | 17 Twig com consumidores específicos | P02 revisão por hook |
| AUD57-12 | MÉDIO | Fontes remotas e CSS sem payload por rota | P07 benchmark |

## 8. Plano de verificação do agente local

**Não executar nesta fase documental.** Quando o responsável iniciar a implementação, respeitar AGENTS.md, docs/operations/AGENT-REVIEW-POLICY.md e docs/operations/RUNTIME-SYNC.md.

1. Inventário: git status --short; git rev-parse HEAD; php scripts/inventory-ui-suite-consumers.php; php scripts/validate-aculta420-sdc-library-contract.php; php scripts/validate-aculta420-sdc-schemas.php.
2. Contratos: php scripts/validate-site-key-route-options.php; php scripts/validate-aculta420-ui-skins.php; php scripts/validate-aculta420-ui-styles.php; php scripts/validate-ui-patterns-views-cutover.php.
3. Render e Sync: somente em ambiente autorizado e com preflight read-only, Drush php:script scripts/validate-aculta420-v2-sdc.php e scripts/validate-aculta420-layout-builder-batch.php; comparar Config Sync/Runtime. Não importar automaticamente.
4. CI: analisar phpunit-kernel e phpstan-gate por check executado. Classificar problemas preexistentes e regressões de cada pacote.
5. Browser: tests/browser/package.json, playwright.visual.config.mjs, playwright.visual.spec.mjs, playwright.a11y.spec.mjs e auth-login.mjs. Provar variáveis de host e identidade da conta antes de declarar PASS.
6. Segurança: versões em composer.lock, composer audit --locked, upstream Field Group e patch; examinar riscos próprios não cobertos por advisory.
7. Performance: estabelecer medição reproduzível por host/rota/modo em ambiente equivalente. Registrar rede e comportamento, não apenas números de lighthouse.
8. Evidências: por pacote registrar comandos, saídas, HEAD, ambiente, capturas, diff, rollback e gates PASS/FAIL/DEFERRED/N/A.

**Operações sensíveis fora deste escopo:** composer install/update no Runtime; config import; update.php/updatedb; uninstall contrib; limpeza da chave órfã system.schema; exclusão de conteúdo; alteração estrutural de banco. Cada uma exige R3 e autorização específica.

## 9. Referências verificáveis

**Git e documentação interna:**
- [ADR-054](../decisions/ADR-054-refatoracao-aculta420-v2-sdc-layout-builder.md)
- [ADR-055](../decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md)
- [ADR-056](../decisions/ADR-056-ui-patterns-2-recomendacoes-e-corte-documentacao.md)
- [ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md](ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md)
- [ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md](ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md)
- [Inventário de consumidores](inventarios/ui-suite-consumidores-iniciais.csv)
- [Tema TODO](../../web/themes/custom/aculta420_v2/TODO.md)
- [Tema gates](../../web/themes/custom/aculta420_v2/docs/gates.md)
- [CI php-quality](../../.github/workflows/php-quality.yml)
- [CI quality-static](../../.github/workflows/quality-static.yml)

**Upstream:**
- [UI Patterns Field Group - Drupal.org](https://www.drupal.org/project/ui_patterns_field_group)
- [UI Patterns releases - Drupal.org](https://www.drupal.org/project/ui_patterns/releases)
- [UI Patterns SA-CONTRIB-2026-075](https://www.drupal.org/sa-contrib-2026-075)
- [Core Web Vitals thresholds - web.dev](https://web.dev/articles/defining-core-web-vitals-thresholds)

**Declaração de evidências:** inspeção estática GitHub e checks remotos, sem execução local PHP/Node/Drush/Playwright, sem acesso ao Runtime e sem alteração de código/config/banco. Status atuais do Runtime permanecem DEFERRED até diagnóstico executado no ambiente correspondente.
