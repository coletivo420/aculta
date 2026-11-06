# Studio Calendar (`aculta420_v2:studio-calendar`)

Componente SDC de calendário do Studio Dashboard (ACULTA).

## Recursos

- **Cabeçalho:** Mês atual com navegação anterior/seguinte e botão "+" para proposição de novos eventos (autenticados).
- **Filtros segmentados:** Cursos, Eventos e Meus eventos.
- **Grade do mês:** Matriz de 7 colunas (segunda a domingo), dia atual (`is_today`), dia selecionado com `--purpose-accent`, indicadores visuais para dias com ocorrências.
- **Resumo do dia:** Lista detalhada dos itens do dia selecionado com horário, local e link.
- **Interação:** Atualização assíncrona do card via HTMX (`data-hx-get`, `data-hx-target="#aculta-studio-calendar-card"`).
- **Responsividade:** Celular: abaixo do cabeçalho hero, antes de "Suas experiências". Desktop: coluna lateral direita, topo alinhado ao conteúdo.
