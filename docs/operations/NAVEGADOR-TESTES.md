# Configuração do navegador para testes

Registro das configurações que funcionam para validação de navegador (Chromium headless) neste ambiente.
Aplica-se ao Homelab, com o Claude Code como processo pai. Diagnóstico de 2026-10-12 em [VALIDACAO-NAVEGADOR-2026-10-12.md](../portal/VALIDACAO-NAVEGADOR-2026-10-12.md).

## Regra principal

**Remova `DBUS_SESSION_BUS_ADDRESS` do ambiente do Chromium.** Com essa variável definida, o Chromium trava em qualquer requisição de rede (inclusive página local). Sem ela, todas as páginas de teste carregam.

Use `env -u DBUS_SESSION_BUS_ADDRESS` antes do comando, ou a própria chamada em `scripts/check-browser-hosts.sh`, que já faz isso.

## Comandos

| Uso | Comando |
| --- | --- |
| Verificação do DOM (título, status, bytes) | `scripts/check-browser-hosts.sh` |
| Verificação de uma URL | `scripts/check-browser-hosts.sh https://exemplo/` |
| Captura de tela | `env -u DBUS_SESSION_BUS_ADDRESS chromium --headless=new --no-sandbox --disable-gpu --hide-scrollbars --window-size=390,900 --virtual-time-budget=6000 --screenshot=/caminho/saida.png URL` |

Flags usadas:
- `--headless=new` (o modo `old` e o padrão também travaram antes da correção).
- `--no-sandbox`: necessário no ambiente atual.
- `--virtual-time-budget=6000`: espera 6 s de JavaScript e animações antes da captura (sem isso, o card do Links aparece transparente).

## Parâmetros do script

| Variável | Padrão | Função |
| --- | --- | --- |
| `TIMEOUT` | 15 | Segundos por URL |
| `CHROME` | `chromium` | Caminho do navegador |
| `OUT_DIR` | `/tmp/aculta-browser-check-<data>` | Saída (HTML, stderr, `resumo.txt`) |

Código de saída: `0` com bytes maiores que zero indica página carregada. `124` indica tempo esgotado.

## Hosts e larguras

- Hosts de teste: `aculta.toca.net.br`, `links.aculta.toca.net.br`, `conta.aculta.toca.net.br/entrar`, `apoio.aculta.toca.net.br`. Controle: `example.com`.
- Larguras de captura: 1280 (desktop) e 390 (celular).

## Limites conhecidos

- Não substitui medição de contraste, rolagem horizontal ou foco por teclado.
- Turnstile aparece como "Verificando…" com o aviso "Somente para teste". É esperado no ambiente de teste.
- Processos antigos de Chromium de sessões anteriores podem ficar ativos. Verificar com `pgrep -a chromium` antes de uma rodada.

## Atualização

Ao mudar o ambiente (versão do Chromium, sessão, variáveis), refazer o diagnóstico: testar `example.com` com e sem `DBUS_SESSION_BUS_ADDRESS` e atualizar esta página.
