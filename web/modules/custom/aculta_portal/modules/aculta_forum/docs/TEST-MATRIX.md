# Matriz de testes por fase

Esta matriz separa plano das evidências já executadas. Na `0.1.0-dev.4`, a
Context API pública e configuração nativa foram exercitadas no Runtime de teste.
Na dev.5, um teste Kernel prova storage Comment compartilhado em três bundles
Node e Unit cobre access/cache do provider editorial; integração editorial no
Runtime e fluxos completos continuam pendentes.

| Camada | Casos mínimos | Evidência |
| --- | --- | --- |
| Instalação/configuração | install profile limpo, dependências, IDs e nomes <=32, field Comment nos bundles Node permitidos | Kernel/install tests; validação de config. |
| Context API | resolver cada contexto, provider ausente/desabilitado, host inválido e URLs canônicas | Unit + Kernel. |
| Comment | tipo compartilhado (ou variantes justificadas), host bundles e field settings, thread, revisão, publicação, permalink/ancora, formulário | Kernel + Functional. |
| Acesso público | anônimo/membro/editor; publicação, conteúdo fechado, flags e permissões | Kernel + Functional/browser. |
| Publicação | comentários no Node editorial, conteúdo unpublished, canonical, âncora, sem Node forum gerado | Kernel + Functional; anti-regressão Magazine. |
| Moderação inicial | Primeiro comentário e pendências em qualquer Node com `field_aculta_discussion`; LMS sem moderação, e comentário LMS publicado libera o bloqueio temporário de tópico | Kernel `PublicDiscussionModerationKernelTest`; Runtime editorial ainda DEFERRED. |
| LMS | duas turmas isoladas, usuário em A e não B, role global estudante sem membership, educator/moderator, atividade em cursos múltiplos | Kernel/Functional; Route/View/Search/Cache e teste de navegador. |
| Wiki | uma thread por verbete, comentário não altera revisão, acesso de edição distinto | Contract tests nesta fase; integração real só após Wiki. |
| Busca/Views | filtro de contexto fixo, access query, títulos/trechos/contagens/facets e paginação sem vazamento | Kernel/Functional, index rebuild no ambiente de teste. |
| Cache | tags/context/max-age em variações por usuário/permissão/Group/membership, revogação e conteúdo despublicado | Kernel + Functional; inspeção de cache. |
| Notificações | dedupe, agrupamento, opt-in e confirmação, revalidação de acesso na fila, retirada/revogação, zero conteúdo sensível | Unit/Kernel do produtor e messenger + Functional. |
| Autoria | conta privada, apelido alterado, foto/privacidade, usuário bloqueado, autor legado, dados ausentes | Unit/Kernel + browser e cache. |
| Imagens/anexos | ingestão obrigatória aculta_image, uso/referências, anexo privado, substituição e limpeza | Gate `validate-aculta-image.php`, Kernel + testes de acesso. |
| Moderação | criar, editar, sinalizar, aprovar, ocultar, despublicar, bloquear, auditoria | Unit/Kernel/Functional por papel. |
| UX/acessibilidade | mobile, teclado, foco, leitor de tela, light/dark, reduced motion e fallback sem JS | Firefox/browser, auditoria manual e automatizada existente. |
| Performance | listagens, thread grande, Views, Query counts e contagens/cache | profiling comparável; limites definidos na fase de benchmark. |
| Remoção/migração | dry run, inventário, exclusão escopada, uninstall, composer remove, rollback/restauro e ausência de residuais | prova somente no Runtime de teste e ponto de recuperação revisado. |

## Evidência dev.5 — política de aprovação inicial compartilhada

- PHPUnit Kernel `PublicDiscussionModerationKernelTest`: 5 testes / 63
  assertions — PASS em MariaDB `aculta_simpletest` (PHP CLI 8.4.26).
- Cobertura: comentários LMS permanecem publicados sem moderação; uma publicação
  LMS conta como aprovada para contextos não LMS e libera o bloqueio temporário;
  política administrativa desligável; campo compartilhado em Wiki/outros Nodes;
  comentário não LMS pendente bloqueia criação de tópico quando configurado.
- `phpcs --standard=Drupal` nos arquivos PHP alterados: PASS. Gate Drupal 11+:
  969 checks — PASS. `git diff --check` — PASS.
- As decisões de acesso incluem a tag `config:aculta_user.settings` e os
  contextos `user`/`user.roles`; mudanças nas opções ou papéis não deixam
  resultados incompatíveis em cache.
- Runtime editorial e instalação/atualização de configuração continuam
  DEFERRED. Nenhum conteúdo ou configuração persistente foi alterado.

## Evidência `0.1.0-dev.4` (2026-10-19)

- Unit aculta_forum: resolver/provider, 7 testes e 25 assertions — executado.
- Unit Portal `ContentPurposeResolver`: 2 testes/2 assertions para o bundle novo e o legado — PASS.
- Gate Drupal 11+: 969 checks; fronteira módulo/tema: 1.290 verificações;
  mapa: 154 checks; pacotes de módulos próprios: 21 checks — PASS.
- No servidor `toca`, host de teste `growroom420.aculta.toca.net.br`, Drupal
  11.4.10: módulo instalado, serviço `DiscussionContextResolver` resolvido,
  campos `body`, `field_aculta_categories` e `field_aculta_discussion` presentes,
  zero Nodes do novo bundle. Config Sync não mostra divergência nos objetos
  novos. Nenhum tópico/resposta de usuário foi criado.
- PHP CLI do servidor reporta 8.4.26; o gate de versão de PHP 8.5 permanece
  pendente de validação pelo PHP-FPM/runtime web.
- Nenhum provider de Publicação/LMS/Wiki, acesso de escrita/moderação, Views,
  UX ou navegação pública foi homologado nesta subfase.

## Evidência dev.5 — provider editorial e Comment multi-bundle

- PHPUnit Unit `aculta_forum`: 12 testes / 52 assertions — PASS (PHP CLI 8.4.26).
- PHPUnit Kernel `SharedDiscussionFieldKernelTest`: 1 teste / 18 assertions —
  PASS usando exclusivamente MariaDB `aculta_simpletest`; o mesmo storage
  `field_aculta_discussion` e Comment type podem ser anexados a `article`,
  `blog` e `aculta_forum_topic`.
- Unit cobre leitura via Entity/Field Access, permissões nativas `post comments`
  e `administer comments`, campo fechado, negação de access, stable ID e cache.
- O Kernel prova a capacidade do Core com a configuração equivalente; não instala
  `aculta_forum`, não cria conteúdo e não substitui teste Functional/Runtime dos
  fields reais da Magazine.
- Runtime de teste: cache rebuild e smoke de DI confirmaram os dois providers; o
  provider editorial não atende Article enquanto o field ainda não existe.
  Nenhuma importação, field editorial ou conteúdo foi criado. RUNTIME STATUS:
  PARTIAL para o registro do serviço; integração editorial segue pendente.

Gates aplicáveis seguem `scripts/validate-aculta-portal-drupal11.php`, fronteira
módulo/tema, mapa módulo-tema, slugs, rotas, endereços, imagem, hierarquia de
papéis e gates relacionados ao código tocado. O gate final será recalibrado na
fase funcional; não assumir que uma lista estática substitui teste real.

Fase RC executará a cobertura integrada: Unit, Kernel, Functional, Domain matrix,
cache/access, browser/mobile, acessibilidade, desempenho, busca, notificações,
instalação/update/uninstall e rollback. Runtime permanece DEFERRED até validação
real no servidor de teste.
