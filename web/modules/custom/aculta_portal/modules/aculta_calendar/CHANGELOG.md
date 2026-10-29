# Changelog — aculta_calendar

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
