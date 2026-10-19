# Verificação de boas práticas — Drupal 11+, SDC e VVJ

Data: 2026-10-19. Versão verificada do tema: `0.6.0-dev.94`.
Escopo: código do tema (`src/Hook`, `templates`, `js`, `components`, `css`) nos pontos de Drupal 11+, SDC e VVJ (VVJB e VVJS).

## Versões de referência

| Componente | Versão instalada | Requisito verificado |
|---|---|---|
| Drupal Core | 11.4.10 | `^11` (tema) |
| Bootstrap (base do tema) | 5.0.2 | pinado pelo gate de Foundation |
| VVJB (`vvjb`) | 2.0.0 | `core_version_requirement: ^11.3 \|\| ^12` |
| VVJS (`vvjs`) | 2.0.2 | `core_version_requirement: ^11.3 \|\| ^12` |
| Bootstrap Icons | 1.11.0 | pacote npm oficial, integridade conferida |

## 1. Drupal 11+

| Prática | Evidência | Resultado |
|---|---|---|
| Hooks como classes com `#[Hook]` e sem `.theme` | `src/Hook/*.php`; gate de Foundation confirma que não há `aculta420.theme` | OK |
| Uma implementação de cada hook por extensão | `ModuleHandler::invoke()` lança `LogicException` com duas. `preprocess_input` tem uma única implementação (`InputHooks`) | OK (corrigido nesta versão) |
| Serviços por injeção, sem service locator | `\Drupal::` ausente em `src/` do tema; hooks recebem `#[Autowire(service: 'extension.list.theme')]` | OK |
| Serviços declarados em `services.yml` | Temas não têm `services.yml`: `DrupalKernel` carrega serviços só de módulos (`moduleList`). As classes `#[Hook]` do tema são autowired | OK, com limite: o tema não declara serviços próprios |
| Renderização segura e tradução de tokens preservada | Teste no Runtime: `image_button` e `textfield` com `data-portal-ui` | OK |
| Testes automatizados dos hooks do tema | Não há PHPUnit para `InputHooks` nem `PortalUiHooks` | **Lacuna** (ver seção 4) |

## 2. SDC (Single Directory Components)

| Prática | Evidência | Resultado |
|---|---|---|
| Schema obrigatório para componentes do tema | `enforce_prop_schemas: true` em `aculta420.info.yml`; chave lida por `ComponentPluginManager` (Core) | OK |
| Props e slots validados | `validate-aculta420-sdc-schemas.php`: 35 componentes, 514 checks | OK |
| Tokens semânticos e modos de cor | `validate-aculta420-design-foundations.php`: 2068 checks | OK |

## 3. VVJ (VVJB e VVJS)

| Prática | Evidência | Resultado |
|---|---|---|
| Não fazer fork do template do módulo | `views-view-vvjb.html.twig` inclui `@vvjb/views-view-vvjb.html.twig` e acrescenta só o rótulo de acessibilidade | OK |
| Não duplicar `id` do módulo | O módulo referencia `aria-labelledby="vvjb-index-…"`; o tema fornece esse `id` e o módulo não o emite | OK |
| API pública do VVJB | `editorial-carousel.js` usa `pause()` (existe em `vvjb-carousel-element.js`), `vvj:ready` (emitido após a hidratação em `vvj-element-base.js`) e `data-vvjb-initialized` (definido em `.vvjb-inner`) | OK |
| Sem timer próprio no carrossel | O comentário do JS registra a decisão; não há `setInterval` no arquivo | OK |
| API pública do VVJS | `album-slideshow.js` usa `goToSlide` (1-based), `getCurrentSlide` e `vvj:slideChanged` com `detail.slide` (confirmados em `vvjs-slideshow-element.js`) | OK |

Fontes da verificação:
- Projeto VVJS no Drupal.org: <https://drupal.org/project/vvjs> (requisitos de Core e PHP da 2.x).
- Issue de override de template do VVJS: <https://www.drupal.org/node/3539388>. O template do módulo não deve ser sobrescrito sem cuidado; o tema usa `include` e não fork.
- Código instalado (fonte de verdade para a versão em uso): `web/core/lib/Drupal/Core/Extension/ModuleHandler.php`, `web/core/lib/Drupal/Core/DrupalKernel.php`, `web/core/lib/Drupal/Core/Theme/ComponentPluginManager.php`, `web/modules/contrib/vvjb`, `web/modules/contrib/vvjs`, `web/modules/contrib/vvj_core`.

Pesquisa web sem documento oficial conclusivo: a busca sobre hooks OOP em temas (Drupal 11.1+) e sobre `enforce_prop_schemas` não retornou documentação de drupal.org. Os pontos acima foram confirmados pelo código instalado e pelo Runtime, não por documentação externa.

## 4. Pendências

1. **Testes dos hooks do tema.** `InputHooks` e `PortalUiHooks` não têm teste automatizado. Recomendado: teste Kernel que renderiza `image_button` e `input` com token (em `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`).
2. **Validação visual.** VVJB, VVJS e `webform_multiple` não foram validados no navegador nesta etapa (estado NÃO VALIDADO).
3. **Lista manual de templates.** O gate de Foundation mantém uma allowlist de overrides Twig escrita à mão. Ela já reprovou uma vez (0.6.0-dev.91). Cada novo template precisa entrar nela no mesmo commit.
