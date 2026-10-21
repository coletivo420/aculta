# Changelog — aculta_lms_messenger

## 0.1.0-dev.10 — injetar QueueFactory do Core — 2026-10-21

- Tipa os produtores de fila com a classe concreta `QueueFactory`, que é o
  serviço `queue` no Core instalado; a interface homônima não é implementada
  por essa fábrica. Corrige falha de construção descoberta no Runtime.
- No Runtime de teste foram habilitados `entity_print` (dependência já declarada
  pelo LMS) e o adapter; importadas somente as cinco configurações Group de
  prazo. Cache rebuild e construção do manager/worker passam; ainda não há turma
  ou deadline para smoke de evento/Cron. A ativação de `entity_print` não obteve
  a tradução pt-br por falha no destino local `translations://`; a ativação
  concluiu apesar do aviso.

## 0.1.0-dev.9 — registrar alias DI do manager — 2026-10-21

- Registra a interface `LearningNoticeManagerInterface` como alias do serviço
  concreto, permitindo autowiring do hook OOP e compilação do container.
- Encontrado ao habilitar no Runtime; a primeira tentativa abortou a compilação
  após marcar o módulo ativo, sem instalar configuração nem criar avisos.

## 0.1.0-dev.8 — lembrete de prazo em fila — 2026-10-21

- Adota provisoriamente a opção recomendada após o pedido de continuidade:
  avisar ao definir/alterar e gerar lembrete quando a data entrar nas 24 horas
  seguintes. Cron enfileira somente UUID/version opacos; worker revalida data,
  publicação, turma, matrícula e acesso antes de criar aviso.
- Lembrete aparece no painel e no resumo diário opt-in, sem e-mail transacional.
  Unit LMS/adapter: 47 testes / 210 assertions PASS; Kernels de Group/Message:
  6 testes / 79 assertions PASS, incluindo persistência e deduplicação em Message
  real. Runtime/cron e revisão visual continuam DEFERRED.

## 0.1.0-dev.7 — aviso de prazo por atividade/turma — 2026-10-21

- Cria aviso de prazo ao definir ou alterar a data por atividade e turma;
  membros ativos com `take course` são destinatários e o acesso é revalidado
  na exibição e no resumo diário. Não envia e-mail transacional.
- Mudanças futuras na data invalidam avisos antigos. O intervalo do lembrete
  antecipado aguarda decisão; Unit LMS/adapter: 43 testes / 197 assertions
  PASS. Kernel com entidades reais do provider, visual e Runtime DEFERRED.

## 0.1.0-dev.6 — aviso de conteúdo liberado e retorno — 2026-10-21

- Envia avisos de curso, aula e atividade recém-publicados aos estudantes
  matriculados; publicação/referência atual, membership e acesso são revalidados
  ao montar o destino.
- Aviso de retorno educador vai ao titular da resposta após confirmar comentário
  publicado, permissão atual de correção, matrícula e acesso. Texto do comentário
  não é copiado para o aviso.
- Ambos permanecem no site e entram no resumo diário opt-in, sem e-mail
  transacional. Unit do LMS e adapter: 40 testes / 188 assertions PASS;
  armazenamento Kernel integrado, visual e Runtime permanecem DEFERRED.

## 0.1.0-dev.5 — validação do resumo diário compartilhado — 2026-10-21

- Exercita o serviço real de resumo do `aculta_messenger` com provider
  educacional: confirma envio, preservação de pendências após falha e descarte
  somente do e-mail quando há opt-out.
- A prova também detectou e corrigiu o idioma `und` inválido na mensagem do
  resumo. Persistência/revalidação das entidades LMS no Runtime e inspeção visual
  permanecem DEFERRED.

## 0.1.0-dev.4 — aviso de resposta pendente — 2026-10-20

- Cria avisos para educadores com permissão atual de correção no curso quando
  chega resposta pendente; a origem e o acesso são revalidados ao abrir.
- O aviso fica no painel multidomínio e no resumo diário opt-in, sem e-mail
  transacional e sem conteúdo/resposta/anexo.
- Testes Unit do produtor e adapter passam; integração Kernel/Runtime e visual:
  DEFERRED.

## 0.1.0-dev.3 — avisos de avaliações publicadas — 2026-10-20

- Integra avaliações LMS à central multidomínio e ao resumo diário opt-in do
  `aculta_messenger`; cada republicação tem deduplicação própria.
- Revalida resposta, identidade, matrícula e acesso antes de exibir o aviso; não
  inclui resposta nem nota. Não envia e-mail transacional deste evento.
- Runtime e revisão visual do aviso: DEFERRED.

## 0.1.0-dev.2 — conclusão no aviso e resumo diário — 2026-10-20

- Publica avisos no site quando a conclusão aprovada é registrada, revalidando
  status, curso e matrícula atual.
- Fornece as conclusões ao resumo diário opt-in já existente no Messenger;
  e-mail confirmado, janela diária e envio único combinam eventos LMS e Fórum.
- Opt-out descarta somente a pendência de e-mail. O aviso no site permanece.
- Unit cobre evento site-only. Revisão visual do drawer/Runtime DEFERRED.

## 0.1.0-dev.1 — avisos de matrícula e certificado — 2026-10-20

- Adiciona adapter opcional com eventos LMS, persistência idempotente em Message,
  listagem no drawer e contagem do `aculta_messenger`.
- E-mails transacionais usam Message Notify, fila, lock e revalidação do
  destinatário e da origem antes do envio; links revalidam access e purpose.
- Testes Unit cobrem a tradução dos eventos e o isolamento de falha no adapter.
  Testes de integração e visual/Runtime: DEFERRED.
