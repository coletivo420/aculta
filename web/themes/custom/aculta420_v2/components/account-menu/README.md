# account-menu

Menu da barra institucional (ADR-047). Usado em `src/Hook/PagePresentationHooks.php`
(`buildAccountMenuComponent`).

## Comportamento

Segue o mesmo padrão dos diálogos utilitários da barra (carrinho, avisos e mensagens):

- **Gatilho:** `button` com `aria-controls`, `aria-haspopup="dialog"` e
  `data-aculta-dialog-trigger`. Mostra só o ícone de três linhas, sem avatar.
  Rótulo acessível "Abrir menu" (visualmente oculto). Variante ghost com tokens
  `--button-*`; alvo de 44 px.
- **Diálogo:** `<dialog>` aberto com `showModal()` pelo `js/utility-dialogs.js`
  compartilhado. Ocupa a tela inteira e tem X no canto superior direito.
  - Esc e X fecham; o foco volta ao gatilho (nativo do `<dialog>`).
  - Foco preso no diálogo enquanto aberto (Tab/Shift+Tab, pelo JS compartilhado).
  - Fundo esmaecido: `::backdrop` com transição de opacidade em
    `components/multidomain-bar/multidomain-bar.css`, igual para os quatro diálogos.
    Respeita `prefers-reduced-motion`.
  - Sem logotipo dentro do diálogo.
- **Fundo:** `--color-surface` (claro e escuro). Separadores: `--color-border`.
- **Foco:** `--button-focus-ring` (verde-escuro no claro, creme no escuro).

O componente não tem JS próprio: a abertura, o Esc e o foco vêm de `js/utility-dialogs.js`.

## Colunas

1. **Conta:** para autenticado, avatar, nome, Minha conta e Sair (slot
   `menu_items`). Para anônimo, Entrar e Criar conta.
2. **Sites e legal:** prop `sites` (Portal, `aculta_domain_sites`) e prop
   `legal_links` (Busca, Política de privacidade; Configurações de privacidade
   só para autenticado).

Desktop (60 rem ou mais): duas colunas. Celular (320 px): uma coluna, na ordem 1, 2.
A navegação principal não é repetida no painel: ela já aparece na barra.

## htmx

Avisos e mensagens carregam o conteúdo com htmx ao abrir (`aculta:drawer:open`).
O menu de conta **não** usa htmx: o conteúdo já vem na página, com access e cache
preservados pelo Portal. Carregar o menu por htmx exigiria uma rota de fragmento
em `aculta_portal`, fora do escopo deste componente.

## Props e slots

- `authenticated` (boolean), `summary_label`, `account_name`, `sites`, `legal_links`.
- Slots: `avatar`, `menu_items`.

## Pendências

- "Termos" e "Informações legais" não têm rota confirmada no código nem no site
  publicado, por isso não aparecem.
- Validação visual em Firefox ESR: ver `docs/status-layouts-e-shell.md`.
