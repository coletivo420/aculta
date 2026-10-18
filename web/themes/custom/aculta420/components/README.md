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
- `content/course_card` -> `aculta420:course_card`, substituto SDC compatível para `lms:course_card`.
- `content/messenger-item`, `content/messenger-compose`, `content/messenger-notification` e `content/institutional-notice` -> componentes experimentais da central; `messenger-item` recebe avatar renderizado, direção de envio e corpo nativo como slots/props neutros.
- `patterns/messenger-list` -> moldura de listas do Private Message/Message.
- `feedback/unread-badge` e `feedback/messenger-state` -> contagem e estados da central.
- `patterns/card` -> cartão composto reutilizável com slots de mídia, cabeçalho, conteúdo e rodapé; variantes vertical e horizontal usam Bootstrap e tokens ACULTA420.

O cartão composto foi adaptado após análise do SDC Card do UI Suite Bootstrap
5.2.3 (GPL-2.0-or-later). O markup é próprio do ACULTA420 e não importa assets,
templates, bibliotecas ou dependências do tema de origem.

Não criar placeholders vazios. Novos diretórios entram com o primeiro componente
real da categoria.

Regras completas: [../docs/components.md](../docs/components.md).
