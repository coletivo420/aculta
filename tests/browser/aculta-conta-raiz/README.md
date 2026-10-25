# Raiz da Conta (Domain ACCOUNT): verificação anônima no navegador

Suíte do roteamento da raiz `/` e de `/conta-legada` no host de conta. Roda no **Firefox ESR visível** na sessão X11 local (`DISPLAY=:0`), pelo geckodriver, via `tests/browser/aculta-cpf-checkout/wdlib.py`. Não usa Chromium nem headless.

## O que cobre

| Caminho | Esperado (anônimo) |
| --- | --- |
| `/` | redireciona para `/entrar` (login) |
| `/conta-legada` | redireciona para `/entrar` (login) |
| `/minha-conta` | HTTP 404 |
| `/studio` | HTTP 404 |
| `/entrar` em 390 px e 1280 px | sem rolagem horizontal |

O status HTTP vem de `performance.getEntriesByType('navigation')[0].responseStatus`. As capturas e o `resultado.json` ficam em `SUITE_OUT`.

## Ambiente

Servidor de testes (`*.aculta.toca.net.br`), host `conta.aculta.toca.net.br`. Não é produção e não é o ambiente local `127.0.0.1`. Não usa senha nem link de login: só navega como visitante.

## Como executar

```sh
DISPLAY=:0 SUITE_OUT=/tmp/aculta-conta-raiz python3 tests/browser/aculta-conta-raiz/verifica_raiz.py
```

Variáveis: `CONTA_HOST` (padrão `https://conta.aculta.toca.net.br`), `SUITE_OUT` (padrão `/tmp/aculta-conta-raiz`), `TIMEOUT`, `FIREFOX`, `GECKODRIVER`.

Código de saída: `0` se tudo passou; `1` se alguma verificação falhou.

## Limites conhecidos

- **Não cobre o estado logado.** `/` logado (hub do Studio), `/conta-legada` logado e o editor de endereço com Número/S-N precisam de uma sessão de teste. A geração do link único de login (`drush uli`) não foi autorizada nesta suíte, então essa parte fica para uma rodada própria.
- Não captura erros do console JavaScript. Para isso seria preciso BiDi (`bidi=True` no `wdlib.Driver`).
- Não cobre o tema Olivero. A regra do projeto exige testar Portal e Revista também com ele; isso depende de troca de tema no servidor de testes, que é um estado compartilhado.
- O texto de ajuda do campo "Usuário ou endereço de e-mail" aparece em inglês ("Enter your username or email address."). Esta suíte não verifica idioma, então não reprova isso.
