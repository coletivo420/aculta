# Candidato RC — `aculta_forum 0.1.0-rc.1`

Data: 2026-10-24. Branch: `docs/aculta-forum-0.1.0-dev.1`.
Fluxo: [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md).

Este documento registra o que foi verificado **antes do deploy**. Ele não é
homologação de Runtime: a validação em Alpha é a etapa seguinte e depende de
autorização operacional.

## Escopo do candidato

O módulo entrega tópicos (`aculta_forum_topic`), respostas revisionáveis
(`aculta_forum_post`), Comments legados em coexistência durante a migração,
moderação/workflow, categorias, busca Search API access-aware, seguir/salvar,
anexos e galeria, mover tópico/comentário, painel administrativo, fila de
revisões, histórico/comparação de revisões, fóruns restritos (desligados por
padrão) e os produtores de aviso para o `aculta_messenger`.

Itens funcionais do roadmap F-M0–F-M7 que dependem de operação (migração sobre
dados reais, ativação de F-GROUP, entrega de e-mail) estão listados em
[Limites conhecidos](#limites-conhecidos-não-são-pass) e pertencem ao Alpha ou a
outra frente.

## Gates executados

| Gate | Resultado |
| --- | --- |
| Unit do Fórum | PASS — 184 testes / 1.333 asserções |
| Kernel do Fórum (MariaDB `aculta_simpletest`) | 150/2.862 |
| PHPCS Drupal/DrupalPractice no módulo | 0 erros; 1 aviso aceito (`version` no `info.yml`, convenção de todos os módulos) |
| `scripts/validate-no-temporary-route-aliases.php` | PASS — 25 arquivos de rota |
| `scripts/validate-aculta-portal-drupal11.php` | PASS — 1.155 checks |
| `scripts/validate-public-slugs.php` | PASS — 380 rotas |
| `scripts/validate-domain-front-paths.php` | PASS — 16 checks |
| `scripts/validate-project-status-index.php` | PASS — 41 componentes |
| `drush updatedb:status` | Nenhuma atualização pendente |
| `scripts/validate-portal-ui-docs.php` | FAIL global (63), **zero** tokens/atributos do Fórum; falhas pertencem a LMS, Statistics, Studio, Commerce, Wiki, Game, Magazine e Mailer |
| `scripts/validate-own-module-packages.php` | FAIL global (3), todas em fixtures de teste de Messenger e Wiki; o fixture do Fórum passa |
| `scripts/validate-environment-addresses.php` | FAIL global (18), nenhum endereço do `aculta_forum`; o único de Fórum é `ForumNoticeMailType` do Messenger |
| `scripts/validate-admin-cleanup.php` (via `drush scr`) | FAIL apenas por cinco Views de exemplo vvj habilitadas no Runtime local; o inventário foi atualizado com `aculta_forum_gallery_slides` |

Os dois gates globais reprovados não têm item do Fórum. Declará-los PASS seria
falso; corrigi-los exigiria alterar arquivos de outras frentes em andamento.

## Revisão de código

Revisão de código de esforço médio concluída em 2026-10-24. Sete achados:

| Achado | Tratamento |
| --- | --- |
| Controlador de histórico registrado com dois argumentos, construtor exige três (500 na rota) | Corrigido; novo `ForumServiceDefinitionsTest` compara argumentos explícitos com construtores |
| Resolvedor de Groups consultava nid e tid no mesmo `entity_id IN`, cruzando IDs iguais | Corrigido; consulta por plugin e teste unitário que registra as condições |
| Denúncia de tópico/comentário aceitava alvo despublicado e não revalidava acesso | Corrigido; publicação e `access('view')` obrigatórios, com testes |
| Progresso do "marcar tudo como lido" usava nid esparso sobre contagem de tópicos | Corrigido; progresso pela quantidade marcada |
| Fila de moderação usava `comment_field_data.cid` fixo | Corrigido; alias do Comment validado, com *fail closed* |
| Painel executa 21 consultas de estatística por render | Registrado como DT-F01 (baixa, cache de 60 s) |
| Predicado de publicação repetido em quatro consultas | Registrado como DT-F02 (média) |

As dívidas F01/F02 estão em
[DEBT-REGISTER](../../../../../../../docs/operations/DEBT-REGISTER.md).

## Revisão visual e acessível pré-deploy

| Evidência | Resultado |
| --- | --- |
| `tests/browser/aculta-forum/accessibility-readonly.py`, ACULTA420 | 49/49 |
| O mesmo script com Olivero | 49/49 |
| `categorias-visual.py` (ACULTA420 e Olivero) | 9/9 e 9/9, reconfirmado no RC |
| `authenticated-readonly.py` (painel, Firefox ESR/X11) | 12/12, reconfirmado no RC |
| `synthetic-preview-and-move.py` (anexo e mover comentário) | 4/4, reconfirmado no RC |
| Reflow do painel | 1024 e 390 CSS px sem overflow horizontal |

A medição de acessibilidade cobre: um `h1` por página, hierarquia sem salto
(h1 → h2 → h3), nome acessível dos controles, `alt` das imagens, texto dos
links, IDs únicos e indicador de foco perceptível em pelo menos quatro paradas
de Tab dentro do conteúdo principal. O toolbar global de administração fica
fora da amostra por ser mobília do tema, não superfície do Fórum.

## Dependências

- Módulos de Runtime declarados em `aculta_forum.info.yml`: `aculta_portal`,
  `aculta_pdf_viewer`, `aculta_image`, `aculta_user`, `aculta_gallery`,
  `aculta_document_viewer`, Media, CKEditor 5, Editor, Filter, Entity Embed,
  Entity Usage, CTools Views, Domain Source, Diff, Comment, Migrate, Content
  Moderation, History, Flag, Group, Views, Node, Search API e Search API DB,
  Taxonomy, Text, Workflows e Views Bulk Operations. Todos habilitados no
  Runtime local.
- Contratos compartilhados: `aculta_messenger` (avisos, ledger, filas e
  preferências), `aculta_mailer` (transporte), `aculta_user` (identidade,
  cartões e participação) e `aculta_image` (política de upload). A fronteira
  está em [OWNERSHIP](OWNERSHIP.md).
- Este candidato não adiciona pacote Composer novo; usa apenas dependências já
  presentes no projeto.

## Rollback

- Configuração: `config/install` (instalações novas) e os updates `9001`–`9005`,
  todos aditivos e idempotentes. Nenhum passo apaga conteúdo.
- Código: reverter o commit do candidato e reimportar a configuração; as rotas,
  Views, blocos e menus do módulo deixam de existir.
- Dados: tópicos, respostas, Comments, mídias e arquivos permanecem. Desabilitar
  o módulo interrompe as rotas sem apagar conteúdo. Desinstalar remove a
  configuração do módulo e deixa as tabelas da entidade `aculta_forum_post`
  órfãs; só fazer isso com backup e procedimento próprio.
- Migração: **não executada** neste candidato. O rollback do lote (mapas SQL e
  ForumPosts criados) está coberto por teste de Kernel e será revalidado no
  Alpha antes de qualquer execução sobre dados reais.

## Limites conhecidos (não são PASS)

- Migração Comment → ForumPost sobre dados reais: código e equivalência em dados
  sintéticos provados; execução exige autorização operacional específica.
- F-GROUP: tecnicamente concluído e **desligado por padrão**; ativação exige
  autorização operacional.
- Entrega de e-mail, digest e concorrência entre processos: pertencem ao
  `aculta_messenger`/`aculta_mailer`; testes de e-mail ficaram fora da
  autorização desta frente.
- Aparelho físico e zoom: não medidos; a cobertura é de 320–1440 CSS px e do
  painel em 1024/390 CSS px.
- PHP CLI do ambiente: 8.4; a baseline 8.5 continua sem validação no PHP-FPM.
- Testes funcionais/HTTP automatizados: o módulo usa Unit e Kernel; a validação
  HTTP autenticada é feita por navegador real (Firefox ESR/X11).

## Checklist de Alpha

1. Autorizar e registrar o deploy do candidato no servidor de testes.
2. Rodar `drush updatedb` e importar a configuração; conferir `drush cr`,
   rotas, permissões, Views e blocos no `growroom420` e no `conta`.
3. Repetir a leitura autenticada do painel e as rotas de moderação com uma
   conta não-superusuária.
4. Validar aviso de resposta e de primeira publicação com duas contas, sem
   envio real de e-mail.
5. Executar a migração em *dry-run*/lote controlado com backup, somente após
   autorização, e comparar contagens e avisos.
6. Registrar PASS/PARTIAL/FAIL e o rollback no CHANGELOG/ROADMAP do módulo.
