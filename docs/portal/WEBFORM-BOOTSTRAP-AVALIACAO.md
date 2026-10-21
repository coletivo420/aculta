# Webform Bootstrap (3.x) — avaliação de portabilidade

Status: auditoria concluída em 2026-10-21. O submódulo legado está desativado no Runtime; as regras úteis identificadas foram portadas ao ACULTA420. Não há pacote Composer independente a remover.
Escopo: inventariar o submódulo `webform_bootstrap` (3.x, marcado como deprecado), conferir sua cobertura no tema ACULTA420 e determinar se há instalação, configuração ou dados para remover.
Vinculado à PR #297 (linha do ACULTA Forum), por commit no branch da própria PR.

## Conclusão

- **As regras úteis já foram portadas ao ACULTA420**, em `0.6.0-dev.92` e `.93`: estilos de campos e múltiplos, estados de foco e ícones Bootstrap Icons para botões de imagem. O tema e o Bootstrap 5/Core cobrem os demais usos aplicáveis. Nenhuma lógica foi adicionada ao `aculta_portal`.
- **O módulo legado não está instalado nem habilitado.** Drush reporta `Disabled`; `moduleExists('webform_bootstrap')` é falso; `core.extension` e `system.schema` não têm entrada correspondente. Não há configuração nem schema para desinstalar do Runtime.
- **O código disponível não é uma dependência separada.** `webform_bootstrap` é um submódulo que vem dentro do pacote `drupal/webform` 6.3.1. Remover somente seus arquivos exigiria editar/patchear código contrib ou retirar o Webform inteiro. Não fazer isso: o projeto proíbe alterações diretas em contrib e o módulo já não executa.
- **Não instalar outro adaptador.** O projeto separado `drupal/webform_bootstrap` mantém integração Bootstrap 3; o `webform_bootstrap5` não tem release estável no Drupal.org. Nenhum deles é necessário para a apresentação atual do ACULTA420.

## 1. Estado verificado

| Item | Evidência |
|---|---|
| Versão do Webform | `drupal/webform` 6.3.1 (`composer.lock`) |
| Submódulo | `web/modules/contrib/webform/modules/webform_bootstrap/`, `info.yml` com `name: 'Webform Bootstrap (3.x)'`, `package: 'Webform [DEPRECATED]'`, `lifecycle: deprecated` |
| Requisito de instalação | `webform_bootstrap_requirements()` reprova se o tema `bootstrap` não for `8.x-3` |
| Tema padrão do Runtime | `aculta420`, com `base theme: bootstrap` (`aculta420.info.yml`) |
| Bootstrap contrib instalado | `web/themes/contrib/bootstrap`, versão `5.0.2` |
| Submódulo no Runtime | Desabilitado (`moduleExists('webform_bootstrap') = false`; `core.extension.yml` não lista o módulo) |
| Classe `Drupal\bootstrap\Bootstrap` | Inexistente no Bootstrap 5 (`class_exists = false`, leitura no Runtime de teste) |
| Forms do Portal | `aculta_contact` e `aculta_participation` (`config/sync/webform.webform.*.yml`), sem `image_button`, sem `likert` como elemento e sem `title_display: inline` |

Ambiente: `hostname=toca`; Drush alcança o Runtime `aculta_runtime` (`Site URI: http://default`, esperado neste ambiente). O Drupal root é `web/` do checkout `/home/piradopirata/projetos/aculta-site`; não há `public_html` nesse caminho.

## 2. Inventário e decisão por funcionalidade

| Recurso do submódulo | O que faz no BS3 | Situação no Bootstrap 5 / ACULTA420 | Decisão |
|---|---|---|---|
| `webform_bootstrap.css` (via `page_attachments`) | Seletores BS3 (`.form-inline`, `.well`, `.panel`, `.ui-widget-content`) e regras para `.webform-element--title-inline` e `.webform-flex--container` | Seletores BS3 não existem no BS5. As regras de título inline só valem se a configuração usar `title_display` inline, o que os forms atuais não usam. | Não portar. |
| `webform_bootstrap.states.js` | Marca `.panel` como obrigatório no `state:required` | Depende do markup BS3 (`.panel-heading`). | Não portar. |
| Smart descriptions (`webform_element_alter`) | Converte `#description` em markup e transforma em tooltip Bootstrap | Depende de `Drupal\bootstrap\Bootstrap::getTheme()` (inexistente no BS5). | Não portar. `#description_display` `before`/`after` do Core já cobre a necessidade. |
| Terms of service e Likert (`hook_webform_element_*_alter`) | Desativam smart description nesses elementos | Sem smart description, não há efeito. Os forms atuais não usam Likert como elemento. | Não portar. |
| `#field_prefix` / `#field_suffix` | Liga `#input_group` | O `form-element.html.twig` do BS5 já gera `.input-group` com `.input-group-text` (linhas 140–152). | Coberto pelo tema base. |
| `image_button` → glyphicon | Troca ícones de adicionar/remover por glyphicons | Glyphicons não existem no BS5. Os forms atuais não usam `image_button`. Pela regra do projeto, ícones de interface são Bootstrap Icons (`bi bi-…`). | Não portar. Se surgir `image_button`, usar `bi`. |
| `form-inline` removido de `tel` | Evita campo de telefone inline no BS3 | `form_classes` padrão do Webform não inclui `form--inline`; o problema não ocorre. | Não portar. |
| Erro de fieldset em `alert alert-danger` | Estiliza erro de fieldset | O `fieldset.html.twig` do BS5 já renderiza `alert alert-danger` (linha 47). | Coberto pelo tema base. |
| `.button` → `.btn` (`link_alter`, `file_managed_file`) | Converte classes Drupal de botão em classes BS | Botões de webform já são estilizados em `forms.css` (`.webform-submission-form .form-submit`). Ainda não há evidência de link ou upload com classe `button` sem estilo. | Avaliar no teste visual. Só portar se houver problema real. |
| Submódulo inteiro | Integração com Bootstrap 3 | Incompatível com o Bootstrap 5 instalado. Não é possível ativá-lo sem quebrar a instalação ou os formulários. | Não habilitar. |

## 3. Por que nada vai para o `aculta_portal`

- O Portal define os formulários por configuração (`webform.webform.aculta_contact`, `aculta_participation`), a rota pública (`/contato`, `entity.webform.canonical` em `aculta_portal.links.menu.yml`) e a política de acesso. Nada disso depende do submódulo.
- Classes, tooltips, CSS e ícones são apresentação. Pela regra "Código no Aculta Portal, Design no ACULTA420", pertencem ao tema e não podem ser emitidos pelo Portal.
- Se um dia a apresentação de um formulário precisar de dado neutro (por exemplo, um rótulo de botão ou um estado), o contrato é o `aculta_portal` preparar o dado e o tema renderizar, conforme [`FRONTEIRA-MODULO-TEMA.md`](FRONTEIRA-MODULO-TEMA.md).

## 4. Cobertura portada ao tema

A implementação atual está em `web/themes/custom/aculta420/css/components/webform.css` e `src/Hook/InputHooks.php`, com registro no changelog e roadmap do tema. A matriz atualizada está na seção 7.2. Os estilos e ícones foram implementados sem alterar o módulo contrib. A validação visual específica dos formulários `/contato` e participação permanece identificada no roadmap do tema.

## 5. Alternativas e riscos

- **`drupal/webform_bootstrap`** é um projeto separado que dá continuidade ao adaptador Bootstrap 3; não confundir com o submódulo homônimo empacotado no Webform. Não é compatível com a arquitetura Bootstrap 5 pretendida sem adaptação e não é necessário aqui.
- **`webform_bootstrap5`** declara integração com Bootstrap 5, mas não tem release estável suportada no Drupal.org. O tema já fornece a apresentação necessária, portanto não adicionar essa dependência.
- **Habilitar o submódulo legado 3.x** não é apropriado: exige Bootstrap 3 e usa APIs ausentes no Bootstrap 5. Mantê-lo desativado evita o risco em formulários.

## 6. Acompanhamento

1. Validar visualmente `/contato` e a página de participação conforme `docs/portal/TESTES-NAVEGADOR.md`; registrar eventuais problemas concretos no roadmap do tema.
2. Acompanhar a release do Webform que remova o diretório legado; não criar patch para apagar arquivos de `web/modules/contrib`.

## Fontes

- Código local: `web/modules/contrib/webform/modules/webform_bootstrap/` (módulo, hooks, `install`, `info.yml`, `README.md`, CSS e JS); `web/modules/contrib/webform/src/WebformThemeManager.php` (`getActiveThemeNames`, `isActiveTheme`); `web/themes/contrib/bootstrap/templates/form/form-element.html.twig` e `fieldset.html.twig`.
- Runtime de teste (leitura): `drush status` e `php:eval` com `class_exists`, `moduleExists`, tema padrão e base do `aculta420`.
- Web: FAQ do Webform 6.x sobre módulos depreciados ([documentação oficial](https://www.drupal.org/docs/8/modules/webform/webform-frequently-asked-questions/what-to-do-about-deprecated-external-libraries-in-webform-6x)); projeto separado [Webform Bootstrap](https://www.drupal.org/project/webform_bootstrap) (continuidade para Bootstrap 3); [Webform Bootstrap5](https://www.drupal.org/project/webform_bootstrap5) (sem release estável suportada na consulta de 2026-10-21).

## 7. Implementação portada e comparação com `webform_bootstrap5`

### 7.1 Análise do `webform_bootstrap5` (referência, não instalado)

Baixado de `git.drupalcode.org/project/webform_bootstrap5.git` (commit `995434c`, 2024-08-27) para um diretório temporário de análise, sem instalação.

- É um fork quase literal do 3.x: o CSS mantém seletores BS3 (`.panel`, `.form-inline`, `.well`); o JS usa `.panel`.
- Verifica o tema `bootstrap5` (`isActiveTheme('bootstrap5')`). O tema base do ACULTA420 é `bootstrap` (Bootstrap 5.0.2), então o módulo seria inativo.
- Anexa a library `webform_bootstrap/webform_bootstrap`, que pertence ao 3.x e não ao próprio módulo.
- Continua chamando `\Drupal\bootstrap\Bootstrap::getTheme()` e `\Drupal\bootstrap\Bootstrap::glyphicon()`, classes ausentes no Bootstrap 5 contrib instalado.
- Sem release estável no Drupal.org (busca de 2026-10-19).

Conclusão: o projeto serve como lista de funcionalidades, não como dependência. Adotá-lo exigiria reescrita.

### 7.2 Matriz de cobertura (Bootstrap 5 + tema ACULTA420)

| Item do 3.x | Cobertura atual | Ação |
|---|---|---|
| Título de composto sem moldura (`.form-composite legend`) | Não coberto | Portado (`webform.css`) |
| Título inline de elemento e de composto | Não coberto | Portado (seletores `.form-select`, não `.select-wrapper`) |
| Likert: ajuda/descrição em telas estreitas | Não coberto | Portado (sem uso nos forms atuais) |
| Modal de confirmação responsivo | Não coberto | Portado |
| Flexbox: `.form-inline .form-control` a 100% | Classe BS3 inexistente no BS5 | Não portado |
| `.alert a.btn` em branco | Coberto: `.btn` usa tokens (`--aculta-button-color`) | Não portado |
| `.ui-widget-content a.btn` | Coberto pelo tema de diálogos (`dialog.css`) | Não portado |
| Seta de `image_select` (`.select-wrapper:after`) | Sem wrapper no BS5 | Não portado |
| `.button` → `.btn` (`link_alter`, `file_managed_file`) | Coberto: `buttons.css` estiliza `.button` e `.btn` | Não portado |
| Smart description / tooltip do BS3 | Sem suporte no Bootstrap 5 contrib | Não aplicável; `#description_display` do Core |
| `#field_prefix`/`#field_suffix` → input-group | Coberto pelo `form-element.html.twig` do BS5 | Não portado |
| Erro de fieldset em `alert-danger` | Coberto pelo `fieldset.html.twig` do BS5 | Não portado |
| `form-inline` no `tel` | O BS5 contrib não gera a classe | Não aplicável |
| `image_button` → ícone | Sem uso nos forms atuais; o BS5 não tem glifo para `input[type=image]` | Portado (0.6.0-dev.93): `src` troca para o SVG oficial `plus-circle` / `dash-circle` do Bootstrap Icons 1.11.0 (`assets/icons/bootstrap-icons/icons/`) e recebe classe de botão; continua imagem para preservar o clique do Form API (`InputHooks::preprocess`) |
| `input[type=image]` | Sem tratamento no tema | Portado (0.6.0-dev.93): cursor e foco visível; largura de campo do `form-control` anulada para ícones |
| `webform_multiple` (tabela, operações, alça, lista vazia, adição) | Não coberto pelo tema; sem uso nos forms atuais | Portado (0.6.0-dev.93) em `webform.css`, com as classes `webform-multiple-table--*` |
| Terms of service / likert: desativar smart description | Sem smart description | Não aplicável |

Os ajustes de `image_button`, `input[type=image]` e `webform_multiple` foram portados a pedido, mesmo sem uso no front-end atual.

Os formulários atuais (`aculta_contact`: textfield, email, textarea; `aculta_participation`: webform_markup, textfield, email, tel, checkboxes, checkbox, textarea) usam poucos elementos cobertos pelos itens portados. Os ajustes são preventivos, para formulários futuros.

### 7.3 Entrega (0.6.0-dev.92 e 0.6.0-dev.93)

- Tema `0.6.0-dev.92`: `css/components/webform.css` (library `global`), CHANGELOG, roadmap do tema e versão no `info.yml`.
- Gate de Foundation: versão atualizada e o template `aculta-user-author-card.html.twig` incluído na allowlist de overrides Twig. A reprovação existia desde a 0.6.0-dev.91 e bloqueava a entrega. Gate com 368 checks PASS. Os demais gates (design foundations, SDC schemas, shell contract, fronteira módulo/tema, mapa módulo/tema) passam.
- Runtime de teste: cache rebuild; `webform.css` confirmado na library `aculta420/global`. Validação visual nos formulários não executada (estado NÃO VALIDADO).

### 7.4 Remoção do `webform_bootstrap` 3.x

Verificado no Runtime de teste em 2026-10-21: o submódulo está `Disabled`; `moduleExists = false`; não há entrada em `core.extension` nem schema em `system.schema`. Portanto, não há instalação no banco para desinstalar. O diretório continua dentro do pacote contrib `drupal/webform` 6.3.1 e não pode ser apagado isoladamente sem alterar o pacote contrib, proibido pelos guardrails. A remoção física virá quando uma versão do Webform retirar o submódulo; até lá, ele permanece inerte e não é incluído como dependência Composer própria.
