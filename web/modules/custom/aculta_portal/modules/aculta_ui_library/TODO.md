# TODO — aculta_ui_library

Estado: retirada aprovada · o Config Sync já não habilita este módulo; versão 0.1.0-dev.2 permanece no código apenas até a desinstalação Runtime.

## Entregas concluídas

- A rota administrativa antiga redireciona para `ui_patterns_library.overview`.
- O escopo MAIN e o acesso estrito de administrador continuam protegendo a rota de compatibilidade.
- Catálogo e prévias próprias de blocos foram removidos; UI Patterns Library é a fonte única de SDCs e stories.

## Próximo passo

- Desinstalar `aculta_ui_library` numa janela estrutural com backup/restore e preflight liberado.
- Depois da desinstalação, apagar o módulo, testes, alias, gate, status e referências ativas; manter `ui_patterns_library` habilitada.

## Limites

- Não cataloga nem renderiza blocos; não duplica UI Patterns Library.
