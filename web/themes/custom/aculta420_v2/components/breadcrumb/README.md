# Breadcrumb

Trilha de navegação por sessão, com ícone do primeiro nível e página atual sem link.
Recebe dados (`trail`, `current`, `sessao`, `variant`), não HTML.

## Sessões

| `sessao` | Ícone do primeiro nível |
| --- | --- |
| `portal` | `squares-four` |
| `revista` | `book-open` |
| `forum` | `chat-circle` |
| `wiki` | globo `wiki420-globe` (assets/icons/wiki420) |
| `ganjalab` | `graduation-cap` |
| `green_stash` | `shopping-cart` |
| `culturas` | `calendar-blank` |

Ícones Phosphor regular vêm do pack do tema. O item de `trail` pode trazer `icon` próprio, que substitui o da sessão.

## Uso pelos módulos

O template do sistema (`templates/navigation/breadcrumb.html.twig`) lê `aculta_breadcrumb_sessao`. Cada módulo define essa variável no preprocess da sua rota. Sem valor, usa `portal`.

## Superfícies

- `padrao`: editorial, sem fundo.
- `superficie`: faixa com `--color-surface-muted`.
- `contextual`: sobre `--color-surface-inverse`, para o Studio.

No mobile (até 40rem), mostra só o nível anterior e a página atual.
