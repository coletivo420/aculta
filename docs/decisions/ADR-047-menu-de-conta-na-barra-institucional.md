# ADR-047 — Menu de conta na barra institucional do tema V2

- **Status:** proposto pelo responsável em 2026-11-04 (desenho aprovado; implementação pendente).
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-046 (shell, Institution Bar e mobile-first), ADR-044 (CTools e AJAX), ADR-045 (compatibilidade com Drupal 12), `docs/architecture/avatar.md`, `docs/architecture/aculta_user.md`, `docs/portal/ADMIN-DOMAIN-POLICY.md`, `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## 1. Contexto

- A barra institucional é contrato do shell (ADR-046): não é redesenhada sem decisão explícita, e a largura mínima é verificada em todos os purposes.
- Hoje o menu de conta é renderizado por `system_menu_block:account` e montado por `AccountShellBuilder` (Portal). O tema V2 recebe a variável `aculta_account_menu` em `PagePresentationHooks`.
- O usuário logado precisa de acesso rápido a avatar, nome, notificações, carrinho e às ações de conta. O visitante precisa de busca, carrinho e entrada.
- O responsável decidiu que o menu fica **fixo na barra institucional**, em todos os purposes, e que "Entrar" **abre o popup de login** (diálogo).

## 2. Decisões

1. **Local e fixação.** O menu de conta é um item da barra institucional, fixa, presente em todos os purposes. Não há menu de conta separado no cabeçalho de domínio.
2. **Estado logado.** A barra mostra avatar, nome de exibição, sino com contador de notificações, carrinho e um dropdown com Meu perfil, Minha conta e Sair.
3. **Estado visitante.** A barra mostra busca, carrinho e "Entrar". Nenhum dado de conta, nome, avatar ou contador é renderizado para visitante.
4. **Dados vêm do Portal.** `AccountShellBuilder` (ou o serviço do `aculta_user`, se a responsabilidade for dele) monta um contrato neutro `account_menu` com: `authenticated`, nome de exibição (apelido do `aculta_user`), avatar pelo `AvatarResolver` (foto liberada → acervo, nunca inicial), itens da Menu API (`account`, preservando access e cache) e contadores. O tema não lê entidade, usuário, Profile ou `user_picture`.
5. **Cacheability.** Contexto `user` e tags do usuário. Contadores com `max-age: 0` ou atualizados na próxima carga de página. Nenhum conteúdo de conta vai para cache público.
6. **Links entre purposes.** Links para Minha conta e outras áreas de conta saem absolutos pelo `DomainPurposeManager`. Nenhum POST ou proxy de escrita atravessa purpose.
7. **Componente.** Novo SDC `account-menu`, com schema estrito e `with_context = false`, usado dentro da barra institucional. Recebe só o contrato neutro, sem entidade ou usuário.
8. **Entrar abre o popup de login.** O item "Entrar" abre o `<dialog>` nativo de login já existente no tema V2. Não usa `core/drupal.dialog.ajax`, não usa jQuery e não cria novo diálogo. O conteúdo do formulário de login pertence ao `aculta_portal`.
9. **Fallback sem JavaScript.** Sem JavaScript, "Entrar" é um link para a página de entrada do Portal. O JavaScript apenas aprimora a abertura do diálogo. A navegação funciona sem JS (ADR-046).
10. **Dropdown.** Usa `<details>`/`<summary>`. O JavaScript mínimo fecha o dropdown com Esc e com clique fora, via `Drupal.behaviors` e `core/once`.
11. **Mobile-first.** No viewport de 320 CSS px, o nome some antes dos ícones; avatar, sino e carrinho ficam visíveis. O dropdown não sai da tela. Alvos de toque de 44 px. Sem `max-width` como estratégia. Como a barra é fixa, o conteúdo recebe compensação de altura (ex.: `scroll-padding`) para não ficar coberto ao rolar.
12. **Variantes por UI Styles.** As variantes (ex.: compacta no mobile) são plugins de UI Styles, sem classes Bootstrap.
13. **Tokens.** Cores e estados vêm de tokens semânticos. Não há seletor de modo claro ou escuro no componente (ADR-046).
14. **CTools e AJAX ficam fora.** O menu não é wizard nem exportable. Não há troca AJAX. Contadores atualizam por carga de página. Se houver atualização ao vivo no futuro, entra no ponto único de AJAX (ADR-046).

## 3. Consequências

- A barra institucional ganha um item e um dropdown; o restante do shell não muda.
- O Portal passa a ser dono da decisão de exibir ou não o menu e dos dados de conta. O tema só apresenta.
- A validação de largura mínima da barra passa a incluir o estado logado (o mais largo).

## 4. Validação (antes de marcar como concluído)

- Gates do tema: `validate-aculta420-sdc-schemas.php`, `validate-aculta420-design-foundations.php`, `validate-browser-validators.php`, e via Drush `validate-aculta420-foundation.php`, `validate-aculta420-shell-contract.php` e `validate-aculta420-v2-sdc.php`.
- Gate de administração e integrações no MAIN, se o dropdown tiver ação administrativa: `validate-admin-routes-main-domain.php`.
- Teste no Firefox: 320 px a desktop, claro e escuro, zoom de 200%, teclado (Tab, Enter, Esc), logado e visitante, em cada purpose.
- Verificação de rolagem horizontal da página em 320 px com a barra fixa.
- Testes das páginas do Portal também com o tema Olivero (CLAUDE.md).
- Runtime: `RUNTIME STATUS: DEFERRED` até o teste no servidor de desenvolvimento.

## 5. Não-decisões

- Não define o conteúdo do formulário de login (pertence ao `aculta_portal`).
- Não define a lista final de itens do dropdown além de Meu perfil, Minha conta e Sair.
- Não define se o contador de notificações será atualizado ao vivo.

## 6. Pendências

- Confirmar com o responsável a lista final de itens do dropdown e o texto do contador do sino.
- Verificar a largura mínima da barra com o estado logado em 320 px, após a implementação.

## 7. Emenda de 2026-11-05 (desenho do menu de conta)

Esta emenda substitui os itens 2, 3 e 8 nos pontos abaixo. Os demais itens continuam valendo.

1. **Barra institucional sem rótulos de conta.** Sem "Minha conta" nem "Sair" na barra, em qualquer largura.
2. **Menu geral em hambúrguer.** O menu fica na barra institucional, como um botão `list`, com o mesmo comportamento do menu hambúrguer que hoje fica no cabeçalho do domínio principal. A conta entra nesse menu geral; não é um menu só de conta. Não há ícone de conta separado.
3. **Visitante, dois pontos de entrada.** (a) O menu mostra "Entrar" como texto comum, sem destaque. (b) A barra institucional mostra um botão "Entrar" de destaque (`--color-brand-highlight` e `--color-on-highlight`), fora do menu. Ambos abrem o popup de login (item 8). Sem JavaScript, vão para a página de entrada (item 9).
3a. **Após o login, nenhum "Entrar" aparece.** Os dois desaparecem; o menu passa a mostrar o cabeçalho da conta e os itens (item 4).
4. **Autenticado.** O menu mostra, no topo, o avatar (sem inicial) e o nome de usuário, sem clique e sem edição. Logo abaixo, um nível já expandido, sem recolher: "Minha conta" e "Meu perfil". "Sair" por último. O avatar não é botão.
5. **Gatilho decidido em 2026-11-05.** Botão `list` (hambúrguer) na barra institucional. Substitui as opções A, B e C que estavam em aberto.
6. **Contrato do Portal.** Além de `authenticated`, `aculta_account_avatar` e `aculta_account_menu`, o `aculta_user` entrega `aculta_account_name` (string, nome de exibição definido pela política do Portal). Sem nome, o cabeçalho mostra só o avatar. O tema não deriva nome de usuário, e-mail ou inicial.
7. **Estado atual do código.** Em 2026-11-05, o `account-menu` já é renderizado dentro de `.aculta-utility` da barra institucional (`multidomain-bar.twig`). Não há menu de conta na faixa de domínio dos templates V2. Se o menu aparece na faixa de domínio em alguma página, a origem é outro template ou o Portal, e precisa de URL ou captura para ser localizada.
8. **Trabalho pendente.** Mudança no tema (SDC `account-menu`; `PagePresentationHooks` passa `aculta_account_name`) e no `aculta_user` (`AccountMenuPresentationBuilder` devolve o nome). Gates e capturas em 320, 390 e 1440 px, claro e escuro, com três cenários: foto, acervo e sem foto.
