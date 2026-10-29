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
- [x] Runtime de teste: todas as seis rotas retornam HTTP 200 em `aculta.toca.net.br` e `eventos.aculta.toca.net.br`; `/calendario` declara canonical único para `eventos.aculta.toca.net.br` em ambos. O sitemap de `eventos` inclui o canonical; o sitemap de `main` não duplica a URL.
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

## ADM8 — consulta de hosts para incorporação
- [x] Painel somente leitura em `/painel-administrativo/calendario/incorporacao`, consumindo os hosts de teste e produção declarados pelo ACULTA Deployer.
- [x] Permissão própria; Kernel confirma negação para usuário comum e acesso autorizado. Nenhuma opção de edição da allowlist é oferecida.

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

## Prontidão para RC — verificação de 2026-10-28

- CA0–CA6, ADM8, DH-A–DH-F e I1–I8 estão implementadas; CA5 inclui o provedor
  de prazos e eventos LMS (`aculta_lms_agenda`, commit `7c37023ef`). O `next_phase`
  do manifesto que indicava CA5 pendente estava desatualizado.
- Suíte completa: 117 testes / 398 assertions PASS, 2 depreciações Core/contrib.
  PHP lint: 60 arquivos PASS. PHPCS Drupal restrito aos PHP: 261 erros e 35
  warnings em 44 arquivos; gate vermelho, sem correção de baseline nesta fase.
- Firefox ESR anterior cobre agenda/mês/interativo, Olivero e leitor de tela;
  a nova página administrativa ADM8 ainda requer revisão visual autenticada.
  A conta/credencial com `administer aculta calendar embedding` não está
  configurada em `secrets/`; a pergunta ao responsável permanece aberta.
- Manter `state: Dev` até PHPCS, revisão visual de ADM8 e revisão de código
  estarem concluídos. Nenhuma configuração Runtime foi importada.

## CA — fechamento PHPCS e fronteira módulo–tema (2026-10-29, dev.3)

- `InteractiveController` e `EmbedHostsController` usam render arrays e tokens
  `data-portal-ui`; IDs e atributos de acessibilidade/comportamento foram
  mantidos. PortalUiHooks mapeia os tokens em classes do ACULTA420.
- PHPCS Drupal/DrupalPractice integral passou nos PHP do módulo, inclusive
  `InteractiveController.php`. Suíte: 117 testes / 399 assertions PASS, duas
  depreciações Core/contrib. Gate global módulo–tema: 3397 verificações PASS.
- Sem revisão visual autenticada nova. Revalidar calendário interativo e ADM8
  no Firefox ESR quando o candidato estiver servido; acesso ADM8 continua
  limitado pela credencial documentada no status do módulo.

## CA — correção da revisão de cancelamentos importados (2026-10-29, dev.6)

- A fila editorial agora distingue `STATUS:CANCELLED` e oferece “Publicar
  cancelamento”; a ação mantém `import_state=cancelado` e publica o aviso. A
  ação comum de publicação recusa um cancelamento.
- O provedor de itens propaga o marcador para agenda e exportações `.ics`, sem
  exibir o cancelamento como evento ativo.
- Kernel cobre a ação do formulário, estado publicado e marcador na agenda.
- Suíte integrada: 126 testes, 428 assertions PASS, 2 depreciações Core.
- PHPCS dos arquivos da microfase PASS. A checagem integral do módulo tem 0
  erros e 1 aviso em `CalendarAgenda.php`, alterado na revisão dev.5.
- A correção não conclui a revisão de código restante nem promove o módulo a
  RC.

## RC — evidência do candidato (2026-10-29, 0.1.0-rc.1)

- Candidato `e36140e63` servido no checkout de teste com `drush cr`.
- ADM8 autenticada: anônimo 403, autenticado 200, tabela somente leitura com 3
  cabeçalhos e 10 linhas, sem controles de edição e sem overflow em 1270/500 px.
- Visão interativa: 200, DHTMLX 7.2.15, 7 dias, alto contraste e fallback de
  agenda abaixo de `md`.
- Smoke do purpose `eventos`: 129/129 verificações em 1280/390/320 px reais.
- Suíte 127 testes/431 assertions PASS (2 depreciações); PHPCS 0 erros e lint
  PASS; gate módulo–tema PASS no dev.3.
- Inclui as correções da revisão de código (dev.5), a publicação explícita de
  cancelamentos importados e a exibição de eventos de vários dias em cada dia
  coberto (dev.6). Runtime sem `config:import`.
- Próxima etapa: validação Alpha após autorização de release.
