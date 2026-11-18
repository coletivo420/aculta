# Color Mode Switcher

SDC isolado para selecionar `light`, `auto` ou `dark`. A entrega não o posiciona
em páginas de produção. Todas as stories usam `context: library`, são
determinísticas e nunca consultam nem gravam a preferência da conta.

## Props

- `mode`: preferência inicial (`light`, `auto`, `dark`).
- `context`: `site` persiste a escolha para a conta ou localmente por origem para
  visitantes; `library` e `demo` alteram somente o preview.
- `appearance`: aparência inicial local à demonstração (`auto`, `light`, `dark`).
- `alignment`: alinhamento do popover (`start`, `end`); o script limita o painel
  à viewport.
- `disabled`: desativa o gatilho e os radios.
- `aria_label`: nome base; o modo selecionado é acrescentado ao nome.
- `attributes`: atributos Drupal do contêiner.
- `instance_id`: identificador opcional validado; sem ele o SDC gera um sufixo
  numérico por instância.
- `initial_open`: abre o painel inicialmente nas stories de estado aberto.

O painel usa Popover API nativa (`popover="auto"`) e fieldset de radios nativos.
Drupal Behaviors e `once()` inicializam posicionamento, Escape/foco, reflow e
fechamento externo de fallback, inclusive nos previews em Shadow DOM da Library.

## Persistência

Em `site`, a escolha atualiza imediatamente `data-color-preference` e o
`data-theme` efetivo. O Portal é dono de `user.data` e do endpoint POST
autenticado protegido por `X-CSRF-Token`; o componente obtém o token pela rota
oficial `/session/token`. Uma fila serializa salvamentos rápidos. O status só
confirma persistência após a resposta; erro restaura o último modo confirmado.
Visitantes usam `localStorage` nesta origem. A preferência não se sincroniza
entre subdomínios.

`library` e `demo` nunca usam localStorage, endpoint ou preferência da conta.
`auto` permanece armazenado como preferência e usa `matchMedia` para resolver e
acompanhar o modo aplicado (`light` ou `dark`). O formulário de aparência da
Conta continua lendo e gravando a mesma `ColorModePreference`.

Um consumidor futuro deve fornecer a preferência já obtida do Portal e escolher
`context: site`; esta entrega não cria placement em header, barra institucional,
rodapé, menus, Conta ou outra página.
