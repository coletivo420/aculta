# Changelog — aculta_calendar

## 0.1.0-dev.4 — revisão visual ADM8 e visão interativa (2026-10-29)

- ADM8 revisada no candidato servido `355e48a89` com conta descartável de
  permissão única: anônimo 403, autenticado 200, tabela somente leitura com três
  cabeçalhos e 10 linhas, sem formulários/botões e sem overflow em 1270 e 500 px
  reais. A conta e o papel foram removidos ao final.
- Visão interativa revalidada no mesmo candidato: 200, DHTMLX 7.2.15, grade com
  sete dias, alto contraste alternando para `contrast-black`, fallback de agenda
  abaixo de `md` e sem overflow.
- Scripts reutilizáveis: `tests/browser/aculta-calendar-admin/` e
  `tests/browser/aculta-calendar-interactive/`, com os artefatos fora do
  repositório.
- Suíte atual no worktree: 117 testes/399 assertions PASS (2 depreciações).
  O candidato servido é anterior ao dev.3; o markup `data-portal-ui` será
  revalidado quando esse commit for servido. Nenhum Runtime foi alterado.

## 0.1.0-dev.3 — fronteira de apresentação via tokens (2026-10-29)

- Interactive e EmbedHosts usam `data-portal-ui` em render arrays neutros; IDs e
  atributos de acessibilidade/comportamento foram preservados.
- Calendar: 117 testes / 399 assertions PASS, 2 depreciações Core/contrib;
  PHPCS Drupal/DrupalPractice integral e gate módulo–tema PASS.

## 2026-10-29 — CA PHPCS cleanup (0.1.0-dev.2)

- Limpas as violações Drupal/DrupalPractice dos PHP do Calendar, exceto `InteractiveController.php`, que permanece sob edição da frente DHTMLX.
- Suíte completa: 117 testes / 398 assertions PASS; lint: 60/60 arquivos PASS. PHPCS do arquivo protegido ainda reporta 2 erros e 2 avisos.
- Renomeado o contrato interno `CalendarFileReader` para `CalendarFileReaderInterface`, conforme a convenção de interfaces Drupal.

## 2026-10-28 — calendário compartilhado com o purpose main (D6)

- Rotas públicas do calendário também são servidas no purpose `main`, com acesso limitado aos purposes `main` e `eventos`.
- Páginas HTML usam canonical do purpose `eventos`, resolvido pelo ACULTA Deployer no ambiente ativo.

## 2026-10-28 — importação remota com proteção SSRF (I5)

- D-21 confirmada: fetch HTTPS apenas para hosts exatos da allowlist da fonte, DNS público fixado no cURL, TLS validado, sem redirecionamentos e com timeout.
- A resposta respeita 2 MiB e alimenta o mesmo leitor, normalizador e processador; itens seguem como rascunhos para revisão.

## 2026-10-28 — documentação da importação (I8)

- README descreve o fluxo local, os limites, a revisão editorial e a integração pelo provedor; a busca remota I5 continua bloqueada pela D-21.

## 2026-10-28 — provedor de itens importados (I7)

- Itens importados publicados são disponibilizados à agenda via `aculta_calendar.item_provider`; rascunhos ficam fora.

## 2026-10-28 — revisão editorial da importação (I6)

- Adicionada fila administrativa protegida para revisar itens importados, publicar uma revisão ou mantê-la como rascunho.
- A importação não publica itens automaticamente.

## 2026-10-28 — reimportação idempotente e itens ausentes (I4)

- Reimportação igual não cria revisão; alterações de item publicado geram revisão de rascunho sem trocar a versão pública.
- UID removido da fonte recebe presença `ausente`, permanece armazenado e não perde publicação; retorno à fonte restaura presença.

## 2026-10-28 — normalização segura dos eventos importados (I3)

- Removidos markup e blocos executáveis, decodificadas entidades e limitados título, descrição e local.
- TZID conhecido é preservado; sem TZID, o horário é interpretado em `America/Sao_Paulo` e a suposição fica registrada.
- `STATUS:CANCELLED` vira estado local cancelado, sem excluir o item.

## 2026-10-28 — leitor local de arquivos iCalendar (I2)

- Adicionado contrato e leitor local de `.ics`, sem rede nem dependência externa, com limite de 2 MiB e 2.000 eventos.
- Fixture escrita à mão e testes para leitura, limites e recusa de URL.

## 2026-10-28 — modelo de importação revisável (I1)

- Adicionadas a fonte configurável de calendário e a entidade revisável de item importado, com estados de rascunho, publicado e cancelado.
- O repositório cria um item por UID externo e fonte; esta microfase não lê arquivos nem acessa a rede.
