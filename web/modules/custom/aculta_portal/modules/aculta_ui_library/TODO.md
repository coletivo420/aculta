# TODO — aculta_ui_library

Estado: Dev · versão 0.1.0-dev.2 (`aculta_ui_library.info.yml`).

## Entregas concluídas

- Controle de acesso estrito via `UiLibraryAccessCheck` exigindo cumulativamente papel `administrator` e permissão `administer blocks`.
- Suíte unitária `UiLibraryAccessCheckTest` cobrindo 6 cenários de controle de acesso (visitante, usuário comum, editor, superusuário).
- Navegação integrada com a UI Patterns Library e UI Suite via botões neutros `aculta_portal_button` com token `ui-library-nav`.
- Gate `validate-aculta-ui-library.php` com 23 verificações aprovadas.

## Próximo passo

- Monitorar primeiros acessos da equipe de administração e acompanhar catálogo de novos blocos.

## Limites

- Não altera configuração de bloco; não substitui UI Patterns Library nem Layout Builder.
