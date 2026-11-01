# Esquema visual — ACULTA Eventos

O visual do OpenCulturas entra como **padrões de layout e de componente**, desenhados com a
identidade do ACULTA420. Não há cópia de tema, de paleta nem de fonte.

Base: `web/themes/custom/aculta420/docs/design-system.md` e `docs/accessibility.md`.

## 1. Identidade (tokens)

| Token | Valor | Uso neste purpose |
| --- | --- | --- |
| `--aculta-green-dark` | `#0c3c29` | Texto principal, títulos de cartão, links |
| `--aculta-green` | `#689427` | Estrutura: faixa de data, separadores, ícones |
| `--aculta-yellow` | `#f2ca36` | Ação principal (“Ver evento”, “Abrir no mapa”) |
| `--aculta-red` | `#d4452d` | Ênfase: “Cancelado”, “Últimas vagas” (sempre com texto) |
| `--aculta-cream` | `#fbf4e8` | Fundo de página e de cartão |
| `--aculta-white` | `#ffffff` | Superfície de contraste |

Tipografia: Oswald para títulos e navegação; Inter para corpo, datas e metadados (`design-system.md`).

Regras de contraste:

- Texto sobre `--aculta-yellow` usa `--aculta-green-dark`, nunca branco.
- `--aculta-green` não serve para texto pequeno sobre `--aculta-cream`; use-o só para estrutura.
- Verificar AA (4,5:1 texto normal; 3:1 componentes) em light e dark antes de fechar F6.

Modo escuro: tokens redefinidos no tema, sem regras específicas por página.

## 2. Páginas (esquema, mobile primeiro)

### Início do purpose — `eventos.aculta` `/`

```
┌──────────────────────────────┐   ≤ 575 px: uma coluna
│ [barra institucional]        │
│ EVENTOS                      │
│ Agenda cultural da rede      │
│ [Ver agenda]  [Ver locais]   │
├──────────────────────────────┤
│ Próximos (lista de 3 cartões)│
├──────────────────────────────┤
│ Locais em destaque (2)       │
└──────────────────────────────┘
≥ 992 px: "Próximos" em 3 colunas; "Locais" ao lado do mapa resumido.
```

### Catálogo — `/eventos`

```
┌──────────────────────────────┐
│ Título · contagem            │
│ [Filtros: tipo ▾ local ▾ período ▾]  (disclosure em < md)
├──────────────────────────────┤
│ ┌─────┐ Título do evento     │  cartão: faixa de data à esquerda,
│ │ 14  │ tipo · local         │  título, local, tipo; alvo do link
│ │ OUT │ 19h · gratuito       │  é o cartão inteiro (um único link)
│ └─────┘                      │
│ ┌─────┐ ...                  │
└──────────────────────────────┘
≥ md: 2 colunas; ≥ lg: 3 colunas.
Paginação com "Carregar mais" ou pager (sem scroll infinito por padrão).
```

### Página do evento — `/eventos/{slug}`

```
┌──────────────────────────────┐
│ Imagem (proporção fixa)      │
│ Título (H1) · subtítulo      │
│ [Próxima data] [Ver no mapa] │
├──────────────────────────────┤
│ Descrição                    │
│ Datas (lista; cada data com  │
│ local e status)              │
│ Organizador (cartão de autor)│
│ Acessibilidade · classificação│
│ [Adicionar ao calendário .ics]│
└──────────────────────────────┘
```

### Locais com mapa — `/locais` e `/mapa`

```
Mobile (padrão):              Desktop (≥ lg):
┌──────────────┐              ┌──────────────┬──────────────┐
│ [Lista|Mapa] │              │ Lista        │   MAPA       │
├──────────────┤              │ (rolagem     │ (marcadores  │
│ Lista (padrão│              │  própria)    │  agrupados)  │
│  sem JS)     │              │ cartão ↔     │              │
│ ou mapa com  │              │ marcador     │              │
│ lista abaixo │              └──────────────┴──────────────┘
└──────────────┘
```

- Sem JavaScript: a lista funciona sozinha (sem mapa).
- Com JavaScript: o mapa do Google carrega junto com a página. Um aviso curto informa que o mapa usa serviço
  do Google, com link para a política de privacidade. A lista continua disponível ao lado do mapa.
- Botão “Ver lista” alterna para a alternativa textual.
- Cada marcador tem rótulo textual acessível e o cartão tem link para o local.
- Controles de zoom com alvo de 44 × 44 px.

### Calendário — `/calendario` (`aculta_calendar`)

```
Mobile (< md): AGENDA (lista por dia)        Desktop (≥ md): MÊS (grade)
┌──────────────┐                              ┌──┬──┬──┬──┬──┬──┬──┐
│ Sábado 14    │                              │ D│ S│ T│ Q│ Q│ S│ S│
│  · evento A  │                              ├──┼──┼──┼──┼──┼──┼──┤
│  · evento B  │                              │  │  │ 1│  │ 3│  │  │
│ Domingo 15   │                              │  │  │• │  │••│  │  │
│  · evento C  │                              └──┴──┴──┴──┴──┴──┴──┘
└──────────────┘                              Grade: <table> com caption
                                              e cabeçalhos; dia com
                                              eventos vira link para a lista
```

- A grade é uma tabela semântica (`caption`, `th scope`). Dias sem evento não são links.
- Em telas pequenas a grade não aparece: a agenda é a única visão.
- Botões “Exportar .ics” do dia e do período.

## 3. Componentes SDC previstos (em `aculta420`)

Nome de componente em `snake_case`/kebab conforme o padrão do tema. Stories obrigatórias para cada um.

| Componente | Usado em | Dados (do módulo, sem consulta de entidade no tema) |
| --- | --- | --- |
| `content/event-card` | Início, catálogo | título, data formatada, local, tipo, URL |
| `content/event-date-chip` | Cartões, lista da agenda | dia, mês abreviado, horário |
| `content/location-card` | Lista de locais | nome, bairro, tipo, URL, distância opcional |
| `content/event-status` | Página, cartões | rótulo textual e variante (cancelado, adiado) |
| `layout/event-detail` | Página do evento | slots: imagem, cabeçalho, descrição, datas |
| `map/locations-map` | `/mapa`, `/locais` | lista de marcadores (lat, long, rótulo, URL) |
| `calendar/month-grid` | `/calendario` (≥ md) | mês, dias com eventos, URLs por dia |
| `calendar/agenda-list` | `/calendario` (< md) | dias e eventos |
| `filters/event-filters` | Catálogo, locais, calendário | campos e valores; estado de disclosure |

Regras de tema:

- O tema não consulta `Domain` nem `Node`: os dados chegam prontos do módulo (`aculta_portal` e
  os submódulos). Ver `web/themes/custom/aculta420/docs/shell.md`.
- Mapa: JavaScript mínimo, carregado só nas rotas que o usam (`/mapa`, `/locais`, bloco).
- Calendário: CSS responsivo sem biblioteca de calendário pesada.

## 4. Acessibilidade (critérios de aceite)

- Hierarquia de títulos: H1 por página; H2 por seção; cartões usam H3.
- Cartão inteiro com um único link; sem links aninhados.
- Filtros com rótulos visíveis; disclosure com `aria-expanded`.
- Mapa: alternativa textual completa; marcadores com rótulo acessível; foco visível.
- Cancelado e adiado usam texto, não só cor.
- Reflow em 320 px sem rolagem horizontal da página (a grade do calendário pode rolar dentro de
  um contêiner, se necessário, com aviso).
- Zoom de 200% sem perda de conteúdo.
- Movimento reduzido respeitado (`prefers-reduced-motion`).

## 5. Ícones

O OpenCulturas usa Phosphor Icons (MIT). Se o ACULTA adotar o mesmo conjunto, incluir o aviso de
licença no tema. Não há decisão tomada sobre o conjunto de ícones (fica para F6).

## 6. O que não entra

- Paleta, fontes, nomes de classe e templates copiados do `opcult`.
- Modo escuro próprio (o ACULTA já usa tokens).
- Banner de cookies do OpenCulturas (o ACULTA tem aceite único).
