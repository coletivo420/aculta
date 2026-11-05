# Especificação visual: ACULTA Studio (dashboard, conta, suítes)

- **Status:** especificação de leitura para o agente que construir o layout. Não é código. Nenhum valor de cor foi copiado das imagens: as cores são dadas como token.
- **Data:** 2026-11-05.
- **Fonte:** três imagens do material visual de referência ("Estruturas pessoais e editoriais", "Studio Dashboard" com versão mobile, e "ACULTA Studio" com as suítes). As telas são propostas com dados ilustrativos.
- **Decisões que governam este documento:** [ADR-048](../../../../../docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md) (layouts e breakpoints), [ADR-047](../../../../../docs/decisions/ADR-047-menu-de-conta-na-barra-institucional.md) (menu de conta), [ADR-046](../../../../../docs/decisions/ADR-046-desenho-tema-v2-shell-e-fundacoes.md) (mobile-first, shell).
- **Relaciona-se com:** [layouts-plan.md](layouts-plan.md), [components.md](components.md), [architecture.md](architecture.md).

## 0. Como ler este documento

1. Os valores de cor são **tokens**, não hexadecimais. Se algo não estiver no `tokens.css`, pare e pergunte antes de criar.
2. Medidas são aproximadas, lidas das imagens. Onde o número importa, ele está marcado como **regra**; o resto é **referência visual**.
3. Textos entre aspas são rótulos da tela. Dados (nomes, e-mails, cursos) são ilustrativos e vêm do Portal.
4. A seção 9 lista o que o material **não resolve** e precisa de decisão antes de implementar.

## 1. Regras gerais (valem para todas as telas do Studio)

- **Variante de layout:** conforme ADR-048. Dashboard, Minha conta e Ganjalab/WE Sativa/Culturas usam `aside` no desktop; o restante usa `stack`. Veja a tabela da seção 4.
- **Breakpoints:** base 320 CSS px, uma coluna. `40rem` (640 px): grade de cartões em duas colunas. `60rem` (960 px): variante `aside` em duas colunas. Nada de `max-width` como estratégia.
- **Tipografia:** títulos de página e nomes de suíte em **Oswald** (`--font-family-heading`), em caixa alta nas suítes (GANJALAB, WE SATIVA, CULTURAS, GREEN STASH). Corpo, rótulos e botões em **Inter** (`--font-family-base`).
- **Alvo de toque:** mínimo `--tap-target-min` (44 px) em botões, abas, itens de navegação, ícones de ação e linhas de tabela no mobile.
- **Foco:** anel de 3 px em `--color-brand-highlight` com 2 px de deslocamento, em todo elemento interativo (é o padrão do tema).
- **Sem ícone sozinho sem nome:** todo botão só com ícone tem `aria-label` (ex.: sino, carrinho, busca, ⋯).
- **Sem rolagem horizontal da página** em 320 px e 200% de zoom. Tabelas rolam dentro do próprio container.

## 2. Cabeçalho global (todas as telas logadas)

### 2.1 Desktop (≥ 60rem)

Duas faixas empilhadas no topo da página.

**Faixa 1 (institucional), fundo `--color-surface-institution`:**
- Esquerda: logotipo ACULTA (wordmark), separador vertical fino, dropdown **"Multidomínio ⌄"** (`caret-down`).
- Direita, nesta ordem, com separadores verticais finos entre os grupos:
  1. `magnifying-glass` — busca.
  2. `shopping-cart` — carrinho, com **badge numérico** (exemplo: 1). Ícone `shopping-cart`.
  3. `chat-circle` — mensagens. Pode ter badge.
  4. `bell` — avisos, com **badge** (exemplo: 4). Badge com `--color-status-error-text` ou o token de destaque de contagem já usado pelo `alerts-trigger`.
  5. `user` + texto **"Minha conta"**.
  6. `sign-out` + texto **"Sair"**.
- Essa faixa é a barra institucional (`multidomain-bar`), que já existe. **Não redesenhar** (ADR-046); o item de conta é o `account-menu` (ADR-047).

**Faixa 2 (contexto do Studio), fundo de superfície elevada ou institucional:**
- Esquerda: nome da área, **"ACULTA Studio"** (ver seção 9, nome confirmado).
- Direita: nada na faixa; as ações ficam no conteúdo. (No material, a faixa 2 mostra abas horizontais; isso fica só nas suítes, ver seção 5.)

### 2.2 Mobile (< 40rem)

Duas linhas, de acordo com o celular do material:

- **Linha 1:** logotipo ACULTA à esquerda; à direita, `shopping-cart` (com badge), `chat-circle`, `bell` (com badge), e os rótulos "Minha conta" e "Sair". **Regra proposta:** abaixo de 40rem, "Minha conta" e "Sair" viram só ícones (`user` e `sign-out`), com `aria-label`, para caber em 320 px sem quebrar. O material mostra os rótulos; esta redução é uma proposta a confirmar (seção 9).
- **Linha 2:** `list` (menu, hambúrguer) à esquerda; logotipo ACULTA; **"Domínios ⌄"** (`caret-down`) à direita; `magnifying-glass` à direita da linha.
- O menu `list` abre a navegação do Studio (seção 3.2), no mesmo padrão de navegação do desktop, mas como painel de sobreposição.

## 3. Navegação do Studio

### 3.1 Desktop: navegação vertical à esquerda (decidido, ADR-048)

- Coluna fixa à esquerda, **largura de referência 16rem**, fundo `--color-surface-institution`, separada do conteúdo por um filete.
- Itens, de cima para baixo, com ícone duotone à esquerda e rótulo:

| Ordem | Rótulo | Ícone (Phosphor duotone) | Estado ativo |
| --- | --- | --- | --- |
| 1 | Studio Dashboard | `house` | pílula de fundo verde-claro (`--color-status-ok-surface`) com texto `--color-text-primary` e peso 600 |
| 2 | Experiências | `stack` | normal |
| 3 | Meus cursos | `graduation-cap` | normal |
| 4 | Meu Perfil | `user` | normal |
| 5 | Minha conta | `gear` | normal |

- Cada item tem altura mínima de 44 px e preenchimento horizontal de `--spacing-base`. Ícone de 20 a 24 px.
- Estado ativo: marcar com `aria-current="page"`, nunca só pela cor.
- O item "Ajuda" do material **não** está nessa lista. Ver seção 9.

### 3.2 Mobile: menu do hambúrguer

- Ao tocar em `list`, abre um painel com os mesmos cinco itens da tabela acima, em lista de uma coluna, alvos de 44 px.
- Fecha com Escape e devolve o foco ao botão `list`. Sem JavaScript, o item pode aparecer como lista expandida dentro do cabeçalho (padrão `details`). Decisão de implementação: o `navigation-bar` já tem esse padrão de drawer; reutilizar.

### 3.3 Suítes (abas horizontais, não a navegação vertical)

- As suítes (Marley, Ganjalab, WE Sativa, Culturas, Green Stash) ficam como **abas horizontais no topo da página**, abaixo da faixa 2, em uma linha. Na imagem de cada suíte, a aba ativa é um botão de fundo `--color-brand-highlight` com texto `--color-on-highlight`.
- Ícones das suítes nas abas do site público (referência): `book-open` (Marley), `flask` (Ganjalab), `plant` (WE Sativa), `users` (Culturas), `package` (Green Stash). Na aba, o ícone é opcional; o nome sozinho já identifica.
- Mobile: abas com rolagem horizontal dentro da linha, sem quebrar a página.

## 4. Telas

Para cada tela: variante (ADR-048), posição, componentes sugeridos e comportamento móvel.

### 4.1 Studio Dashboard

- **Variante:** `aside` (desktop), `stack` (mobile).
- **Desktop:**
  - Esquerda: navegação vertical (seção 3.1), com "Studio Dashboard" ativo.
  - Conteúdo, de cima para baixo:
    1. **Título** "Studio Dashboard" (H1, Oswald). Logo abaixo, um **traço curto em `--color-brand-highlight`** (cerca de 3 rem de largura, 4 px de altura) como acento. É a marca de título do material.
    2. **Cartão de identidade**, largura total do conteúdo, borda fina, fundo `--color-surface-raised`, padding de `--spacing-lg`. Dentro, à esquerda: **avatar** (72 a 88 px, da Portal, `aculta_account_avatar`); ao lado: **"Olá, <apelido>"** (Oswald, grande) e o **e-mail mascarado** (ex.: `felm…@…`), em `--color-text-secondary`. À direita, separado por um filete vertical: botão de contorno **"Meu Perfil ↗"** (`user` + `arrow-square-out`) com a legenda "Seu perfil público na ACULTA" em texto menor.
    3. **Seção "Suas experiências"**: subtítulo "Acesse as experiências da ACULTA e explore conteúdos, projetos e comunidades." Grade de **quatro cartões** (uma coluna em mobile, duas em 40rem, quatro em desktop largo; ver nota). Cada cartão: ícone grande no topo à esquerda (`book-open`, `flask`, `plant`, `users`), nome em negrito (Oswald), descrição em duas linhas em `--color-text-secondary`, e botão de largura total **"Acessar →"** (`arrow-right`) em verde escuro (ação primária do cartão, ver seção 6).
    4. **Faixa "Green Stash"**: largura total, fundo de superfície verde-claro (`--color-status-ok-surface`), ícone `package` à esquerda, nome "Green Stash", selo "Acesso integrado" em texto verde (`--color-status-ok-text`), e a descrição "Seu stash de conteúdos da ACULTA, integrado às experiências." Não é um botão; é informação.
    5. **Seção "Seus cursos"**: subtítulo "Continue seus estudos na ACULTA." Duas cartas lado a lado (uma coluna em mobile). Cada carta: nome do curso (Oswald), selo de situação "Em andamento" (fundo `--color-status-warning-surface`, texto `--color-status-warning-text`), barra de progresso **com texto** (ex.: "40%") e botão **"Continuar curso →"** em verde escuro. A barra usa `--color-status-ok-text` sobre trilho cinza-claro.
    6. **Cartão "Minha conta"**: largura total, ícone `gear` em um bloco quadrado claro à esquerda, título "Minha conta", subtítulo "Dados, segurança e preferências", e `arrow-right` à direita.
- **Mobile (< 40rem):** navegação no menu (seção 3.2). Conteúdo em coluna única:
  - Cartão de identidade empilhado: avatar e "Olá, <apelido>" lado a lado; e-mail abaixo; botão "Meu Perfil ↗" em largura total com a legenda.
  - "Suas experiências" vira **lista**, não grade: cada linha tem ícone à esquerda (36 a 40 px), nome e descrição no meio, e botão compacto **"Acessar →"** à direita (verde escuro, altura 44 px).
  - Faixa "Green Stash" em largura total, mesmo estilo.
  - "Seus cursos": cartas empilhadas, botão "Continuar curso →" em largura total na linha inferior.
  - "Minha conta": linha com `gear`, título, subtítulo e `arrow-right`.

**Nota sobre colunas da grade de experiências:** o material mostra quatro colunas com a navegação à esquerda. Com a navegação de 16 rem, a área de conteúdo a 960 px fica perto de 640 px, o que dá duas colunas. Use `repeat(auto-fit, minmax(14rem, 1fr))` e deixe a contagem depender da largura disponível; não fixe quatro colunas.

### 4.2 Meu Perfil (edição do perfil público)

- **Variante:** `stack` (desktop e mobile).
- **Título:** "Meu Perfil" (H1). Abaixo, um cartão com borda fina.
- **Desktop, dentro do cartão, duas áreas lado a lado:**
  - Esquerda, coluna estreita: **avatar** (cerca de 140 a 160 px, com cantos arredondados de `--radius-base`) e, abaixo, botão de contorno **"Ver perfil público ↗"** (`arrow-square-out`).
  - Direita, coluna larga: título do bloco "Editar perfil público" (Oswald), depois o formulário, com rótulo à esquerda e campo à direita:
    - **Apelido público*** (campo de texto, obrigatório). Este é o nome que aparece no site.
    - **Biografia** (área de texto, altura de três a quatro linhas).
    - **Redes sociais** (campo de URL).
    - **Visibilidade** (lista de seleção com `caret-down`): "Público (visível para todas as pessoas)".
    - Botão **"Salvar"** em amarelo (ação primária do formulário, ver seção 6), alinhado à esquerda, abaixo do formulário.
- **Mobile:** avatar centralizado no topo; botão "Ver perfil público" abaixo do avatar; formulário em coluna única, com rótulo **acima** do campo (proposta: o material não mostra mobile para esta tela).

### 4.3 Minha conta (dados cadastrais)

- **Variante:** `aside` (desktop) com a sub-navegação da conta; `stack` (mobile). **Decisão pendente**, ver seção 9: a imagem mostra a sub-navegação vertical à esquerda, mas a navegação do Studio também fica à esquerda no dashboard.
- **Título:** "Minha conta" (H1).
- **Desktop:**
  - Sub-navegação vertical à esquerda, dentro da área de conteúdo (não a navegação do Studio), com estes itens, nesta ordem: **Dados cadastrais**, Endereço, Documentos, E-mail e segurança, Senha, Contas conectadas, Meus pedidos, Meu apoio, Preferências. Item ativo: fundo `--color-brand-highlight`, texto `--color-on-highlight`, peso 600, `aria-current="page"`.
  - Painel à direita, cartão com título **"Dados cadastrais (privados)"** (Oswald). O rótulo "(privados)" é texto fixo: informa que a tela não é pública.
  - Campos, em duas colunas de rótulo e campo, com asterisco nos obrigatórios:
    - Nome completo* (texto)
    - E-mail* (texto)
    - Telefone (texto, máscara a definir)
    - Data de nascimento (data, com ícone `calendar-blank` no campo)
  - Botão **"Salvar"** amarelo, abaixo, alinhado à esquerda.
- **Mobile:** a sub-navegação vira uma **lista horizontal com rolagem** no topo do conteúdo, com o item ativo marcado. Abaixo, o formulário em coluna única, com rótulo acima do campo.

### 4.4 Meu trabalho (Marley: revista e galeria)

- **Variante:** `stack`.
- **Faixa de suíte:** abas da suíte (Marley ativo) na faixa 2, e, logo abaixo, uma linha de **abas de tipo**: **Revista** (ativa, fundo amarelo) e **Galeria**.
- **Título:** "Meu trabalho" (H1). Abaixo, botão **"+ Criar"** amarelo (ação primária; o ícone `plus` pode ser usado se existir; confirmar).
- **Abas de situação**, em linha: **Rascunhos** (ativa, fundo verde-claro, borda), Publicados, Arquivados, Comentários e moderação.
- **Tabela**, dentro de um cartão com borda, colunas nesta ordem:
  - **Título** (texto, largura maior).
  - **Status** (selo: "Rascunho" em cinza; "Publicado" em verde; "Em revisão" em amarelo).
  - **Atualização** (data e hora, `dd/mm/aaaa hh:mm`).
  - **Ações**: links "Editar" e "Excluir" separados por `|`. "Excluir" usa a cor de erro (`--color-error`) e pede confirmação em diálogo.
- **Mobile:** a tabela vira **lista de cartões**, um por linha: título em negrito, selo de status, data, e as ações como botões de largura parcial. Cabeçalhos de coluna somem, com rótulo inline ("Status", "Atualizado em").
- Componentes: `tabs` (a criar), `badge` (selo), `data-table` (a criar, com versão em cartão).

### 4.5 Ganjalab (estudante e educador)

- **Variante:** `aside` (desktop); `stack` (mobile).
- **Faixa de suíte:** GANJALAB ativo; subtítulo "Aprendizagem e formação".
- **Seletor de papel**: segmentado, **Estudante** (ativo, amarelo) e **Educador**, logo abaixo do subtítulo. Esse seletor define qual painel está visível; o Portal decide quais papéis a pessoa tem.
- **Coluna principal (estudante):**
  - Cartão **"Meu curso em andamento"**: miniatura à esquerda (imagem do curso), título do curso em negrito, linha "Módulo 2 de 5 • Em progresso", **barra de progresso com texto (40%)**, e botão **"Continuar"** amarelo à direita.
  - Cartão **"Minha jornada"**: lista de lições numeradas, com marcador à esquerda: **concluída** (ícone `check-circle`, verde), **atual** (ícone de círculo com seta ou `circle` preenchido, em destaque de fundo amarelo-claro na linha), **pendente** (`circle` vazio). Texto de cada lição: "Lição 1 – Contexto e histórico", etc.
- **Coluna lateral (educador), à direita no desktop:**
  - Título "Educador • Meu espaço", seletor segmentado com **Educador** ativo.
  - Subseção "Meus cursos" com botão **"+ Novo curso"** amarelo no canto superior direito.
  - Lista de cursos: nome, selo de situação ("Publicado" verde, "Rascunho" cinza) e botão de ação em linha (⋯ com `dots-three`, `aria-label` "Ações do curso").
- **Mobile:** coluna lateral empilhada abaixo da principal; o seletor de papel continua no topo.
- Componentes: `progress` (a criar, com texto), `badge`, `button`, `status-messages` quando não há cursos (`empty-state`, a criar).

### 4.6 WE Sativa (contribuições da wiki)

- **Variante:** `aside` (desktop); `stack` (mobile).
- **Faixa de suíte:** WE SATIVA ativo; subtítulo "Wiki colaborativa".
- **Coluna principal, cartão "Contribuições":**
  - Tabela com colunas **Título**, **Situação** (selo: "Publicado" verde, "Em revisão" amarelo, "Rascunho" cinza) e **Ações** (botão de contorno "Abrir" e ⋯).
- **Coluna lateral, cartão "Editar contribuição":**
  - Campo **Título** (texto, valor preenchido).
  - **Barra de ferramentas do editor**: parágrafo (seletor "Parágrafo ⌄"), negrito (`text-b`, se existir; senão confirmar), itálico, lista com marcadores, lista numerada, link, imagem. **O editor é o Drupal (CKEditor 5);** o tema só estiliza a barra e a área de texto, não substitui o editor.
  - Área de texto com o conteúdo.
  - Botões no rodapé: **"Salvar"** amarelo e **"Cancelar"** de contorno.
- **Mobile:** o cartão de edição abaixo da tabela, em largura total. Ações "Salvar" e "Cancelar" lado a lado, cada uma com pelo menos 44 px de altura.
- Componentes: `status-messages`, `badge`, `button`. O editor é do Drupal; não criar componente de editor.

### 4.7 Culturas (eventos, locais e organizações)

- **Variante:** `aside` (desktop); `stack` (mobile).
- **Faixa de suíte:** CULTURAS ativo; subtítulo "Eventos, locais e organizações".
- **Seletor de seção** segmentado: **Eventos** (ativo, amarelo), **Locais**, **Organizações**.
- **Coluna principal:**
  - Botão **"+ Criar evento"** amarelo no topo.
  - Título de bloco "Próximos eventos" (Oswald).
  - Lista de eventos. Cada linha: ícone `calendar-blank` à esquerda, título em negrito, linha "20 mar 2025 • São Paulo, SP" (data e cidade/UF), e botão de contorno **"Abrir"** à direita.
- **Painel "Selecionar local"** (à direita no desktop):
  - Cabeçalho com título "Selecionar local" e botão `x` para fechar (`aria-label` "Fechar seleção de local").
  - Campo de busca com `magnifying-glass` à esquerda do campo, placeholder "Buscar local por nome ou cidade".
  - Lista de locais com **botão de rádio** à esquerda de cada item: nome em negrito e cidade/UF abaixo.
  - Rodapé com **"Cadastrar local"** (contorno, à esquerda) e **"Selecionar"** amarelo (à direita).
- **Mobile:** o painel de seleção **vira diálogo** em tela cheia (`<dialog>` nativo, como o login), abrindo a partir de quem chamou. Não fica como coluna empilhada. Essa mudança é de comportamento do componente, a confirmar (seção 9).
- Componentes: `tabs` ou seletor segmentado, `button`, `badge`, `dialog` (a criar).

### 4.8 Green Stash (gestão da loja)

- **Variante:** `stack`.
- **Faixa de suíte:** GREEN STASH ativo; subtítulo "Gestão da loja".
- **Abas de seção** em linha: **Visão geral** (ativa, amarela), **Produtos**, **Envios**.
- **Cartão "Produtos":**
  - Tabela com colunas **Nome**, **Situação** (selo "Publicado" verde, "Rascunho" cinza), **Ações** (botão "Abrir" de contorno e ⋯).
- **Rodapé do cartão:** linha com ícone `info` e o texto "Acesso conforme permissões". Mostra que a tela depende de permissão; a regra é do Portal.
- **Mobile:** tabela vira lista de cartões (como em 4.4).
- Componentes: `tabs`, `data-table`, `badge`, `button`, `status-messages` (o aviso de permissão).

## 5. Padrões que se repetem (para não reinventar)

| Padrão | Onde aparece | Regra |
| --- | --- | --- |
| Título de página (H1) | todas | Oswald, grande, com traço amarelo curto abaixo, só no Studio Dashboard; nas demais, sem traço |
| Nome da suíte (faixa 2) | suítes | Oswald, caixa alta, com as abas horizontais abaixo |
| Seletor segmentado | Ganjalab (papel), Culturas (seção), Green Stash (seção) | Um item ativo em amarelo; o conteúdo muda conforme o item |
| Abas de situação | Meu trabalho | Mesmo padrão de abas; pílula ativa em verde-claro |
| Tabela com ações | Meu trabalho, WE Sativa, Green Stash | No mobile, vira cartões |
| Cartão de seção | todas | Borda fina, fundo `--color-surface-raised`, padding `--spacing-lg` |
| Selo de situação | tabelas, cursos | Verde = publicado/concluído; cinza = rascunho; amarelo = em revisão ou em andamento |
| Barra de progresso | Dashboard, Ganjalab | Sempre com texto de percentual; nunca só cor |
| Botão com seta | "Acessar →", "Continuar curso →" | Verde escuro, `arrow-right` à direita |

## 6. Botões: variantes que o material exige

O material usa **três** aparências de botão. Confira no `button.component.yml` antes de implementar:

| Aparência no material | Exemplos | Mapeamento sugerido |
| --- | --- | --- |
| **Amarelo** (fundo `--color-brand-highlight`, texto `--color-on-highlight`) | Salvar, Criar, + Criar evento, Continuar (Ganjalab), Selecionar, Novo curso | ação primária de formulário e de criação |
| **Verde escuro** (fundo `--color-brand-primary`, texto `--color-on-brand`) | Acessar →, Continuar curso → | ação primária de cartão |
| **Contorno** (borda `--color-brand-primary`, fundo transparente) | Abrir, Ver perfil público, Meu Perfil ↗, Cadastrar local, Cancelar | ação secundária |

**Lacuna:** o `button` atual tem `primary`, `secondary`, `outline` e `ghost`. Não está claro se `primary` é amarelo ou verde. Antes de construir, confirmar qual é qual, e se falta a variante amarela (`highlight`). Não criar botão novo sem registrar no `components.md`.

## 7. Tokens por função

Use o token que descreve a função. Os valores estão em `src/styles/tokens.css`.

| Função visual | Token |
| --- | --- |
| texto principal, títulos | `--color-text-primary` |
| texto secundário (descrições, e-mail) | `--color-text-secondary` |
| texto de apoio (legendas) | `--color-text-muted` |
| verde da marca, botão verde escuro, traço do título | `--color-brand-primary` |
| amarelo de destaque (aba ativa, botão amarelo, traço) | `--color-brand-highlight` |
| texto sobre amarelo | `--color-on-highlight` |
| texto sobre verde | `--color-on-brand` |
| faixa institucional e navegação lateral | `--color-surface-institution` |
| cartões e painéis | `--color-surface-raised` |
| fundo da página | `--color-canvas` |
| bordas | `--color-border` |
| selo "publicado", "concluído", barra de progresso | `--color-status-ok-text` (texto), `--color-status-ok-surface` (fundo) |
| selo "em andamento", "em revisão" | `--color-status-warning-text` (texto), `--color-status-warning-surface` (fundo) |
| selo "rascunho" | cinza de superfície: `--color-surface` com `--color-text-muted`; confirmar token neutro |
| ação de excluir, erro | `--color-error`, `--color-status-error-text` |
| sombra de cartão | `--shadow-raised` |
| raio de cartão e botão | `--radius-base` (cartão), `--radius-sm` (chips) |

Regras: nada de cor fixa no componente; nada de `prefers-color-scheme`; o modo escuro vem pelos mesmos nomes de token.

## 8. Ícones por função (Phosphor duotone, todos existem no tema)

| Função | Ícone |
| --- | --- |
| Menu (mobile) | `list` |
| Busca | `magnifying-glass` |
| Carrinho | `shopping-cart` |
| Mensagens | `chat-circle` |
| Avisos | `bell` |
| Minha conta | `user` |
| Sair | `sign-out` |
| Domínios, menus suspensos | `caret-down` |
| Studio Dashboard | `house` |
| Experiências | `stack` |
| Meus cursos | `graduation-cap` |
| Meu Perfil | `user` |
| Configurações / Minha conta (cartão) | `gear` |
| Marley | `book-open` |
| Ganjalab | `flask` |
| WE Sativa | `plant` |
| Culturas | `users` |
| Green Stash | `package` |
| Link externo (perfil público) | `arrow-square-out` |
| Seta de ação | `arrow-right` |
| Data | `calendar-blank` |
| Lição concluída | `check-circle` |
| Lição pendente / atual | `circle` |
| Ações de linha | `dots-three` |
| Fechar diálogo | `x` |
| Informação | `info` |
| Editar | `pencil` |
| Excluir | `trash` |

Tamanhos: 20 a 24 px na navegação e nos botões; 36 a 40 px nos ícones de cartão de experiência; 28 a 32 px nos ícones de cartão de conta.

## 9. Pendências e incoerências do material (decidir antes de implementar)

1. **Navegação das telas além do dashboard.** A imagem do dashboard mostra navegação vertical à esquerda. As imagens de Meu Perfil, Minha conta e Meu trabalho mostram **abas horizontais no topo** com as mesmas seções. A decisão (ADR-048) fixou a vertical no dashboard. **Falta decidir o padrão das demais telas do Studio.** Recomendação: vertical à esquerda em todas as telas pessoais (dashboard, perfil, conta), e abas horizontais só nas suítes.
2. **Sub-navegação da conta.** A conta tem a lista de nove seções à esquerda, dentro do conteúdo. Se a navegação do Studio também estiver à esquerda, a tela fica com duas colunas de navegação. Recomendação: manter a navegação do Studio à esquerda e transformar as seções da conta em **abas horizontais** no topo do conteúdo. Isso muda a seção 4.3; é preciso confirmar.
3. **Rótulos "Minha conta" e "Sair" no mobile.** O material os mostra no celular, mas em 320 px podem não caber. Proposta: só ícone, com `aria-label`. Confirmar.
4. **Item "Ajuda".** Aparece nas abas do material, mas não está na navegação do dashboard nem no ADR. Incluir ou não?
5. **Rota do Studio.** A URL do material é `/studio`. O ADR-038 diz que todos os caminhos `/studio/*` respondem 404, e que cada suíte tem caminho próprio (`/marley`, `/ganjalab`, `/we-sativa`, `/culturas`, `/green-stash`). **O caminho da tela inicial do Studio ainda não foi definido.** Isso é decisão do responsável e de quem cuida das rotas.
6. **Domínio na imagem:** a imagem mostra `aculta.org.br`. O projeto usa `aculta.org`. Não usar o domínio da imagem em nenhum texto ou link.
7. **Nome:** "ACULTA Studio" (ADR-048). A imagem mostra "ACULTA Drupal Studio" e "Multidomínio". Usar "ACULTA Studio". "Multidomínio" aparece no cabeçalho desktop; confirmar se fica.
8. **Dados privados:** "Dados cadastrais (privados)" inclui telefone e data de nascimento. Antes de implementar esses campos, confirmar com o responsável se são necessários, e tratar a tela como privada (`noindex`, sem cache público, ADR-048).
9. **Culturas no mobile:** o painel "Selecionar local" vira diálogo em tela cheia (proposta da seção 4.7). Confirmar.
10. **Papel Estudante/Educador:** o seletor depende do Portal informar os papéis da pessoa. Confirmar a origem do dado com o `aculta_lms`.
11. **Dados ilustrativos:** nomes, datas, cidades e e-mails das imagens são exemplos. Não copiar para fixtures de produção.

## 10. O que o agente deve entregar a partir daqui

- Componentes que **faltam** para as telas: `tabs`, `data-table` (com versão em cartão), `progress` (com texto), `empty-state`, `dialog` (painel de seleção), `studio-nav` (navegação vertical, com versão em painel no mobile), `experience-card`, `form-field` (rótulo, campo, ajuda, erro). Nomes sugeridos; o agente pode propor outros, desde que registre em `docs/components.md`.
- Componentes que **já existem** e devem ser reutilizados: `button`, `badge`, `icon`, `status-messages`, `pagination`, `navigation-bar` (padrão do drawer mobile), `breadcrumb`, `card-cultural` (se o cartão de experiência for o mesmo padrão).
- Cada tela entra com story e captura (320, 390, 768, 960, 1440 px; claro e escuro; zoom 200%; teclado), conforme `AGENTS.md` seção 4 e seção 6.
