# Validação de navegador — 2026-10-12

Resultado da rodada de validação com Chromium headless nos hosts de teste (`*.aculta.toca.net.br`) e no controle `example.com`.
Ferramenta: `scripts/check-browser-hosts.sh`.

## Resultado

| URL | Código | Bytes | Título |
| --- | --- | --- | --- |
| `https://example.com/` | 0 | 2375 | Example Domain |
| `https://aculta.toca.net.br/` | 0 | 41606 | Associação Cultural Antiproibicionista \| Goiânia - GO |
| `https://links.aculta.toca.net.br/` | 0 | 20954 | Redes sociais da ACULTA |
| `https://conta.aculta.toca.net.br/entrar` | 0 | 22466 | Entrar \| Conta ACULTA |
| `https://apoio.aculta.toca.net.br/` | 0 | 18883 | Apoie a Associação \| Associação Cultural Antiproibicionista |

Todas as páginas carregaram no Chromium headless (código 0, DOM serializado com conteúdo).

## Causa do travamento anterior

O Chromium travava em qualquer requisição de rede quando a variável `DBUS_SESSION_BUS_ADDRESS` estava definida no ambiente do Claude Code.
Com essa variável removida, o carregamento funciona. Diagnóstico:

- Página local (`http://127.0.0.1`) também travava, então o problema não era do destino nem de DNS, TLS ou QUIC.
- Flags testadas sem efeito: `--no-zygote`, `--disable-quic`, `--single-process`, `--disable-ipv6`, `--host-resolver-rules`, proxy CONNECT local, `--headless=old`, desativação de serviços em segundo plano.
- Removendo só `DBUS_SESSION_BUS_ADDRESS` (`env -u DBUS_SESSION_BUS_ADDRESS`), o Chromium passou a carregar todas as páginas.

## Limites desta validação

- É validação de DOM renderizado (título, conteúdo e status), não de layout visual.
- Não cobre largura de 390 px, contraste, foco visual nem captura de tela.
- Os testes de Kernel continuam pendentes e dependem de `SIMPLETEST_DB`.

## Como reproduzir

```
scripts/check-browser-hosts.sh
```

Padrão: 15 s por URL (`TIMEOUT`), `CHROME=chromium` e `DBUS_SESSION_BUS_ADDRESS` removida apenas para o navegador.
