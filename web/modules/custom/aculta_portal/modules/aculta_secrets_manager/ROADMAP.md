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


## Decisão de fronteira

UI administrativa renderizada no Claro mantém CSS mínimo no próprio submódulo, com seletores de classe e `data-portal-ui`. O CSS de abas e segredos não será migrado para o tema; classes equivalentes permanecem para o ACULTA420.
