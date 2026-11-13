# Layout de página (V2)

Descrição completa do layout que a V2 deve produzir: zonas da página, ordem no
HTML e na tela, variantes, posições na barra institucional, menu hambúrguer,
popups, medidas por largura e comportamento. Também registra o que existe hoje
no código e o que muda.

Fontes de decisão:
[ADR-046](../../../../../docs/decisions/ADR-046-desenho-tema-v2-shell-e-fundacoes.md),
[ADR-047](../../../../../docs/decisions/ADR-047-menu-de-conta-na-barra-institucional.md),
[ADR-048](../../../../../docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md).
Plano de execução: [layouts-plan.md](layouts-plan.md). Telas do Studio:
[studio-visual-spec.md](studio-visual-spec.md) e [studio-estrutura.md](studio-estrutura.md).

## 0. Como ler

**Estado implementado (2026-11-13):** os shells clássico e `shell-v2` compõem
diretamente o SDC `layout-page`; o fallback de markup e CSS `.page__main` foi
removido. A homologação visual no Runtime ainda está pendente.

- **Alvo:** o layout que a V2 deve ter ao fim da fase P1 do ADR-048.
- **Hoje:** o que o `page.html.twig` faz agora. Onde difere do alvo, está marcado **muda**.
- **Padrão:** valor adotado como recomendação, sem confirmação do responsável. Marcado **padrão**.
- Medidas em `rem` seguem a raiz de 16 px. Larguras de tela são em CSS px.

## 1. Visão geral

A página é uma pilha vertical de zonas. Só o conteúdo e a barra lateral dividem
a largura, e só na variante `aside` a partir de 60rem.

```
Desktop (≥ 60rem)
+-----------------------------------------------------------------------+
| 1. BARRA INSTITUCIONAL (fixa)                                          |
|    logo | busca · carrinho · mensagens · avisos · [Entrar] · ☰         |
+-----------------------------------------------------------------------+
| 2. FAIXA DO DOMÍNIO (cabeçalho da área)                                |
+-----------------------------------------------------------------------+
| 3. Destaque · 4. Ajuda · 5. Trilha de navegação                        |
+-----------------------+-----------------------------------------------+
| 6a. BARRA LATERAL     | 6b. CONTEÚDO                                   |
|     (só em aside)     |     (leitura até 36rem, ou largura da variante)|
|     16rem             |                                               |
+-----------------------+-----------------------------------------------+
| 7. RODAPÉ                                                              |
+-----------------------------------------------------------------------+

Mobile (< 40rem)
+-----------------------------------+
| 1. BARRA: logo · ícones · ☰       |  (2 linhas, fixa)
|    Domínios ⌄ · busca             |
+-----------------------------------+
| 2. Faixa do domínio               |
| 3–5. Destaque, ajuda, trilha      |
| 6b. CONTEÚDO (uma coluna)         |
| 6a. Barra lateral, depois         |
| 7. RODAPÉ                         |
+-----------------------------------+
```

Entre 40rem e 60rem o layout é o do mobile, com cartões em duas colunas a partir de 40rem.

## 2. Zonas e ordem no HTML

| # | Zona | Região do Drupal | Elemento | Landmark | Hoje | Alvo |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Barra institucional | (componente `multidomain-bar`) | `div.aculta-institution-bar` | `region` "Barra institucional" | existe | existe, com a regra de ícones (seção 3) |
| 2 | Faixa do domínio | `header` | `header.page__header` | `banner` | existe | existe |
| 3 | Destaque | `highlighted` | `div.page__highlighted` | — | existe | existe |
| 4 | Ajuda | `help` | `div.page__help` | — | existe | existe |
| 5 | Trilha | `breadcrumb` | `div.page__breadcrumb` | `navigation` (SDC `breadcrumb`) | existe | existe |
| 6b | Conteúdo | `content` | `main#main-content` | `main` | existe | slot `content` do `layout-page` (**muda**) |
| 6a | Barra lateral | `sidebar_first` | `aside.layout-page__aside` | `complementary`, só com conteúdo | existe | slot `aside` (**muda**) |
| 7 | Rodapé | `footer` | `footer.page__footer` | `contentinfo` | existe | existe |

**Ordem no HTML:** a barra e as zonas 2 a 5 vêm antes do conteúdo. O conteúdo
(6b) vem antes da barra lateral (6a), mesmo quando a barra lateral aparece à
esquerda na tela. Isso dá leitura correta para leitor de tela e em zoom.

**Implementação C4:** `page.html.twig` mantém as regiões `header`, `navigation`,
`highlighted`, `help`, `breadcrumb`, `content`, `sidebar_first` e `footer`.
Conteúdo precede a barra lateral no DOM; o `site-footer` recebe a região do
Drupal ou, quando presente, o rodapé social renderizado pelo Portal. A diferença
visual do rodapé social usa a variante do SDC, sem inferir purpose no tema.

**Exceção proposta (padrão, a confirmar):** em Minha conta, a sub-navegação é
navegação, não conteúdo secundário. Se ela ficar depois do formulário no HTML,
quem usa leitor de tela a encontra depois dos campos. Proposta: a
sub-navegação vem antes do conteúdo no HTML, e só se posiciona à esquerda com
CSS. Decidir antes de construir a tela.

**Código:** `page.html.twig` usa o SDC `layout-page` e preserva as regiões
Drupal. P2 mapeia wiki e tópico do fórum para aside; revista, home, catálogo e
mapa de eventos para full; demais rotas caem em stack. Catálogo e produto da
loja aguardam autorização explícita de tarefa; a validação visual permanece
pendente.

## 3. Barra institucional (fixa)

É o topo fixo da página, presente em todos os purposes. Duas linhas no mobile,
uma no desktop.

### 3.1 Desktop (≥ 60rem), da esquerda para a direita

1. Logotipo ACULTA. Link para a página inicial do domínio.
2. Espaço livre, que cresce.
3. Ações, com separadores verticais finos entre os grupos:
   - busca (`magnifying-glass`), que abre o popup de busca;
   - carrinho (`shopping-cart`), com badge numérico;
   - mensagens (`chat-circle`), com badge quando houver;
   - avisos (`bell`), com badge;
   - **"Entrar"** de destaque, só para visitante (`--color-brand-highlight`, texto `--color-on-highlight`);
   - **hambúrguer** (`list`), que abre o menu geral.

Não há seletor "Multidomínio" (removido em 2026-11-05) e não há texto "Minha
conta" nem "Sair" na barra.

### 3.2 Mobile (< 40rem)

- **Linha 1:** logotipo à esquerda; à direita, carrinho, mensagens, avisos,
  hambúrguer e "Entrar" (visitante). Ícones, sem rótulos de conta.
- **Linha 2:** "Domínios ⌄" à esquerda e busca à direita.

### 3.3 Estados

| Estado | Barra |
| --- | --- |
| Visitante | busca, carrinho, mensagens, avisos (se houver), "Entrar" de destaque, hambúrguer |
| Autenticado | busca, carrinho, mensagens, avisos, hambúrguer. Sem "Entrar" |

Mensagens e avisos aparecem só quando o domínio tem o recurso (`messages_url`
e `alerts_url`). Sem o recurso, o ícone não é renderizado.

### 3.4 Alvo de toque

- A Institution Bar usa alvos de 35 px e ícones de 30 px, conforme instrução
  explícita do responsável para esta fase. Demais controles mantêm o mínimo
  geral de 44 px.

### 3.5 Altura e compensação

- A barra é fixa. Sua altura é gravada na variável `--aculta-institution-bar-height`
  pelo `multidomain-bar.js`.
- O conteúdo recebe `scroll-padding-top` com essa altura, para que âncoras e
  foco não fiquem cobertos pela barra.

## 4. Menu hambúrguer

Mesmo comportamento do menu hambúrguer que hoje fica no cabeçalho do domínio
principal. Abre como painel de sobreposição, ancorado ao botão `list`.

### 4.1 Posição e tamanho (**padrão**)

- Largura: `min(20rem, 100vw − 2 × --spacing-sm)`, para caber em 320 px.
- Abre abaixo da barra, alinhado à direita do botão.
- Fecha com Escape, com clique fora e com novo clique no botão.
- Sem animação de movimento quando `prefers-reduced-motion` está ativo.

### 4.2 Conteúdo por estado

**Visitante:**

- "Entrar" como texto comum, sem destaque. Abre o mesmo popup de login.
- Demais itens do menu geral, se houver.

**Autenticado:**

- Cabeçalho, sem clique e sem edição: avatar (do `AvatarResolver`, sem inicial)
  e nome de usuário (`aculta_account_name`). Sem nome, só o avatar.
- Logo abaixo, já expandidos, sem recolher: "Minha conta" e "Meu perfil".
- Separador fino.
- "Sair", por último.

## 5. Faixa do domínio, destaque, ajuda e trilha

- **Faixa do domínio (`header`):** nome da área e navegação do domínio. Nas
  páginas do Studio, mostra "ACULTA Studio" (seção 7).
- **Destaque (`highlighted`):** mensagens de página e avisos de sistema, com o
  componente `status-messages`.
- **Ajuda (`help`):** texto de apoio de uma tela, abaixo do destaque.
- **Trilha (`breadcrumb`):** caminho até a página atual, com o SDC `breadcrumb`.

Essas zonas ocupam a largura total da página, em todas as variantes.

## 6. Variantes do `layout-page`

Um único SDC com a prop `variant` em enum fechado: `stack`, `aside` e `full`.
Valor inválido ou ausente cai em `stack` (ADR-048, 1 e 2). O tema escolhe a
variante em `preprocess_page`; o Portal não envia chave de layout.

| Variante | Abaixo de 60rem | A partir de 60rem |
| --- | --- | --- |
| `stack` | Uma coluna | Uma coluna, com container estreito configurável |
| `aside` | Conteúdo; barra lateral depois do conteúdo, em coluna única | Duas colunas: barra lateral de 16rem à esquerda, conteúdo à direita |
| `full` | Uma coluna, largura total | Uma coluna, sem limite de largura de leitura |

Observações:

- **`aside`:** a barra lateral fica à esquerda a partir de 60rem. Abaixo disso,
  vem depois do conteúdo. O DOM preserva a leitura conteúdo → aside; o CSS
  coloca o aside visualmente à esquerda no desktop. Se a barra lateral não tiver
  conteúdo, o layout é o de `stack`, sem coluna vazia. O fallback do shell
  preserva a mesma geometria quando a descoberta SDC estiver indisponível.
- **Medida da coluna de conteúdo:** leitura de 36rem para texto corrido. Somando
  36rem de leitura, 16rem de barra lateral, espaço entre elas e margens, dá
  cerca de 56rem. O breakpoint de 60rem deixa essa folga.
- **Container externo × medida de leitura:** `stack` usa o container estreito
  do tema (`--aculta-narrow-container-width`, 750 px por padrão, ajustável pelo
  slider de largura estreita) e o mesmo valor do fallback
  `.page__main--stack`. A medida de leitura (68–70 ch) fica no conteúdo
  textual, não no container, para não estrangular formulários e tabelas. O
  SDC `layout-narrow` continua sendo a ferramenta específica de medida de
  leitura (68 ch).
- **`full`:** para grades de cartões, catálogos e listas de evento. Cada cartão
  tem sua largura; a página não limita o texto.

### 6.1 Mapeamento tipo de conteúdo → variante (ADR-048, 3)

| Tipo ou área | Variante | Observação |
| --- | --- | --- |
| Página básica, FAQ, aviso | `stack` | |
| Wiki (artigo) | `aside` | Índice na barra lateral. Largura da wiki: até 76rem (V1). |
| Tópico do fórum | `aside` | Categorias na barra lateral. Discussão até 58rem (V1). |
| Revista (edição) | `full` | |
| Evento (lista e mapa) | `full` | |
| Loja (catálogo) | `full` | |
| Loja (produto) | `stack` no mobile; `aside` a partir de 60rem | |
| Studio dashboard | `aside` no desktop; `stack` no mobile | Barra lateral com a navegação do Studio. |
| Minha conta | `aside` no desktop; `stack` no mobile | Barra lateral com a navegação do Studio; sub-navegação dentro do conteúdo. |
| Meu Perfil, Meu trabalho, Green Stash | `stack` | |
| Ganjalab, WE Sativa, Culturas | `aside` | |

## 7. Telas do Studio: blocos por zona

### 7.1 Dashboard (`aside`)

- **Barra lateral:** navegação do Studio, vertical, com "Studio Dashboard",
  "Experiências", "Meus cursos", "Meu Perfil" e "Minha conta" (ícones `house`,
  `stack`, `graduation-cap`, `user` e `gear`).
- **Conteúdo:**
  1. cartão de identidade: avatar, nome e "Ver perfil público" (`arrow-square-out`);
  2. "Suas experiências", com grade de cartões de suíte (Marley, Ganjalab,
     WE Sativa, Culturas, Green Stash). Duas colunas a partir de 40rem, uma
     coluna abaixo disso;
  3. "Seus cursos", com cartões de progresso;
  4. cartão "Minha conta", com link para a tela de conta.
- **Mobile:** a barra lateral vira painel, aberto pelo botão `list` da faixa do
  domínio. O conteúdo fica em uma coluna.

### 7.2 Minha conta (`aside`)

- **Barra lateral:** navegação do Studio, como no dashboard.
- **Conteúdo, em duas partes:**
  1. sub-navegação vertical com as nove seções, dentro do conteúdo, à esquerda
     do formulário;
  2. cartão "Dados cadastrais (privados)", com os campos em coluna.
- **Duas colunas de navegação nessa tela** (decisão de 2026-11-05). A regra
  geral do Studio é uma coluna de navegação; esta tela é a exceção.
- **Mobile:** a sub-navegação vira uma linha horizontal com rolagem no topo do
  conteúdo. A navegação do Studio vira painel, como no dashboard.
- **Privacidade:** `noindex` por rota e sem cache público (ADR-048, 8).

### 7.3 Meu Perfil (`stack`)

- Cartão com duas áreas: à esquerda, avatar e "Ver perfil público"; à direita,
  o formulário "Editar perfil público".
- A partir de 60rem, as duas áreas ficam lado a lado. Abaixo disso, em coluna única.
- O avatar abre o popup de foto (seção 8.2).

### 7.4 Suítes (Marley, Ganjalab, WE Sativa, Culturas, Green Stash)

- Abas horizontais no topo da página, abaixo da faixa do domínio.
- A aba ativa tem fundo `--color-brand-highlight` e texto `--color-on-highlight`.
- O corpo da suíte segue a variante da tabela da seção 6.1.

## 8. Popups

Todos usam `<dialog>` nativo com template na página, abertos e preenchidos pelo
tema, como o login da V2 (ADR-048, 10).

### 8.1 Popup de login

- Abre pelo "Entrar" da barra ou pelo "Entrar" do menu.
- Centralizado, com largura de referência de 288, 358 e 440 px (roadmap da V2).
- Fecha pelo botão de fechar, por clique no fundo e pela tecla Escape.
- Depois do login, fecha e a página recarrega. Os dois "Entrar" somem.

### 8.2 Popup de foto de perfil

- Abre pelo avatar, em "Meu Perfil" ou na área de conta.
- Largura de `min(560px, 100vw − 2rem)` e altura máxima de 75vh, com rolagem
  interna (medidas da V1, `dialog.css`).
- Conteúdo, de cima para baixo: prévia da foto atual, texto de orientação,
  campo "Foto", linha de ações ("Sortear novo avatar", "Remover foto",
  "Salvar foto do perfil").
- Ao enviar um arquivo, abre sozinho o recorte "Recortar foto", com "Aplicar
  recorte" e "Cancelar".
- Mecanismo de envio e sorteio: Fetch próprio no `<dialog>`, por um único módulo
  JavaScript (ADR-049). Sem `Drupal.ajax`.

### 8.3 Regras comuns dos popups

- Foco vai para o primeiro campo ao abrir e volta ao gatilho ao fechar.
- Escape fecha. Clique no fundo também fecha.
- Cabeçalho com título e botão de fechar com alvo de toque adequado.
- Sem JavaScript, o gatilho é um link para a página equivalente.

## 9. Largura, medidas e rolagem

| Largura (CSS px) | Comportamento |
| --- | --- |
| 320 | Base. Uma coluna. Barra em duas linhas. Sem rolagem horizontal. |
| 390 | Igual a 320, com mais espaço. |
| 640 (40rem) | Cartões em duas colunas. |
| 768 | Barra em uma linha se houver espaço; Meu Perfil em coluna única. |
| 959 | Último tamanho antes de `aside` em duas colunas. |
| 960 (60rem) | `aside` em duas colunas. |
| 961 | Primeiro tamanho com `aside` em duas colunas, para validar o limite. |
| 1440 | Largura de referência do desktop. |

- Validar em 959, 960 e 961 px, e em zoom de 200% e 400%.
- Sem rolagem horizontal da página em nenhum desses casos.
- Claro e escuro em todas as capturas. O modo vem só de `data-theme`
  (ADR-046). Não há `prefers-color-scheme`.

## 10. Tokens de espaço e forma

Usar os tokens semânticos do tema. Não criar valores fixos onde já há token.

| Uso | Token |
| --- | --- |
| Alvo mínimo de toque | `--tap-target-min` (44px; ver seção 3.4 para o conflito) |
| Raio de cartão e botão | `--radius-base` |
| Raio pequeno (campo, ícone) | `--radius-sm` |
| Sombra de painel e popup | `--shadow-raised` |
| Fundo da barra institucional | `--color-surface-institution` |
| Fundo de cartão | `--color-surface-raised` |
| Destaque (Entrar, aba ativa, item ativo) | `--color-brand-highlight`, com `--color-on-highlight` |
| Ação de cartão | `--color-brand-primary`, com `--color-on-brand` |
| Texto | `--color-text-primary` |
| Espaço entre itens | `--spacing-xs` e `--spacing-sm` |
| Fonte de títulos | `--font-family-heading` (Oswald) |
| Fonte de texto | `--font-family-base` (Inter) |

Os valores de cada token estão em `src/styles/tokens.css`.

## 11. Acessibilidade

- Landmarks: `banner` (barra e faixa do domínio), `main` (conteúdo),
  `complementary` (barra lateral, só com conteúdo), `contentinfo` (rodapé).
- Link para pular ao conteúdo, apontando para `#main-content`, no topo da
  página. Aparece com o foco do teclado.
- Foco visível em todos os controles, com contorno de 3px em `--color-brand-highlight`.
- Aba ativa e item ativo com `aria-current="page"`.
- Hambúrguer com `aria-expanded` e `aria-controls`.
- Popups com foco preso enquanto abertos e retorno do foco ao gatilho.
- Nada transmitido só por cor. Badges têm número ou texto.
- Animações respeitam `prefers-reduced-motion`.

## 12. Como a variante chega ao HTML

1. `preprocess_page` no tema escolhe a variante pelo tipo de conteúdo e pela área (seção 6.1).
2. `page.html.twig` renderiza `layout-page` com `variant` e passa as regiões nos
   slots `header`, `highlighted`, `help`, `breadcrumb`, `content`, `aside` e `footer`.
3. O SDC `layout-page` aplica o grid da variante em CSS, sem JavaScript.
4. Variante inválida cai em `stack`.

Nenhuma região do Drupal muda de nome nesta fase, e nenhum bloco é remapeado
(ADR-048, 3).

## 13. Ordem de entrega

Pela ADR-048, seção 4:

- **P1:** `layout-page` com as três variantes, stories e refatoração do
  `page.html.twig` sem mudar regiões. Código e gates concluídos; capturas e
  landmarks visuais pendentes por falta de `DISPLAY`.
- **P2:** mapeamento de tipos e rotas públicas implementado; rotas de loja
  aguardam autorização e validação visual.
- **P3:** área do Studio e de Minha conta, com `noindex` conferido.
- **P4:** demais áreas (evento, LMS, FAQ).
- **P5:** revisão de Layout Builder, só se o responsável pedir.

Cada fase termina em PR própria, com `RUNTIME STATUS: DEFERRED` enquanto o
código não tiver sido testado no servidor de desenvolvimento.

## 14. Verificação

- Gates do tema, conforme [gates.md](gates.md), no Homelab.
- Capturas em 320, 390, 768, 959, 960 e 1440 px, claro e escuro, zoom de 200%
  e 400%, em `$SUITE_OUT`, fora do repositório.
- Captura lado a lado V1 × V2 nas telas de wiki e fórum (acabamento).
- Teclado: pular para o conteúdo, hambúrguer, popups e abas.
- Visitante e autenticado: os dois "Entrar" somem após o login.
- Sem rolagem horizontal da página em 320 px.

## 15. Em aberto

Detalhes em [studio-estrutura.md](studio-estrutura.md), seção 5. Os que afetam o layout:

1. Como trocar de domínio sem o seletor "Multidomínio".
2. Alvo mínimo de toque da barra: 44 px (ADR-047) ou 35 px (commit `85e4a1d5`).
3. Sub-navegação de Minha conta antes do conteúdo no HTML (seção 2).
4. Largura mínima da barra logada em 320 px: medir depois da implementação.
