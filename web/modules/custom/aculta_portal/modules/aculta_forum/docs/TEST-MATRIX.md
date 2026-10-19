# Matriz de testes por fase

Esta matriz separa plano das evidências já executadas. Na `0.1.0-dev.4`, a
Context API pública e configuração nativa foram exercitadas no Runtime de teste;
os demais contextos e fluxos completos continuam planejados.

| Camada | Casos mínimos | Evidência |
| --- | --- | --- |
| Instalação/configuração | install profile limpo, dependências, IDs e nomes <=32, field Comment nos bundles Node permitidos | Kernel/install tests; validação de config. |
| Context API | resolver cada contexto, provider ausente/desabilitado, host inválido e URLs canônicas | Unit + Kernel. |
| Comment | tipo compartilhado (ou variantes justificadas), host bundles e field settings, thread, revisão, publicação, permalink/ancora, formulário | Kernel + Functional. |
| Acesso público | anônimo/membro/editor; publicação, conteúdo fechado, flags e permissões | Kernel + Functional/browser. |
| Publicação | comentários no Node editorial, conteúdo unpublished, canonical, âncora, sem Node forum gerado | Kernel + Functional; anti-regressão Magazine. |
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

Gates aplicáveis seguem `scripts/validate-aculta-portal-drupal11.php`, fronteira
módulo/tema, mapa módulo-tema, slugs, rotas, endereços, imagem, hierarquia de
papéis e gates relacionados ao código tocado. O gate final será recalibrado na
fase funcional; não assumir que uma lista estática substitui teste real.

Fase RC executará a cobertura integrada: Unit, Kernel, Functional, Domain matrix,
cache/access, browser/mobile, acessibilidade, desempenho, busca, notificações,
instalação/update/uninstall e rollback. Runtime permanece DEFERRED até validação
real no servidor de teste.
