# ADR-048 — Layouts de página do tema ACULTA420 V2

- **Status:** aceito pelo responsável em 2026-11-04. Atualizado em 2026-11-05: nome confirmado; sub-navegação da conta em coluna vertical dentro do conteúdo, conforme a imagem do responsável (item 5); rota inicial do Studio na raiz do purpose de conta (item 9); a navegação principal do Studio está em revisão, porque a imagem mostra abas horizontais (item 5). Pontos abertos da especificação visual estão na seção 9 de `web/themes/custom/aculta420_v2/docs/studio-visual-spec.md`.
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-046 (shell, fundações e Fase 4), ADR-047 (menu de conta), ADR-038 (suítes do ACULTA Studio), `web/themes/custom/aculta420_v2/docs/layouts-plan.md` (pesquisa e detalhamento).

## 1. Contexto

- O `page.html.twig` do tema V2 tem uma única estrutura, com barra lateral opcional (`sidebar_first`), e não existe componente de layout.
- Os templates de conteúdo novos (revista, wiki, fórum) e as áreas do ACULTA Studio (dashboard, conta, suítes) precisam de estruturas diferentes: uma coluna, duas colunas e largura total.
- Layout Builder e Layout Discovery estão instalados no site, mas não são usados em nenhuma exibição. UI Styles está instalado, mas o tema V2 ainda não define estilos.

## 2. Decisões

1. **Componente SDC de layout com variantes.** Um único componente `layout-page`, com a prop `variant` em enum fechado: `stack`, `aside` e `full`. Não se usa Layout Builder por entidade. Layout Builder continua instalado e desabilitado nas exibições; ativá-lo exige decisão própria.
2. **Quem escolhe a variante é o tema**, pelo tipo de conteúdo e pela área, em `preprocess_page`. O Portal não envia chave de layout. Variante inválida ou ausente cai em `stack`.
3. **Mapeamento por tipo de conteúdo e área:**

   | Tipo / área | Variante |
   | --- | --- |
   | página básica, FAQ, aviso | `stack` |
   | wiki (artigo) | `aside` |
   | tópico do fórum | `aside` |
   | revista (edição) | `full` |
   | evento (lista e mapa) | `full` |
   | loja (catálogo) | `full` |
   | loja (produto) | `stack` no mobile; `aside` a partir de `60rem` |
   | Studio dashboard | `aside` no desktop; `stack` no mobile |
   | Minha conta | `aside` no desktop; `stack` no mobile (ver item 5) |
   | Meu Perfil, Meu trabalho, Green Stash | `stack` |
   | Ganjalab, WE Sativa, Culturas | `aside` |

4. **Breakpoints, a partir de uma coluna em 320 CSS px:**
   - `40rem` (640 px): grades de cartões passam a duas colunas.
   - `60rem` (960 px): a variante `aside` passa a duas colunas.
   - A proposta de `60rem` vem do cálculo das colunas (leitura de 36 rem, barra lateral de 16 rem, espaço e margens, cerca de 56 rem), não de uma referência externa verificada. A validação deve testar 959, 960 e 961 px e zoom de 200%.
5. **Navegação do Studio.**
   - **Desktop, em revisão:** a conversa de 2026-11-05 confirmou navegação vertical à esquerda em todas as telas pessoais. A imagem enviada depois mostra a navegação do Studio como **abas horizontais** no topo das quatro telas (Dashboard, Meu Perfil, Minha conta, Meu trabalho). Pergunta aberta ao responsável: qual dos dois vale para a navegação principal do Studio.
   - **Mobile:** o mesmo padrão do mockup de celular. A navegação vira um menu no cabeçalho (`list`) e o conteúdo fica em uma coluna.
   - As suítes (Marley, Ganjalab, WE Sativa, Culturas, Green Stash) mantêm abas horizontais no topo da página.
   - **Minha conta (confirmado em 2026-11-05 pela imagem do responsável):** as seções da conta (dados cadastrais, endereço, documentos, e-mail e segurança, senha, contas conectadas, pedidos, apoio e preferências) ficam em uma **coluna vertical dentro do conteúdo**, à esquerda do formulário, com a seção ativa destacada. No mobile, essa lista vira uma linha horizontal com rolagem no topo do conteúdo. Isso não é a navegação principal do Studio, que segue o item acima.
6. **Nome da área:** "ACULTA Studio", confirmado pelo responsável em 2026-11-05, conforme o ADR-038. O nome "ACULTA Drupal Studio" do material visual não é usado em rótulos.
7. **Mobile-first e acessibilidade:**
   - Landmarks: `banner`, `main`, `complementary` (só quando a barra lateral tem conteúdo) e `contentinfo`.
   - A ordem do DOM coloca o conteúdo antes da barra lateral.
   - Compensação da barra fixa com `scroll-padding-top` na base, sem JavaScript.
   - Sem rolagem horizontal da página em zoom de 200% e 400%.
8. **Privacidade das telas de conta.** As telas de Minha conta e Meu Perfil mostram dados privados: `noindex` por rota e sem cache público.
9. **Rota inicial do Studio.** A tela inicial (dashboard) fica na raiz (`/`) do purpose de conta, ou seja, no subdomínio `conta.aculta.*`. Caminhos `/studio*` continuam não existindo (ADR-038). As suítes mantêm caminho próprio (`/marley`, `/ganjalab`, `/we-sativa`, `/culturas`, `/green-stash`) no mesmo purpose. Os nomes reais de host ficam em `docs/architecture/multidomain.md`, não neste ADR.

## 3. Consequências

- `page.html.twig` passa a renderizar `layout-page` no lugar do `<main>` atual. As regiões do Drupal permanecem as mesmas; nenhum bloco é remapeado.
- As classes `page__main--with-sidebar` e `page__sidebar` deixam de existir; a regra vira a variante `aside`.
- O Portal não precisa de mudança de contrato para layouts. Isso remove a fase P3 do plano anterior.
- A barra institucional, o menu de conta e as utilidades não mudam.

## 4. Fases

| Fase | Entrega | Critério de aceite |
| --- | --- | --- |
| P1 | `layout-page` SDC com as três variantes, stories e refatoração do `page.html.twig` sem mudar as regiões | gates do tema; capturas em 320, 390, 768, 959, 960 e 1440 px, claro e escuro, zoom de 200%; landmarks conferidos |
| P2 | Mapeamento da seção 2, item 3, em `preprocess_page` | wiki, revista, fórum e loja renderizam com a variante definida; nenhuma regra por domínio |
| P3 | Área do Studio e de Minha conta | capturas em 320 e 1440 px; sub-navegação com `aria-current`; `noindex` conferido |
| P4 | Demais áreas da Fase 4 (evento, LMS, FAQ) | captura e story próprias por área |
| P5 | Revisão de Layout Builder, só se o responsável pedir vitrine editável | nova decisão registrada; nenhuma exibição ativada sem ela |

Cada fase termina em PR própria, com `RUNTIME STATUS: DEFERRED` enquanto o código não tiver sido testado no servidor de desenvolvimento.

## 5. Não-decisões

- Não define o conteúdo da wiki nem a lista final de itens da conta.
- Não define campos, preço e ações da página de produto; só o layout.
- Não ativa Layout Builder nem Layout Discovery.
- Não altera o contrato do Portal.
- Não define a versão do tema que contém o layout; ela será marcada na entrega validada (ADR-046, MINOR).

## 6. Validação

- Gates do tema e Portal, conforme `docs/gates.md`, executados no servidor de desenvolvimento.
- Capturas em Firefox ESR visível, nas larguras e modos da seção 4, em `$SUITE_OUT`, fora do repositório.
- Páginas do Portal também com o tema Olivero (CLAUDE.md).
