# Roadmap — ACULTA Calendário (`aculta_calendar`)

Roadmap único do submódulo. Plano: `PLANO-IMPLANTACAO.md`. Arquitetura: ADR-031 (D-20).
Status: D6 implementada no Runtime de teste; as rotas públicas do calendário também respondem no purpose `main`.

## CA0 — Fundação independente
- [x] Ajustar `aculta_calendar.info.yml` (descrição sem dependência de purpose de eventos).
- [x] Definir `CalendarItem` e a interface do provedor, com a tag `aculta_calendar.item_provider`.
- [x] Teste de contrato: o submódulo não importa classes de `aculta_events` nem de `aculta_lms_agenda` (`tests/src/Unit/IsolamentoTest.php`).

## CA1 — Motor de recorrência (já existe em código)
- [x] Validador da regra e expansão na janela, com testes Unit (R1 e R2 de `aculta_events`).
- [x] Mover o motor para cá sem mudança de comportamento; `aculta_events` passa a chamá-lo.

## CA2 — Exportação `.ics` (já existe em código)
- [x] Gerador próprio com escape RFC 5545 e horário de Brasília (E6).
- [x] Exportação de um item, de um dia e de um período, a partir de provedores.

## CA3 — Embed (já existe em código)
- [x] Allowlist de origem do `source_uri` (E5).
- [x] Rota de incorporação ligada a um provedor, quando o purpose correspondente estiver ativo.

## D6 — calendário também no purpose `main`
- [x] Agenda, mês, dia, `.ics`, embed e visão interativa aceitam os purposes `eventos` e `main`; rotas administrativas não são compartilhadas.
- [x] Páginas HTML declaram canonical no host do purpose `eventos` resolvido pelo ACULTA Deployer no ambiente ativo.
- [x] Runtime de teste: todas as seis rotas retornam HTTP 200 em `aculta.toca.net.br` e `eventos.aculta.toca.net.br`; `/calendario` declara um único canonical para `eventos.aculta.toca.net.br` em ambos.
- [x] Suítes Kernel/Unit do calendário e Unit da política de purpose; 126 testes, 414 assertions, 2 depreciações. PHP lint e PHPCS passaram.
- Evidência de código: `9800e0d62`.

## CA4 — Visões de agenda e de mês
- [x] Agenda por dia e grade do mês, com filtros comuns, a partir da API de itens.
- [x] Verificação no Firefox ESR: desktop, 320 px e modo escuro (capturas fora do repositório).

## CA5 — Provedores
- [x] `aculta_events` registra o provedor de datas de evento (F5 de eventos).
- [x] `aculta_lms_agenda` registra o provedor de prazos e eventos de turma (D-15), com contexto de cache por usuário e resposta não compartilhável.

## CA6 — Documentação para agentes
- [x] README com a API, o que cada área pode e não pode fazer, e onde ficam as regras.

## DH — DHTMLX Scheduler (visão interativa)

Decisão do responsável em 2026-10-28; detalhes, origem e hashes em `DHTMLX.md` e `ESTUDO-DHTMLX-SCHEDULER.md`.

- [x] DH-A — componente 7.2.15 local (npm pack), library só na rota `/calendario/interativo`, sem CDN; `LICENSE` do pacote não incluído (decisão do responsável).
- [x] DH-B — `key_nav` antes do init; teclado medido no Firefox ESR.
- [x] DH-C — fontes externas removidas do CSS do componente (0 URLs externas).
- [x] DH-D — dados via `CalendarItemSource`/`CalendarOccurrences`; o componente só apresenta.
- [x] DH-E — grade a partir de md; agenda em HTML abaixo; link "Visão interativa" na agenda.
- [x] DH-F — plano de saída documentado em `DHTMLX.md`.
- [x] Tamanho medido: JS 151.259 B gzip; CSS 13.040 B gzip; HTML 5.106 B.
- [x] Alto contraste: botão com `aria-pressed`; `contrast-black` em 11.94:1 (texto/fundo) e 7.28:1 (evento).
- [x] Olivero: 13/13 no Firefox ESR; tema restaurado.
- [x] Leitor de tela (Orca): contêiner `application`, foco nas seções de dia com `Notify: True`, botão `toggle`.

## Importação revisável de arquivos iCalendar

- [x] I1 — fonte de calendário como entidade de configuração e itens importados como entidade revisável; UID deduplicado por fonte.
- [x] I2 — contrato e leitor local de `.ics`, com limites de 2 MB e 2.000 eventos; sem biblioteca externa.
- [x] I3 — normalização, sanitização de conteúdo e tratamento de fuso/status cancelado.
- [x] I4 — reimportação idempotente, atualização como revisão e marcação de itens ausentes.
- [x] I5 — buscar por URL: D-21 confirmada; HTTPS restrito à allowlist, sem redirecionamentos e com validação/pinagem de DNS.
- [x] I6 — painel de revisão editorial, acesso restrito, sem publicação automática.
- [x] I7 — somente itens publicados entram no provedor do calendário.
- [x] I8 — documentar a API de importação e as regras no README.
