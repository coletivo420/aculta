## 0.6.0-dev.73 — módulos VVJ e slideshow de álbuns — 2026-10-18

Classificação: MINOR (novos formatos de Views disponíveis; o palco de fotos migra para o VVJS apropriado a uma sequência editorial).

- Incluídos VVJA, VVJB, VVJF, VVJH, VVJL, VVJR, VVJS e VVJT; VVJC (3D Carousel) e VVJP (Parallax) ficam excluídos por decisão do responsável.
- A View `aculta_album_fotos` usa VVJS com API pública de miniaturas/eventos. Home e Projetos permanecem VVJB; PhotoSwipe continua responsável pelo lightbox.
- O plano mapeia os usos adequados e registra limites para não converter SDCs, navegação, formulário ou conteúdo que não seja uma View.
- Runtime de teste: módulos habilitados, cache reconstruído e View atualizada por configuração direcionada. Inspeção visual do slideshow pendente; não houve importação global de configuração.

## 0.6.0-dev.72 — LMS em português e “Aulas” — 2026-10-18

Classificação: PATCH de tradução e vocabulário, sem mudança de contrato visual.

- O catálogo de interface do LMS passa a usar “aula/aulas” em todas as traduções antes exibidas como “lição/lições”.
- Cobertura pt-BR do LMS 1.2.4 ampliada com as 168 entradas do pacote oficial; o arquivo `lms-terms.pt-br.po` substitui as duas traduções padrão de “Lesson” e “Lesson type” sem alterar outros termos padrão do Drupal.
- Runtime de teste: catálogo principal importado (7 entradas novas, 334 atualizadas); vocabulário do LMS importado (2 atualizadas). Exportações pt-BR customizadas e padrão não contêm mais “lição/lições”.
- RUNTIME STATUS: ACTIVE no servidor de testes (`aculta_runtime`, `https://aculta.toca.net.br`).

## 0.6.0-dev.71 — carrinho com um botão de checkout e assinatura em espaço próprio — 2026-10-17

Classificação: MINOR da linha aberta (estrutura do painel do carrinho).

- Um botão "Finalizar carrinho" para as categorias (loja, cursos, apoio); caixas e separadores mantidos.
- Assinatura em bloco próprio, com botão "Finalizar assinatura".

## 0.6.0-dev.70 — carrinho com rolagem e rodapé neutro; cartões de produto com borda — 2026-10-17

Classificação: PATCH da linha aberta (ajuste visual, sem contrato novo).

- Cartões de produto com borda de 2 px (`border-2`).
- Carrinho lateral: rodapé "Compre na loja…" com superfície neutra e borda de destaque (tokens claro e escuro), distinto dos
  botões. O corpo rola dentro do painel, como as gavetas de mensagens e avisos; o rodapé fica fixo embaixo.
- Cartões de produto da página inicial da loja com borda neutra (classe `border`), sem cor de botão.
- SDC: descrições acrescentadas aos quatro componentes de doação (`donation-dedication`, `donation-notification`,
  `donation-pane`, `donation-summary-container`); schema SDC PASS.
- Verificação: `validate-module-theme-boundary` PASS; `validate-aculta420-sdc-schemas` PASS.

## 0.6.0-dev.69 — F5: cartão ACULTA420 no catálogo LMS — 2026-10-17

Classificação: MINOR da linha aberta (novo SDC experimental com contrato compatível).

- `components/content/course_card/` substitui `lms:course_card` por SDC negotiation. O schema aceita
  os props/slots existentes e inclui `description`, que o row plugin do LMS já fornece.
- Drupal LMS e a View `courses_catalog` continuam donos dos dados, filtro de publicação, URL,
  botão de entrada, access e cacheability; não houve alteração de configuração da View nem no contrib.
- O tema assume o markup e o CSS do cartão, aplica tokens semânticos, limita a descrição visual a
  seis linhas e mantém foco visível de 3 px. O rail continua nativo e não requer JavaScript.
- Verificação: SDC schemas PASS (25 componentes/377 checks); design foundations PASS (1895);
  `validate-lms-skin` PASS (17 checks); Core negociou o componente como `aculta420:course_card`;
  `drush cr` PASS; View executada como anônimo e
  como conta autenticada com papel estudante; HTTP 200 e Firefox headless com Tab, nome acessível
  e contorno de foco. Há somente um curso publicado e nenhum não publicado no Runtime para testar
  o caso negativo de listagem. Homelab PHP 8.4.26; produção não verificada.
- RUNTIME STATUS: ACTIVE no servidor de testes (`aculta_runtime`, `https://aculta.toca.net.br`).

## 0.6.0-dev.68 — zoom do viewer PDF preservado em viewport estreito — 2026-10-17

Classificação: PATCH (apresentação responsiva; sem alterar o contrato de acesso ou markup semântico).

- `css/components/pdf-viewer.css`: a área da página passa a rolar quando o zoom excede o viewport; o canvas deixa de ser redimensionado para caber em 100% da largura.
- A marcação neutra do módulo envolve o canvas em `page-stage`, que recebe a rolagem visual.
- O canvas só aparece depois de renderizado, evitando espaço vazio quando o carregamento falha.
- Cache rebuild aplicado no Runtime de teste; verificação visual em Firefox e mobile continua pendente.

## 0.6.0-dev.67 — painéis de vendas e doações (tokens sales-*) — 2026-10-16

Classificação: MINOR da linha aberta (componente de apresentação compatível; nenhum contrato stable quebrado).

- `src/Hook/PortalUiHooks.php`: 13 tokens `sales-*` (painel, seção, título, tabela, paginação, pagamento, ações normal e perigosa, e cinco estados de pagamento).
- `css/components/sales-panel.css` (novo, na biblioteca `global`): só tokens semânticos (`--aculta-status-*`, `--aculta-surface-muted`, `--aculta-text-muted`, `--aculta-border-subtle`, `--aculta-space-section`); tabela rola dentro do painel, sem rolagem horizontal da página.
- Gate de fundação: versão 0.6.0-dev.67.
- Verificação: `validate-portal-ui-docs` PASS (250 tokens no mapa); `validate-module-theme-map` PASS; `validate-module-theme-boundary` PASS; `validate-aculta420-design-foundations` PASS; `validate-aculta420-sdc-schemas` PASS; `validate-aculta420-foundation` e `validate-aculta420-shell-contract` PASS (executados via `drush php:script`, como indica `docs/development.md`).
- Pendente: captura visual com administrador logado e pedidos de teste (DEFERRED).

## 0.6.0-dev.66 — barra e cabeçalho por flags do Portal (shell-contract PASS) — 2026-10-16

Classificação: PATCH (troca de decisão; o HTML e a aparência continuam os mesmos por purpose).

- `templates/page.html.twig` deixa de comparar `identity.purpose`. Usa as flags `domain_presentation.layout`: `institution_bar_account` (variante de conta da barra), `show_utility_bar` (busca, carrinho, Messenger, avisos e Minha conta), `links_page` (shell de LINKS) e `show_site_header` (cabeçalho de domínio). As flags vêm do Portal (`aculta_portal` 0.4.0-dev.63).
- A barra institucional mantém o marcador literal `<div class="aculta-institution-bar">` no ramo padrão, porque o gate `validate-aculta420-shell-contract.php` confere esse texto. A variante de conta usa um ramo próprio com a classe `aculta-institution-bar--account`.
- Gate de fundação: versão 0.6.0-dev.66.
- Verificação: `validate-aculta420-shell-contract` PASS (11 checks, antes FAIL pré-existente); `validate-aculta420-foundation` PASS (345); `validate-aculta420-design-foundations` PASS; `validate-aculta420-sdc-schemas` PASS (24 componentes).
- Visual (Firefox headless, Runtime de teste): `main` com barra e cabeçalho; `wiki` com barra e cabeçalho da Wiki; `cursos` e `apoio` com barra e sem cabeçalho; `conta` com a variante de conta. A página inicial do `main` mostra o cabeçalho, e as demais páginas do `main` não. As capturas foram feitas com sessão de administrador ativa no navegador de teste.
- Não verificado: `links`, cuja raiz responde 404 no servidor de testes.
- RUNTIME STATUS: cache rebuild no Runtime de teste com a URI de teste.

## 0.6.0-dev.65 — correspondências do tema com módulos e tokens faltantes — 2026-10-16

Classificação: PATCH (tokens e CSS de apresentação; gate de fundação atualizado).

- `PortalUiHooks::TOKENS` recebe três tokens que o módulo já emitia e o tema não mapeava: `public-profile-avatar-image` (`portal-profile__avatar-image`), `achievement-notices` (`aculta-achievement-notices`) e `aculta-image-audit` (`aculta-image-audit`).
- `css/components/messenger-drawer.css`: `.aculta-achievement-notices` com espaçamento vertical; a cor vem dos tokens de texto.
- `css/components/portal-requirements-report.css`: `.aculta-image-audit > * + *` separa as seções do relatório de imagens.
- Gate de fundação: versão 0.6.0-dev.65 e allowlist de templates com o fórum (`forum-list`, `comment--comment-forum`, `node--forum--full`, dev.62 e dev.63).
- Mapa de correspondências: `docs/portal/MAPA-MODULO-TEMA.md` (Portal `docs/portal/`).
- Verificação: `validate-aculta420-foundation` PASS (345 checks, via Drush no Runtime de teste); `validate-aculta420-design-foundations` PASS; `validate-aculta420-sdc-schemas` PASS (24 componentes); `validate-portal-ui-docs` PASS (237 tokens, 82 atributos).
- Pendente (não corrigido): `validate-aculta420-shell-contract` FAIL, pré-existente. `templates/page.html.twig` decide por `identity.purpose` em cinco pontos (barra institucional, barra de busca, cabeçalho e layout de LINKS). Corrigir exige flags de layout no presenter do Portal e mudança do contrato `domain_presentation`, com verificação visual nos quatro purposes.
- RUNTIME STATUS: cache rebuild no Runtime de teste (`--uri=https://aculta.toca.net.br`, banco `aculta_runtime`). Validação visual não executada nesta rodada.

## 0.6.0-dev.58 — conversas na conta proporcionais à tela — 2026-10-16

Classificação: PATCH (ajuste de apresentação em um contexto; gaveta da barra inalterada).

- Aba Mensagens de Minha conta: caixa de conversa com largura máxima de 42 rem, linha mais compacta e raio padrão.
  Regras só dentro de `[data-portal-ui="account-messenger-drawer"]` (messenger-drawer.css).
- Verificado no Firefox headless, perfil dedicado, conta de teste.

## 0.6.0-dev.57 — foco e alvo de toque padronizados; raio padrão das caixas — 2026-10-16

Classificação: MINOR (novo utilitário e novo padrão de raio).

- `css/base.css`: foco visível único para elementos interativos (`:where`, especificidade zero).
- `css/base.css`: utilitário `.aculta-touch-target` (alvo de toque de 2,75 rem) para componentes novos.
- Raio padrão das caixas de superfície: `var(--bs-border-radius)` (0,1875 rem), definido por captura comparativa.
  Aplicado em 4 regras do messenger e 5 do painel da conta.
- Documentação: `docs/portal/COMPONENTES-AGRUPAMENTO.md`, seção 6.

## 0.6.0-dev.56 — agrupamento de gatilhos da barra e escaneamento de componentes — 2026-10-16

Classificação: PATCH (sem mudança de marcação nem de aparência).

- Os gatilhos de avisos e de mensagens deixaram de repetir a base do gatilho (`aculta-utility-trigger`); cada um mantém
  só a posição do contador.
- Escaneamento de estilos repetidos documentado em `docs/portal/COMPONENTES-AGRUPAMENTO.md` (foco visível em 23 arquivos,
  pílulas em 4, próximas etapas listadas).

## 0.6.0-dev.55 — contador de não lidos padronizado (token único) — 2026-10-16

Classificação: MINOR (novo contrato de apresentação: `aculta-unread-count`).

- `css/components/unread-count.css` é a fonte da aparência: a mesma do contador da barra multidomínio.
- Carrinho, avisos, mensagens, badge de conta e contadores de conteúdo (fórum) usam a mesma classe.
- `PortalUiHooks.php`: token `forum-topic-unread` mapeado para `aculta-unread-count`.
- Removidas as regras antigas de contador de `portal-cart.css` e do componente `unread-badge.css`; posição do
  gatilho de avisos e de mensagens mantida, agora sobre a classe única.
- Documentação: `docs/portal/CONTADORES.md` e `docs/components.md`.
- Pendente: teste visual no navegador; contador do LMS (sem dado ainda).

## 0.6.0-dev.54 — cartões de conquistas no perfil público — 2026-10-15

Classificação: MINOR (novo contrato de apresentação no tema).

- `PortalUiHooks.php` traduz tokens neutros do Game em classes do perfil ACULTA420.
- `public-profile.css` apresenta cartões responsivos, imagens, descrições e destaque, em light/dark via tokens semânticos.
- Firefox no Runtime: 19/19 verificações da integração, incluindo perfil responsivo; Foundation (342) e fronteira módulo/tema aprovados.
- RUNTIME STATUS: ACTIVE (Homelab).

## 0.6.0-dev.53 — tokens do Messenger, CSS e JS separados, janelas e acordeão — 2026-10-15

Classificação: PATCH (apresentação do Messenger; sem mudança de comportamento para o membro).

- `PortalUiHooks`: tokens do Messenger (mesmas classes de antes) e `preprocessDetails` (ordem última) para a caixa "Opções". Removidos dois tokens sem emissor.
- `ThemeHooks`: removido o mapeamento da página nativa do Private Message (redireciona).
- CSS: `css/components/messenger-drawer.css` (gaveta, lista, menu, avisos, janela); `messenger-item.css` fica só com a bolha. Seletores do formulário de resposta e do menu por atributo (`data-aculta-reply-form`, `data-aculta-drawer-action`).
- JS: `js/alerts-dismiss.js` (dispensar aviso, biblioteca global), fora de `messenger-drawers.js`.
- `portal-cart.css`: o "×" do aviso é selecionado por `data-aculta-alert-dismiss`.
- RUNTIME STATUS: ACTIVE no teste (suíte de navegador 70 de 70).
- `messenger-drawer.css`: menu "Opções" da lista só com o ícone (sem rótulo nem seta de acordeão); painel sobre a linha, alinhado à direita.
- `messenger-drawer.css`: cartão do aviso persistente em cor de aviso (`aculta-notice-summary--persistent`), diferente dos dispensáveis.
- `messenger-drawer.css` e `PortalUiHooks.php`: resumo de aviso em caixa de botão (tokens `notice-summary*` e `notice-detail`).
- `messenger-drawers.js`: o menu "Opções" de cada linha da lista é carregado na primeira abertura.
- Revisão de código: `PortalUiHooks` com os hooks de `preprocess()` restaurados e `preprocessDetails` separado; mapeamento de classe de link de menu aceita classe em string.

## 0.6.0-dev.52 — confirmação para bloquear e botões da nova conversa — 2026-10-15

Classificação: PATCH (apresentação da confirmação de bloqueio e dos botões de nova conversa).

- `messenger-drawers.js`: "Bloquear" e "Desfazer amizade" abrem a janela de confirmação (dialog, identificada por `data-aculta-dialog`) e "Cancelar" a fecha; o clique não envia o formulário.
- `messenger-item.css`: aparência da janela de confirmação (botões "Sim" e "Cancelar"), para bloquear e desfazer amizade.
- `PortalUiHooks.php`: token `new-conversation-cancel` (botão "Cancelar" da nova conversa, outline secundário); "Voltar para o início" segue outline primário.
- RUNTIME STATUS: ACTIVE no teste (abrir/cancelar na gaveta; bloquear pelo "Sim" no perfil; desbloqueio restaurado).

## 0.6.0-dev.51 — menu "Opções" nas conversas e no perfil; largura e fonte — 2026-10-15

Classificação: PATCH (apresentação do menu "Opções").

- `messenger-item.css`: o painel "Opções" de cada linha abre por cima da lista, alinhado à direita da linha, sem alargar a gaveta.
- `messenger-item.css`: os botões do menu de relação usam a mesma aparência e fonte dos itens "Opções" (antes saíam com a fonte de títulos).
- `messenger-drawers.js`: "Iniciar conversa" do perfil abre a gaveta já na etapa de nova conversa (`drawer.acultaNavigate`).
- RUNTIME STATUS: ACTIVE no teste (capturas de tela); a suíte de 52 verificações não foi reexecutada.

## 0.6.0-dev.50 — envio de resposta na gaveta sem navegação — 2026-10-15

Classificação: PATCH (comportamento do envio de resposta na gaveta).

- `messenger-drawers.js`: o formulário de resposta é enviado por `fetch` (sem os campos de formulário do Drupal, que disparariam o processamento nativo); o token vai em `reply_token`.
- RUNTIME STATUS: ACTIVE no teste (52 verificações de navegador).

## 0.6.0-dev.49 — aviso de conversa restrita e aviso de bloqueio no lugar da caixa de resposta — 2026-10-15

Classificação: PATCH (apresentação do aviso).

- Token `conversation-notice-action` mapeia o botão do aviso para `aculta-messenger-notice__action` (link discreto dentro do texto).
- Estilo `aculta-messenger-notice`: caixa de aviso com o texto e a ação na mesma linha, quando couber.
- RUNTIME STATUS: ACTIVE no teste.

## 0.6.0-dev.48 — "Opções" em menu sanfona na mesma linha da barra — 2026-10-15

Classificação: MINOR (novo menu de ações na conversa).

- A barra da conversa é um grid de duas colunas numa linha: "← Todas as conversas" à esquerda e "Opções" à direita. A regra genérica dos filhos da conversa não sobrescreve mais a barra.
- O painel de Opções é um menu compacto, com uma ação por linha e separador, alinhado à direita. No celular, só o hambúrguer.
- Token `conversation-action` passa a usar a classe do menu (`aculta-messenger-options__action`), no lugar dos botões.
- `messenger-drawers.js` envia também o formulário de Opções por fetch (`form[data-aculta-drawer-action]`).
- Medido no Firefox headless: link e Opções no mesmo topo (y=72) em 1280 e 390 px.
- RUNTIME STATUS: ACTIVE no teste.

## 0.6.0-dev.47 — gaveta de mensagens com rolagem; link de volta no topo — 2026-10-15

Classificação: PATCH (apresentação da gaveta de mensagens).

- O corpo da gaveta de mensagens rola quando o conteúdo é maior que a janela, no desktop e no celular. A conversa mantém a altura natural, e a caixa de resposta fica no fim da rolagem.
- "← Todas as conversas" fica no topo, à direita, abaixo do X, em todas as etapas (conversa, Nova conversa, compor e envio).
- `messenger-drawers.js`: o envio do formulário de nova conversa usa fetch e troca o conteúdo da gaveta pela resposta; o botão "Iniciar nova conversa" também abre a etapa.
- Medido no Firefox headless: conversa com 1264 px contra 658 px visíveis rola até o fim (1264 e 658 em desktop, 1214 e 702 em 390 px).
- RUNTIME STATUS: ACTIVE no teste.

## 0.6.0-dev.46 — respiro superior compacto sem cabeçalho de domínio — 2026-10-15

Classificação: PATCH (ajuste responsivo de espaçamento do shell existente).

- Quando o cabeçalho de domínio não é exibido, o conteúdo começa mais perto da barra multidomínio: 0.5rem–1.25rem, conforme a largura da tela.
- O espaçamento padrão da home principal e o cabeçalho da Wiki permanecem inalterados.
- Inspeção visual por Firefox headless no Runtime (1440×1000 e 390×844): o intervalo aparente entre a barra e o primeiro texto caiu de ~70 para ~42 px no desktop e de ~48 para ~30 px no mobile. A home principal e o cabeçalho de busca da Wiki foram comparados e permanecem inalterados. RUNTIME STATUS: ACTIVE.

## 0.6.0-dev.45 — cabeçalho principal somente na home — 2026-10-15

Classificação: PATCH (visibilidade do cabeçalho existente; contrato e barra multidomínio preservados).

- O cabeçalho com marca e menu do site principal aparece somente na página inicial do purpose `main`.
- Cabeçalho próprio da Wiki420 permanece visível; a barra multidomínio não foi alterada.
- Verificado por HTTP no Runtime: cabeçalho presente na home principal e Wiki; ausente em páginas internas do domínio principal e nos demais purposes consultados. Barra multidomínio preservada. RUNTIME STATUS: ACTIVE.

## 0.6.0-dev.44 — "Iniciar nova conversa" na barra de mensagens — 2026-10-15

Classificação: MINOR (novo fluxo de conversa na barra de mensagens, com tokens novos).

- Novos tokens `data-portal-ui` para a etapa de nova conversa: `new-conversation-link`, `new-conversation-submit`, `new-conversation-back`, `new-conversation-links`, `new-conversation-message`, `new-conversation-results` e `new-conversation-result`. Documentados em `docs/portal/TOKENS-E-ATRIBUTOS.md`.
- O script `messenger-drawers.js` ignora o envio do formulário de nova conversa, que é feito pelo AJAX do Drupal dentro da própria gaveta.
- RUNTIME STATUS: rotas verificadas por HTTP no runtime de testes; interação no navegador ainda não verificada.

## 0.6.0-dev.43 — painel da conta no celular sem texto de carregamento — 2026-10-15

Classificação: PATCH (apresentação da sanfona da conta).

- Removido o texto "Carregando…" dos painéis e do marcador do shell (tema e fallback do módulo), com as regras CSS sem uso.
- Seção de Mensagens removida do menu e da sanfona (ver `aculta_messenger` 0.1.1-dev.16).
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.42 — 99+ em todos os contadores, inclusive atualização ao vivo — 2026-10-15

Classificação: PATCH (apresentação dos contadores).

- Teste com os valores máximos no runtime de teste: carrinho com 1250 unidades, 120 avisos não lidos e 100 conversas não lidas (também no badge da navegação da conta). Todos mostram 99+; em 320 px e no desktop a barra continua em uma linha, sem quebra.
- Atualização ao vivo: o carrinho é atualizado após "Adicionar" por um comando AJAX do módulo que grava o número direto no DOM. Novo script `js/counters.js` (biblioteca global) reaplica a regra sempre que `data-count` muda. Verificado em página de teste (120 e 150 viram 99+).
- O módulo `aculta_portal` ainda grava o número bruto no comando (`AddToCartAjax`); a apresentação é corrigida pelo tema. Ajuste de contrato pendente: o módulo deve enviar só o dado.
- Dados de teste removidos; contadores de volta a zero. RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.41 — limite de exibição 99+ nos contadores — 2026-10-15

Classificação: PATCH (apresentação dos contadores).

- Regra: acima de 99 o badge mostra `99+`. Vale para conversas não lidas, avisos, carrinho e o contador da navegação da conta (`unread-badge`).
- O `data-count` e o `aria-label` mantêm o número exato, para scripts e leitores de tela. O JavaScript (`updateCounter`) aplica a mesma regra nas atualizações ao vivo.
- Simulado no runtime de teste: 100 conversas com mensagem não lida de um membro de teste. O badge passou a mostrar `99+` em aculta e conta, e coube em uma linha em 320 px e no desktop. Dados de teste removidos e contador de volta a zero.
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.40 — barra multidomínio com contadores de dois dígitos no mobile — 2026-10-15

Classificação: PATCH (apresentação mobile da barra multidomínio).

- Simulação no runtime de teste: carrinho com 12 unidades e 12 comunicados não lidos (dados removidos ao final).
- Com badges de dois dígitos, a barra mobile cabe em uma linha em 320, 360 e 390 px. Em até 374,98 px o logo da barra sai da área visível (o link continua acessível; o logo do cabeçalho segue visível).
- Desktop sem mudança.
- Pendência: badge de mensagens com dois dígitos não foi simulado (exige dez conversas não lidas).
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.39 — barra multidomínio em uma linha no mobile e destaque de Minha conta por propósito — 2026-10-15

Classificação: PATCH (apresentação da barra multidomínio; sem mudança de API).

- Mobile (até 767,98 px): a barra institucional fica em uma linha. Logo, ícones e links da conta são compactados em vez de quebrar; a altura de toque dos links é mantida.
- O destaque de "Minha conta" passa a ser decisão do tema, pelo propósito do domínio no contrato `domain_presentation` (`identity.purpose == 'account'`). A barra recebe a classe `aculta-institution-bar--account` só nesse caso. O módulo marca o link com o token `account-home` em todos os domínios e não decide mais a cor.
- Corrige o dev.38, que aplicava a classe em todos os domínios por posição e depois pelo módulo.
- Verificado no runtime: principal com cor padrão, conta em vermelho; 360, 390 e 414 px sem quebra de linha. RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.38 — link Minha conta identificado por token, não por posição — 2026-10-15

Classificação: PATCH (correção visual da barra multidomínio).

- A regra que pintava o link "Minha conta" era posicional (`:first-child`): pintava o primeiro item do menu, não o item de conta. Ela foi removida.
- O módulo marca o link com o token `account-home`; o tema traduz para a classe `aculta-account-home` no mesmo mapa `PortalUiHooks::TOKENS`. O CSS estiliza essa classe.
- Duas regras duplicadas em `institution-bar.css` foram unidas em uma.
- Verificado no runtime: a classe aparece no link em aculta, conta e loja; não sobra atributo `data-portal-ui`. Entrega: PR de pendências do Messenger. RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.6.0-dev.37 — rota de conversas e token de ação no tema — 2026-10-15

Classificação: PATCH (mapeamento de rota e token documentado; sem mudança visual).

- Mapeia `aculta_messenger.conversations` à lista de mensagens e rotula como "Todas as conversas".
- Registra `messenger-preview-inbox-link`, documentado em `TOKENS-E-ATRIBUTOS.md`.
- Não reaplica a biblioteca `messenger-drawers` no root da página: os templates já a anexam.
- Foundation (343) PASS. Entrega: PR #284. RUNTIME STATUS: DEFERRED para validação visual.

## 0.6.0-dev.36 — conversa no drawer via navegação AJAX — 2026-10-15

Classificação: PATCH (navegação no painel lateral).

- Abre a conversa e volta para a lista no próprio painel lateral, em navegação AJAX.
- Verificação estática PASS; validação no runtime pendente. RUNTIME STATUS: DEFERRED. Entrega: PR #283.


## 0.6.0-dev.35 — data visível nos contatos — 2026-10-14

Classificação: PATCH (apresentação do contato e composição nativa da conversa).

- A data curta da última mensagem é apresentada em elemento próprio acima do nome, inclusive na gaveta lateral da barra multidomínio.
- O tema preserva o espaço visual compacto do contato e aplica tipografia secundária pequena.
- Validação visual pendente; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.34 — data nos contatos do Messenger — 2026-10-14

Classificação: PATCH (apresentação de metadado na caixa existente).

- A data e hora da última mensagem aparece em fonte pequena acima do nome do remetente.
- O alinhamento mantém o avatar e o botão com a dimensão mínima já definida.
- Validação visual pendente; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.33 — contatos compactos e botão de amizade no perfil — 2026-10-14

Classificação: PATCH (ajustes visuais e de apresentação sem alterar contratos estáveis).

- A gaveta de mensagens destaca cada remetente em uma caixa clicável com avatar circular e nome, sem detalhes extras.
- O perfil público apresenta a ação de amizade no cabeçalho, com o estilo do botão “Ver Perfil” da Minha Conta.
- CSS de conversa é carregado pela biblioteca da gaveta mesmo quando a resposta AJAX não renderiza o SDC de mensagem.
- Capturas locais da lista em desktop/mobile revisadas; validação interativa do fluxo de amizade permanece DEFERRED.

## 0.6.0-dev.30 — destaque vermelho para Minha Conta — 2026-10-14

Classificação: MINOR da linha aberta (consistência visual da barra multidomínio).

- O primeiro item do menu de conta usa o token vermelho de estado, em harmonia com os badges da barra.
- O destaque de foco e hover usa a superfície semântica correspondente; os outros itens do menu mantêm suas cores atuais.
- Validação visual no navegador pendente; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.29 — contadores vermelhos ao lado dos ícones — 2026-10-14

Classificação: MINOR da linha aberta (ajuste visual da barra multidomínio).

- Carrinho, mensagens e avisos exibem seus contadores em vermelho, imediatamente ao lado dos ícones.
- Os três continuam usando uma única regra compartilhada, com tokens semânticos e badge arredondado.
- Validação visual no navegador pendente; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.28 — conversa em balões e avisos vermelhos — 2026-10-14

Classificação: MINOR da linha aberta (aprimoramento visual experimental do Messenger).

- A lista apresenta avatar do remetente e prévia em balões de entrada/saída; a conversa abre em diálogo Drupal com histórico e resposta nativa.
- O contador é zerado ao abrir a caixa, pela marcação nativa de leitura do Private Message.
- Os três contadores da barra multidomínio agora usam o mesmo token, posição, tamanho e tipografia do carrinho.
- Avisos Site Alert e persistentes usam vermelho semântico; X dispensa por usuário e atualiza a contagem.
- Validação interativa no Runtime local/Homelab pendente. RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.27 — atualização sob demanda dos contadores — 2026-10-14

Classificação: MINOR da linha aberta (atualização de interface multidomínio).

- Ao abrir cada gaveta, as respostas AJAX atualizam o badge de mensagens ou de avisos junto com o conteúdo,
  sem polling em segundo plano.
- O throbber de espera do Drupal Core é ocultado apenas no desktop para o ícone de busca; a abertura AJAX e a
  indicação em telas menores permanecem.
- Gates estáticos e validação visual/runtime pendentes nesta cópia; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.26 — gavetas de comunicação no padrão do carrinho — 2026-10-14

Classificação: MINOR da linha aberta (apresentação de mensagens e avisos).

- Mensagens e avisos usam o mesmo shell Bootstrap do carrinho: botão, offcanvas pela direita,
  largura máxima de 420 px, cabeçalho, botão de fechar, badge e tokens semânticos compartilhados.
- Cada abertura consulta um fragmento HTML same-origin com `fetch` e credenciais de sessão;
  não há polling. Comportamentos Drupal do conteúdo renderizado são anexados após a resposta.
- Falha da consulta mantém o painel aberto e mostra uma mensagem acessível para nova tentativa.
- Gates e validação visual/runtime no Homelab: pendentes; RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.25 — gavetas laterais e palco do álbum — 2026-10-14

Classificação: MINOR da linha aberta (apresentação experimental de uma feature existente).

- Os ícones de mensagens e avisos abrem drawers off-canvas do Drupal Core pela direita, com 420 px,
  igual à largura máxima da gaveta do carrinho.
- A conversa e os avisos são buscados via Ajax ao abrir; não há consulta recorrente em segundo plano.
- O conteúdo não inclui mais o link de saída “Abrir caixa de mensagens”; conversas e avisos aparecem
  no próprio painel.
- Gates de SDC, design foundations, fronteira Portal/tema e Foundation: pendentes nesta cópia local.
- RUNTIME STATUS: DEFERRED; validar abertura, foco, rolagem e responsividade no Homelab.
- Palco do álbum com moldura semântica por token, miniaturas responsivas e slideshow no palco; a biblioteca `aculta420/album-slideshow` fica global e não referencia a View no template.
- Gate de contrato do álbum (`validate-album-stage-contract.php`) cobre seletores, atributos e API pública do slideshow.

## 0.6.0-dev.24 — modais da barra e estado de leitura — 2026-10-14

Classificação: MINOR da linha aberta (SDCs experimentais de entrada e estado).

- Ícone Bootstrap de avisos ao lado de mensagens na barra, com modal Drupal.
- Rótulo de conversa lida/não lida deriva do estado nativo Private Message.
- Mensagens e avisos mantêm o mesmo comportamento de apresentação em modos de cor.
- SDC schema e design foundations: PASS; gate de Foundation com bootstrap Drupal,
  validação visual e RUNTIME STATUS: DEFERRED no Homelab.
- RUNTIME STATUS: DEFERRED; validar modal, acessibilidade e responsividade no Homelab.

## 0.6.0-dev.23 — componentes ACULTA Messenger — 2026-10-14

Classificação: MINOR da linha aberta (novos SDCs experimentais e contratos de slots).

- Apresentação de conversas, itens de mensagem, composição, avisos Site Alert,
  notificações, contador não lidas e estados vazios/erro/carregamento via SDC.
- Formulários e render arrays permanecem nativos. A severity do Site Alert é
  preservada e mapeada em tokens semânticos; sem JavaScript adicional.
- SHA de implementação: `61939c9`.
- Gates: SDC schemas (21 componentes/334 checks), design foundations (1.816),
  Portal/theme boundary (907) e Twig allowlist ajustada; Foundation gate requer
  bootstrap Drupal e não foi executado nesta cópia sem settings.
- RUNTIME STATUS: DEFERRED; inspeção visual desktop/mobile nos dois modos de cor pendente.

## 0.6.0-dev.22 — durações de movimento em tokens (DT-T19) — 2026-10-14

Classificação: PATCH (sem mudança visual; mesmos valores).

- `portal-account.css`: durações fixas (180ms, 150ms) trocadas por `var(--aculta-motion-fast)` e `var(--aculta-motion-quick)`.
- `tokens.css`: novo token `--aculta-motion-quick: 150ms`, ao lado de `--aculta-motion-fast`.
- Gate de Foundation: PASS (328 verificações). Antes falhava por DT-T19 e pela allowlist de Twig (`login-with`).
- RUNTIME STATUS: verificado no Homelab por gate. Inspeção visual humana pendente.

## 0.6.0-dev.21 — modelo padrão de publicação da Revista — 2026-10-14

Classificação: MINOR da linha aberta (componente novo `experimental`; sem quebra de contrato).

- Novo componente `aculta420:revista-publication`: cabeçalho, capa, chamada, resumo, corpo, rodapé, caixa de autoria
  e link da discussão. Todas as publicações da Revista usam o mesmo layout; não há ajuste por publicação.
- `node--article--full` (Notícia) e `node--blog--full` (blog) chamam o mesmo componente e mantêm as classes de
  visualização completa (`node`, `node--type-*`, `node--view-mode-full`).
- Capa com moldura 4:3 única, recorte pelo centro; blocos de texto na mesma coluna de leitura (68ch).
- Gates: SDC (14 componentes), shell (11), fronteira módulo/tema (847) PASS. Gate de Foundation segue bloqueado
  pela allowlist de Twig (DT-T19), pré-existente.
- RUNTIME STATUS: verificado no Homelab (HTTP 200 em todas as publicações da Revista; captura do artigo e do blog
  de teste). Inspeção visual humana pendente.

## 0.6.0-dev.20 — Revista: imagens do corpo e intertítulos — 2026-10-14

Classificação: PATCH (apresentação da postagem; sem mudança de contrato nem de API).

- Imagens embutidas no corpo de publicações da Revista (Notícia e blog) saem centralizadas na coluna de leitura,
  com cantos e fundo dos tokens do tema, sem distorção (`revista-post.css`).
- Intertítulos (h2/h3) do corpo ficam alinhados à esquerda: o texto justificado abria espaços nos títulos.
- Sem mudança de marcação: a imagem chega como `media--type-image` pelo formato de mídia do Drupal.
- Gate de Foundation atualizado para a versão 0.6.0-dev.20.
- RUNTIME STATUS: verificado no Homelab (HTTP 200 da publicação com duas imagens e captura headless). Inspeção
  visual humana pendente; a publicação sem imagem segue com o erro de cache descrito na PR da importação.

## 0.6.0-dev.19 — sugestões interativas na busca Wiki420 — 2026-10-13

Classificação: MINOR (reutiliza a interação de busca existente em um novo shell).

- A região de busca do cabeçalho Wiki recebe o dropdown de prévia, com superfície
  elevada, lista rolável, foco visível e tokens semânticos do tema.
- A Institution Bar multidomínio não é alterada; o comportamento JS continua no
  `aculta_portal` e a apresentação fica no ACULTA420.
- Design foundations (1683), SDC (231), fixtures do shell (19) e fronteira
  módulo/tema (789): PASS. O HTML do Runtime local contém os atributos neutros
  da prévia e o alvo do dropdown; inspeção visual e navegação por teclado em
  navegador no servidor de testes pendentes. **RUNTIME STATUS: DEFERRED.**
- Gate Foundation permanece bloqueado pela dívida DT-T19 preexistente em
  `css/components/portal-account.css` (durações de transição fora de `tokens.css`).

## 0.6.0-dev.18 — apresentação Wiki420 inspirada na Wikipédia — 2026-10-13

Classificação: PATCH (apresentação e acessibilidade; sem mudança de contrato).

- Categorias da página inicial passam de títulos grandes herdados do tema para
  links compactos em cartões, com foco visível e tokens semânticos.
- O texto dos verbetes recebe espaçamento consistente em parágrafos e listas,
  citação em bloco e legenda de imagem.
- O Domain Header da Wiki fica compacto, sem o menu primário comum, com a marca
  e a busca GET do Portal lado a lado; a Institution Bar continua inalterada.
- No Runtime local, home e verbete renderizam HTTP 200; a busca fica ao lado da
  marca, o menu primário é omitido no cabeçalho Wiki e a Institution Bar segue
  presente no markup global. Gate shell (11), SDC (231) e design foundations
  (1683) passaram. Foundation permanece bloqueado por DT-T19 preexistente em
  `portal-account.css`.
- Chromium não concluiu a captura da versão local. Inspeção visual desktop,
  mobile e claro/escuro no servidor de testes pendente. **RUNTIME STATUS:
  DEFERRED** para validação visual remota.

## 0.6.0-dev.17 — marca de domínio como contrato neutro (fronteira módulo x tema) — 2026-10-13

Classificação: PATCH (sem mudança visual; o asset do logo da Wiki passa a ser montado pelo tema a partir de uma chave do Portal).

- `ThemeHooks::brandMediaRender()` transforma `#brand_key` (`wiki420`) em imagem com o asset do tema. O Portal não referencia mais `themes/custom/aculta420`.
- `PortalBrandHooks`: removida a tentativa de alter de módulo (temas não recebem `hook_*_alter`).
- Teste de compatibilidade com o tema Claro (Core): popup de foto e recorte funcionam; logo da Wiki volta com aculta420.
- `aculta420.info.yml` 0.6.0-dev.17. RUNTIME STATUS: DEFERRED (inspeção visual do cabeçalho da Wiki pendente).

## 0.6.0-dev.16 — Apresentação enciclopédica da Wiki420 — 2026-10-13

Classificação: MINOR (pattern SDC experimental e presenters específicos da Wiki).

- Adiciona o pattern aculta420:wiki-entry para artigos e teasers, com título,
  síntese, áreas de conteúdo, categorias, imagem, referências, relacionados e
  ações nativas condicionadas pelo access do Drupal.
- Redesenha home, busca, cards e alterações recentes com layout de leitura
  enciclopédica, Views/VVJB existentes e tokens semânticos ACULTA420.
- Autoria nominal sai da página pública e das alterações recentes; continua
  armazenada nas entidades e revisões para workflow/administração.
- Gates locais: design foundations (1683), SDC (13 componentes/231 checks),
  shell contract (11) e fronteira módulo/tema (714) PASS. Foundation falha por
  DT-T19 preexistente (transições literais em portal-account.css).
- Local HTTP via host mapeado a 127.0.0.1: home, lista, busca e verbete 200; CSS
  e um único H1 presentes, autoria ausente no HTML. Isso não é o servidor de
  testes. Captura Chromium expirou; inspeção visual e Runtime remoto DEFERRED.
  SHA na main: preencher na etapa de release/merge.

## 0.6.0-dev.15 — Somente a Institution Bar fica fixa no scroll — 2026-10-13

Classificação: PATCH (ajuste de comportamento do shell após teste do responsável).

- A propriedade sticky passa ao elemento `.aculta-institution-bar`. O Domain Header permanece no fluxo normal e rola com o restante da página.
- O deslocamento da Toolbar continua sendo respeitado. Âncoras e foco consideram a altura da barra, sem prender o cabeçalho do domínio. O gate de contrato do shell verifica que somente a barra usa sticky.
- Fronteira: alteração somente de estrutura/apresentação no ACULTA420; `aculta_portal` mantém o contrato neutro e a lógica de domínio.
- Chrome no servidor de testes, autenticado em `/dados/outros-dados`: desktop 1280×800 (scrollY=634) e mobile 390×844 (scrollY=700); a barra permaneceu em `top: 0`, enquanto o cabeçalho do domínio rolou para fora do viewport. Cache Drupal reconstruído antes da verificação. **RUNTIME STATUS: PASS (Chrome desktop/mobile, 2026-10-13). Foundation gate: bloqueado por DT-T19 preexistente (`portal-account.css`); gates de design, SDC, shell e fronteira passaram. SHA do merge na `main`: a preencher no merge.

## 0.6.0-dev.14 — Barra multidomínio persistente no scroll — 2026-10-13

Classificação: PATCH (correção de comportamento do shell; sem novo contrato de componente).

- Um wrapper sticky mantém a barra institucional e o cabeçalho do domínio juntos no topo, respeitando o deslocamento da Toolbar do Drupal. No purpose LINKS, que omite o cabeçalho do domínio, somente a barra fica no wrapper.
- O tema controla a estrutura e a apresentação. `aculta_portal` continua preparando o contrato neutro de identidade e ações; nenhum comportamento funcional ou decisão de Domain foi movido para o tema.
- `scroll-padding-top` foi ajustado para manter destinos de âncora e foco abaixo do shell persistente.
- **Não validado:** inspeção em navegador após alteração; `RUNTIME STATUS: DEFERRED`. SHA do merge na `main`: a preencher no merge.

## 0.6.0-dev.13 — Renderização de botões configuráveis em LINKS — 2026-10-12

Classificação: PATCH (apresenta uma lista de botões já normalizada pelo Portal).

- Renderiza os botões adicionais em sequência vertical, com a mesma apresentação do botão principal.
- Foundation, SDC e markup do Portal: PASS. Ainda sem botões extras salvos no Runtime para inspeção visual destes novos itens.

## 0.6.0-dev.12 — CTA para o site principal em LINKS — 2026-10-12

Classificação: PATCH (texto e destino do link primário existente).

- A página LINKS apresenta “Visite nosso Site” no botão principal; canais sociais continuam no rodapé.
- Reduz o espaçamento vertical externo e interno do pattern, mantendo uma folga mínima entre barra institucional, conteúdo e rodapé.
- Oculta visualmente o título “Redes sociais da ACULTA”, preservando o H1 acessível para tecnologias assistivas.
- Runtime: CSS e HTML atualizados; inspeção visual desta compactação fica pendente.

## 0.6.0-dev.11 — Carrossel de projetos e rodapé LINKS — 2026-10-12

Classificação: MINOR (novo uso do VVJB e rodapé dedicado ao purpose LINKS).

- Reutiliza o display VVJB da View `aculta_projects`, com os cards `node--project--teaser` existentes, navegação por toque/teclado e um card em telas móveis.
- Estende o CSS e o comportamento de foco já existentes no carrossel da home; não cria engine JavaScript própria.
- Usa ícones Bootstrap monocromáticos via `currentColor`, tokens do ACULTA420, e adiciona rodapé específico da página.
- Verificado no servidor de testes por Chromium em 390 px, nos temas claro e escuro; cards, controles VVJB, ícones, logos, barra institucional e rodapé próprios presentes; cabeçalho e rodapé comuns ausentes.
- Foundation gate ainda acusa durações literais preexistentes em `css/components/portal-account.css`; este trabalho não altera esse CSS.

## 0.6.0-dev.10 — Página mobile de links sociais — 2026-10-12

Classificação: MINOR (novo pattern experimental `social-links-page`).

- A página LINKS usa o asset de logo ACULTA existente no tema e links verticais no estilo Linktree para telas móveis.
- O shell preserva somente a barra institucional multidomínio; omite Domain Header, utilitários e rodapé comuns.
- O template recebe os links cadastrados no Drupal e referências aos projetos publicados preparadas pelo Portal.
- Servidor de testes: estrutura HTTP verificada; inspeção visual em navegador permanece pendente.

## 0.6.0-dev.9 — Busca: teclado, popup e prévia — 2026-10-11

Classificação: PATCH (correção de acessibilidade e comportamento; sem componente novo).

- `search.css`: a prévia com muitos resultados rola dentro do diálogo (`max-height: 40vh`), e o campo e o botão Buscar continuam visíveis.
- Verificado no servidor de testes com Claude in Chrome (ver relatório da F3): setas ↑/↓ percorrem os resultados, Tab cicla dentro do popup, Esc fecha.
- Pendente de nova verificação pelo navegador após as correções.

## 0.6.0-dev.8 — Carrinho na barra multidomínio e gaveta lateral — 2026-10-11

Classificação: MINOR (componente novo `experimental`: `cart-offcanvas`).

- `components/patterns/cart-offcanvas`: ícone do carrinho com contagem e gaveta lateral (offcanvas do Bootstrap). Recebe só dados neutros do Portal (contagem, itens, total, URL do carrinho no MAIN).
- `templates/page.html.twig`: o ícone aparece na barra multidomínio, antes do menu da conta.
- Gate de Foundation: versão 0.6.0-dev.8.
- Verificado no servidor de testes (HTML): ícone e gaveta presentes na página da loja; estado vazio ("Sem produto(s) no carrinho.") e banner de apoio.
- Pendente: verificação visual e o estado com itens (ainda não há item no carrinho de teste).

## 0.6.0-dev.7 — Página do produto da loja em SDC (galeria, compra, detalhes e abas) — 2026-10-11

Classificação: MINOR da linha 0.6 (componentes novos, `experimental`; sem alteração de componentes `stable`).

- `components/patterns/product-gallery`: carrossel Bootstrap de imagens do produto, com indicadores, setas e rótulos em português. Props `id`, `label`, `count`; slot `slides`.
- `components/content/product-buy`: categoria, título, preço e compra (formulário do Commerce no slot `purchase`). Props `category`, `title`, `price`.
- `components/content/product-details`: tabela da aba "Informação adicional".
- `components/patterns/product-tabs`: abas Bootstrap "Descrição", "Informação adicional" e "Avaliações (0)", abaixo dos blocos.
- `templates/commerce-product--produto-loja.html.twig`: compõe os componentes em grade de duas colunas (galeria à esquerda; compra à direita). Usa `product.*` (variável que o Commerce entrega). Preço da variação padrão, formatado em R$.
- `css/components/product.css`: só espaçamento do conjunto; o resto fica no CSS de cada componente (`assets` do SDC), com tokens.
- Galeria: imagens em derivado de 720×900 (estilo `produto_galeria`, carregamento imediato) e largura máxima de 480 px, para não ocupar a altura inteira da tela. Slides inicial e controles corrigidos (ver commit da correção).
- Gate de Foundation: template de produto na allowlist de overrides de Twig; versão em `info.yml` e no gate.
- Gates: design foundations PASS (1554); SDC schema PASS (11 componentes, 155 checagens); validadores de navegador PASS; Foundation PASS (312); shell-contract PASS.
- Verificado no servidor de testes (HTML): galeria com 7 imagens, título, categoria, descrição, preço R$ 30,00, tamanhos P a XXG e as três abas. Captura de tela DEFERRED (o Chromium não consegue navegar neste ambiente).
- Pendente (Runtime, não está em `config/sync`): módulo `commerce_product` habilitado, tipo `produto_loja` com `field_descricao`, `field_imagens` e `field_categoria`, tipo de variação `camiseta_tamanho`, displays de visualização e vocabulário `categoria_loja`. Exportar antes do RC.

## 0.6.0-dev.6 — Marca da barra institucional: logo no lugar do nome — 2026-10-10

Classificação: PATCH (apresentação; sem componente, contrato Portal → tema ou dependência novos).

- `templates/page.html.twig`: `p.aculta-institution-bar__brand` imprime a logo branca (`img.aculta-institution-bar__logo`, 78×26, com `alt` "ACULTA - Associação Cultural Antiproibicionista"). O texto "ACULTA" e o nome completo saem da barra.
- `assets/branding/aculta420/web/`: `logo-aculta420-horizontal-branco-78x26.webp` e `-156x52.webp` (2x), gerados com Pillow (Lanczos, lossless) a partir de `source/logo-aculta420-horizontal-branco.png` (1800×600).
- `css/components/institution-bar.css`: `.aculta-institution-bar__logo` com `filter: var(--aculta-shell-logo-filter)`, o mesmo tratamento do cabeçalho principal (escura no claro, sem filtro no escuro). Removida a regra que escondia o nome em telas pequenas.
- Gate de Foundation (`validate-aculta420-foundation.php`): a lista de exportações de branding em `web/` passa a aceitar as duas logos da barra, além do favicon e da logo padrão. Mudança deliberada do gate, não relaxamento.
- `docs/shell.md`: contrato da barra atualizado ("logo ACULTA; sem texto visível").
- Verificado: a página do servidor de testes serve a logo; gates de design, SDC schema e validadores de navegador PASS. Medição do tamanho renderizado por navegador DEFERRED (o Chromium travou neste ambiente ao navegar e capturar tela).

## 0.6.0-dev.5 — F3: apresentação da busca (página, popup e resultados) — 2026-10-10

Classificação: PATCH (apresentação; sem componente, template, contrato Portal → tema ou dependência novos).

- `css/components/search.css`: formulário da página `/busca`, lista de resultados, popup da barra (`.aculta-search-preview`) e botão "pesquisa completa", com tokens semânticos. Carregado pela biblioteca global.
- Botão "pesquisa completa" do popup estilizado com tokens; a regra equivalente do `aculta_portal` (`search-preview.css`) usa cores hexadecimais fixas. A regra do tema tem seletor mais específico (`.aculta-search-preview .aculta-search-preview__full.btn`), então prevalece sem alterar o Portal. A regra do Portal foi removida na `aculta_portal` 0.2.0-dev.46 (PR #186, mesclada).
- Foco por teclado com `--aculta-focus-ring`; movimento reduzido sem transição no botão de envio.
- Lista de resultados sem recuo do Drupal (`.item-list`), ajustada após a captura de tela.
- Gates: SDC schema PASS (98 checagens); design foundations PASS (1508 checagens, contraste dark incluído); browser validators PASS. Gates de Foundation e shell-contract dependem de bootstrap do Drupal (Drush) e não rodaram aqui.
- Verificado no servidor de testes (2026-10-10): `/busca?q=aculta` renderiza com o formulário, o resultado e o `search.css` na agregação, em 1280 px (captura de tela; 390 px antes do ajuste da lista, sem nova captura). Popup da barra e teste com teclado ficam DEFERRED.

## 0.6.0-dev.4 — Ícone de busca na barra multidomínio e alinhamento da barra — 2026-10-10

Classificação: PATCH (ajuste visual; sem componente novo).

- `page.html.twig`: a barra institucional imprime `aculta_search_utility` (ícone de busca, montado pelo Portal) antes do menu da conta.
- `institution-bar.css`: o contêiner de utilidades vira flex com alinhamento vertical centralizado. Antes, o ícone ficava colado no topo, acima de "Minha conta" e "Sair".
- Verificado no Runtime de teste (MAIN e apoio): centro do ícone, da marca e dos links da conta iguais ao centro da barra. Gate de design PASS.
- Depende da PR #180 (F4, 0.6.0-dev.3): ao mesclar, ajustar a sequência de versões.

## 0.6.0-dev.3 — F4 concluída: UI Patterns Library com estados documentados — 2026-10-10

Classificação: MINOR da linha 0.6 (biblioteca navegável e stories; sem alteração de markup nem de componente).

- `ui_patterns` e `ui_patterns_library` passam a constar em `config/sync/core.extension.yml`. Antes estavam habilitados no Runtime e fora do sync, então um deploy novo ficaria sem a biblioteca.
- Seis stories de estados novos, para os componentes do tema: grade de três colunas; seção na variante `participation` sem título; hero só com título; carrossel com um slide; cartão de projeto sem chamada; trilho vazio. Total: 15 stories em 7 componentes.
- Verificado no Runtime de teste (kernel, administradora): visão geral `/painel-administrativo/aparencia/ui/components` 200 com 27 componentes; páginas dos 7 componentes do tema 200, com as stories novas visíveis.
- Gates do tema: SDC schema PASS; design PASS.
- Pendente (roteiro F4): consumidores em Views e Manage Display só quando houver uso real.

## 0.6.0-dev.2 — Validador de navegador: regras de barra, sigla e apoio indisponível — 2026-10-09

Classificação: PATCH (ferramenta de verificação; sem alteração de apresentação, componente ou dependência). Substitui o PR #143 (0.4.6), refeito sobre a `main` para não regredir a versão.

- Regra da sigla: reprova só quando "ACULTA" aparece sem o nome completo "Associação Cultural Antiproibicionista" (`shell.md`: marca "ACULTA + nome completo").
- Barra de conta (`.aculta-utility`, "quando existir"): a presença não reprova mais. A barra não foi alterada.
- Apoio com gateway indisponível: a checagem de botão desabilitado só vale quando há botão.
- Validação: `validate-institution-browser.mjs` com `ACULTA_SITE_ORIGIN` e `ACULTA_SUPPORT_ORIGIN` contra `https://aculta.toca.net.br`: exit 0 (sitemap pelo deployer PASS; apoio no estado indisponível; favicon 200).

## 0.6.0-dev.1 — T5: reconstrução em ambiente novo verificada (ferramenta de conteúdo) — 2026-10-09

Classificação: mudança de ferramenta (`scripts/content`), sem alteração de código do tema; versão vigente mantida.

- Loader do conteúdo cria o que não existe: o nó da home (com `nid` 1, a página inicial) e os 3 destaques editoriais publicados, por UUID. Antes, a home de um ambiente novo ficava sem nó (404).
- `home-content.json`: `nid` no nó da home e os 3 destaques (nós 14, 15 e 16 do Runtime; os exemplos de teste ficam de fora).
- Verificado em ambiente novo (perfil `standard`, configuração importada, sem segredo real): a home mostra 27 de 27 textos declarados; uma segunda execução não altera nada.
- Depende do arquivo de serviços do site (`web/sites/default/services.aculta.yml`), incluído por `settings` em cada ambiente.

## 0.6.0-dev.1 — F4: UI Patterns e biblioteca de componentes — 2026-10-09

Classificação: MINOR (dependência nova e biblioteca navegável; sem alteração de markup nem de componentes).

- Dependências: `drupal/ui_patterns` ^2.0 (2.0.22), com `justinrainbow/json-schema` (validação de props, exigida pelo pacote). Justificativa: decisão do responsável; a biblioteca gera as páginas a partir dos SDCs do tema, sem páginas de exemplo escritas à mão.
- Stories: 9 arquivos `*.story.yml` em `components/` (padrão para os 7 componentes, mais hero sem ações e cartão editorial sem chamada).
- Verificado (kernel, administradora): `/painel-administrativo/aparencia/ui/components` 200; cada componente do tema 200 e com o conteúdo das stories renderizado; páginas públicas 200.
- Gates: design PASS; SDC schema PASS (7 componentes); Foundation via Drush.
- Pendente: consumidores em Views e Manage Display (sem uso real identificado).

## 0.5.1 — F2 (ícones) concluída: Bootstrap Icons sem dependência nova — 2026-10-09

Classificação: PATCH da linha 0.5 (gate e documentação; sem componente, template ou dependência novos).

- Decisão: ícones pelo Bootstrap Icons 1.11.0, já presente em `bootstrap5` e carregado pela biblioteca global `bootstrap5/global-styling`.
- Gate `validate-aculta420-design-foundations.php`: exige que os ícones cheguem pela biblioteca global e reprova SVG inline (`<svg>`) em templates `.twig` e `.html` do tema. Teste negativo: um template temporário com `<svg>` foi reprovado; sem ele, o gate passa.
- Verificado no site público: a família `bootstrap-icons` carrega e `bi-house` renderiza o glifo.
- Sem SDC de ícone: nenhum consumidor com contrato reutilizável ainda.

## 0.5.0 — F1 (modo de cor) concluída — 2026-10-09

Classificação: MINOR da linha 0.5, release da F1. A partir de `dev.1`, a linha reunia medição, contrato do Portal (0.2.0-dev.17 e dev.18) e correção do atributo (dev.3).

- Modo de cor com três estados: claro, escuro e automático. A escolha é da pessoa usuária em Minha Conta > Configurações; sem escolha, vale o padrão do site (claro, por padrão).
- Aplicação por `b5_theme_mode`, com `automático` deixando o `data-bs-theme` vazio; `tokens.css` tem o bloco `prefers-color-scheme` espelhado do bloco escuro.
- Validação no Runtime oficial (2026-10-09): roteiro automatizado 7/7; HTTP `light`/`dark`/`auto` correto; automático segue o sistema no navegador; contraste no escuro com 52 medidas, pior caso 8,82:1.
- Gates da `main` passam: design, Foundation (310), SDC, validadores de navegador e Portal.
- Sem teste humano (decisão do responsável). Tag não criada (não pedida).

## 0.5.0-dev.3 — F1 (modo de cor): atributo via b5_theme_mode — 2026-10-09

RUNTIME STATUS: DEFERRED no Runtime oficial. Em cópia isolada do Runtime (com o Portal 0.2.0-dev.18), requisições HTTP anônimas à página inicial confirmaram o atributo nos três padrões do site, e a escolha de uma pessoa usuária sobrepôs o padrão.

Classificação: correção da 0.5.0-dev.2, dentro da linha 0.5 (F1).

- O `html.html.twig` do Bootstrap 5 grava `data-bs-theme` a partir de `b5_theme_mode` e sobrescrevia o atributo que a 0.5.0-dev.2 colocava em `html_attributes`. Resultado: o modo escuro saía `light`. Passa a usar `b5_theme_mode`: `light`, `dark` ou vazio em `auto`.
- Verificado: site `light` → `"light"`; site `dark` → `"dark"`; site `auto` → `""`; escolha `dark` de usuária com padrão `light` → `"dark"`.

## 0.5.0-dev.2 — F1 (modo de cor): claro, escuro e automático — 2026-10-09

RUNTIME STATUS: DEFERRED (validação no Runtime com o Portal 0.2.0-dev.17 pendente).

Classificação: MINOR da linha 0.5 (F1), com contrato novo do Portal (`aculta_color_mode`).

- `ThemeHooks::preprocessHtml` (`preprocess_html`) aplica `data-bs-theme` em `light` ou `dark`, conforme o contrato do Portal. Em `auto` não grava atributo.
- `css/tokens.css`: bloco `@media (prefers-color-scheme: dark)` guardado por `:root:not([data-bs-theme="light"])`, espelho exato do bloco escuro (copiado por script e verificado pelo gate).
- Gate `validate-aculta420-design-foundations.php` e analisador: aceitam só esse bloco automático (espelhado) e um único ramo de modo em PHP, o consumo do contrato em `ThemeHooks`. Testes negativos: valor adulterado no bloco automático e `@media` fora do formato são reprovados.
- Teste de CSS em Chromium (`prefers-color-scheme: dark`, sem atributo, `light` e `dark`): automático segue o sistema; `light` vence o sistema; `dark` força o escuro.
- Tokens da barra institucional não foram alterados.

Validação: `validate-aculta420-design-foundations.php` PASS (1473 checagens); medição do modo escuro (`validate-color-mode-browser.mjs`) em 0.5.0-dev.1.

## 0.5.0-dev.1 — F1 (modo de cor): medição do modo escuro — 2026-10-09

Classificação: MINOR da linha 0.5 (F1), commit de medição sem mudança de tokens, componente ou JavaScript de produção.

- `scripts/validate-color-mode-browser.mjs`: força `data-bs-theme="dark"` e mede contraste WCAG de textos principais, link, menu e barra de conta em `/`, `/institucional`, `/projetos` e `/contato` (desktop e 390 px). Desliga transições antes de ler.
- Resultado no servidor de testes: 52 medidas, pior caso 8,82:1 (mínimo 4,5:1), sem overflow. Os tokens da barra no modo escuro não precisaram de alteração.
- Leitura inicial de 1,2:1 a 1,7:1 era artefato de transição CSS; corrigido na medição.
- Pendências da F1: controle de modo (local na interface, a decidir sem tocar a barra), persistência anônima e persistência para conta (`aculta_portal`, linha separada).

## 0.4.5 — Fechamento da linha 0.4.x — 2026-10-09

Classificação: fechamento de linha (release). Sem alteração funcional além da marcação; o conteúdo já está em `main`.

- Linha 0.4.x fechada em `0.4.5`. Inclui 0.4.4 (PR #122: gate institucional validado pelo hero), 0.4.5-dev.1 (kit Bloco Sativa 420, PR #124), 0.4.5-dev.2 e dev.3 (cards de projeto, PR #129), 0.4.5-dev.4 (validador de apoio no host SUPPORT, PR #130) e 0.4.5-dev.5 (sitemap pelo `aculta_deployer`).
- A linha 0.4 fecha sem versão `0.4.0` retroativa e sem tag, por decisão do responsável. `0.4.0` seria regressão de versão. A decisão T1 (estruturas internas como conteúdo rico) está em `docs/components.md`.
- Pendências que não bloqueiam o fechamento, registradas em `docs/operations/DEBT-REGISTER.md`: DT-T18 pendente (independente do tema, até concluir o sistema de apoios); T5 com reconstrução em ambiente novo DEFERRED; QA em navegador DEFERRED dos kits (0.4.3 e 0.4.5-dev.1). A F1 (modo de cor) está em avaliação, sem código.

Validação: gate de Foundation via Drush (310 checagens, com a versão 0.4.5); gates de design e de SDC; `aculta-deployer sitemap --env=test` PASS.

## 0.4.5-dev.5 — Sitemap do validador delegado ao aculta_deployer; DT-T18 pendente — 2026-10-09

Classificação: PATCH da linha 0.4.x (ferramenta de verificação e registro; sem alteração de apresentação, componente ou Domain).

- `validate-institution-browser.mjs` deixa de montar o sitemap por conta própria. Chama `aculta-deployer sitemap --env=production|test` (ambiente pelo host do site) e registra a saída em `sitemap-deployer.txt`. O resultado do deployer é o critério de `sitemapValid`.
- Registro: `DEP-0002` (`registry/deploy-registry.json`) traz o valor verificado no servidor de testes (`/support/sitemap.xml` lista `https://apoio.aculta.org/`). A entrada segue aberta e bloqueante até a verificação de produção no RC.
- DT-T18 passa a **Pendente**, independente do tema, até concluir o sistema de apoios. Permanecem abertos: o botão de apoio com gateway ativa (servidor de testes sem gateway) e o favicon oficial.

Validação: `aculta-deployer sitemap --env=test` PASS no servidor de testes; `validate-institution-browser.mjs` executado com `ACULTA_SUPPORT_ORIGIN` (ver saída na PR).

## 0.4.5-dev.4 — Validador de navegador: página de apoio no host SUPPORT (DT-T18) — 2026-10-09

Classificação: PATCH da linha 0.4.x (ferramenta de verificação; sem alteração de apresentação, componente ou Domain).

- `validate-institution-browser.mjs` lê a página de apoio no host SUPPORT (`ACULTA_SUPPORT_ORIGIN`), não mais `/apoio` no domínio principal. O validador de navegador agora exige duas variáveis de ambiente: `ACULTA_DEVTOOLS_PORT`, `ACULTA_SITE_ORIGIN` e `ACULTA_SUPPORT_ORIGIN`.
- Regra do botão de contribuição: com a gateway ativa, o botão aparece após a escolha; com a gateway indisponível, aparece o aviso e o botão não aparece.
- Regra do sitemap: aceita qualquer host oficial `*.aculta.org` e exige o host de apoio.

Execução no servidor de testes (Chromium headless com DevTools): apoio 200 com escolha correta; aviso de indisponibilidade presente com a gateway desativada; favicon 200.

**Falhas reais que o validador agora aponta (não ocultadas):**
- Canonical da página de apoio aponta para o host de teste; a política exige produção.
- O sitemap não inclui o host de apoio.
- O favicon oficial ainda não foi definido: o site serve `aculta420-favicon.ico`, o validador espera `aculta_favicon.ico`.

SHA do merge na `main`: a preencher no merge.

## 0.4.5-dev.3 — Cards de projeto: imagem 256 px com 2x e conteúdo centralizado — 2026-10-09

Classificação: PATCH da linha 0.4.x (apresentação e configuração de imagem; sem componente stable, Domain ou cabeçalho alterados).

- Imagem do card: estilo `aculta_card_1x` (256 px) com fonte 2x `aculta_card_2x` (512 px) via `srcset`, montado em `ThemeHooks::preprocessImageStyle` só para esse estilo. Telas retina recebem a fonte 512 px; telas comuns baixam 256 px, o que reduz o peso no mobile.
- Imagem limitada a 16rem (256 css px), para que a fonte de 512 px dê densidade 2x nítida. Antes ficava em 480 px, com baixa resolução relativa.
- Conteúdo do card centralizado: imagem, categoria, título, descrição e link.
- Configuração exportada: `image.style.aculta_card_1x`, `image.style.aculta_card_2x` e a imagem do teaser de projeto (`core.entity_view_display.node.project.teaser`).
- Medição (Apache, HTTPS, host de testes local): desktop 1280 px, imagem 256×256; mobile 390 px, imagem 256×256.
- Limite: as fontes do kit têm no máximo 512 px (1024 apenas no Bloco). Densidade acima de 2x exige nova fonte, não é possível por reconversão.

Validação: gates de design, SDC e LMS skin PASS; Foundation via Drush com a versão 0.4.5-dev.3. **Não validado**: validador de navegador (DT-T18). SHA do merge na `main`: a preencher no merge.

## 0.4.5-dev.2 — Imagem dos cards de projeto e grade responsiva — 2026-10-09

Classificação: PATCH da linha 0.4.x (correção visual; sem componente stable, template de dados ou Domain alterados).

- Causa: o CSS do card de projeto (`components/content/project-card/project-card.css`) não era entregue ao navegador. O teaser inclui o SDC por `include`, e isso não anexa a biblioteca de componente. O CSS passa para a biblioteca `global`, carregada em todas as páginas.
- Imagem do card: ocupa a largura do card em moldura 1:1, com `object-fit: contain` (sem corte das logos) e fundo `--aculta-surface-muted`. Antes ficava em 480 px fixos, sem moldura.
- Grade `--2` (único consumidor: view de projetos) passa a uma coluna abaixo de 576 px. Antes o card ficava estreito e a imagem reduzia para 105 px em 390 px de largura.
- Medição no Apache (HTTPS, host de testes local): desktop 1280 px, 527×527 px com `contain`; mobile 390 px, 308×308 px.
- Limite: não há `srcset`; a imagem de 480 px é servida em todas as larguras.

Validação: `validate-aculta420-design-foundations.php` e `validate-aculta420-sdc-schemas.php` PASS; `validate-lms-skin.php` PASS. Capturas no navegador (desktop e mobile) conferidas. **Não validado**: gate de Foundation sob o novo número de versão (exige bootstrap; ver nota de versão). SHA do merge na `main`: a preencher no merge.

## 0.4.5-dev.1 — Kit de artes Bloco Sativa 420 (branch feat/bloco-sativa420-media-assets) — 2026-10-09

Classificação: PATCH da linha 0.4.x (assets de identidade, documentação e um validador offline; sem componente, template, Domain ou cabeçalho alterados). Numeração: 0.4.4 está reservada à PR #122; esta PR usa a próxima linha livre (regra de `docs/versioning.md`).

- Kit em `assets/branding/bloco-sativa420/`: originais PNG (horizontal 2048×682; quadrado 1024×1024) em `source/` e nove derivados WebP opacos (horizontal 640, 960, 1280 e 2048 px; quadrado 256, 512 e 1024 px) em `web/`. Copiados byte a byte do pacote; sem reconversão.
- Verificador `scripts/verify-bloco-sativa420-assets.py`: 9 arquivos, bytes, SHA-256, dimensões, formato, proporção dos derivados e opacidade. Caso negativo verificado.
- Documentação em `docs/branding-bloco-sativa420.md`, com índice e README do tema atualizados.
- Nenhum template consome o kit. A arte de projeto pertence à Media Library (`field_image`).

Validação: verificador PASS; fidelidade dos derivados medida (PSNR 25,6 a 38,8 dB contra o original reduzido) e inspeção visual sem perda de texto. **Não validado**: gates de runtime (nenhum consumidor); QA em navegador (DEFERRED). **Pendente**: autoria e licença, confirmadas pelo responsável antes de publicação. SHA do merge na `main`: a preencher no merge.
## 0.4.4-dev.1 — Gates executados no runtime e correção do gate institucional — 2026-10-09

Classificação: PATCH da linha 0.4.x (correção de gate de verificação; sem componente, template, Domain ou cabeçalho alterados).

- Gate `validate-institution.php`: a home (nó 1) é validada pelo título do hero (`field_hero_title`), porque seu conteúdo público está nos campos de hero e nos blocos `home_*` de `home-content.json`, e não no corpo. Os demais nós continuam exigindo corpo. Os nove blocos `home_*` e `institution_data` existem no runtime.

Execução no runtime pelo Drush (`drush scr`, bootstrap do Drupal neste checkout):

- PASS: `validate-aculta420-foundation.php` (309 checagens, inclui a versão 0.4.4-dev.1).
- PASS: `validate-aculta420-shell-contract.php` (9 checagens).
- PASS: `validate-domain-presentation-contract.php` (127 checagens).
- PASS: `validate-home-carousel.php` com `--uri=http://localhost` (registro temporário revertido).
- PASS: `validate-institution.php` (após a correção acima; Twig e YAML do tema).
- PENDING: `validate-final-contact.php`. O formulário é protegido por Turnstile, que rejeita envio automatizado por desenho; o teste exige uma exceção de CAPTCHA aprovada só para teste. O Drush reporta código 1 para o código 2 do script, que significa pendente.
- DEFERRED: `validate-final-sitemap.php`. Espera URLs `https://aculta.org/`; neste ambiente o domínio principal é `aculta.toca.net.br` e o Drupal gera `http://localhost`. Precisa ser executado com o host canônico do ambiente, ou o gate precisa aceitar o host do ambiente.

Validação: os gates acima. **Não validado**: `validate-institution-browser.mjs` (DT-T18, reescrita pendente) e QA em navegador.

## 0.4.3 — Revisão documental dos kits de logos (sem alteração de versão)

Mudança apenas documental, sem alterar código, assets ou `aculta420.info.yml`.

- `docs/branding-podplant420.md`: caminhos de `web/stacked/` e `web/horizontal/` escritos por extenso, a partir da raiz do tema.
- `assets/branding/podplant420/README.md`: referência ao documento de branding corrigida (antes apontava para um diretório inexistente).
- `assets/branding/baque-sativa/README.md`: seção "Publicação" atualizada para o estado instalado; o texto "destino proposto / PR independente" foi removido.
- Autoria e licenças dos kits Podplant420 e Baque Sativa registradas como informadas pelo responsável: produção pelo coletivo420 (ACULTA), que detém as licenças.
- `docs/branding-baque-sativa.md`: contraste medido das variantes (amarelo sobre claro 1,44:1, o que não atende a 3:1) e inspeção ampliada de 64, 96 e 128 px (sem halos; texto ilegível em 64 px). SHAs de merge preenchidos no CHANGELOG das entradas 0.4.2 e 0.4.3.

## 0.4.3 — Kit de logomarcas Baque Sativa (PR #115) — 2026-10-09

Classificação: PATCH da linha 0.4.x (assets de identidade, documentação e um validador offline; sem componente, template, Domain ou cabeçalho alterados). Versão de commit da PR; a versão de merge `0.4.3` será marcada no commit de release antes do merge.

- Kit instalado em `assets/branding/baque-sativa/`: dois originais 1024×1024 (`source/`), 52 derivados PNG/WebP (masters amarelo 521×899 e vermelho 557×957; alturas h64 a h768; quadrados 128 a 512, composição inteira) e `manifest.json` com bytes e SHA-256. Nomes e arquivos preservados como entregues; nada foi reconvertido.
- As variantes `yellow` e `red` são versões da marca, não modos claro/escuro. Não são usadas no cabeçalho global.
- Verificador `scripts/verify-baque-sativa-assets.py`: 54 arquivos, bytes, SHA-256, dimensões, formato e transparência.
- Documentação em `docs/branding-baque-sativa.md`, com índice e README do tema atualizados.
- Nenhuma mudança em Institution Bar, Domain Header, Domain purpose ou SDC genérico. Nenhum template consome o kit nesta fase.

Validação: PASS no verificador do kit, inspeção visual das duas cores em fundo claro e escuro, sem cortes, halos ou distorção visíveis a 520 px, `git diff --check` limpo e links da documentação conferidos. **Não validado**: gate de Foundation (exige bootstrap Drupal, não executado neste checkout); QA em navegador e runtime Drupal (DEFERRED). SHA do merge na `main`: b40929e (PR #115).

## 0.4.2 — Assets visuais Podplant420 (PR #114) — 2026-10-09

Classificação: PATCH da linha 0.4.x (assets de identidade, documentação e um validador offline; sem componente, template, Domain ou Institution Bar alterados). Versão de commit da PR; a versão de merge `0.4.2` será marcada no commit de release antes do merge.

- Quatro identidades aprovadas: stacked e horizontal, para superfícies claras e escuras. Runtime em `assets/branding/podplant420/web/` (10 WebP); pacote de handoff fora do tema, em `handoff/podplant420/` (originais, masters e 720/960 px).
- Derivados WebP com transparência: stacked 128/256/512 px e horizontal 240/480 px no tema. Horizontais 720/960 px só no handoff, sem consumidor.
- Horizontais: somente o canvas transparente excedente foi removido, com margem de segurança; sem deformação, recoloração, filtro CSS ou vetorização.
- Inventário com SHA-256, dimensões, transparência e peso em `handoff/podplant420/asset-inventory.json` (22 arquivos, caminhos relativos à raiz).
- Validadores: `scripts/verify-podplant420-assets.py` (22 imagens) e `scripts/validate-podplant420-assets.php` (10 derivados web).
- Origem, nomenclatura, acessibilidade, responsividade, cache, Media API e anti-regressão em `docs/branding-podplant420.md`.
- Nenhuma mudança em Institution Bar, Domain Header, Domain purpose ou SDC genérico. Nenhuma imagem editorial ou capa foi criada.
- Versão marcada em `aculta420.info.yml` e na asserção do gate de Foundation.

Validação: PASS nos dois validadores de assets, `git diff --check` limpo e conferência visual de fundo claro e escuro. **Não validado**: gate de Foundation (exige bootstrap Drupal, não executado neste checkout); QA visual em navegador e runtime Drupal (DEFERRED). SHA do merge na `main`: 3fefbca (PR #114).

## 0.4.1 — T4, T5 e T6: validadores, conteúdo por UUID e skin do LMS sem variáveis internas — 2026-10-09

Classificação: PATCH da linha 0.4.0 (ferramentas, portabilidade e correção de acoplamento; sem mudança visual). Primeira versão marcada pela regra sequencial por merge (`docs/versioning.md`). Sem componente stable alterado.

**T4 — validadores de navegador (DT-T09)**
- Os oito validadores `scripts/*.mjs` deixaram de fixar a porta DevTools (9223) e a origem (`localhost:8080`). Lêem `ACULTA_DEVTOOLS_PORT` e `ACULTA_SITE_ORIGIN` por `scripts/lib/browser-env.mjs` e param antes de conectar quando elas não existem.
- Gate `validate-browser-validators.php`: 19 checagens; reprova endpoint literal. Caso negativo verificado.
- Execução real: `validate-institution-browser.mjs` conectou ao Chromium e leu o site. Saiu com código 1 pelas expectativas de conteúdo antigas (DT-T18), não pela porta.

**T5 — conteúdo da home por UUID (DT-T10, DT-O03 parcial)**
- `scripts/content/institution/home-content.json` declara 13 blocos `basic` (seções, missão, cabeçalho de projetos) e o hero do nó 1, por UUID. Exportador somente leitura e loader idempotente, em dry-run por padrão (`ACULTA_APPLY=1` grava).
- Gate `validate-institution-content.php`: 39 checagens; o JSON cobre todo UUID das colocações `aculta_home_*` e `aculta_projects_header_*`.
- Dry-run no Runtime: 14 entidades sem diferença. Teste negativo: alteração do slogan detectada.
- DEFERRED: reconstrução completa em ambiente novo; o snapshot em `estados/` é anterior aos campos de seção e do hero.

**T6 — skin do LMS sem variáveis internas (DT-T11)**
- `course-card.css` deixou de definir e de consumir as variáveis `--color-*` do LMS. As propriedades usam tokens ACULTA diretamente, nos mesmos seletores e com a mesma ordem de cascata.
- Medição antes/depois no catálogo: 12 elementos (estado padrão e foco, 1280 e 390 px) sem diferença de estilo computado; capturas idênticas byte a byte.
- Cores de status do LMS (sucesso, informação, aviso, erro, neutro) continuam sendo o fallback do próprio módulo; o tema não as define. Decisão registrada.
- Gate `validate-lms-skin.php`: 10 checagens. Fixa a versão do LMS revisada (1.2.3); um upgrade reprova até a skin e a captura de referência serem revisadas. Caso negativo e simulação de upgrade verificados.
- Limite: estados de status (atividade, avaliação, continuar, reiniciar) não aparecem para visitante anônimo e não foram medidos.

- Versão marcada em `aculta420.info.yml` e na asserção do gate de Foundation; sem tag.

## 0.4.0-dev.6 — Logomarca Wiki420 no Domain Header (PR #112) — 2026-10-09

Classificação: MINOR da linha 0.4.0 (novo asset de identidade e apresentação por purpose); sem componente stable alterado.

- Assets versionados em `assets/branding/wiki420/web/` (horizontal 480/720/960 WebP, empilhada 256/512 WebP, globo 32/48/96/192/512 PNG) e `source/` (masters 960 PNG e 512 PNG).
- Cabeçalho da Wiki420 com `srcset` (480/720/960 px) e `sizes="18rem"`; carregamento eager e `fetchpriority=high`. Integração via `brand_media` do Portal; o tema não inspeciona purpose nem hostname.
- Correção: com `brand_media`, o fallback textual não é montado (decisão no pré-processamento de `ThemeHooks`, template inalterado). Antes havia dois links para a home no cabeçalho da Wiki.
- Verificação: no desktop (1280 px, modo claro) o cabeçalho mede 159 px contra 139 px na principal, ou seja, +20 px de altura; em 390 px, 141 px. Sem overflow horizontal em 1280 e 390 px. Respostas alternadas Wiki/principal (duas rodadas) sem vazamento de marca.
- Limites: masters são derivados (originais não vieram no pacote); razão de contraste da marca no modo escuro não medida; zoom real não testado; o Portal conhece o caminho dos assets (ver `docs/branding-wiki420.md`).

## 0.4.0-dev.5 — T3 concluída: CSS residual dos padrões nos SDCs — 2026-10-09

Classificação: PATCH da linha 0.4.0 (movimentação de CSS sem mudança visual); sem componente stable alterado.

- Hero: `.aculta-hero`, `.aculta-eyebrow`, `h1`, `.aculta-hero-lead` e `.aculta-hero-slogan` em `components/patterns/hero/hero.css`.
- Título de seção: `.aculta-section-title` em `components/patterns/content-section/content-section.css`, com a regra de 575 px.
- Card de projeto: moldura `.aculta-project` (presenter) e filhos em `components/content/project-card/project-card.css`, incluindo a redução de movimento.
- Medição antes/depois: estilo computado idêntico por propriedade em 17 seletores; 14 capturas de tela idênticas byte a byte (1280 e 390 px, páginas `/inicio`, `/institucional`, `/projetos`, `/atividades`, `/noticias`, `/transparencia` e um projeto); foco e movimento reduzido do card idênticos.
- Os seletores de `.card` continuam em `content.css`; não há SDC de card para eles.
- Versão marcada em `aculta420.info.yml` e na asserção do gate de Foundation; sem tag.

## 0.4.0-dev.4 — T2 concluída: rail com cursos reais e foco visível no card — 2026-10-09

Classificação: PATCH da linha 0.4.0 (correção de foco no card do LMS e fechamento da validação em navegador); sem componente stable alterado.

- Rail (DT-T05) validado com quatro cursos reais publicados, temporários e removidos após a medição. Em 1280 px a rolagem por seta moveu 151 px; em 390 px, 310 px; a página não rolou horizontalmente; Tab alcança cada card.
- Foco (DT-T06) medido com foco real (Chromium headless com `Emulation.setFocusEmulationEnabled`). Shell e rail com contorno sólido de 3px. O card do LMS (`lms:course_card`) tinha só a troca de borda para amarelo, cerca de 1,6:1 sobre superfície clara, abaixo dos 3:1 da WCAG 1.4.11. A correção está em `css/components/course-card.css`: anel de 3px com `--aculta-focus-ring` (claro: verde escuro; escuro: amarelo). O seletor espelha o do LMS com maior especificidade, sem editar o contrib.
- Ordem de Tab medida a partir do topo: cabeçalho, depois o rail como parada própria, depois os cards.
- Versão marcada em `aculta420.info.yml` e na asserção do gate de Foundation; sem tag.

## 0.4.0-dev.3 — T2 concluída, rótulos em pt-BR e curso de introdução — 2026-10-09

Classificação: MINOR da linha 0.4.0 (JavaScript do carrossel, catálogo de tradução do tema e conteúdo do LMS); sem componente stable alterado.

- Carrossel da home reativado (`block.block.aculta_home_editorial_highlights`, `status: true`). Validado em navegador: autoplay com movimento normal, parada com movimento reduzido, Enter avança com movimento reduzido, foco visível no botão Próximo.
- Rótulos em inglês corrigidos: catálogo `translations/aculta420.pt-br.po` importado com `--override=none` (12 strings novas). Região do carrossel traduzida no `js/editorial-carousel.js`. Medido no DOM: região "Carrossel", controles "Controles do carrossel", "Slide anterior", "Próximo slide", "Navegação dos slides". Só restam textos mistos em português com a palavra "Login", que são nomes próprios de ação.
- Curso "Introdução ao Antiproibicionismo" (id 1) publicado com 7 lições e 14 atividades. Conteúdo versionado em `scripts/content/courses/introducao-antiproibicionismo.json` e carregado por `scripts/content/load-course-introducao-antiproibicionismo.php` (idempotente, sem apagar nada). Fontes: UNAIDS, Harm Reduction International, MPPR, IPEA, Lei 11.343/2006, nota técnica do MPPR sobre o RE 635.659 e ConJur.
- Rail validado com o curso real: rótulo, card, foco por teclado e largura em 1280 e 390 px. A rolagem com vários cards segue pendente.
- Diretiva de slugs públicos em português em `AGENTS.md` e gate `scripts/validate-public-slugs.php` (linha de base DT-P20, caso do curso em DT-P21).
- LMS em português: 331 strings do módulo `lms` (contrib) que estavam em inglês foram traduzidas em `translations/lms.pt-br.po` e importadas sem sobrescrever as 125 traduções existentes. Verificado pelo serviço de tradução do Drupal em pt-BR (verificar resposta, aguardando correção, retomar treinamento, progresso etc.). Contrib não foi alterado.
- Gates: SDC schemas, design, fixtures, Foundation, shell-contract, domain-presentation e Portal Drupal 11+ — ver o registro de dívidas.

## Validação T2 (sem nova versão), 0.4.0-dev.2 — 2026-10-09

Validação em navegador, sem alteração de código do tema. Versão vigente permanece 0.4.0-dev.2.

- Carrossel da home validado com teclas reais: avanço automático com movimento normal; sem avanço automático com movimento reduzido; Enter avança mesmo com movimento reduzido; contorno de foco no botão Próximo. Space não conclusivo.
- Placement `aculta_home_editorial_highlights` ativada no Runtime só para o teste e restaurada para desativada. Config inalterada.
- Novo achado: rótulos em inglês nos controles e na região do carrossel (DT-T15).
- Rail: ainda validado só com clones; falta curso publicado (DT-T05).

## 0.4.0-dev.2 — Saneamento T0, T1 e T4 — 2026-10-09

Classificação: PATCH de qualidade (gate novo e documentação), dentro da linha 0.4.0. Validado: gates de tema e Portal (ver abaixo).

- Novo gate `scripts/validate-aculta420-sdc-schemas.php`: valida metadados e schema de cada `component.yml`, tipos, enums e defaults, e confirma que cada `include('aculta420:…')` passa só variáveis declaradas. Testado com caso negativo (variável inexistente reprovada).
- Decisão T1: estruturas internas (`aculta-areas`, `aculta-callout`, `aculta-page-intro`, CTAs, notas) permanecem como rich text, registrada em `components.md`.
- T0: `architecture.md`, `design-system.md` e `development.md` descrevem o estado atual, e não a Foundation ou o planejamento.
- Registro de dívidas: DT-T01, DT-T03, DT-T08 e DT-T14 atualizados.
- Gates: SDC schemas PASS (98 checagens); design-foundations PASS; Foundation PASS (309); shell-contract PASS (9); domain-presentation PASS (127); Portal Drupal 11+ PASS (366).
- Não feito nesta fase: T2 (exige view do carrossel ativa no Runtime), T3 (CSS residual), T5 (scripts de migração ainda dependem de IDs do Runtime) e T6 (regressão após upgrade do LMS).

## Versionamento por marcação no código (sem tag), 0.4.0-dev.1 — 2026-10-09

- Política: a versão é assinalada no código (`aculta420.info.yml`, este changelog, roadmap e gate). Tags Git não são criadas sem pedido explícito do responsável. `docs/versioning.md`, `AGENTS.md` e `docs/operations/RELEASES.md` atualizados.
- `aculta420.info.yml` passa a marcar a versão atual, `0.4.0-dev.1`. Asserção do gate de Foundation acompanha.

## Documentação (sem tag), versão vigente 0.3.1 — 2026-10-09
- Limpeza documental: removidos `design-b-qa.md` e `features.md` (histórico no Git, último commit `9c95420`); índices reescritos; referências corrigidas. Regras que sobreviveram ficam em `docs/portal/GUARDRAILS.md` e nos guardrails do tema.

- Revisão documental: registro único de dívidas (`docs/operations/DEBT-REGISTER.md`), roadmap reescrito em saneamento antes de features, sem plano de 1.0.
- Versões obsoletas corrigidas em `README.md`, `docs/README.md` e `docs/features.md`. Regra de mudança apenas documental em `docs/versioning.md`.

## 0.4.0-dev.1 — Rail de cursos — 2026-10-09

Classificação (antes de codar, conforme `docs/versioning.md`): componente `experimental` novo e mudança de configuração da view `courses_catalog` → MINOR, linha 0.4.0, subversão `-dev.1`.

- Novo padrão `aculta420:rail` (experimental): região rolável com `scroll-snap`, sem JavaScript, rotulada pelo título da view, foco por teclado com contorno de token (`--aculta-focus-ring`).
- `views-view-unformatted--courses-catalog.html.twig` envolve as linhas (cards do LMS, sem alteração) no rail.
- Configuração: `views.view.courses_catalog` passa de `grid_responsive` (3 colunas) para estilo de linhas (`default`). Efeito visível: o catálogo vira uma faixa horizontal.
- Validado em Chromium com teclas reais: região e rótulo corretos, rolagem com quatro itens (dois clones de teste, só no DOM), foco por teclado com contorno visível, seta direita rola, sem rolagem horizontal da página em 1280 e 390 px.
- Gates: design-foundations PASS; fixtures 156 e 19 PASS; Foundation PASS (309); shell-contract PASS (9); domain-presentation PASS (127); Portal Drupal 11+ PASS (366); `config:status` só com as exceções já documentadas.
- Não validado: a rolagem com cursos reais, pois o Runtime tem apenas um curso publicado.

## 0.3.1 — Patterns SDC (fase parcial da linha 0.4) — 2026-10-09

Versão decidida pelo responsável para esta fase. Pela regra de `docs/versioning.md`, uma entrega com componentes novos seria MINOR; a exceção está registrada na seção "Decisão de versão" do próprio `docs/versioning.md`. Nenhum componente `stable` teve API alterada.

Mudanças principais, em ordem (SHA na `main`):

- **Padrões SDC experimentais** `content-section`, `hero`, `content-grid` e `carousel` (`d865502`).
- **Campos e placement de seção e hero**: `field_section_title`, `field_section_heading`, `field_section_variant` em `basic`; `field_hero_*` em `page`; placement `aculta_home_mission` separado de "Quem somos" (`db0ac93`).
- **Hook de tema `aculta_section`** declarada pelo `aculta_portal` (`PortalHooks::theme()`) e entregue por `EditorialHooks::entityViewAlter()`; template `aculta-section.html.twig` do tema (`1645359`, `ae94114`).
- **Cabeçalho de projetos por purpose**: dois placements (`aculta_projects_header_home` e `aculta_projects_header_page`) com `aculta_domain_purpose = main`; removido da view (`939eab9`, `7618eba`).
- Títulos de seção usam a barra de título em todas as seções (mudança visual aprovada). Cabeçalho único do Domain Header inalterado.

Validação do estado final (`a286db2`): gates de design, fixtures, Foundation (309 checks), shell-contract, domain-presentation e Portal Drupal 11+ (366) passam; HTTP da home, de `/projetos` e de `cursos` conferido.

Não validado: carrossel em navegador (view da home desativada no Runtime); rail de cursos (não criado).

Pendente para a linha 0.4.0: rail de cursos e estruturas internas em rich text.

## 0.3.0 — Card System v1 — 2026-10-09

- 0.3.0 — Card System v1: novo SDC experimental `aculta420:project-card`, extraído do teaser de projeto com a mesma marcação (`node--project--teaser.html.twig` passa a ser só o presenter). Skin de `lms:course_card` pela `css/components/course-card.css`, com variáveis LMS e botão mapeados para tokens; reduced motion e modo escuro medidos. `editorial-card` sem alteração. Gate de Foundation: discovery e compilação do `project-card` (compilação de SDC agora usa o id do componente) e status `experimental`. Sem product-card, sem variantes e sem abstração de mídia, por falta de consumidor ou duplicação comprovada.

## 0.2.0 — Multidomain Shell Foundations — 2026-10-09

- 0.2-F — Design B QA concluída; linha 0.2.0 fechada. QA em Chromium com teclas reais e medições. Correções: logo branca invisível no header claro (novo token `--aculta-shell-logo-filter`, sem novo asset); anel de foco do header que usava texto invertido (agora `--aculta-shell-domain-text`); sticky do header quebrado pelo `h-100` do wrapper do off-canvas do Bootstrap5 base (override de `content/off-canvas-page-wrapper.html.twig` com `min-vh-100 flex-shrink-0`, incluído na allowlist revisada do gate de Foundation); reduced motion ignorado nos links do shell por especificidade (regra repetida em `navigation.css`). Todos os purposes com shell e tablet verificados; limites em `docs/design-b-qa.md`. Versão do tema: `0.2.0`.
- 0.2-E — Mobile/Sticky Shell: `.aculta-header` passa a `position: sticky` com `top` vindo de `--drupal-displace-offset-top` do Core (respeita o Toolbar), sem compensação no `body` e sem `fixed`; `scroll-padding-top` evita que âncoras e foco fiquem sob o header; controles de shell ganham alvo mínimo de 44px; no mobile o menu expandido rola dentro da viewport. Reaproveita o Collapse do Bootstrap e o `navigation.js` existente. Account dropdown e search trigger não foram criados (não existem no shell; busca é da 0.6.0). Visual QA em navegador fica DEFERRED para 0.2-F.
- 0.2-D — Domain Header: o `<header class="aculta-header">` deixa a superfície verde sólida e passa a usar `--aculta-shell-domain-bg` / `--aculta-shell-domain-text`, com linha inferior `--aculta-border-accent`; navegação, título e slogan herdam esses tokens e o toggle mobile mantém o verde escuro. Mudança visual intencional, prevista em `docs/shell.md` (Domain Header dominante e superfície elevada). Nenhum ramo por purpose ou hostname foi adicionado; visual QA em navegador fica DEFERRED para 0.2-F.
- 0.2-C — Institution Bar: faixa institucional global, discreta e superior ao Domain Header, com marca textual ACULTA (“Associação Cultural Antiproibicionista”, oculta no mobile estreito) e o menu de conta migrado da linha abaixo do header para a faixa (classe `.aculta-utility` mantida). Usa exclusivamente os semantic tokens `--aculta-shell-institution-*` e `--aculta-shell-border`; ordem do DOM: Institution Bar → `<header class="aculta-header">`. A marca não é link: a URL institucional de MAIN depende de dado a ser preparado pelo Portal. `regions.*` permanece `NULL` (sem consumidor no Portal); nenhuma regra de Domain, hostname ou purpose foi adicionada ao tema. Visual QA em navegador fica DEFERRED para 0.2-F. Versão `info.yml` mantida em 0.1.0 até o fechamento da linha 0.2.0.
- 0.2-B.3 conecta `domain_presentation.identity` ao shell sem redesign e deriva somente o fallback realmente consumido (`label` + `home_url`). `page.header` é sempre preservado e o fallback só entra sem `system_branding_block`; `purpose` não é emitido no DOM e `logo_alt` não é repurposed em links textuais. Nenhuma regra Domain/hostname/Portal service foi movida para o tema.
- Adiciona o gate Runtime read-only `validate-aculta420-shell-contract.php` para contrato completo/parcial/ausente, ausência de object leakage e independência funcional do tema.
- 0.2-B.4 extrai `Aculta420ShellContractAnalyzer` e adiciona fixtures independentes do Drupal para branches por purpose em Twig/PHP/JS, qualquer namespace/service Portal, APIs Domain, hosts hardcoded, hostname/service locator, `regions.*` prematuras e regressões de fallback; comentários PHP não contam como dependência real e o gate Runtime reutiliza o mesmo analyzer.
- Corrige o gate B.3 para carregar explicitamente a classe de hook ao testar seu comportamento no Drush e distinguir um fallback explicitamente `NULL` de uma chave ausente; ajusta o docblock para não disparar o verificador estático da Foundation.

- Revisão Codex da PR #83: a análise de literais CSS agora ignora comentários, reconhece funções modernas de cor e nomes de cores em propriedades color-bearing. Fixtures cobrem comentário, `oklch()`, `lab()` e `red`; nenhum CSS visual foi alterado.
- Revisão Codex da PR #83: aplica a checagem de color-mode persistence também a `<script>` inline Twig, detecta mutações de classe com getters de modo, preserva URLs em regex literals ao remover comentários, rejeita statements CSS como `@import` em `tokens.css`, ignora texto comum contendo “dark theme” nas condições e inspeciona `<style>` inline Twig. Fixtures positivas e negativas adicionadas.
- Revisão Codex da PR #83: preserva o predicado Twig em qualquer posição ao redor de ternários, ignora regex literals em corpos de `switch`, resolve `VAR()` sem distinção de caixa, valida o RGB da superfície ACULTA, examina scripts inline Twig e rejeita regras arbitrárias em `tokens.css`; fixtures adicionadas sem alterações visuais.
- Revisão Codex da PR #83: preserva ternários JavaScript com arms objeto, mantém predicados Twig externos, trata case PHP encerrado por `;`, ignora `?.`/`??` como níveis ternários, reconhece getter `getColorScheme()`, writes compostos de `className`, todas as classes de modo em `classList`, regex literals em condições e seletores de atributo parciais; exige a união dos tokens ACULTA/Bootstrap nos dois modos. Fixtures cobrem cada regressão sem alterar CSS visual.
- Revisão adicional da PR #83: valida posição do slash-alpha em `rgb()`, percorre predicados ternários Twig/JavaScript aninhados sem analisar resultados e detecta atribuições diretas de classes de modo por `className`.
- Revisão suplementar da PR #83: permite formas RGB/hex equivalentes para superfícies aprovadas, isola labels em switches mistos/aninhados, cobre chamadas opcionais de `setAttribute`, ternários multiline e valida sintaxe RGB contra mistura inválida de separadores.
- Revisão final adicional da PR #83: aplica comparação numérica também às superfícies dark, trata switches PHP aninhados nos dois estilos, reconhece ternários JavaScript multiline e chamadas opcionais `setAttribute?.()`, e rejeita mistura de separadores legacy/modernos em cores CSS.
- Revisão adicional da PR #83: cobre ternários Twig parenthesizados, `endswitch` aninhado, `if` JavaScript com parênteses balanceados, `setAttribute()` dinâmico e operadores de atributo CSS; valida a borda Bootstrap translúcida e aceita representações numericamente equivalentes de cores.
- Revisão final da PR #83: restringe análise de ternários Twig ao predicado, de PHP `match` às condições dos arms, reconhece flags de atributo CSS `i`/`s` e valida fontes semânticas dos tokens base Bootstrap em cada modo; fixtures cobrem falsos positivos e alteração coordenada de cor/RGB.
- Fortalece o gate dark token-only: detecta seletores/branches alternativos, resolve aliases e cascata de tokens, protege superfícies carvão/grafite e rejeita duplicatas, ciclos e valores sem contraste; adiciona fixtures negativas isoladas.
- Hardening ACULTA420 0.2-A.2: cobre regras de modo dentro de `tokens.css`, arms `switch/case`, ternários Twig em statements, `dataset`, resolução de todos os tokens e mappings Bootstrap por modo, além de raízes Windows; adiciona fixtures positivas/negativas sem alterar valores visuais.
- Revisão automática da PR #83: compõe alpha nos cálculos de contraste, valida o contrato completo de cores/RGB, detecta writes dinâmicos de `dataset`, switches alternativos PHP e switches JavaScript com chamadas, seletores de modo em `:where()`/`:is()`, e ignora comentários PHP nas condições; fixtures agora cobrem esses casos.
- Segunda revisão automática da PR #83: rejeita alpha malformado, cobre atribuições compostas de `dataset`, analisa somente labels de `switch`, remove comentários JavaScript inline com scanner sensível a strings, valida os pares RGB Bootstrap e reconhece `colorScheme`; suíte de fixtures ampliada.
- 0.2-A.1 consolida o contrato dark com superfícies carvão/grafite quentes, texto creme, acentos de marca e verificação WCAG; light permanece inalterado.
- Color mode é token-only: estrutura, layout, tipografia e comportamento são compartilhados; o gate impede branches por modo fora de `tokens.css`.
- Documenta variante de asset de logo apenas como exceção futura de legibilidade, sem mudança de DOM ou dimensões.
- Inicia 0.2-A Semantic Foundations: semantic surfaces/text/borders/interactions, shell tokens, light/dark-ready values e superfície de página Design B; não implementa o shell, Institution Bar ou Domain Header.
- Bootstrap body mapping e componentes globais passam a consumir semantic tokens onde a função visual é clara; primitivas da paleta permanecem em `tokens.css`.
- Adiciona validator estático para valores por color mode, fronteiras do tema, CSS literals e ausência prematura de UI/script de color mode.
- Formaliza autenticação/anti-bot como fronteira de apresentação: ACULTA420 estiliza formulários e markup neutro do Portal sem depender de Social Auth, OAuth, CAPTCHA ou Turnstile.
- CSS de botões usa o contrato semântico `.aculta-auth-provider`, fornecido pelo Portal, em vez de classes específicas de provider.
- Gate da Foundation impede referências técnicas de autenticação e anti-bot no runtime do tema e dependências correspondentes em info/libraries.

## 0.1.0 — Foundation — 2026-10-07

### Identidade técnica

- novo nome: **ACULTA420**;
- novo machine name: `aculta420`;
- novo diretório funcional: `web/themes/custom/aculta420`;
- provider legado de compatibilidade removido antes do fechamento da Foundation; o único tema custom público é `aculta420`;
- provider SDC: `aculta420:*`;
- libraries: `aculta420/*`;
- settings: `aculta420.settings`;
- hooks PHP: `aculta420_preprocess_*`.

### Configuração

- a configuração sincronizada aponta exclusivamente para `aculta420`; compatibilidade com provider histórico não permanece no runtime final;
- `core.extension` passa a instalar `aculta420`;
- `system.theme` passa a usar `aculta420` como default;
- block placements passam a depender do novo tema;
- settings/favicons apontam para o novo diretório;
- IDs de conteúdo/config `aculta_*` são preservados quando não representam o
  provider do tema.

### Design system

- Bootstrap5 permanece infraestrutura;
- Core SDC permanece base de componentização;
- `enforce_prop_schemas: true` ativado;
- `editorial-card` organizado em `components/content/`;
- primeiro SDC stable passa a ser `aculta420:editorial-card`;
- CSS do card continua auto-carregado pelo SDC;
- VVJB permanece engine do carousel editorial.

### Documentação

A documentação histórica de refatoração foi consolidada e substituída por um
conjunto normativo:

- architecture;
- features;
- design-system;
- components;
- development;
- accessibility;
- decisions;
- roadmap;
- versioning;

### Validação Runtime R0.1

- O gate compila o Twig do SDC pelo ID canônico `aculta420:editorial-card`, resolvido pelo loader de componentes do Drupal.
- shell multidomínio planejado, com Institution Bar global e Domain Header por purpose;
- D-012 e regras anti-regressão para impedir hostname/Domain entity no tema;
- instruções de agentes consolidam `aculta420` como único provider e proíbem aliases/shims legados.

### Saneamento pós-rename

- relatório de requisitos do Portal passa a reconhecer `aculta420` como provider público;
- dados institucionais saem do tema e passam para `aculta_portal.settings`;
- validadores ativos passam a inspecionar o contrato atual do provider `aculta420`;
- instruções de agentes deixam de apontar para documentação Portal removida;
- gate de autenticação reflete o baseline atual sem `user_registrationpassword`.
### Limpeza da Foundation

- remove o diretório/provider de compatibilidade temporária;
- remove o runbook transitório de migração da documentação normativa;
- estabelece como gate da 0.1.0 a ausência de provider legado no código e na configuração;
- histórico da transição permanece no Git/ADR, sem virar contrato de runtime.

### Ownership de CSS

- remove o catch-all `css/style.css`;
- footer, institucional/participação, formulários e Conta passam a ter ownership explícito;
- o validador percorre a árvore CSS real em vez de depender de um arquivo agregado obsoleto;
- regra anti-regressão impede recriação de CSS residual genérico.

### Runtime mínimo

- remove override de ícone RSS sem display feed público consumidor;
- remove `css/responsive.css` e devolve media queries aos componentes donos;
- centraliza duração rápida/easing e sombra de hover em tokens;
- remove hook de login duplicado no Portal;
- remove exports web sem consumidor; preserva originais de branding e os assets efetivamente usados;
- alinha defaults de fresh install de logo/favicon ao config sync;
- hooks do tema usam OOP/DI em `src/Hook/ThemeHooks.php`; o arquivo procedural `.theme` deixa de existir.

- remove caminho morto de `system_powered_by_block`/`aculta_site_credit`, sem placement configurado.

- move CSS do carrossel editorial para a library contextual já anexada pela View VVJB;
- registra allowlist dos sete overrides Twig com delta comprovado.

### Gate da Foundation

- cria `scripts/validate-aculta420-foundation.php` como gate runtime exclusivo do tema;
- separa validação ACULTA420 de Portal/Commerce/security;
- valida OOP hooks, provider/config, libraries/assets, Twig/YAML, SDC e invariantes de CSS;
- valida consistência de plugin, settings e dependências por UUID nos block placements ativos e sincronizados;
- corrige documentação normativa do Portal para o provider `aculta420`.

### Base herdada

0.1.0 reaproveita a base madura do antigo tema `aculta`:

- tokens;
- CSS/JS organizados por responsabilidade;
- contextual asset loading;
- cleanup Twig;
- boundary Portal/theme;
- acessibilidade/progressive enhancement.

O histórico detalhado anterior permanece no Git; não é especificação corrente.

## Não versionado — revisão módulo x tema (2026-10-12)

- Regra permanente: **Código no Aculta Portal, Design no ACULTA420** (AGENTS.md, README do módulo, doc do tema, gate
  `scripts/validate-module-theme-boundary.php`).
- Migrado para o tema: shell da Minha conta (`templates/aculta-portal-shell.html.twig` em design), modal de termos,
  editor de foto, caixa de status do carrinho (template e CSS), CSS de Minha conta, CEP (número, tamanho) e recurso de
  layout do CEP (`js/address-layout.js`), relatório de requisitos e suporte. Mapa completo em
  `docs/portal/FRONTEIRA-MODULO-TEMA.md`.
- O módulo mantém fallback neutro (sem classes) nos templates migrados e o comportamento dos JS usa `data-portal-*`.
- Evidência: marcação da Minha conta 8/8 idêntica após normalização; roteiro de aceitação 10/10 com ACULTA420 e com
  Olivero (`docs/portal/TESTES-NAVEGADOR.md`, seção L). Captura de tela não executada.
- Design do Aculta Portal concentrado aqui: `src/Hook/PortalUiHooks.php` traduz `data-portal-ui` em classes (mapa
  `TOKENS`); templates de apresentação do shell, modal de termos, editor de foto, caixa do carrinho, gatilho da busca e
  breadcrumb (`data-portal-breadcrumb`); CSS de Minha conta, Número/CEP, relatórios, suporte e carrinho.
- Caixa do Mercado Pago em Meus pedidos: logo `assets/brands/mercado-pago/mercado-pago.svg`, layout e estilo
  (`css/components/portal-payment-notice.css`, `templates/portal-payment-notice.html.twig`).
- Ícones da Minha conta visíveis no desktop (Bootstrap Icons); ícone de edição da foto em Bootstrap Icons (`bi-pencil`).
- Cores só em tokens: CSS dos componentes (conta, relatório, suporte, modal) sem cor literal. Novos tokens:
  `--aculta-status-*` (erro, ok, atenção) e `--aculta-scrim`, nos modos claro e escuro. Corrige texto ilegível da área da
  conta no modo escuro (antes 1,3:1 a 1,6:1 com cores fixas).

## 0.6.0-dev.32 — menus de conversa e badge contextual — 2026-10-14

Classificação: PATCH da linha 0.6.0 (organização de estilo sobre o componente experimental existente).

- A prévia de mensagens apresenta caixas por remetente, com foco visual no nome e nas conversas recentes.
- As caixas e suas conversas são ordenadas pelo timestamp de atualização fornecido pelo Private Message.
- CSS do tema permanece exclusivamente de apresentação; agrupamento e ordenação pertencem ao ACULTA Messenger.
- O badge de não lidas em Minha Conta usa vermelho como na barra multidomínio; fora do shell `.portal-account`, preserva a cor semântica original.
- Conversas oferecem menu contextual de limpeza pessoal por clique direito e botão acessível; a ação global de excluir thread fica oculta na caixa.
- RUNTIME STATUS: DEFERRED; aplicação e validação visual no checkout local pendentes.

## 0.6.0-dev.36 — navegação de conversa no drawer — 2026-10-15

Classificação: PATCH (navegação da caixa de mensagens existente).

- Abrir uma conversa e voltar à lista acontece dentro do painel lateral com fetch same-origin, sem modal.
- O ACULTA Messenger continua dono de acesso e renderização; o ACULTA420 controla navegação e apresentação.
- A caixa inteira do remetente funciona como área de clique, preservando o menu contextual.
- Verificações estáticas: PHP lint e `node --check` PASS; gates do Portal/tema pendentes.
- RUNTIME STATUS: DEFERRED.

## 0.6.0-dev.63 — tópico do Fórum: ações sociais e respostas organizadas (F4) — 2026-10-17

Classificação: MINOR (novos templates de tópico e de resposta do Fórum).

- `node--forum--full.html.twig`: metadados, abertura da Revista, corpo, barra de ações (seguir, salvar, denunciar) e seção "Respostas".
- Barra de ações: botões do site; estado marcado em destaque (`.flag.action-unflag a`) e rótulos "Seguindo" e "Salvo".
- `comment--comment-forum.html.twig`: resposta em cartão, com avatar pequeno, autor, data, texto e rodapé. Respostas diretas recuam (árvore, configurada no campo `comment_forum`).
- Colunas em `minmax(0, 1fr)`: a imagem da abertura não estica a página no celular.
- Verificado no Runtime com conta de teste logada: desktop e 390 px; estado marcado; resposta aninhada. Modo escuro: pendente.

## 0.6.0-dev.62 — índice do Fórum em cartões (F4, primeira parte) — 2026-10-17

Classificação: MINOR (novo template de lista e novo componente de apresentação do Fórum).

- `templates/forum-list.html.twig` substitui a tabela do módulo forum: contêineres viram seções, fóruns viram cartões com tópicos, posts e última postagem. Mesmas variáveis do módulo.
- `css/components/forum-index.css`: tokens semânticos, mobile primeiro; o botão "Entre para publicar" usa o estilo dos botões de ação do site.
- Verificado no Runtime (growroom420, anônimo) em 1280 px e 390 px. Modo escuro e conta logada: pendentes.
- Pendência de dados (não de tema): o termo "General discussion" vem do instalador do módulo forum e aparece no índice; remover exige decisão do responsável.

## 0.6.0-dev.61 — "Ver todos os avisos" como botão no rodapé da gaveta — 2026-10-16

Classificação: PATCH (ajuste de apresentação de um componente).

- Gaveta de avisos (`alerts-trigger`): rodapé fixo com o botão "Ver todos os avisos", no estilo `btn btn-primary` do botão do carrinho. Nova prop `all_href`, vinda do Portal.
- O rodapé não aparece para visitante (sem `all_href`).
- Gaveta de mensagens (`messenger-trigger`): mesmo rodapé, com "Ver todas as mensagens".
- Gaveta de avisos: corpo rolável (`min-height: 0`, `overflow-y: auto`) e rodapé fora da rolagem.
- Verificado por HTML no Runtime (conta de teste): link absoluto no purpose `account`. Visual na gaveta no Firefox: pendente.

## 0.6.0-dev.60 — apresentação do leitor PDF compartilhado — 2026-10-16

Classificação: PATCH (apresentação/acessibilidade sem API visual nova).

- `css/components/pdf-viewer.css` estiliza o contrato neutro de `aculta_pdf_viewer` usando tokens ACULTA420 e controles Bootstrap compatíveis.
- Layout flexível para toolbar, busca, canvas, estados, fallback de download e tela cheia; alvos de toque de 2,75 rem e foco visível.
- PDF.js/Media continuam donos do comportamento e acesso; o tema só apresenta os atributos neutros do formatter.
- Validação visual no Runtime: DEFERRED até anexar PDF de teste temporário.

## 0.6.0-dev.61 — apresentação do leitor PDF compartilhado — 2026-10-16

Classificação: PATCH (apresentação acessível de formatter já funcional).

- ACULTA420 estiliza os atributos neutros emitidos por `aculta_pdf_viewer` com tokens semânticos, foco visível, alvos de toque, layout adaptável e canvas responsivo.
- PDF.js, Media, autorização e controles continuam sob responsabilidade do módulo; sem markup funcional duplicado no tema.
- Runtime de teste `revista420.aculta.toca.net.br`: PDF.js sample (14 páginas) renderizou em desktop e em 390 px; canvas 652×844/324×420, controles com 44 px e sem overflow horizontal no mobile.
- Fixture temporária (node 287, Media 233, File 413) removida e cache reconstruído. RUNTIME STATUS: ACTIVE no Homelab; Hostinger LiteSpeed e PDF privado permanecem pendentes.

## 0.6.0-dev.64 — contagem de respostas, chamada para visitante e modo escuro do tópico (F4) — 2026-10-17

Classificação: PATCH (dados e estilos novos em um tópico; sem mudança de estrutura).

- Título da seção "Respostas" com a contagem (`forum-reply-total`); chamada para visitante (`forum-topic-visitor`) na barra de ações.
- Verificado no Runtime: visitante (claro e escuro, com o atributo `data-bs-theme` forçado em cópia local), conta logada (desktop e 390 px), respostas em árvore no escuro, índice no escuro.
- Limitação de teste: o modo de cor é decidido pelo Portal (`aculta_color_mode`); a conta logada no escuro foi verificada só pelo HTML.
# Unreleased — migração do tema base — 2026-10-18

- Trocada a base `bootstrap5` pela base Drupal Bootstrap (`drupal/bootstrap` 5.0.2), compatível com Drupal 11.
- Framework Bootstrap atualizado para 5.3.8 e carregado localmente via Composer.
- Bootstrap Icons 1.11.0 copiado para os assets do ACULTA420 com licença MIT preservada, desacoplando os ícones da base anterior.
- Integrado Display Suite 3.37.0, habilitado na configuração exportada, com layouts responsivos ACULTA420 de duas e três colunas.
- RUNTIME HOMELAB: DEFERRED; falta importar a configuração e fazer smoke test visual no ambiente de testes.

