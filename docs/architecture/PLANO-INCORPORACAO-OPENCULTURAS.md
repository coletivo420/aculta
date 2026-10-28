# Plano de incorporação do OpenCulturas no ACULTA — status

Documento vivo. Mostra o que do código do OpenCulturas já foi reescrito no ACULTA, o que falta e o que foi
decidido não copiar. Fontes: `web/modules/custom/aculta_portal/modules/aculta_events/docs/AUDITORIA-OPENCULTURAS.md`
(achados S1–S8, T1–T14), `OPENCULTURAS-INCORPORACAO-CODIGO.md` (C-01 a C-10) e
`docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-15).

Regra de cópia: nenhum arquivo é copiado como está. Cada trecho derivado tem cabeçalho de atribuição e uma linha em
`aculta_events/docs/ORIGEM-OPENCULTURAS.md`. Repositório em GPLv3 e origem em GPL-2.0-or-later: compatível.

## Status por microfase

| Id | Origem no OpenCulturas | Destino no ACULTA | Situação | Evidência |
| --- | --- | --- | --- | --- |
| E0 | Dependências do `info.yml` | `aculta_events`, `aculta_calendar` sem `openculturas_custom` | **Parcial.** Recorrência usa `rlanvin/php-rrule` 3.0, com autorização a confirmar. `smart_date` não entra (D-13). | `7ae9e54e4` |
| E1 | `node.type.event/date/location` e campos | Entidades `Evento`, `Local`, data com exceção; `aculta_event_type` | **Parcial.** Falta `aculta_location_type`. Campos de venda não entraram. | `0e6d81397`, `bb0a907c8`, `8668fc957` |
| E2 | Views `event_catalogue`, `locations`, `related_dates_*` | Catálogo em controller (desvio registrado no roadmap); locais e datas relacionadas pendentes | **Parcial.** Catálogo feito; export sem telefone e e-mail (S5) pendente | `0174e641c` |
| E3 | `DateEventHooks::nodeViewAlter` e `viewsPostExecute` | Hooks com `#[Hook]`, cache até a próxima data | **Pendente** | — |
| E4 | `DateEventHooks::nodePrepareForm` | **Não copiar** (S1). Pré-preenchimento só com checagem de acesso, se for necessário | **Decidido: não copiado.** Pedido pendente se o responsável quiser o recurso | — |
| E5 | `openculturas_calendar_widget` (allowlist do `source_uri`, S3) | `EmbedSourceAllowlist` | **Feito** (lógica). Rota `/calendario/incorporar` pendente: depende de F1 com o purpose ativo | `7985bc36f` |
| E6 | `AddToCal` (regra, sem cópia) | `IcsEventBuilder` próprio, escape RFC 5545, CR/LF como espaço (S2, T10) | **Feito.** Exportação de página pendente | `a26bff741`, `e8e8d0151` |
| E7 | `openculturas_map` | Google Maps (ADR-014), sem Leaflet nem OSM | **Pendente.** Espera a F4 e as verificações de termos, cobrança e LGPD | ADR-014 |
| E8 | `openculturas_section` (cor por vocabulário) | Frente B, B2 (cores fechadas) | **Feito** no tema. Consumidor na Revista pendente | `15a3f0488` |
| E9 | `openculturas_faq` | FAQ, opcional (I-15) | **Não iniciado.** Só se o responsável pedir | — |
| T5 | `SmartDateUpcomingFormatter` | `DateRangeUpcomingFormatter` com `daterange` do core, cache até a próxima data | **Feito** | `56725c71d` |
| D-15 | Calendário e LMS (decisão própria, sem origem no OC) | `aculta_lms_agenda` e painel do aluno | **Parcial.** Falta confirmar acesso por matrícula no `field_turma` | `ac43e7d94`, `9766bb731` |
| R | Recorrência `smart_date_recur` (replicada, com modificações próprias, D-14) | `aculta_calendar`: validador (R1) e expansão na janela (R2) | **Parcial.** Regra persistida em `EventDate` (`field_regra`, `field_limite_meses`) e expansão testada. Falta a interface de edição e a edição de uma ocorrência (D-14) | `5f3b61ee7`, `1caa5fc7d`, `dcea19c4a` |

## Não copiar (decidido)

- Mapa Leaflet e tiles do OpenStreetMap (substituídos pelo ADR-014).
- `AddToCal` como classe (reescrito, E6).
- `nodePrepareForm` (S1).
- `LocationPrecisionHooks` (áreas desenhadas, não privacidade; T9).
- `ECA`, `config_devel`, importação de conteúdo de exemplo, cookies, tours, venda e ingressos.
- Blocos e temas do OpenCulturas como cópia: só como referência (ver a frente B).

## Pendências que bloqueiam o próximo passo

1. **Confirmar a autorização de `composer require` de `rlanvin/php-rrule`** (entrou em `composer.json` em `7ae9e54e4`).
2. **Verificações do Google Maps antes da F4**: termos, cobrança, lista de hosts no deployer e LGPD (ADR-014).
3. **Rota `/calendario/incorporar`** depende de o purpose `eventos` estar com Domain ativo no servidor de teste.
4. **Revisão visual de eventos** não tem registro (F6).

## Próximos passos, em ordem

1. E3: hooks de datas reescritos, com teste de data passada e de cache.
2. E2: view de locais e de datas relacionadas, sem telefone nem e-mail no export (S5).
3. E1: `aculta_location_type` (ainda não existe no código) e teste Kernel de acesso por purpose (não localizado).
4. R: interface de edição da regra e de uma ocorrência (D-14), com teste de expansão no fuso de Brasília.
5. F4 (E7): só depois das verificações da pendência 2.
6. F5: visões de agenda e de mês, e a rota de incorporação quando o purpose estiver ativo.
7. F6, F7, F8, F9.
