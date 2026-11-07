# Growroom420 — plano visual do Fórum (ACULTA420 V2)

**Status:** planejado e aprovado pelo responsável em 2026-11-07. Execução autônoma por agente, sem interrupções. Perguntas só quando bloqueio real, conforme a seção 10.

**Fontes:** cinco imagens de referência (lista de tópicos desktop, página de categoria desktop, página de tópico, formulário de novo tópico e fórum mobile) e referências públicas do Discourse, usadas **apenas como padrão visual**: [guia de elementos de interface do Discourse](https://meta.discourse.org/t/interface-nomenclature-guide/103351?tl=en), [Topic Cards](https://awesome.ecosyste.ms/projects/github.com%2Fdiscourse%2Fdiscourse-topic-cards) e [Topic List Previews](https://meta.discourse.org/t/topic-list-previews-tlp/209973/692?tl=en). Não copiar marca, textos nem ícones do Discourse.

**Escopo:** apresentação do tema `aculta420_v2`. Não altera a V1 (`web/themes/custom/aculta420/`), não altera lógica de acesso, grants, moderação ou revisões do módulo `aculta_forum`.

## 1. Decisões fechadas

| Tema | Decisão |
| --- | --- |
| Padrões Discourse | Cards com miniatura, prévia do último post e pílulas de status |
| Layout do post | Card com faixa colorida no topo (não o estilo Discourse sem card) |
| Dados ausentes (visualizações, Seguindo, Salvar, participantes, moderadores) | Renderizar com placeholder neutro (seção 5) |
| Rota nova de categoria | Autorizada a criar a View `/categorias/{termo}` em `config/sync`, **somente na entrega 2** |
| Paleta | Só tokens existentes do tema; ver seção 2 |
| Versão | MINOR (mudança de shell e de componentes, ADR-046). Número só na entrega validada |

## 2. Paleta (tokens existentes)

Usar os tokens de `src/styles/tokens.css`. Não criar primitivas novas fora desse arquivo.

- Marca e texto: `--color-brand-primary` (`#0c3c29`), `--color-text-primary`, `--color-text-secondary`, `--color-text-muted`.
- Destaque: `--color-brand-highlight` (`#f2ca36`), `--color-surface-highlight` (`#fbf4e8`), `--color-border-highlight`.
- Links e ações editoriais: `--color-link-editorial` (`#b52f22`), para o vermelho das bordas de card e dos links de "Responder".
- Status: `--color-status-ok-*`, `--color-status-warning-*`, `--color-status-error-*`.
- Verde de categoria (faixas e pontos): usar um token de categoria novo **só se** a paleta existente não tiver verde claro; caso contrário, `--color-status`.
- Modo escuro: só via tokens em `tokens.css` e `data-theme`. Nada de `prefers-color-scheme` fora dele.

## 3. Componentes novos (SDC com schema estrito e story)

Cada um em `web/themes/custom/aculta420_v2/components/<nome>/` com `<nome>.component.yml`, `<nome>.twig`, `<nome>.css`, `<nome>.default.story.yml` e README.

Regras comuns: `with_context = false`; nenhum parâmetro de entidade, usuário ou render array inteiro; slots para conteúdo Drupal; sem JavaScript, salvo onde a seção 9 disser.

### 3.1 `forum-category-header` (entrega 1)
- Props: `title` (string), `description` (string), `icon` (enum neutro), `stats` (array de `{label, value}`, com `value` opcional).
- Mostra eyebrow "GROWROOM420 / COMUNIDADE", título, descrição e três estatísticas em faixa com borda vermelha no topo.
- Referência: imagens 2 e 6 (mobile).

### 3.2 `forum-tabs` (entrega 1)
- Props: `items` (array de `{label, href, active, count}`), `label` (aria do grupo).
- Abas com sublinhado verde no ativo e contador vermelho para não lidos. Em telas pequenas, rolagem horizontal **só dentro da barra** (ver seção 7).
- Filtros de ordenação e de tipo ficam em slot `filters`, com `select` nativo.
- Referência: imagens 1, 2 e 6.

### 3.3 `forum-topic-row` (entrega 1)
- Props: `pinned` (bool), `badges` (array de `{label, tone}`, tone em `neutral|highlight|success|official`), `replies`, `views`, `last_activity`.
- Slots: `title` (link), `meta` (categoria e autor), `preview` (prévia do último post, opcional), `thumbnail` (opcional).
- Desktop: tabela com colunas Respostas, Visualizações e Última atividade. Mobile: contadores em linha abaixo do título.
- Destaque (fixado) com fundo `--color-surface-highlight` e faixa verde à esquerda.
- Referência: imagens 1, 2 e 6.

### 3.4 `forum-topic-card` (entrega 1) — padrão Discourse Topic Cards
- Variante de cards com miniatura, usada quando a lista estiver em modo cards.
- Miniatura: imagem do tópico quando existir; senão, ícone neutro da categoria. **Nunca** letra ou inicial como avatar (regra do tema).
- Props iguais às de `forum-topic-row`, mais `thumbnail_src` (string) e `thumbnail_alt`.

### 3.5 `forum-post-card` (entrega 1)
- Props: `stripe` (enum `brand|highlight|member`, default `brand`), `author_name`, `author_role` (string opcional), `author_posts` (string opcional), `timestamp`, `reactions` (array `{label, count}`), `reply_url`.
- Slots: `avatar`, `body`, `actions` (botões nativos), `reply` (link ou botão).
- Faixa colorida no topo do card, cabeçalho com avatar, nome, papel e contagem. Corpo com `line-height` confortável.
- Papel e variante de faixa vêm **do Portal** via contrato (seção 6). O tema não lê papel de usuário.

### 3.6 `forum-sidebar-panels` (entrega 2)
- Painéis em coluna: `stats` (números da comunidade), `featured` (tópicos em destaque com miniatura), `moderators` (lista com badge), `rules` (lista de checks), `related` (categorias relacionadas com ponto colorido).
- Cada painel é um card com faixa vermelha ou amarela no topo, alternando.
- Dados vazios: placeholder (seção 5).
- Referência: imagens 1, 2 e 6.

### 3.7 `forum-composer-guidelines` (entrega 2)
- Props: `tips` (array de strings), `community_rules` (array de `{title, text}`), `collapsed` (bool).
- Duas colunas no desktop, uma no mobile. Cards com faixa amarela no topo.
- Se `collapsed` for verdadeiro, usa `<details>` nativo (sem JavaScript).
- Referência: imagem 4.

## 4. Mapeamento das páginas

| Página | Estado atual | Entrega | O que muda |
| --- | --- | --- | --- |
| `/discussoes` (e `/novas`, `/mais-comentadas`) | Existe (View `aculta_forum_public_topics`) | 1 | Cabeçalho, abas, tabela, painéis laterais (entrega 2), botão "+ Novo tópico" largo no mobile |
| Página de tópico (`node--aculta-forum-topic`) | Existe | 1 | Cabeçalho com breadcrumb, pílulas de categoria e tags, botões Seguir/Salvar/mais (placeholder), cards de post, editor de resposta no fim |
| `/criar-topico` | Existe | 2 | Formulário em card com faixa vermelha; diretrizes via `forum-composer-guidelines`; botões Publicar, Salvar rascunho e Cancelar |
| `/categorias` | Existe | 1 | Cards de categoria com faixa vermelha e contagem |
| `/categorias/{termo}` | **Não existe** | 2 | Página nova: `forum-category-header`, subcategorias em pílulas coloridas, `forum-tabs` e lista, painéis laterais |

Observação: hoje o link de categoria aponta para `/?categoria={{ tid }}`. Na entrega 2 ele passa a apontar para `/categorias/{termo}`, sem quebrar o filtro atual enquanto a rota nova não estiver validada.

## 5. Dados ausentes: placeholder

Quando um dado não existir no contrato do Drupal:
- Renderizar o rótulo e o elemento com valor **`—`** e `<span class="visually-hidden">Dado indisponível</span>`.
- Controles como Seguir, Salvar e moderadores aparecem desabilitados (`disabled`, `aria-disabled="true"`) com texto "Em breve" visível.
- Nunca inventar números nem nomes em página real. Valores de exemplo só dentro das stories.
- Cada placeholder registrado em `docs/architecture.md` (seção 2) como "pendente".

## 6. Contratos com o Portal (pendentes para o Portal)

O tema não lê entidade, papel ou regra de domínio. Os itens abaixo são pedidos ao Portal, documentados como pendentes e tratados com placeholder até existirem:
- Papel do autor e variante de faixa (`stripe`).
- Contagem de posts e de visualizações por tópico.
- Prévia do último post.
- Lista de moderadores e participantes.
- Estado "seguindo" e "salvo" por usuário.

Quando o Views já fornecer um campo (contagem de respostas e última atividade já existem), o tema usa o campo. Nada de campo novo em `config/sync` na entrega 1.

## 7. Responsividade e acessibilidade (obrigatório)

- Base em **320 CSS px**, sem rolagem horizontal da página. Rolagem horizontal só dentro de `forum-tabs`, com alternativa acessível.
- Alvo de toque de 44 px. Foco visível (`:focus-visible`). Estado nunca só por cor (pílulas têm texto).
- Texto longo com `overflow-wrap: anywhere` e `min-inline-size: 0`.
- Zoom 200% e 400% funcionais. `prefers-reduced-motion` respeitado.
- HTML semântico: `nav` nas abas, `article` nos cards de post, `dl` no meta, `h2`/`h3` na ordem.
- Sem JavaScript obrigatório. A única interação com JavaScript aceita é a alternância entre modos de lista (cards/tabela), e precisa de fallback sem JS (ver seção 9).

## 8. Validação

**Grade de larguras:** 320, 360, 390, 599, 600, 768, 1024, 1280 e 1440 CSS px.

**Estados:** claro e escuro; anônimo (obrigatório); autenticado (quando a conta de teste permitir; senão `DEFERRED` com motivo).

**Gates estáticos** (rodar só os que se aplicam ao diff):
- `npm run lint:css` (no tema)
- `php scripts/validate-aculta420-sdc-schemas.php`
- `php scripts/validate-aculta420-design-foundations.php`
- `php scripts/validate-module-theme-boundary.php`
- `php scripts/validate-aculta420-mapped-classes.php`
- `git diff --check`

**Browser:** Firefox ESR visível em `DISPLAY=:0`, via `geckodriver` e os scripts existentes em `tests/browser/aculta-forum/` (reusar `wdlib.py`). Não usar Chromium nem headless como aceite visual.

**Capturas:** `tests/browser/capturas/growroom420-visual/<AAAA-MM-DD>/`, nos 320, 390 e 1440 px em claro e nos 390 px em escuro, além de zoom 200%. Logs e artefatos temporários em `$SUITE_OUT`, fora do repositório.

**Pendências:** registrar como `DEFERRED` com o motivo. Não declarar PASS sem execução.

## 9. JavaScript

- Somente onde há interação, com `Drupal.behaviors` e `core/once`, dentro de `aculta420_v2.libraries.yml`.
- Alternância cards/tabela: usar `<details>` ou links de visualização alternativa quando possível; se usar JS, o padrão sem JS (tabela) precisa funcionar.
- Sem `#ajax` nem `use-ajax`. Interações novas com HTMX, conforme o AGENTS do tema.

## 10. Execução e perguntas

- Trabalhar na worktree `worktree-growroom420-visual` (`.claude/worktrees/growroom420-visual`), baseada em `dev`.
- **Entrega 1:** seção 3 (exceto 3.6 e 3.7), seção 4 (exceto `/criar-topico` e categoria nova), seções 5 a 9. Commit atômico, push para `origin/dev` ao final, com `CHANGELOG.md`, `docs/roadmap.md`, `docs/components.md` (com story citada), `docs/architecture.md` e `docs/gates.md` atualizados no mesmo commit.
- **Entrega 2:** seções 3.6, 3.7, `/criar-topico` e a View de `/categorias/{termo}` em `config/sync` (autorizada). Mesmo procedimento de commit e documentação.
- Versão: MINOR em `aculta420_v2.info.yml` **junto** da entrega validada, com a entrada correspondente no `CHANGELOG.md`.
- Commits em português com o trailer `Claude-Session` e sem `Co-Authored-By`.
- Não fazer PR nem merge. Não fazer force push.
- Não alterar o módulo `aculta_forum` (PHP) nem a V1.
- Não gravar segredos. Não escrever no Runtime sem necessidade. Limpar cache do Runtime de desenvolvimento após sincronização com `origin/dev`, conforme memória do projeto.
- **Perguntar** só se: (a) um gate falhar por causa fora do escopo, (b) a View em `config/sync` precisar de decisão que este documento não cobre, ou (c) faltar acesso ao servidor de desenvolvimento. Nos demais casos, assumir o padrão deste documento e registrar a suposição no relatório final.

## 11. Prompt para o agente executor

Copie o bloco abaixo ao iniciar o agente:

```
Você vai executar, sem interrupções, a tarefa descrita em
docs/architecture/GROWROOM420-VISUAL-PLANO.md (na worktree
.claude/worktrees/growroom420-visual, branch baseada em dev).

Leia primeiro o plano inteiro e o web/themes/custom/aculta420_v2/AGENTS.md.
Depois execute a ENTREGA 1 e a ENTREGA 2 na ordem, seguindo as seções 3 a 10.

Regras:
- Use somente os tokens existentes em src/styles/tokens.css.
- Cada componente novo tem schema estrito, story e entrada em docs/components.md.
- Dados ausentes viram placeholder (seção 5). Nada de números ou nomes inventados em página real.
- Nada de contexto de entidade ou papel de usuário no tema.
- Sem alterar a V1 nem o módulo aculta_forum (PHP).
- Rode os gates aplicáveis e cole a saída. Não declare PASS sem execução.
- Capturas em tests/browser/capturas/growroom420-visual/<data>/ com Firefox ESR visível (DISPLAY=:0).
- Commit atômico em português por entrega, com o trailer Claude-Session, e push direto para origin/dev ao fim de cada entrega.
- Não faça PR, merge, force push ou escrita no Runtime fora do necessário.
- Pergunte só nos casos da seção 10. Caso contrário, assuma o padrão e registre no relatório.

Ao final, entregue um relatório com: o que foi feito por entrega, commits e
hashes, gates executados com saída, capturas, pendências (DEFERRED com motivo),
suposições feitas e o próximo comando, se houver.
```

## 12. Critérios de aceite

- Lista de tópicos, página de tópico, `/criar-topico` e `/categorias` seguem as imagens de referência em claro e escuro, em 320 e 1440 px.
- Página de categoria nova (`/categorias/{termo}`) existe, tem story e foi validada sem overflow.
- Nenhum dado inventado em página real.
- Gates aplicáveis com saída colada.
- Documentação atualizada no mesmo commit de cada entrega.
