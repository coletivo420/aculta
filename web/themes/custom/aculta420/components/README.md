# Components

ACULTA420 organiza SDCs por responsabilidade visual.

Estrutura alvo:

```text
components/
├── primitives/
├── content/
├── navigation/
├── sections/
└── feedback/
```

O Drupal Core permite subdiretórios arbitrários dentro de `components/`.

## 0.1.0

Disponível:

- `content/editorial-card` -> `aculta420:editorial-card`.
- `content/messenger-item`, `content/messenger-compose`, `content/messenger-notification` e `content/institutional-notice` -> componentes experimentais da central; `messenger-item` recebe avatar renderizado, direção de envio e corpo nativo como slots/props neutros.
- `patterns/messenger-list` -> moldura de listas do Private Message/Message.
- `feedback/unread-badge` e `feedback/messenger-state` -> contagem e estados da central.

Não criar placeholders vazios. Novos diretórios entram com o primeiro componente
real da categoria.

Regras completas: [../docs/components.md](../docs/components.md).
