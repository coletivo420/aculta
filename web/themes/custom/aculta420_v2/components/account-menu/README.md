# account-menu

Menu da barra institucional (ADR-047). Usado em `src/Hook/PagePresentationHooks.php`
(`buildAccountMenuComponent`).

## Comportamento

- **Gatilho:** só o ícone de três linhas, sem avatar. Rótulo acessível "Abrir menu"
  (visualmente oculto), `aria-expanded` do `<details>`, foco visível com
  `--button-focus-ring`. Variante ghost com tokens `--button-*`; alvo de 44 px.
- **Painel:** tela cheia sobre a página, com X no canto superior direito.
  - Escape e X fecham; o foco volta ao gatilho.
  - Enquanto aberto, Tab e Shift+Tab ficam presos no painel.
  - Sem JavaScript, o `<details>` ainda abre e fecha.
  - Sem logotipo dentro do painel.
- **Fundo:** `--color-surface` (claro e escuro). Separadores: `--color-border`.

## Colunas

1. **Navegação principal:** slot `primary_menu_items`, com o mesmo menu
   `system_menu_block:main` que a barra usa.
2. **Conta:** para autenticado, avatar, nome, Minha conta e Sair (slot
   `menu_items`). Para anônimo, Entrar e Criar conta.
3. **Sites e legal:** prop `sites` (Portal, `aculta_domain_sites`) e prop
   `legal_links` (Busca, Política de privacidade; Configurações de privacidade
   só para autenticado).

Desktop (60 rem ou mais): três colunas. Celular (320 px): uma coluna, na ordem 1, 2, 3.

## Props e slots

- `authenticated` (boolean), `summary_label`, `account_name`, `sites`, `legal_links`.
- Slots: `avatar`, `primary_menu_items`, `menu_items`.

## Pendências

- "Termos" e "Informações legais" não têm rota confirmada no código nem no site
  publicado, por isso não aparecem.
- Validação em Firefox ESR (capturas antes/depois, 320/1440 px, claro/escuro,
  anônimo/autenticado) ainda não foi feita. Ver `docs/status-layouts-e-shell.md`.
