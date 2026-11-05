# ADR-048 — Layouts de página do tema ACULTA420 V2

- **Status:** aceito pelo responsável em 2026-11-04. Atualizado em 2026-11-05: nome confirmado; navegação vertical no dashboard e sub-navegação da conta em coluna dentro do conteúdo (item 5); rota inicial do Studio na raiz do purpose de conta (item 9). Pontos abertos da especificação visual estão na seção 9 de `web/themes/custom/aculta420_v2/docs/studio-visual-spec.md`.
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
5. **Navegação do Studio (decidida em 2026-11-05).**
   - **Dashboard:** navegação vertical à esquerda, pela imagem do dashboard.
   - **Minha conta:** a navegação do Studio segue à esquerda, e a sub-navegação da conta (nove seções) fica dentro do conteúdo. São duas colunas de navegação nessa tela. Esta decisão contraria a regra anterior de não ter duas colunas; registrada como escolha do responsável.
   - **Suítes:** abas horizontais no topo da página.
   - **Mobile:** o menu `list` abre a navegação do Studio como painel.
6. **Nome da área:** "ACULTA Studio", confirmado pelo responsável em 2026-11-05, conforme o ADR-038. O nome "ACULTA Drupal Studio" do material visual não é usado em rótulos.
7. **Mobile-first e acessibilidade:**
   - Landmarks: `banner`, `main`, `complementary` (só quando a barra lateral tem conteúdo) e `contentinfo`.
   - A ordem do DOM coloca o conteúdo antes da barra lateral.
   - Compensação da barra fixa com `scroll-padding-top` na base, sem JavaScript.
   - Sem rolagem horizontal da página em zoom de 200% e 400%.
8. **Privacidade das telas de conta.** As telas de Minha conta e Meu Perfil mostram dados privados: `noindex` por rota e sem cache público.
9. **Rota inicial do Studio.** A tela inicial (dashboard) fica na raiz (`/`) do purpose de conta, ou seja, no subdomínio `conta.aculta.*`. Caminhos `/studio*` continuam não existindo (ADR-038). As suítes mantêm caminho próprio (`/marley`, `/ganjalab`, `/we-sativa`, `/culturas`, `/green-stash`) no mesmo purpose. Os nomes reais de host ficam em `docs/architecture/multidomain.md`, não neste ADR.
10. **Popup de foto de perfil no sistema novo da V2.** Decisão do responsável em 2026-11-05, que substitui a versão anterior deste item (manter o modal do Core). O popup de foto usa o mesmo sistema do popup de login da V2 (`multidomain-bar.js`): `<dialog>` nativo com template na página, conteúdo carregado por Fetch de um fragmento HTML e envio de formulário por Fetch, com tratamento dos comandos AJAX. Não usa `core/drupal.dialog.ajax` nem jQuery UI.
    - **Mantido:** gatilho no avatar, título "Alterar foto do perfil", campo "Foto", botões "Sortear novo avatar" (só acervo, sem gravar), "Remover foto" (só pessoal) e "Salvar foto do perfil", recorte "Recortar foto" com "Aplicar recorte" e "Cancelar", e as regras de estado (acervo sem "Remover" e sem recorte; nunca inicial).
    - **Muda na V2:** aparência (regras da V1 para `photo-dialog` e `avatar-crop-dialog` passam a valer para o `<dialog>`); abertura e fechamento (`showModal`, `close`, clique no fundo, Escape); abertura do recorte após o envio (hoje um link `use-ajax` criado por `photo-flow.js`, que passa a abrir o mesmo `<dialog>`).
    - **Não é só tema.** A troca exige mudança no `aculta_user` (PR própria no Portal, com gate de foto): o gatilho deixa de depender de `use-ajax`, a rota de foto e a de recorte passam a devolver fragmento com identificador de popup (como `#aculta-login-popup`), e `PhotoEditorHooks` deixa de anexar `core/drupal.dialog.ajax`.
    - **Mecanismo do envio e do sorteio:** Fetch próprio com resposta aplicada no `<dialog>`, por um único módulo JavaScript (ADR-049). Não é `Drupal.ajax`.
    - **Alternativas citadas em pesquisa e não adotadas:** `dialog_native` (módulo contrib em alpha, sem release estável, pensado para Drupal ^10) e a issue de Core #2158943, ainda em revisão. Não são necessárias com o sistema da V2.
11. **Fórum e Wiki mantêm o visual da V1.** A V1 é a referência visual para o acabamento (decisão do responsável, ver ADR-046). O layout muda a estrutura, não o acabamento. Valores da V1 que a V2 precisa reproduzir e conferir em captura lado a lado:
    - Wiki: container de até 76rem (`wiki.css`).
    - Discussão do fórum: largura máxima de 58rem (`discussion.css`).
    - Cartões do fórum: raio `--bs-border-radius-lg`, fundo de superfície elevada.
    - Contadores de respostas: pílula com raio de 999px e fundo de superfície secundária.
    - Ações: botão amarelo de interação, texto verde escuro, altura mínima de 2,75rem, raio `--bs-border-radius`.
    - A variante `aside` da wiki e do tópico do fórum mantém essa aparência dentro da coluna principal e da barra lateral.
    - Verificação: capturas V1 × V2 em 320, 390, 960 e 1440 px, claro e escuro, em `$SUITE_OUT`.

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
