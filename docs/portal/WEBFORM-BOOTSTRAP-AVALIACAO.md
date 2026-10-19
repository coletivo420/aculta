# Webform Bootstrap (3.x) — avaliação de portabilidade

Status: avaliação documental (sem alteração de código, configuração ou Runtime).
Escopo: verificar o que do submódulo `webform_bootstrap` (3.x, marcado como deprecado) pode ser portado para o tema ACULTA420 e para o `aculta_portal`.
Vinculado à PR #297 (linha do ACULTA Forum), por commit no branch da própria PR.

## Conclusão

- **Não há portagem obrigatória.** O submódulo resolve problemas do Drupal Bootstrap 3. O ACULTA420 usa o Bootstrap 5 (`bootstrap` contrib 5.0.2), e a maior parte do que o submódulo faz já é coberta pelo tema base e pelo Core.
- **Não habilitar o submódulo.** Ele exige o tema `bootstrap` 8.x-3 e, se ativado, chama `\Drupal\bootstrap\Bootstrap::getTheme()`, classe inexistente no Bootstrap 5. Como a checagem de tema ativo considera também os temas base, o ACULTA420 o acionaria. Risco de erro fatal em todo formulário Webform.
- **Não portar nada para o `aculta_portal`.** O que o submódulo faz é apresentação (classes, tooltips, CSS, ícones). Pela fronteira "Código no Aculta Portal, Design no ACULTA420", isso pertence ao tema. O Portal só forneceria dados neutros, e nenhum desses dados é necessário hoje.
- **Itens candidatos ao tema, de baixa prioridade**, dependem de evidência visual no teste e de autorização explícita para alterar `web/themes/custom/aculta420/**` (ver seção 4).

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

## 4. Itens candidatos ao tema (somente se o teste visual mostrar necessidade)

Não estão autorizados nesta avaliação. Alterar `web/themes/custom/aculta420/**` exige autorização explícita na solicitação.

1. **Classes de botão Drupal → Bootstrap** (`.button` → `.btn`) em `link_alter` ou `file_managed_file`, apenas se houver link ou upload de webform sem estilo.
2. **Ajuste de `.form-inline`/`.form--inline`**, apenas se uma configuração futura do Webform usar essas classes.
3. **Ícones de `image_button`** com `bi`, apenas se um formulário passar a usar `image_button`.

Os três itens são de baixa prioridade. Nenhum é necessário para os forms atuais.

## 5. Alternativas e riscos

- **`webform_bootstrap5`** (projeto contrib sucessor, no Drupal.org): a busca retornou "no supported stable releases" na página do projeto. Não há versão estável confirmada. Adotar exige decisão do responsável e justificativa de dependência (regra 14 do `AGENTS.md`). **Recomendação: não adotar agora.**
- **Habilitar `webform_bootstrap` 3.x** no Runtime: falha de requisito no update e risco de erro fatal em formulários. **Não fazer.**
- **Escrever um submódulo próprio no tema**: possível, mas só se o teste visual mostrar necessidade. Pelo que foi verificado, não há.

## 6. Próximos passos sugeridos

1. Validar visualmente `/contato` e a página de participação no teste (Firefox, conforme `docs/portal/TESTES-NAVEGADOR.md`), sem alterar configuração, e registrar se há problema de botão, descrição ou erro de campo.
2. Só se houver problema: abrir decisão sobre o item correspondente da seção 4 e pedir autorização para o tema.
3. Acompanhar a migração do Webform 6.x: o submódulo está marcado como deprecado com o link do FAQ do Webform 6.x, e o Core/Webform 7 pode removê-lo.

## Fontes

- Código local: `web/modules/contrib/webform/modules/webform_bootstrap/` (módulo, hooks, `install`, `info.yml`, `README.md`, CSS e JS); `web/modules/contrib/webform/src/WebformThemeManager.php` (`getActiveThemeNames`, `isActiveTheme`); `web/themes/contrib/bootstrap/templates/form/form-element.html.twig` e `fieldset.html.twig`.
- Runtime de teste (leitura): `drush status` e `php:eval` com `class_exists`, `moduleExists`, tema padrão e base do `aculta420`.
- Web: FAQ do Webform 6.x sobre bibliotecas externas deprecadas, [drupal.org/docs/8/modules/webform/webform-frequently-asked-questions/what-to-do-about-deprecated-external-libraries-in-webform-6x](https://www.drupal.org/docs/8/modules/webform/webform-frequently-asked-questions/what-to-do-about-deprecated-external-libraries-in-webform-6x) (citado pelo `info.yml`; o conteúdo não foi lido diretamente); issue [drupal.org/project/webform/issues/3609423](https://www.drupal.org/project/webform/issues/3609423) (reclamação de requisito Bootstrap 3.x após atualizar para 6.3.0); projeto [drupal.org/project/webform_bootstrap5](https://drupal.org/project/webform_bootstrap5) (sucessor citado; sem release estável no momento da busca).

## 7. Atualização: portagem autorizada e referência `webform_bootstrap5`

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

### 7.4 Desinstalação do `webform_bootstrap` 3.x

Verificado no Runtime de teste (2026-10-19): o módulo existe no código do pacote Webform 6.3.1 (`web/modules/contrib/webform/modules/webform_bootstrap/`), mas não está instalado: `moduleExists = false`, sem entrada em `core.extension`, sem schema em `system.schema`. Não há o que desinstalar no banco. O código continua no pacote contrib do Webform, que não pode ser alterado pelo projeto (regra de contrib). A remoção do código acontece com a atualização do Webform que retirar o submódulo.
