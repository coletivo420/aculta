# DHTMLX Scheduler 7.2.15 (edição GPL)

Incorporação aceita pelo responsável em 2026-10-28. O estudo, os critérios e a decisão de licença estão em
`ESTUDO-DHTMLX-SCHEDULER.md` (seção 10).

## Origem e integridade

| Item | Valor |
| --- | --- |
| Pacote | `dhtmlx-scheduler@7.2.15`, obtido com `npm pack` (sem CDN) |
| Tarball | `dhtmlx-scheduler-7.2.15.tgz`, sha256 `bcb5bdb00b4b2d5e134690aa72b612d6b19112fa69fe016c91e681a6cface5e2` |
| `js/dhtmlx/dhtmlxscheduler.js` | sha256 `44c2dd4fb73bec301bbe1ef5f73ed5bf9cf39317ba8956088d267f1f5613bed2` (539.946 bytes) |
| `css/dhtmlx/dhtmlxscheduler.css` | sha256 `4e194267715657f3e5c75bbcf4b9326e26cad9b75cbd83318662f002e987bb34` (119.763 bytes) |

## Licença

GPL v2 (edição Standard). Decisão do responsável: **remover o `LICENSE` do pacote e assumir os riscos**
(`ESTUDO-DHTMLX-SCHEDULER.md`, seção 10). O aviso de copyright está no cabeçalho do JS
(`@license dhtmlxScheduler v.7.2.15 Standard ... (c) XB Software Ltd.`) e neste documento. Não copiamos
`license.txt`, mapas, `.d.ts`, `es.js` nem demos.

## Carregamento

A library `aculta_calendar/dhtmlx` é anexada só pela rota `/calendario/interativo`. O CSS está em
`css/dhtmlx/dhtmlxscheduler.css` e o JS em `js/dhtmlx/dhtmlxscheduler.js`; nenhum arquivo vem de CDN.
O JS e o CSS do pacote usam `preprocess: false`: já vêm minificados, e o agregador do Drupal omitia a
library quando tentava processá-los (o HTML saía sem os dois arquivos).

## Fontes

O CSS original traz 5 `@font-face` de `fonts.gstatic.com`. A remoção está registrada em DH-C (nenhuma
requisição a terceiros por padrão).

## Teclado (DH-B)

Medido no Firefox ESR (geckodriver, `DISPLAY=:0`) em 1280 e 320 px: com `scheduler.plugins({ key_nav: true })`
antes do init, o Tab alcança o calendário (`tabindex="0"` no contêiner; foco em `dhx_focus_slot`) e as setas
movem o foco interno (de 28/10/2026 para 30–31/10/2026). Sem o plugin, o Tab não entrava (piloto da seção 8 do
estudo). Capturas em `$SUITE_OUT`.
