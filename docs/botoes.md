# Botões do ACULTA420 V2

Status: **links e ações migrados para o SDC; envios e casos com comportamento próprio ficam como exceção documentada.** A limpeza de CSS de área e a verificação visual dos purposes seguem pendentes.

## Componente

`web/themes/custom/aculta420_v2/components/button/` (SDC `aculta420_v2:button`).

- Variantes: `primary`, `secondary`, `outline`, `ghost`, `danger`.
- `size`: `default` ou `small`. `small` altera só espaçamento e fonte; o alvo de toque continua em 44 px.
- `text` é opcional quando `aria_label` existir (botão só com ícone). `aria_label` vira `aria-label`.
- `url` gera `<a>`; sem `url`, gera `<button>`. `disabled` com `url` gera `<span aria-disabled="true">`.
- Classes e atributos extras passam por `attributes` (`create_attribute` no Twig; `#attributes` no PHP).
- Ícones: `icon_before` e `icon_after`.

Uso em PHP (render array):

```php
[
  '#type' => 'component',
  '#component' => 'aculta420_v2:button',
  '#props' => ['text' => (string) $this->t('Cancelar'), 'url' => $url->toString(), 'variant' => 'secondary'],
  '#attributes' => ['data-x' => 'y'], // opcional
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

Tokens em `src/styles/tokens.css` (`--button-danger-*`). Mínimos: texto 4,5:1; borda e anel 3:1.

## Seletor de modo de cor da UI Library

- Seletor `role="radiogroup"` no topo da UI Library, rótulos Claro e Escuro, alvo de 44 px, padrão Claro.
- Troca `data-theme` em **todas** as pré-visualizações de componente (`.ui_patterns_story__preview` e `.ui_patterns_story__story`), não só nos botões.
- `:root, [data-theme="light"]` e `:root[data-theme="dark"], [data-theme="dark"]` compartilham os tokens (sem duplicação).
- Escolha lembrada em `localStorage` com try/catch.
- A barra institucional não aparece na UI Library (`templates/layout/page.html.twig`).
- A grade "Esquema de botões" não tem modo próprio: herda o modo da página.
- Captura e checagem: `tests/browser/transversal/aculta420-v2-component-library/library-mode.py` (conta de teste por `drush uli` no host de conta; rota real `/painel-administrativo/aparencia/ui/components`). Resultado: seletor com 2 opções, sem barra institucional, 89 pré-visualizações com `data-theme` nos dois modos, sem rolagem horizontal em 320 e 1440 px.

## Migrado para o SDC

| Área | Pontos | Commit |
| --- | --- | --- |
| Portal (decisão UGC, ownership, data de evento) | Cancelar e Voltar | `d2f4d85b7` |
| Portal (cadastro, suporte, conexão Google) | 4 links | `04b76f6ea` |
| Fórum (criar tópico) | 1 link | `c0c76ead1` |
| Studio (calendário) | adicionar evento e setas de mês | `1f9de9b2a` |
| Studio (conexões) | 2 links | `1b6fd3a52` |
| Eventos (editar evento e datas) | 1 link | `1b6fd3a52` |
| Eventos (membros, recuperação, localização, admin, painel do organizador, workspace) | links de voltar, cancelar, limpar e ações | `068359018`, `6526fb5b2`, `6fe053338` |
| Studio (novo curso) | 1 link com `data-aculta-studio` em `#attributes` | `6fe053338` |
| Cursos da conta | 2 links | `17e0f3b49` |
| Portal (ver informações sobre o cadastro) | 1 link | `6fe053338` |

## Exceções (não vão ao SDC sem mudar comportamento)

- **Envios de formulário** (`#type => submit`, `button type=submit`): precisam continuar como `<input>` ou `<button name=…>` para preservar `name`, `value`, `id`, `formnovalidate` e o envio/HTMX. Pontos: `EventOrganizationMembersForm` (adicionar), `EventOrganizationAdminController` (filtrar), `EventOrganizerDashboardController` (filtrar, GET).
- **`#type => button` com JS próprio**: `SocialLinkForm` e `AccountBioForm` (botão de limpar, com `data-aculta-clear` e `data-aculta-bio-clear`, usados por script). Migrar exige revisar o JS.
- **Link "Citar" do fórum** (`ForumQuoteHooks.php`): usa o tema `links__comment__comment` com `#links`, que não aceita componente.
- **Link do Melhor Envio** (`MelhorEnvioStatusController.php`): o teste Kernel `MelhorEnvioWiringKernelTest` renderiza sem o tema V2, e o componente não existe nesse ambiente. Migrar pede ajuste do teste (habilitar o tema) antes.

## Pendente

- Limpeza de CSS de área (`forum.css`, `studio.css`, `portal-ui-map.css`, `event-organization.css`, `buttons.css`). Foi tentada e **revertida**: as regras ainda estilizam os envios e os tokens de exceção acima. Só remover depois dessas exceções e de verificação visual.
- Purposes: a marcação de botões dos purposes `shop`, `courses`, `magazine`, `wiki`, `links`, `account`, `support` e `main` está migrada na parte de links; falta a verificação visual por purpose.
- Captura no Firefox ESR: feita para a UI Library (claro e escuro, 320 e 1440 px). Falta a verificação visual de Fórum, Studio e Eventos.
- Modo escuro dos componentes SDC: revisão de todos os componentes e criação do modo escuro onde faltar (pedido posterior; depois da conclusão dos botões).

## Validação feita

- Gates: `validate-aculta420-v2-foundations.php`, `validate-aculta420-ui-styles.php` e `validate-aculta420-palette-schemes.php` passam.
- Suítes Kernel rodadas na worktree (banco de teste): `EventOrganizationAdminAndModalKernelTest` (8, OK), `EventDomainBoundaryKernelTest` (10, OK), `EventOrganizationLifecycleKernelTest` (31, OK). `UgcModerationKernelTest` (5, OK, rodada antes na cópia principal).
- Unitários: `PublicForumTopicCreateActionHooksTest` (5, OK), `CourseAuthoringControllerTest` (4, OK).
- Renders via Drush na worktree: link, botão desativado, só ícone, calendário do Studio e a grade de estados.

## Modo escuro da UI Library: auditoria de contraste

- Medição: `tests/browser/transversal/aculta420-v2-component-library/library-mode.py` (auditoria no modo escuro, razão mínima 4,5:1).
- Resultado atual: 1 falha, `rfc-matrix` do módulo contrib `simple_oauth_21` ("No RFC data available.", texto cinza fixo `#6b7280`, 3,77:1). Fora do tema; registrado como exceção, sem patch em contrib.
- Causas corrigidas: cor de texto resolvida no `body` (herdada com o valor claro); tokens derivados por `var()` sem par escuro; seletor global `.page a` sobrescrevendo o texto de destaque; controles do mapa com fundo branco fixo; variante neutra do rodapé com texto de rodapé verde.
