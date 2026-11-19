# Leitor de tela (Orca) — botões em estados autenticados

Teste com leitor de tela real para os botões do SDC `aculta420_v2:button` em
páginas que exigem sessão autenticada. Usa o log de depuração do Orca
(`--debug-file`) para conferir o nome acessível que o leitor anuncia ao focar
cada botão.

## Requisitos

- Sessão gráfica em `DISPLAY=:0` com AT-SPI ativo (`at-spi2-registryd`).
- Orca em execução com log de depuração fora do repositório:
  `orca --replace --debug-file=/caminho/fora/do/repo/orca.log`.
- `geckodriver` em `http://127.0.0.1:4455` (`GECKODRIVER` para outro endereço).
- `SUITE_OUT` e `ORCA_LOG` fora do repositório (o log contém fala e nomes de
  conta; não deve ser versionado).
- Conta de teste administrativa (`ACULTA_ADMIN_TEST_UID`, padrão `257`), obtida
  por `drush uli`. O teste só navega e foca; não envia formulários.

## Comando

```sh
DISPLAY=:0 \
DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus \
ORCA_LOG=/caminho/fora/do/repo/orca.log \
SUITE_OUT=/caminho/fora/do/repo/out-AAAA-MM-DD \
node tests/browser/transversal/aculta-botoes-a11y/orca-buttons.mjs
```

## O que verifica

Em cada página autenticada abaixo, para todo `.button` visível:

1. que existe botão visível;
2. que todo botão tem nome acessível (`aria-label`, `value` ou texto);
3. que o Orca anuncia o nome ao receber foco (procura `[link: '…']` ou
   `[push button: '…']` no log desde o início da página).

Páginas cobertas:

| Apelido | URL |
| --- | --- |
| `conta` | `https://conta.aculta.toca.net.br/` (calendário do Studio) |
| `eventos` | `https://eventos.aculta.toca.net.br/culturas` (painel do organizador) |
| `admin-redes` | `https://aculta.toca.net.br/painel-administrativo/aculta/redes-sociais` |
| `admin-caminhos` | `https://aculta.toca.net.br/painel-administrativo/aculta/caminhos` |

Saídas: `results.json`, `buttons-<página>.json` e `orca-buttons-<página>.txt`.

## Resultado de 2026-11-08

- **12/12 verificações OK**, 22 botões anunciados:
  `conta` 4, `eventos` 6, `admin-redes` 3, `admin-caminhos` 9.
- Botões só com ícone (`Propor novo evento`, `Mês anterior`, `Próximo mês`)
  foram anunciados pelo `aria-label`.
- Botões de envio (`Filtrar`, `Salvar`, `+ Adicionar botão`) foram anunciados
  como `push button`.
- Resumo versionado em `tests/browser/capturas/botoes-a11y/2026-11-08/results.json`.
  O log de fala completo fica fora do repositório.
- Limitação: páginas `conta`/`eventos` usam o tema V2; as rotas
  administrativas usam o Gin, então o envio ali é o `<input type="submit">` do
  Gin (nome acessível vem de `value`). O teste cobre os dois casos.
