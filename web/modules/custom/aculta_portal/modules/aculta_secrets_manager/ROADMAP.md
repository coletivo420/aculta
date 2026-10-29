# Roadmap

## Feito (0.1.0-dev.1)

- Abas Produção/Testes com estado real só no ambiente ativo.
- Escopo por ambiente no núcleo e negação no controller.
- No-store nas rotas do painel.

## Pendente antes de RC

- Testes Functional (instalação, AJAX dos popups, CSRF com sessão real, teclado). O Kernel de rotas e
  permissões passa no banco de teste dedicado (ver CHANGELOG).
- Decisão do responsável: manter a permissão `administer aculta secrets` única nesta versão. Separar em
  consultar, gerenciar e revelar fica para uma versão futura, com atualização de perfis e sem concessão
  automática.
- Revisão visual no Firefox ESR (DISPLAY=:0) em largura de celular, com Olivero e ACULTA420.
- Procedimento de rotação e recuperação (ver docs/operations/SECRETS.md).
- Guia de migração Portal → submódulo para instalações existentes.

### Revalidação em 2026-10-29

- O módulo no checkout de desenvolvimento (`355e48a89`) é idêntico ao candidato
  nesta branch. HTTP com sessão real passou 9/9; Firefox ESR passou 16/16 no
  painel, 7/7 nos popups sem envio e 28/28 em foco/alvos e larguras 1280/390/320.
- As capturas foram inspecionadas manualmente: a tabela fica ilegível em 320 px,
  com conteúdo quebrado verticalmente; em 1280 px uma coluna também aparece
  comprimida em caracteres/itens verticais. Os asserts atuais de ausência de
  rolagem não verificam largura mínima/legibilidade. Evidência privada em
  `/home/piradopirata/private/aculta/test-evidence/secrets-manager-20261029`.
- Unit: 13/56 PASS. Kernel Routes não iniciou as asserções: erro `Unavailable
  module: commerce_shipping`, 0 assertions. O módulo não está disponível no
  checkout de teste; não foi instalada dependência nem alterada a fixture.
- PHP lint, PHPCS Drupal/DrupalPractice com extensão PHP e `node --check` passam.
- A fase permanece Dev. Bloqueios: correção de layout e reteste; ambiente Kernel
  com `commerce_shipping`; teste Functional de instalação/rollback; revisão
  visual ainda exigida pelo roteiro. Não houve alteração funcional no Runtime.


## Decisão de fronteira

UI administrativa renderizada no Claro mantém CSS mínimo no próprio submódulo, com seletores de classe e `data-portal-ui`. O CSS de abas e segredos não será migrado para o tema; classes equivalentes permanecem para o ACULTA420.
