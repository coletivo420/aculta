# Layout de página (V2)

Descrição do layout que a V2 deve produzir: zonas da página, variantes,
posições na barra institucional, comportamento por largura e o que ainda
está em aberto. As decisões têm origem nas ADRs citadas; o plano de execução
está em [layouts-plan.md](layouts-plan.md).

Fontes: [ADR-046](../../../../../docs/decisions/ADR-046-desenho-tema-v2-shell-e-fundacoes.md),
[ADR-047](../../../../../docs/decisions/ADR-047-menu-de-conta-na-barra-institucional.md),
[ADR-048](../../../../../docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md).

## 1. Zonas da página

Ordem vertical, de cima para baixo:

1. **Barra institucional** (fixa, `multidomain-bar`). Ver seção 3.
2. **Faixa do domínio** (`header`). Nome da área e navegação do domínio.
3. **Destaque** (`highlighted`), quando houver mensagens ou alertas de página.
4. **Ajuda** (`help`) e **trilha de navegação** (`breadcrumb`).
5. **Conteúdo** (`main`), com a barra lateral (`sidebar_first`) quando a variante for `aside`.
6. **Rodapé** (`footer`).

A barra institucional é fixa. O conteúdo recebe compensação de altura
(`scroll-padding-top`) para não ficar coberto ao rolar. Sem JavaScript para isso.

### 1.1 Marcação e landmarks

- `banner`: barra institucional e faixa do domínio.
- `main`: conteúdo principal.
- `complementary`: barra lateral, só quando ela tiver conteúdo.
- `contentinfo`: rodapé.
- No DOM, o conteúdo vem antes da barra lateral. Em largura estreita, a barra
  lateral aparece depois do conteúdo.

## 2. Variantes do `layout-page`

Um único SDC com a prop `variant` em enum fechado. Variante inválida ou ausente
cai em `stack` (ADR-048, 1 e 2).

| Variante | Estrutura | Abaixo de 60rem | A partir de 60rem |
| --- | --- | --- | --- |
| `stack` | Uma coluna | Uma coluna | Uma coluna, com largura de leitura |
| `aside` | Conteúdo + barra lateral | Uma coluna; barra lateral depois do conteúdo | Duas colunas: conteúdo à esquerda, barra lateral à direita |
| `full` | Largura total | Uma coluna | Uma coluna, sem limite de largura de leitura |

Os breakpoints são 40rem (cartões em duas colunas) e 60rem (`aside` em duas
colunas). A base é 320 CSS px, mobile first (ADR-048, 4).

A proposta de 60rem vem do cálculo das colunas: leitura de 36rem, barra lateral
de 16rem, espaço e margens, cerca de 56rem. Validar em 959, 960 e 961 px e em
zoom de 200%.

## 3. Barra institucional

### 3.1 Desktop (a partir de 60rem)

Da esquerda para a direita:

1. Logotipo ACULTA.
2. Separador, e "Multidomínio ⌄" (a manter ou não: pendência 4 de studio-estrutura.md).
3. Ações, à direita, com separadores finos:
   - busca (`magnifying-glass`);
   - carrinho (`shopping-cart`), com badge;
   - mensagens (`chat-circle`);
   - avisos (`bell`), com badge;
   - **hambúrguer** (`list`), que abre o menu geral;
   - **"Entrar"** de destaque, só para visitante.

### 3.2 Mobile (abaixo de 40rem)

- Linha 1: logotipo à esquerda; à direita, carrinho, mensagens, avisos,
  hambúrguer e "Entrar" (visitante). Sem rótulos de conta.
- Linha 2: "Domínios ⌄" e busca.

### 3.3 Menu hambúrguer

Mesmo comportamento do menu hambúrguer que hoje fica no cabeçalho do domínio
principal. Abre como painel de sobreposição.

- **Visitante:** "Entrar" como texto comum, sem destaque.
- **Autenticado:** no topo, avatar e nome de usuário, sem clique. Logo abaixo,
  já expandidos: "Minha conta" e "Meu perfil". "Sair" por último, separado por
  um fio fino.

Os dois "Entrar" (barra e menu) abrem o mesmo popup de login e desaparecem após
o login (ADR-047, 3 e 3a).

### 3.4 Popup de login e popup de foto

- `<dialog>` nativo com template na página. Abre com `showModal`, fecha por
  botão, clique no fundo e Escape.
- Largura de referência: 288, 358 e 440 px (roadmap da V2).
- Popup de foto: largura de `min(560px, 100vw − 2rem)`, altura máxima de 75vh
  com rolagem interna (medida da V1, `dialog.css`).

## 4. Telas do Studio e Minha conta

### 4.1 Minha conta (`aside`)

- **Barra lateral (`aside`):** sub-navegação vertical com as nove seções.
  Item ativo com destaque e `aria-current="page"`.
- **Conteúdo (`main`):** título "Minha conta", depois o cartão "Dados cadastrais
  (privados)" com os campos em coluna.
- **Mobile:** a sub-navegação vira linha horizontal com rolagem no topo do
  conteúdo; o formulário fica em coluna única.

### 4.2 Dashboard do Studio (`aside` no desktop)

- **Conteúdo:** cartão de identidade e grade de suítes (duas colunas a partir
  de 40rem).
- **Navegação do Studio:** pendente (pergunta 1). Se for vertical à esquerda,
  a barra lateral do `aside` é ocupada por ela e a sub-navegação de Minha conta
  fica em outra coluna, o que a decisão anterior rejeitou. Se for abas no topo,
  a barra lateral fica livre para a sub-navegação de Minha conta.

### 4.3 Telas de suíte e perfil

- Suítes: abas horizontais no topo da página, abaixo da faixa do domínio.
- Meu Perfil: `stack`, com avatar à esquerda e formulário à direita a partir de
  60rem; em coluna única abaixo disso.

## 5. Largura e rolagem

- Sem rolagem horizontal da página em 320 px e em zoom de 200% e 400%.
- Larguras de validação: 320, 390, 768, 959, 960 e 1440 px.
- Para cada largura, claro e escuro. O modo vem só de `data-theme` (ADR-046).

## 6. Largura máxima de conteúdo (referência da V1)

- Wiki: até 76rem.
- Discussão do fórum: até 58rem.
- Demais: definir por tipo quando a tela for construída.

## 7. Em aberto

Ver as perguntas interativas da conversa e a seção 5 de
[studio-estrutura.md](studio-estrutura.md).
