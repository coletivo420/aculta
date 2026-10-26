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
- Separar a permissão em consultar, gerenciar e revelar. Não concedida automaticamente: exige
  decisão e atualização de perfis.
- Revisão visual no Firefox ESR (DISPLAY=:0) em largura de celular, com Olivero e ACULTA420.
- Procedimento de rotação e recuperação (ver docs/operations/SECRETS.md).
- Guia de migração Portal → submódulo para instalações existentes.
