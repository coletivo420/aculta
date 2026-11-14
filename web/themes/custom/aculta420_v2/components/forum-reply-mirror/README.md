# forum-reply-mirror — Cartão do Post do Fórum Espelhado

Variante de resposta do fórum para comentários pares. Reproduz o layout de `forum-post-card` espelhado:

- **Desktop (a partir de 48rem):** autor à direita, faixa lateral à direita, ações à esquerda e reações à direita.
- **Mobile (abaixo de 48rem):** só a faixa muda de lado; ações e reações mantêm a posição.
- **Cores:** faixa de destaque à direita em vermelho (`--button-danger-bg`); linha acima das ações em verde (`--color-brand-primary`).

## Propriedades
- `author_name` (obrigatório), `timestamp`, `author_posts`.

## Slots
- `avatar`, `body`, `actions`, `reactions_slot`.

## Consumidores
- Os comentários pares de `field_aculta_discussion` usam regras de espelhamento em `forum-post-card.css`, pelo seletor `:nth-of-type(even)`. Este componente é a versão isolada, para biblioteca e uso manual.
