# DHTMLX Scheduler 7.2.15 (edição GPL)

Incorporação aceita pelo responsável em 2026-10-28. O estudo, os critérios e a decisão de licença estão em
`ESTUDO-DHTMLX-SCHEDULER.md` (seção 10).

## Origem e integridade

| Item | Valor |
| --- | --- |
| Pacote | `dhtmlx-scheduler@7.2.15`, obtido com `npm pack` (sem CDN) |
| Tarball | `dhtmlx-scheduler-7.2.15.tgz`, sha256 `bcb5bdb00b4b2d5e134690aa72b612d6b19112fa69fe016c91e681a6cface5e2` |
| `js/dhtmlx/dhtmlxscheduler.js` | sha256 `44c2dd4fb73bec301bbe1ef5f73ed5bf9cf39317ba8956088d267f1f5613bed2` (539.946 bytes) |
| `css/dhtmlx/dhtmlxscheduler.css` | sha256 `96c3c48c1de83a09746baec0bc585b1a2c3efa3056ea29c4cd61221d5a316b31` (113.465 bytes; do pacote original: `4e194267715657f3e5c75bbcf4b9326e26cad9b75cbd83318662f002e987bb34`, 119.763 bytes, antes da remoção das fontes em DH-C) |

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

O CSS original traz 5 `@font-face` de `fonts.gstatic.com`; os blocos foram removidos em DH-C, junto da
variável `--dhx-scheduler-font-family`, que agora usa `inherit`. O CSS do repositório não tem URL externa
nem referência a Inter: nenhuma requisição a terceiros por padrão. O tema ACULTA420 continua carregando
Inter e Oswald do Google em todas as páginas; isso é do tema, não do componente, e segue a frente visual.

## Dados (DH-D)

O componente recebe `drupalSettings.acultaCalendar.items` (`id`, `text`, `start_date`, `end_date`, `local`,
`url`, `cancelado`) e `date` (mês inicial). O `InteractiveController` consulta `aculta_calendar.item_source`,
expande com `CalendarOccurrences` (motor do calendário) e entrega as ocorrências; o componente só apresenta.
O período é o mês anterior e o seguinte, e a grade abre no mês da primeira ocorrência. Cancelados saem com
`[Cancelado]`. O motor de recorrência, o `.ics` e a regra de acesso continuam no `aculta_calendar`.

## Responsividade (DH-E)

Abaixo de md (768 px), o JavaScript esconde o componente e mostra a agenda em HTML (`#aculta-calendar-agenda`,
renderizada pelo `AgendaController`); a partir de md, mostra a grade e esconde a agenda. A alternância reage a
mudanças de largura. A agenda é a mesma da visão em lista, com `.ics` por dia; nada é escondido sem alternativa.
Medido no Firefox ESR: 1280 px com a grade (6 eventos) e a agenda escondida; 320 px com o componente escondido e
a agenda visível, sem rolagem horizontal. O link "Visão interativa" aparece na agenda.

## Teclado (DH-B)

Medido no Firefox ESR (geckodriver, `DISPLAY=:0`) em 1280 e 320 px: com `scheduler.plugins({ key_nav: true })`
antes do init, o Tab alcança o calendário (`tabindex="0"` no contêiner; foco em `dhx_focus_slot`) e as setas
movem o foco interno (de 28/10/2026 para 30–31/10/2026). Sem o plugin, o Tab não entrava (piloto da seção 8 do
estudo). Capturas em `$SUITE_OUT`.
