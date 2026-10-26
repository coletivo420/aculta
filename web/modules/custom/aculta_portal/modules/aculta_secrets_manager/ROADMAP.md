# Roadmap

## Feito (0.1.0-dev.1)

- Abas Produção/Testes com estado real só no ambiente ativo.
- Escopo por ambiente no núcleo e negação no controller.
- No-store nas rotas do painel.

## Pendente antes de RC

- Testes Kernel e Functional (instalação, rotas, permissões, CSRF, AJAX dos popups, teclado, mobile).
  Um Kernel isolado do submódulo não sobe: `aculta_portal` exige a pilha ACULTA inteira (commerce, LMS,
  aculta_user e outros) no contêiner. Só é viável com o site instalado; executar no servidor de testes
  com autorização.
- Decisão do responsável: manter a permissão `administer aculta secrets` única nesta versão. Separar em
  consultar, gerenciar e revelar fica para uma versão futura, com atualização de perfis e sem concessão
  automática.
- Revisão visual no Firefox ESR (DISPLAY=:0) em largura de celular, com Olivero e ACULTA420.
- Procedimento de rotação e recuperação (ver docs/operations/SECRETS.md).
- Guia de migração Portal → submódulo para instalações existentes.
