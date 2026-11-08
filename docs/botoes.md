# Botões do ACULTA420 V2

Status: **em andamento**. A migração não está concluída, e nenhuma etapa aqui está marcada como finalizada sem captura e gate.

## Componente

`web/themes/custom/aculta420_v2/components/button/` (SDC `aculta420_v2:button`).

- Variantes: `primary`, `secondary`, `outline`, `ghost`, `danger`.
- `size`: `default` ou `small`. `small` altera só espaçamento e fonte; o alvo de toque continua em 44 px, salvo exceção documentada.
- `text` é opcional quando `aria_label` existir (botão só com ícone). `aria_label` vira `aria-label`.
- `url` gera `<a>`; sem `url`, gera `<button>`. `disabled` com `url` gera `<span aria-disabled="true">`.
- Classes extras e atributos passam por `attributes` (`create_attribute` no Twig; `#attributes` no PHP).
- Ícones: `icon_before` e `icon_after`.

Uso em PHP (render array):

```php
[
  '#type' => 'component',
  '#component' => 'aculta420_v2:button',
  '#props' => ['text' => (string) $this->t('Cancelar'), 'url' => $url->toString(), 'variant' => 'secondary'],
]
```

Uso em Twig:

```twig
{{ include('aculta420_v2:button', { text: 'Entrar', url: '/login', variant: 'primary' }, with_context = false) }}
```

`action` vira `primary`. `small` não é variante.

## Contraste (medido)

| Par | Modo claro | Modo escuro |
| --- | --- | --- |
| `danger` texto sobre fundo | 6,19:1 | 7,46:1 |
| `danger` hover e active | 7,88:1 e 9,96:1 | 8,54:1 e 6,30:1 |
| `danger` borda/anel sobre canvas | 5,70:1 | 7,46:1 |

Os tokens estão em `src/styles/tokens.css` (`--button-danger-*`). Mínimos: texto 4,5:1; borda e anel 3:1.

## Seletor de modo de cor da UI Library

- Seletor `role="radiogroup"` no topo da UI Library, rótulos Claro e Escuro, alvo de 44 px, padrão Claro.
- Define `data-theme` nos contêineres de pré-visualização (`.ui_patterns_story__preview`), não no `<html>`.
- `:root, [data-theme="light"]` e `:root[data-theme="dark"], [data-theme="dark"]` compartilham os tokens (sem duplicação).
- Escolha lembrada em `localStorage` com try/catch.
- A barra institucional não aparece na UI Library (`templates/layout/page.html.twig`).

## Migrado

| Área | Pontos | Commit |
| --- | --- | --- |
| Portal (formulários de decisão UGC, ownership e data de evento) | Cancelar e Voltar, 5 links | `d2f4d85b7` |
| Portal (cadastro, suporte, conexão Google) | 4 links | `04b76f6ea` |
| Fórum (criar tópico) | 1 link | `c0c76ead1` |
| Studio (calendário) | adicionar evento e setas de mês, 4 botões | `1f9de9b2a` |
| Studio (bloco de conexões) | 2 links | `1b6fd3a52` |
| Eventos (editar evento e datas) | 1 link (prefixo renderizado pelo SDC) | `1b6fd3a52` |

## Exceções (não vão ao SDC sem mudar comportamento)

- **Botões de envio do Drupal** (`#type => submit`, `input.button--primary`, `input--submit`): precisam continuar como `<input>` ou `<button name=…>` para preservar `name`, `value`, `id`, `formnovalidate` e o envio HTMX/AJAX do formulário. Um `<a>` do SDC mudaria o envio. Registrado como exceção; o tratamento aqui seria por `preprocess_input` e não pelo SDC.
- **Link "Citar" do fórum** (`ForumQuoteHooks.php`): usa o tema `links__comment__comment` com `#links`, que não aceita componente. Exige ajuste do tema de links.
- **Mapa `PortalUiHooks.php`**: mantém os tokens ainda não migrados. Remover uma entrada só depois que a marcação dela sair.

## Pendente

- Formulários e botões de envio do Drupal (`#type => submit`/`button`, cerca de 140 ocorrências em `aculta_portal`). Ver exceção acima.
- Tokens `data-portal-ui` de botão restantes em `aculta_events` (organizador, recuperação, membros), `aculta_user`, `aculta_studio` (`CourseAuthoringController`), `aculta_commerce` (`MelhorEnvioStatusController`) e no `PortalController`.
- Limpeza de CSS de área (`forum.css`, `studio.css`, `portal-ui-map.css`, `event-organization.css`, `buttons.css`). Foi tentada e **revertida**: as regras ainda estilizam formulários não migrados. Só remover depois da migração acima e de verificação visual.
- Purposes restantes (`shop`, `courses`, `magazine`, `wiki`, `links`, `account`, `support`, `main`), por área de marcação.
- Parte C: stories por variante, `disabled`, ícones e "Esquema de botões" (claro e escuro lado a lado).
- Capturas e validação no Firefox ESR (320 e 1440 px, claro e escuro), foco visível, sem rolagem horizontal.
- Suítes Kernel: `UgcModerationKernelTest` rodou OK (5 testes) a partir da cópia principal. Precisa rodar de novo na worktree, e as suítes de Eventos (`EventDateManagementKernelTest`) e User (`OrganizationClaimKernelTest`).

## Validação feita

- Gates: `validate-aculta420-v2-foundations.php`, `validate-aculta420-ui-styles.php` e `validate-aculta420-palette-schemes.php` passam nos commits de `origin/dev` a partir de `1ea253aba`.
- Renders via Drush na worktree: link, botão desativado, botão só com ícone e calendário do Studio.
- Teste unitário `PublicForumTopicCreateActionHooksTest`: OK.
