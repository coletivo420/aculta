# Calendário interativo — revisão visual servida

`interactive.py` revisa a rota pública `/calendario/interativo` (rota
`aculta_events.interactive`, apresentada por `aculta_calendar`) no Firefox ESR
visível em `DISPLAY=:0`, contra o servidor de desenvolvimento.

## Cobertura

- HTTP 200 anônimo e título `Calendário interativo`;
- DHTMLX Scheduler carregado e grade inicializada (`.dhx_cal` e sete dias);
- agenda alternativa oculta em 1280 px e visível abaixo do breakpoint `md`;
- botão de alto contraste alternando `data-scheduler-theme` para
  `contrast-black` com `aria-pressed="true"`;
- ausência de rolagem horizontal e de mensagens de erro visíveis;
- capturas PNG e `resultado.json` em `$SUITE_OUT`.

## Execução

```sh
DISPLAY=:0 \
SUITE_OUT=/caminho/fora/do/repo/aculta-calendar-interactive \
python3 tests/browser/aculta-calendar-interactive/interactive.py
```

| Variável | Padrão | Função |
| --- | --- | --- |
| `CALENDAR_HOST` | `https://aculta.toca.net.br` | host servido (purpose `main`) |
| `SUITE_OUT` | — | diretório de artefatos, fora do repositório |

Reutiliza `tests/browser/aculta-cpf-checkout/wdlib.py` e cria um perfil
temporário dentro de `$SUITE_OUT`, removido ao encerrar o WebDriver. Não
autentica, não envia formulário e não grava dados.

## Limitações

- O gerenciador X11 desta sessão impõe largura mínima de janela em torno de
  500 px; `set_window(390)` mede 500 px. O breakpoint `md` (767 px) continua
  exercitado, mas não há evidência exata de 390/320 px por este script.
- Não cobre leitor de tela nem foco por teclado; a evidência anterior desses
  itens está em `web/modules/custom/aculta_portal/modules/aculta_calendar/docs/ROADMAP.md`.
- O código testado é o commit do checkout servido, não o do worktree.
