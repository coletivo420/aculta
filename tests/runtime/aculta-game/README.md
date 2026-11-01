# Verificação HTTP do Game

Executar somente após conferir hostname, diretório, Git e Drush. O banco precisa
ser `aculta_runtime`; os dois hosts são validados contra `test_host` do Deployer.
Usa a conta existente `teste.comum`, seu perfil público e uma conquista existente;
admin existente para operações administrativas. Não imprime links de login.

```sh
python3 tests/runtime/aculta-game/admin-smoke.py "$GAME_MAIN_BASE" "$GAME_ACCOUNT_BASE"
```

Fornecer as URLs de MAIN e ACCOUNT do mapeamento do Deployer. Não usar produção.
O script inclui o smoke HTTP básico e verifica POST real, preferências entre
sessões, cache, critérios, CRUD de definição, concessão, revogação e histórico.
A fixture temporária deve estar ausente antes da criação. `finally` remove apenas
sua definição/concessões e restaura preferências/disponibilidade anteriores.
Não altera usuários, pedidos ou conteúdo. Logins normais atualizam a sessão.

Resultados privados locais em `.suite-out/game/`, fora do Git. Para navegação AJAX,
mobile e capturas usar `tests/browser/aculta-game/smoke.mjs`, Firefox/geckodriver
próprios e variáveis `SUITE_BASE`, `SUITE_LOGIN_URI`, `GAME_ACCOUNT_BASE`,
`GECKODRIVER` e `SUITE_OUT`. Não encerrar drivers/sessões de outra execução.
