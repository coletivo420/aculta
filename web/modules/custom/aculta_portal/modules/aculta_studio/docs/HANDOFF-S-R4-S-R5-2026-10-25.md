# Handoff S-R4 → homologação e S-R5 (2026-10-25)

**Escopo:** PR [#297](https://github.com/coletivo420/aculta/pull/297), branch `docs/aculta-forum-0.1.0-dev.1`, repositório `coletivo420/aculta`. Confira o HEAD atual, não use SHAs deste documento como base imutável. Preserve commits de outras frentes. Sem nova PR, merge ou deploy sem autorização.

## Contratos inegociáveis

- `/` do purpose **ACCOUNT** apresenta o hub Studio; `/studio` é rota técnica bloqueada 404 quando acessada diretamente.
- `/conta-legada` é a Minha Conta original **permanente**. `/minha-conta` técnico 404. Não remover shell, menus, JS nem formulários do Portal legado.
- `/studio/conta` e `/studio/perfil` são experiências **separadas**. Documento CPF/CNPJ, e-mail, dados cadastrais, Commerce, pedidos, apoio, autenticação e segurança são da Conta. Apelido, foto/avatar, bio, redes e visibilidade pertencem ao Perfil/User.
- O perfil público `/u/{apelido}` só vive no purpose MAIN; `PublicProfileLink` e `PublicProfilePolicy` determinam link e dados públicos. Sem apelido resolvido, não exibir `User::getDisplayName()` como identidade comunitária.
- ACULTA420 controla apenas Twig, CSS, HTML, schema e slots dos SDCs; User controla dados/Form API/políticas e `aculta_image` é o pipeline de todas as imagens. Studio só compõe. Evitar duplicate owner, storage, formulário, URL e padrões de CSS de tema no módulo.

## Implementação que já está no código

Studio `0.1.0-dev.22`: `StudioProfileController::page()` usa `#type: component` `aculta420:profile-editor` com componentes aninhados `profile-identity`, `profile-bio` e `profile-social`. `AuthorIdentityResolver` e `PublicProfileLink` continuam donos de identidade/link, `AccountBioCard` e `SocialLinksCard` dos valores; avatar do titular via `aculta_image.render`. Os formulários originais são acessados por rotas dedicadas em Studio, mantendo o fluxo AJAX de foto. Fallback neutro `Apelido não informado` substitui username privado.

Tema ACULTA420 `0.7.0-dev.2`: quatro SDCs `experimental`, cada qual com esquema, Twig, CSS e duas stories. Unit `ProfileSdcContractTest` e Kernel `ProfileSdcRenderKernelTest` preparados. Os testes **não foram executados** neste ambiente.

## Passos do agente com checkout completo (obrigatório)

1. Confirmar `git status --short`, `git branch --show-current`, `git rev-parse HEAD`, `gh pr view 297 --json headRefName,headRefOid,state` e arquivos de outras frentes. Trabalhar na **mesma PR #297**, commits pequenos focados. Consultar `AGENTS.md`, regras locais Studio/tema e `docs/architecture/environments.md`. Nunca aplicar reset, force-push ou commit misturado.
2. Executar lint `php -l` nos arquivos PHP alterados; PHPCS e PHPUnit conforme configuração **real** do repositório. Mínimo:
   - `web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Unit/StudioProfileControllerTest.php`
   - `web/themes/custom/aculta420/tests/src/Unit/ProfileSdcContractTest.php`
   - `web/themes/custom/aculta420/tests/src/Kernel/ProfileSdcRenderKernelTest.php`
   - testes Unit correlatos S-R3/B1/B2/B3.
   Registrar comando, código de saída e números de testes. Corrigir falhas observadas, não ajustar expectativa para mascarar erro.
3. Rodar `php scripts/validate-aculta420-foundation.php`, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php`, `php scripts/validate-no-temporary-route-aliases.php` e `php scripts/validate-aculta-image.php`. Se houver falha preexistente, identificar a causa e não alterá-la fora do escopo sem autorização.
4. Com Drush e **apenas no ambiente de teste identificado**, confirmar `aculta_studio.profile` = `/studio/perfil` no ACCOUNT, os quatro SDCs descobertos em `plugin.manager.sdc`, CSS agregado, render real, props/slots, `enforce_prop_schemas`, tags/contextos de cache e absence de `#component` desconhecido. Comparar também com Olivero (tema não pode determinar ACL).
5. Usar **duas contas de teste distintas** com perfil publicado/privado e com foto pública/não pública; verificar render de cada titular, ausência total dos dados da outra conta, que avatar próprio privado aparece apenas para titular, que `/u/{apelido}` continua sujeito a `PublicProfilePolicy`. Sem nickname, label neutro e nenhum slug inferido. Validar nenhuma exposição de CPF/CNPJ, e-mail, checkout ou outros dados de Conta na experiência Perfil.
6. Validar rotas e handlers do User: `/studio/perfil/apelido`, `/studio/perfil/privacidade`, `/studio/perfil/bio`, `/studio/perfil/redes/{rede}`, modal `aculta_user.foto` (upload, crop e proteção `aculta_image`). Preservar POST/CSRF/Form API; repetir sem JS e teclado. Testar valores vazios, preenchidos, validação e cancelamento sem perda de dados.
7. Executar no Firefox ESR **visível** com `DISPLAY=:0`, geckodriver, usando os testes existentes `tests/browser/aculta-studio/`. A suíte `visual.mjs` atual cobre majoritariamente Marley e não prova S-R4. Criar/estender script focado em Perfil na mesma pasta, com README e artefatos em `$SUITE_OUT`. Conferir 320, 375/390, 768, 1024 e 1440 px, zoom, claro/escuro, foco, ARIA, sem overflow, AJAX/no-JS e comparação S-R5 com `/conta-legada`. Nunca usar headless nem testar em produção.
8. S-R5: elaborar matriz por campo/ação/estado entre a Conta Studio e legado `/conta-legada`, além de Meu Perfil e seu perfil público em MAIN. Comparar CEP/Número/S/N, CPF/CNPJ opcional e máscara, senha/OAuth, preferências, pedidos e assinaturas, e bio/foto/redes/privacidade, com prints e evidências de permissões por domínio. Não remover o legado.
9. Atualizar `CHANGELOG.md`, README, roadmap, `docs/operations/status/`, índice raiz e gate de versão conforme regras; publicar relatório PASS/FAIL/SKIP e bloqueios. RC somente após gates concluídos, sem mesclar/deploy sem pedido expresso.

## Cuidados e lacunas conhecidas

- `aculta_id_cpf` opcional tem limite **transacional de unicidade concorrente** na verificação HMAC; não alegar resolvido nesta frente. Sem owner, documentos Studio retornam 404. Não criar CPF em Profile.
- Avaliar a compatibilidade `GeneratedUrl`/url generator nos Unit e a renderização de slots SDC. Confirmação estática com documentação Drupal 11 não substitui teste Kernel real.
- Os SDCs experimentais só se tornam `stable` com validação de consumidores, acessibilidade e render real.
- A PR recebe commits paralelos: use HEAD atual como base e sempre `git fetch` antes de editar. Nunca sobrescrever alterações de Fórum, Wiki, Messenger, tema ou outras frentes sem inspecionar.

## Condição de conclusão

S-R4 somente homologada com Unit/Kernel/Functional, ACL/cache/Domain, HTML/UI Patterns e browser/testes de duas contas aprovados. S-R5 exige matriz funcional e visual; não há remoção planejada de `/conta-legada`. O agente deve implementar correções verificadas em commits focados na **PR #297** e entregar relatório com SHA, testes reais, pendências e próximo handoff.
