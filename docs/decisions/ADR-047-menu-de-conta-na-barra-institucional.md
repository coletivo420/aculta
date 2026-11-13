# ADR-047 — Menu de conta na barra institucional do tema V2

- **Status:** proposto pelo responsável em 2026-11-04 (desenho aprovado; implementação pendente).
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-046 (shell, Institution Bar e mobile-first), ADR-044 (CTools e AJAX), ADR-045 (compatibilidade com Drupal 12), `docs/architecture/avatar.md`, `docs/architecture/aculta_user.md`, `docs/portal/ADMIN-DOMAIN-POLICY.md`, `docs/portal/PAYMENT-DOMAIN-POLICY.md`.

## 1. Contexto

- A barra institucional é contrato do shell (ADR-046): não é redesenhada sem decisão explícita, e a largura mínima é verificada em todos os purposes.
- Hoje o menu de conta é renderizado por `system_menu_block:account` e montado por `AccountShellBuilder` (Portal). O tema V2 recebe a variável `aculta_portal_menu` em `PagePresentationHooks`.
- O usuário logado precisa de acesso rápido a avatar, nome, notificações, carrinho e às ações de conta. O visitante precisa de busca, carrinho e entrada.
- O responsável decidiu que o menu fica **fixo na barra institucional**, em todos os purposes, e que "Entrar" **abre o popup de login** (diálogo).

## 2. Decisões

1. **Local e fixação.** O menu de conta é um item da barra institucional, fixa, presente em todos os purposes. Não há menu de conta separado no cabeçalho de domínio.
2. **Estado logado.** A barra mostra avatar, nome de exibição, sino com contador de notificações, carrinho e um dropdown com Meu perfil, Minha conta e Sair.
3. **Estado visitante.** A barra mostra busca, carrinho e "Entrar". Nenhum dado de conta, nome, avatar ou contador é renderizado para visitante.
4. **Dados vêm do Portal.** `AccountShellBuilder` (ou o serviço do `aculta_user`, se a responsabilidade for dele) monta um contrato neutro `portal_menu` com: `authenticated`, nome de exibição (apelido do `aculta_user`), avatar pelo `AvatarResolver` (foto liberada → acervo, nunca inicial), itens da Menu API (`account`, preservando access e cache) e contadores. O tema não lê entidade, usuário, Profile ou `user_picture`.
5. **Cacheability.** Contexto `user` e tags do usuário. Contadores com `max-age: 0` ou atualizados na próxima carga de página. Nenhum conteúdo de conta vai para cache público.
6. **Links entre purposes.** Links para Minha conta e outras áreas de conta saem absolutos pelo `DomainPurposeManager`. Nenhum POST ou proxy de escrita atravessa purpose.
7. **Componente.** Novo SDC `account-menu`, com schema estrito e `with_context = false`, usado dentro da barra institucional. Recebe só o contrato neutro, sem entidade ou usuário.
8. **Entrar abre o popup de login.** O item "Entrar" abre o `<dialog>` nativo de login já existente no tema V2. Não usa `core/drupal.dialog.ajax`, não usa jQuery e não cria novo diálogo. O conteúdo do formulário de login pertence ao `aculta_portal`.
9. **Fallback sem JavaScript.** Sem JavaScript, "Entrar" é um link para a página de entrada do Portal. O JavaScript apenas aprimora a abertura do diálogo. A navegação funciona sem JS (ADR-046).
10. **Dropdown.** Usa `<details>`/`<summary>`. O JavaScript mínimo fecha o dropdown com Esc e com clique fora, via `Drupal.behaviors` e `core/once`.
11. **Mobile-first.** No viewport de 320 CSS px, o nome some antes dos ícones; avatar, sino e carrinho ficam visíveis. O dropdown não sai da tela. Para esta fase, o responsável fixou os alvos da Institution Bar em 35 px e os ícones em 30 px; demais controles preservam 44 px mínimos. Sem `max-width` como estratégia. Como a barra é fixa, o conteúdo recebe compensação de altura (ex.: `scroll-padding`) para não ficar coberto ao rolar.
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
- Runtime: `RUNTIME STATUS: DEFERRED` até o teste no servidor de desenvolvimento.

## 5. Não-decisões

- Não define o conteúdo do formulário de login (pertence ao `aculta_portal`).
- Não define a lista final de itens do dropdown além de Meu perfil, Minha conta e Sair.
- Não define se o contador de notificações será atualizado ao vivo.

## 6. Pendências

- Confirmar com o responsável a lista final de itens do dropdown e o texto do contador do sino.
- Verificar a largura mínima da barra com o estado logado em 320 px, após a implementação.
