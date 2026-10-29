# Changelog — aculta_calendar

## 0.1.0-dev.5 — correções da revisão de código (2026-10-29)

- Ocorrência única em curso, iniciada antes da janela, deixa de ser descartada;
  usa a mesma sobreposição dos provedores (`start <= ate` e `end >= de`).
- UID externo numérico não vira chave inteira e não interrompe a importação.
- Janela de 366 dias inclusivos é aceita; 367 dias é recusada na agenda e no
  `.ics`, sem estourar o teto de 366 ocorrências.
- Uma série inválida é isolada: a agenda registra a falha e continua com os
  demais itens, em vez de derrubar a página.
- `RECURRENCE-ID` é revalidado contra a janela: ocorrência movida para dentro é
  incluída e a movida para fora não vaza para o dia original.
- `UNTIL` anterior ao início da série é erro explícito no expansor e no
  formulário de recorrência, em vez de série vazia.
- Revisão de importação mostra o início no fuso do site e ignora referência de
  fonte órfã sem WSOD; a exportação do dia mantém os filtros ativos.
- Dia inteiro aparece como "Dia inteiro" na agenda e o interativo recebe
  `all_day` com duração não nula.
- Limite conhecido: eventos de vários dias continuam agrupados pelo dia inicial
  na agenda e na grade do mês; a definição de expansão por dia depende de
  decisão de produto.
- Validação: Unit 98/299 PASS; Kernel 27/120 PASS (2 depreciações); PHPCS 0
  erros e `php -l` nos arquivos alterados. Revisão visual do candidato com
  estas correções pendente porque o checkout de teste está com trabalho
  concorrente não commitado de outra sessão.

## 0.1.0-dev.4 — revisão visual ADM8 e visão interativa (2026-10-29)

- ADM8 revisada no candidato atual `a4f878d55` (dev.4), servido com `drush cr`,
  com conta descartável de
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
  A revisão visual cobre o markup `data-portal-ui` do dev.4 no candidato
  `a4f878d55`. Nenhum `config:import` ou atualização do Runtime foi executado;
  a conta descartável foi criada e removida.

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
