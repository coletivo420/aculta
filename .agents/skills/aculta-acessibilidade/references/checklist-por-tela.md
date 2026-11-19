# Checklist de acessibilidade por tipo de tela

1. **Formulários e erros:** todo campo tem label visível associada; erro é anunciado (texto + `aria-describedby`, ou `role="alert"` em atualização dinâmica); foco vai ao primeiro erro após submissão.
2. **Modais e diálogos:** foco entra no diálogo, Esc fecha, foco retorna ao gatilho, o fundo recebe `inert` ou equivalente. Confira também o caminho HTMX de abertura.
3. **Atualizações HTMX:** a região atualizada tem live region adequada ou o foco é movido de forma previsível; behaviors são reanexados (`Drupal.behaviors` / `core/once`) sem listeners duplicados.
4. **Ícones:** decorativo com `aria-hidden="true"`; informativo com nome acessível (texto, `aria-label` ou `title` do controle). O SDC `aculta420_v2:icon` emite `aria-hidden="true"`, então um ícone que é a única informação precisa de texto no controle.
5. **Foco visível:** indicador presente em claro e escuro, com contraste mínimo de 3:1 contra o fundo adjacente. Não remova `outline` sem substituto.
6. **Alvos de toque:** 24×24 CSS px no mínimo (WCAG 2.2 2.5.8); na prática, o alvo do design system.
7. **Cor e tema:** teste claro e escuro (`data-theme`). Texto normal 4.5:1; texto grande e componentes de interface 3:1.
8. **Zoom 200% e reflow 320 px:** sem perda de conteúdo nem rolagem horizontal em conteúdo textual.
9. **Movimento:** animações respeitam `prefers-reduced-motion`.
10. **Idioma e título:** `lang="pt-BR"` na página e `<title>` específico por rota.
