# Configuração do navegador para testes

## Regra vigente

Testes de navegador rodam no **Firefox ESR visível na sessão X11 local** (`DISPLAY=:0`), controlado pelo geckodriver (WebDriver W3C). Não use Chromium headless nem Firefox headless. Referências: Firefox ESR 153.4 e geckodriver 0.37.1, verificados em 2026-10-22.

O Chromium headless travou abas e requisições de rede em várias rodadas, e remover `DBUS_SESSION_BUS_ADDRESS` não resolveu de forma confiável. Por isso ele foi descontinuado para testes.

## Comando

```
scripts/check-browser-hosts.sh [URL ...]
```

Sem argumentos, usa os hosts de teste (`*.aculta.toca.net.br`) e `example.com` como controle. A saída é uma tabela (URL, código, bytes, título) e um HTML por URL em `OUT_DIR`.

| Variável | Padrão | Função |
| --- | --- | --- |
| `DISPLAY` | `:0` | Sessão X11 local |
| `TIMEOUT` | 15 | Segundos por página |
| `FIREFOX` | `/usr/bin/firefox-esr` | Binário do Firefox |
| `GECKODRIVER` | `geckodriver` | Driver WebDriver |
| `OUT_DIR` | `/tmp/aculta-browser-check-<data>` | Saída (HTML e `resumo.txt`) |

Códigos de saída por URL: `0` página carregou; `124` tempo esgotado; `1` erro do WebDriver. O script sai com `2` se faltar a sessão X11, o geckodriver ou o Firefox.

O script só verifica o carregamento. Capturas de tela e medições ficam nas suítes de `tests/browser/`.

## Hosts e larguras

- Hosts de teste: `aculta.toca.net.br`, `links.aculta.toca.net.br`, `conta.aculta.toca.net.br/entrar`, `apoio.aculta.toca.net.br`. Controle: `example.com`.
- Larguras de captura: 1280 (desktop) e 390 (celular).

## Limites conhecidos

- Não substitui medição de contraste, rolagem horizontal ou foco por teclado.
- Turnstile aparece como "Verificando…" com o aviso "Somente para teste". É esperado no ambiente de teste.
- Firefox de sessões anteriores pode continuar aberto. Verificar com `pgrep -a firefox-esr`. Fechar só os que não estão em uso, conforme `AGENTS.md`.

## Atualização

Ao trocar a versão do Firefox ou do geckodriver, rodar o script contra `example.com` e atualizar as versões nesta página e em `AGENTS.md`.
