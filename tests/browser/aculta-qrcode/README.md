# QR Code e mobile dos links: verificação no navegador

Script `verifica_qrcode.py`. Roda no **Firefox ESR visível** na sessão X11 local (`DISPLAY=:0`), pelo geckodriver, via
`tests/browser/aculta-cpf-checkout/wdlib.py`. Não usa Chromium nem headless.

## O que cobre

| Caminho | Host | Esperado | Último resultado (servidor de teste) |
| --- | --- | --- | --- |
| `/painel-administrativo/aculta/codigos-qr` | `aculta.toca.net.br` | 403 para anônimo | PASS |
| `/painel-administrativo/aculta/codigos-qr/previa?url=...` | `aculta.toca.net.br` | 403 para anônimo | **FAIL: 404**, rota não publicada |
| `/painel-administrativo/aculta/codigos-qr/lote?ids=...` | `aculta.toca.net.br` | 403 para anônimo | **FAIL: 404**, rota não publicada |
| `/` (links) em 390 e 1280 px | `links.aculta.toca.net.br` | sem rolagem horizontal, HTTP 200 | PASS |
| `/entrar` (conta) em 390 e 1280 px | `conta.aculta.toca.net.br` | sem rolagem horizontal, HTTP 200 | PASS |

Resultado da última execução: 5 de 7 verificações OK.

## O que não cobre

- **Prévia e lote com administrador logado.** Exigem sessão e um código existente. Não há credenciais de teste no
  repositório, e o script não as usa.
- **Layout das micropáginas `/pagina/{slug}`.** Exige um slug publicado no servidor de teste.
- **`/redes-sociais`.** Responde 404 no servidor de teste: a rota ainda não está publicada. Fica fora da lista até lá.
- **Código desta branch.** O servidor de teste serve o checkout principal (`docs/aculta-forum-0.1.0-dev.1`), que não
  tem a prévia nem o lote. Os dois FAIL acima são esperados enquanto a branch não for publicada no servidor de teste.

## Como executar

```sh
DISPLAY=:0 SUITE_OUT=/tmp/aculta-qrcode python3 tests/browser/aculta-qrcode/verifica_qrcode.py
```

Variáveis: `QR_HOST` (padrão `https://aculta.toca.net.br`), `LINKS_HOST` (padrão `https://links.aculta.toca.net.br`),
`CONTA_HOST` (padrão `https://conta.aculta.toca.net.br`), `SUITE_OUT` (padrão `/tmp/aculta-qrcode`), `TIMEOUT`,
`FIREFOX`, `GECKODRIVER`.

Código de saída: `0` se tudo passou; `1` se alguma verificação falhou.

## Ambiente

Servidor de testes (`*.aculta.toca.net.br`). Não é produção. Evidência (PNG e `resultado.json`) fica em `SUITE_OUT`,
fora do repositório.
