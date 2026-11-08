# Botões do ACULTA420 V2

Status: **links, ações e envios de formulário usam o SDC `aculta420_v2:button`; exceções abaixo são só as que dependem de comportamento próprio do navegador/JS.** Limpeza de CSS de área, verificação visual por purpose e validação funcional dos envios HTMX concluídas em 2026-11-08.

## Componente

`web/themes/custom/aculta420_v2/components/button/` (SDC `aculta420_v2:button`).

- Variantes: `primary`, `secondary`, `outline`, `ghost`, `danger`.
- `size`: `default` ou `small`. `small` altera só espaçamento e fonte; o alvo de toque continua em 44 px.
- `text` é opcional quando `aria_label` existir (botão só com ícone). `aria_label` vira `aria-label`.
- `url` gera `<a>`; sem `url`, gera `<button>`. `disabled` com `url` gera `<span aria-disabled="true">`.
- Classes e atributos extras passam por `attributes` (`create_attribute` no Twig; `#attributes` no PHP).
- Ícones: `icon_before` e `icon_after`.
- `action` vira `primary`. `small` não é variante.

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
- Captura e checagem: `tests/browser/transversal/aculta420-v2-component-library/library-mode.py` (conta de teste por `drush uli` no host de conta; rota real `/painel-administrativo/aparencia/ui/components`).

## Migração por variação da tabela

| Variação encontrada | Estado final |
| --- | --- |
| `data-portal-ui: button`, `button-primary`, `aculta-button-primary` | SDC `variant: primary` |
| `data-portal-ui: button-secondary`, `aculta-button-secondary` | SDC `variant: secondary` |
| `button-ghost`, `button-outline`, `aculta-button-outline` | SDC `outline` (links de volta/ação) ou `ghost` (navegação de calendário); tokens mantidos no mapa só por exigência do `ui_styles` |
| `button--danger`, `event-*-danger` | SDC `variant: danger` (envios via `input--submit`) |
| `button--small`, `*-small` | SDC `size: small` |
| `button--action`, `*-primary-action` | SDC `variant: primary` |
| `button--cta`, `oc-button--primary`, `.form-submit` | SDC `primary` via `input--submit` |
| `aculta-btn-outline`, `aculta-btn-primary` (`buttons.css`) | removidos do mapa e do CSS; links passam por `outline`/`primary` |
| `<input type="submit">` | `input--submit` mantém `id`, `name`, `value`, `formnovalidate`, `js-form-submit` e `form-submit` |
| `<button type="button">` com JS próprio | exceção documentada (sem conversão) |
| links com `data-portal-ui` de botão dentro de `#links` | SDC via array de links (Fórum) |
| botão só com ícone | SDC com `aria_label` e sem `text` |
| botão desativado (`is-disabled`, `aria-disabled`) | SDC `disabled`; exceção do Fórum abaixo |
| botão que dispara `#ajax` (envio ou link `use-ajax`) | HTMX onde havia rota e fragmento claros; demais exceções em `docs/htmx-envios.md` |

## Migrado para o SDC (fechamento de 2026-11-08)

| Área | Pontos | Commit |
| --- | --- | --- |
| Learner Dashboard do Studio | Continuar aula/curso, Ver jornada, Explorar catálogo, Ver certificado | `ad6335e19` |
| LMS | Voltar às respostas (revisão de resposta) | `ad6335e19` |
| Wiki | Contribuir e Retirar proposta | `ad6335e19` |
| Eventos | Cadastrar organização, Ver pedidos de reivindicação, Filtrar do painel do organizador | `ad6335e19` |
| Caminhos | Editar superfície (`size: small`, mantém o modal jQuery) | `ad6335e19` |
| Mensagens | Iniciar nova conversa (removeu `btn btn-primary`) | `ad6335e19` |

Migram para o SDC em entregas anteriores: Portal, Fórum, Studio (calendário, conexões, novo curso), Eventos (editar, membros, recuperação, organizador, locais, cadastro), cursos da conta, Melhor Envio, envios de formulário via `input--submit` e os três envios convertidos para HTMX.

## Exceções (não vão ao SDC sem mudar comportamento)

- **Envios de formulário com `name`/`value`** permanecem `<input>`/`<button>` do Form API e são renderizados pelo SDC por `templates/input--submit.html.twig`. Pontos com token próprio: `EventOrganizationMembersForm`, `EventOrganizationAdminController`, `EventOrganizerDashboardController`, `SocialLinksSettingsForm`, `FriendshipActionForm`, `NoticeReadStateForm`, dialogs e widgets de eventos.
- **`#type => button` com JS próprio**: `SocialLinkForm` e `AccountBioForm` (limpar), `ConfirmDialog` (abrir/fechar `<dialog>`), widgets de local/organização de eventos.
- **Links `use-ajax` que abrem/fecham diálogo jQuery**: superfícies de caminhos, foto do perfil, redes sociais, avatares, segredos e busca. Não há comando HTMX para o diálogo jQuery; detalhes em `docs/htmx-envios.md`.
- **Botões "Em breve" do tópico do Fórum**: `<button disabled>` com ícone, texto e badge próprio (`aculta-forum-action-btn__badge`). O SDC não tem slot de badge; converter perderia o selo "Em breve". Revisão futura do componente.
- **Filtros GET em marcação crua** (`statistics`, `agenda`, `memorial`, `green stash`, `mailer`): `<button type="submit">` sem classe de variante, dentro de HTML gerado por `#markup`/`#template`; styled pelo CSS global de elemento. Converter exigiria reescrever cada formulário como render array, sem ganho de variante.
- **Gavetas e gatilhos da barra institucional**: `<button type="button">` com JS, exceção já registrada no `AGENTS.md` do tema.

## Limpeza de CSS de área (2026-11-08)

- Removidos de `buttons.css` os seletores `[data-portal-ui="aculta-btn-primary"]` e `[data-portal-ui="aculta-btn-outline"]`, sem markup ativo.
- Removidas de `portal-ui-map.css` as regras `.aculta-button--outline`, `.aculta-button--primary` e `.aculta-button--secondary`.
- Removidos de `studio.css` os atalhos `.button--cta` e `.oc-button--primary` dentro das listas `:is(...)`; `.button--primary` e `.button--danger` continuam porque o próprio componente SDC emite essas classes.
- Mantidas as regras globais de elemento (`button`, `input[type="submit"]`, `.form-submit`) e as regras de `.button--primary`/`.button--secondary` em `forum.css` e `event-organization.css`, ainda aplicadas aos botões do SDC e aos envios.

## Modo escuro da UI Library: auditoria de contraste

- Medição: `tests/browser/transversal/aculta420-v2-component-library/library-mode.py` (auditoria no modo escuro, razão mínima 4,5:1).
- Resultado em 2026-11-08: 1 falha, `rfc-matrix` do módulo contrib `simple_oauth_21` ("No RFC data available.", texto cinza fixo `#6b7280`, 3,77:1). Fora do tema; exceção registrada.
- Causas corrigidas em entregas anteriores: cor de texto resolvida no `body`; tokens derivados por `var()` sem par escuro; seletor global `.page a`; controles do mapa com fundo branco fixo; variante neutra do rodapé.

## Revisão dos SDC no modo escuro

- Componentes SDC do tema: 55. Todos têm story na UI Library e entram na auditoria.
- Nenhum componente tem cor fixa no CSS: usam tokens semânticos, que trocam de valor com `data-theme`.
- Exceção de contraste: `rfc-matrix` do contrib `simple_oauth_21` (3,77:1), fora do tema.
- Superfícies claras no modo escuro, intencionais: amarelo de destaque em badges e faixas e polegar de rolagem claro sobre trilho escuro.

## Validação de 2026-11-08

- Gates: `validate-aculta420-ui-styles.php`, `validate-aculta420-v2-foundations.php` e `validate-aculta420-palette-schemes.php` PASS.
- `php -l` em todos os PHP alterados.
- Unitários: `aculta_studio` 70 testes OK (inclui `LearnerDashboardComposerTest`, ajustado para registrar `url_generator`); `aculta_events` 30 testes OK.
- Kernel: **DEFERRED**. As suítes de `aculta_events` e `aculta_lms` não inicializam porque vários `$modules` de teste ainda citam `aculta_ugc`, removido pela unificação em `aculta_cgu` (commit `ca1015201`). O `LearningDashboardKernelTest` também falha por dependência de serviço `aculta_events.occurrences` não habilitada no teste. Nenhuma falha é causada pelas mudanças de botão.
- UI Library (Firefox ESR, `DISPLAY=:0`, claro e escuro, 320 e 1440 px): 2 opções no seletor, sem barra institucional, 89 pré-visualizações com `data-theme` nos dois modos, `overflow_px` 0 em todas as capturas. Evidência: `tests/browser/capturas/botoes-modo-cor/2026-11-08/`.
- Verificação visual por purpose (`buttons-purpose-runtime.py`, 10 purposes × claro/escuro × 320/1440 px, incluindo 200% de zoom): **360/360 verificações PASS**. O fixture foi atualizado do token legado `aculta-btn-outline` para a marcação do SDC (`button button--outline`, fundo transparente e tokens secundários). Evidência: `tests/browser/capturas/purpose-buttons/2026-11-08/`.

## Leitor de tela em estados autenticados (Orca)

- Teste: `tests/browser/transversal/aculta-botoes-a11y/orca-buttons.mjs` (Orca real com `--debug-file`, Firefox via geckodriver em `DISPLAY=:0`, conta de teste por `drush uli`).
- Páginas: conta (calendário do Studio), painel do organizador em Eventos, redes sociais e caminhos no painel administrativo.
- O que confere por página: existe botão visível; todo botão tem nome acessível (`aria-label`, `value` ou texto); e o Orca anuncia esse nome ao receber foco.
- Resultado em 2026-11-08: **12/12 verificações OK**, **22 botões** anunciados (conta 4, eventos 6, admin-redes 3, admin-caminhos 9). Botões só com ícone foram anunciados pelo `aria-label`; botões de envio, como `Filtrar`/`Salvar`, como `push button`.
- Evidência resumida: `tests/browser/capturas/botoes-a11y/2026-11-08/results.json`. O log de fala completo fica fora do repositório.
- Limitação: as rotas administrativas usam o tema Gin; nelas o envio é o `<input type="submit">` do Gin (nome acessível em `value`). O teste cobre os dois temas.
