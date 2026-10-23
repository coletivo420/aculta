# Matriz de testes — ACULTA Wiki

Cobertura planejada e executada do submódulo. Os cenários abaixo listados como
`planejado` continuam pendentes; a seção da Fase 5 registra o que já roda.

## Fase 5 — busca e aviso de revisão

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Índice `aculta_conteudo` exportado | Unit | `wiki_entry` entre os bundles, `index_directly` e `track_changes_in_references` verdadeiros | aprovado |
| Verbete sai de rascunho para revisão | Unit | `entity_update` dispara o aviso | aprovado |
| Verbete continua em revisão | Unit | nenhum aviso | aprovado |
| Verbete publicado entra em revisão | Unit | aviso disparado (compara com `getOriginal()`) | aprovado |
| Verbete publicado é editado sem mudar de estado | Unit | nenhum aviso | aprovado |
| Atualização de outro bundle | Unit | nenhum aviso | aprovado |
| Entidade que não é nó | Unit | nenhum aviso | aprovado |
| Tipo sem campo `moderation_state` | Unit | nenhum aviso | aprovado |
| Aviso desligado na configuração | Unit | nenhum aviso | aprovado |
| Fila do mailer ausente | Unit | nada enfileirado, sem erro | aprovado |
| Aviso desligado | Unit | nenhuma mensagem enfileirada | aprovado |
| Lista com endereço inválido | Unit | endereço descartado, envio só aos válidos | aprovado |
| Dois destinatários cadastrados | Unit | uma mensagem por endereço, mesma correlação lógica | aprovado |
| Sem URL pública disponível | Unit | contexto com `link` vazio | aprovado |
| `isEnabled` | Unit | reflete a configuração sem alterar o padrão desligado | aprovado |
| Tipo de e-mail `aculta_wiki_review` | Unit | ID válido e variáveis `verbete`, `estado`, `link` | aprovado |
| Assunto e corpo do aviso | Unit | usam os três marcadores e nenhum campo de autoria | aprovado |
| Contexto do aviso | Unit | sem `autor`, `autora`, `user` ou `uid` | aprovado |

Suíte do submódulo verificada em 2026-10-22: `57 testes, 258 asserções, OK`,
com duas deprecações do PHPUnit. Os cenários
de navegador (busca do site, verbete publicado no resultado, tela
`/painel-administrativo/aculta/wiki`) continuam na seção **Navegador** e só rodam
depois da importação de configuração no servidor de testes.

## Fase 5 — listagem e imagem de destaque

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Envio de imagem válida com descrição | Unit | sem erro de validação | aprovado |
| Descrição vazia ou acima de 512 caracteres | Unit | erro em `wiki_lead_image][alt` | aprovado |
| Mais de um arquivo por envio | Unit | erro em `wiki_lead_image][file` | aprovado |
| Arquivo fora das extensões de imagem da política | Unit | erro em `wiki_lead_image][file` | aprovado |
| Aba intocada | Unit | nenhum erro e nenhum campo alterado | aprovado |
| Envio aceito | Unit | mídia `image` publicada com `alt`, campo aponta para ela | aprovado |
| Reenvio após erro de validação | Unit | reusa a mídia do mesmo `form_state` | aprovado |
| Caixa de remoção marcada | Unit | `field_wiki_image` esvaziado | aprovado |
| Formulário do verbete | Unit | aba no grupo `advanced`, `#tree`, validadores `wiki`, ganchos de validação e envio | aprovado |
| Política sem extensão de imagem | Unit | aba não aparece e o widget central continua visível | aprovado |
| Consumidor `wiki` exportado | Unit | `consumers.wiki` no `config/sync` e no schema | aprovado |
| Diretório privado é preparado pelo uploader | Unit | chamada ao serviço FileSystem com criação e permissões | aprovado |
| Visibilidade da Media e do arquivo segue estado do verbete | Kernel | rascunho permanece privado; verbete publicado permite acesso | aprovado (2 casos de dados MariaDB) |
| Falha após persistência do Node | Kernel | rollback remove Node e Media e restaura File temporário | aprovado (MariaDB) |
| Remoção em nova revisão com referência antiga | Kernel | revisão antiga mantém a Media; arquivo continua presente e acessível | aprovado (1 teste / 24 assertions em MariaDB) |
| Reuso da Media em outro conteúdo e limpeza global de referências | Kernel | preservar arquivo até não haver usos em nenhuma entidade/revisão | pendente; depende do contrato transversal do Portal |
| Falha de validação antes de submit | Kernel | sem Media órfã nem File permanente | pendente |

## Fase 2 — componentes movidos

| Componente | Tipo | Cenários | Estado |
| --- | --- | --- | --- |
| `WikiController::home` | Unit / Kernel | Página inicial responde 200 no purpose `wiki`; sem blocos quando o purpose é outro | planejado |
| `WikiController::search` | Unit | Busca vazia e busca com menos de `MIN_CHARS`; limite de `MAX_CHARS` | planejado |
| `WikiController::preview` | Unit | Prévia abaixo do mínimo não consulta o banco; resposta com contagem e links | planejado |
| `WikiSearchFormBuilder` | Unit | `#action` e `data-preview-url` apontam para os nomes de rota estáveis | planejado |
| Rotas | Kernel | `/wiki`, `/wiki/busca`, `/wiki/busca/previa` com os mesmos nomes e caminhos | aprovado (dev.20) |

## Fase 4 — métricas de publicação

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Primeira publicação de um verbete | Unit | Um evento `wiki.entries.published` | planejado |
| Revisão publicada de verbete já publicado | Unit | Um evento `wiki.entries.updated`, nenhum `published` | planejado |
| Node importado ou sincronizado | Unit | Nenhum evento | planejado |
| Agregado diário com publicação e revisão | Kernel | Contadores diários separados por métrica | planejado |

## Rotas, grants e cache — Kernel real (dev.13)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Discovery das três rotas públicas da Wiki | Kernel | `/wiki`, `/wiki/busca` e `/wiki/busca/previa` com nomes/caminhos existentes | aprovado |
| Discovery da página da View | Kernel | `view.wiki_entries.page_1` em `/wiki/verbetes` | aprovado |
| Permissão de rota pública | Kernel | `access content` permite e negar a permissão bloqueia | aprovado |
| Busca com grants distintos na mesma função | Kernel | cada conta recebe somente seu verbete publicado | aprovado |
| Busca vazia e negativa | Kernel | sem resultados e cache inclui `user.node_grants:view` | aprovado |
| CTA inicial — acesso real de criação | Kernel | `createAccess('wiki_entry')`; pessoa confirmada autorizada e não confirmada negada | aprovado (dev.20) |
| Fila de revisão com candidatos negados | Kernel | 26 VIDs recentes sem grant não ocultam uma revisão autorizada mais antiga | aprovado (dev.21) |

Resultado mais recente da classe Kernel (dev.21): 9 testes / 344 assertions,
sem warnings; 284 depreciações Drupal/contrib e 10 do PHPUnit.

Suíte Kernel da Wiki inicializa `aculta_wiki` e dependências reais, com MariaDB
isolada. O fixture de grants está apenas em `tests/modules`; Runtime e
configuração de produção não são usados. Resultado dev.20: 9 testes / 342
assertions, sem warnings; 284 avisos de depreciação Drupal/contrib e 10 do
PHPUnit. Unit: 66 testes / 338 assertions, 3 depreciações PHPUnit. A
configuração Core `filter` foi instalada na fixture para usar o formato de
fallback real e evitar warning falso de `entity_usage`.

## Persistência de propostas — Kernel real (dev.14)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Submissão autorizada no purpose Wiki | Kernel | Entidade privada salva com autor, intenção, múltiplas fontes e VID-base do verbete publicado | aprovado (1 teste / 37 assertions) |
| Leitura da proposta | Kernel | Autora e equipe editorial autorizada podem ler; outra conta não | aprovado |
| Escrita da proposta pelo participante | Kernel | Autora não pode atualizar a proposta existente | aprovado |
| Envio sem permissão | Kernel | Serviço nega antes de persistir | aprovado |
| Envio fora do purpose Wiki | Kernel | Serviço nega antes de persistir | aprovado |
| Fonte insegura | Kernel | Rejeita URI `javascript:` antes de persistir | aprovado |
| Alvo alterado após carregar a página | Kernel | Serviço recarrega o Node e salva o VID padrão atual | aprovado |
| Limite de abuso e idempotência | Kernel | Rejeitar submissão excessiva/duplicada | planejado |
| Decisão editorial e histórico | Kernel | Form API/Content Moderation cria revisão e bloqueia aplicação quando o VID-base ficou obsoleto | planejado |

O Kernel focal passou sem warning; existem avisos de depreciação Core/contrib e
do PHPUnit na closure completa de dependências. Não há rota/formulário que
exponha propostas nesta entrega.

## Consulta editorial — Kernel real (dev.15)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Consulta de propostas pendentes | Kernel | Apenas estados submetidos/autorizados; paginação 25+1 | aprovado |
| Cursor de proposta inválido | Kernel | Reinicia a consulta sem erro | aprovado |
| Sem permissão de moderação | Kernel | Serviço nega acesso antes da consulta | aprovado |
| Revisão atual em `review` | Kernel | Retorna revisão `wiki_entry` atual com `view`, `view revision`, `update` e transição autorizada | aprovado |
| Revisão anterior ou sem transição válida | Kernel | Não aparece na fila | cobertura parcial: revisão atual e transição válida caracterizadas |
| Escopo de domínio | Kernel | Consulta dentro do purpose `wiki` e restaura `main` | aprovado |
| Cache | Kernel | Metadados incluem conta, permissões, grants, domínio e cursor | aprovado |
| Paginação da fila de revisões com candidatos inacessíveis | Kernel | Enche páginas sem pular resultados válidos | planejado |
| Resultado/URL da fila no Studio | Kernel/Functional | Operações só por Form API com acesso editorial | planejado |

Kernel MariaDB da classe completa: 8 testes / 274 assertions, sem warnings;
284 depreciações Drupal/contrib e 9 do PHPUnit. Não há rota nem UI da fila.

## Envio contextual de proposta — Kernel real (dev.16)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Rota de contribuição | Kernel | `/wiki/verbetes/{node}/contribuir`, exige `submit wiki proposals` e `node.view` | aprovado |
| Formulário | Kernel | Form API constrói intenção, texto, justificativa e fontes | aprovado |
| Validação do formulário | Kernel | Texto/justificativa obrigatórios e fontes apenas HTTP(S), máximo dez | aprovado (válido exercitado; inválidos no serviço) |
| Callback de submissão | Kernel | Persiste pela API proprietária, atualiza revisão-base e redireciona ao verbete | aprovado |
| Autorização e purpose no serviço | Kernel | Mantém proteção contra conta sem permissão, domínio incorreto e alvo despublicado | aprovado na cobertura do serviço dev.14 |
| Aplicar o conteúdo sugerido ao Node | Kernel | Exige decisão editorial explícita e revisão nova autorizada | pendente de definição do fluxo |

O módulo está desabilitado no Runtime; o endpoint só existe após ativação
controlada.

## Acesso ao envio de proposta — Kernel real (dev.17)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Conta confirmada com permissão e sem update | Kernel | Link contextual aparece no verbete publicado em purpose Wiki | aprovado |
| Conta não confirmada com permissão | Kernel | Link não aparece e serviço nega antes de persistir | aprovado |
| Cache do link | Kernel | Varia por permissões/domínio e inclui metadata de Entity Access | aprovado |
| Sem permissão de proposta ou fora do domínio Wiki | Kernel | Link ausente | pendente |

Resultado da suíte Kernel da classe: 8 testes / 290 assertions, sem warnings;
284 depreciações Drupal/contrib e 9 do PHPUnit.

## Decisão editorial de proposta — Kernel real (dev.18)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Pedido de ajustes via Form API | Kernel | Nova revisão padrão privada, nota editorial e estado `needs_changes` | aprovado |
| Recusa | Kernel | Nova revisão com estado `declined` e motivo | aprovado |
| Revisão anterior da proposta | Kernel | Continua disponível com estado anterior e nota original | aprovado |
| Decisão com VID esperado obsoleto | Kernel | Conflito, sem gravar revisão extra | aprovado |
| Conta colaboradora sem permissão editorial | Kernel | Decisão negada | aprovado |
| Nota vazia | Kernel | Serviço rejeita antes de persistir | aprovado |
| Nota maior que 5.000 caracteres | Kernel | Form/service rejeita antes de persistir | planejado |
| Aceite e aplicação na revisão do verbete | Kernel | Só publica por workflow após aplicação editorial e conferência do VID-base | pendente de decisão de fluxo |
| Concorrência real entre duas sessões MariaDB | Kernel | Lock serializa; segunda ação obsoleta recebe conflito | planejado; lock implementado, concorrência multiprocesso não executada |

O formulário de decisão não tem rota nem integração no Studio; seu consumo fica
sob o contexto editorial do GanjaLab e acesso do backend Wiki.
Kernel MariaDB da classe completa: 8 testes / 301 assertions, sem warnings;
284 depreciações Drupal/contrib e 9 do PHPUnit.

## Moderação na WikiTab — Kernel combinado (dev.19)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Moderador de propostas no Studio | Kernel | Só seção Moderação/Propostas; sem listagem genérica de verbetes/revisões | aprovado |
| Fila de propostas no Studio | Kernel | Usa queue Wiki autorizada, pagina e gera links para o Form API proprietário | aprovado |
| Fila de revisões Wiki | Kernel | Usa revisão atual e exibe apenas ação de formulário cujo route access passa no purpose Wiki | aprovado |
| Usuário sem gate editorial | Kernel | Sem seção de moderação | aprovado |
| Visual no Firefox ESR/X11 e WCAG | Browser | Form/filas mobile-first em ACULTA420 e Olivero, foco, teclado e reflow | pendente; Runtime ainda não habilitado |

## Controller — cache e acesso (dev.11)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Busca com `accessCheck(TRUE)` | Unit | Cache varia por `user.node_grants:view`, inclusive sem resultados | aprovado |
| CTA para criar verbete | Unit | Usa `createAccess('wiki_entry')` e mantém o `AccessResult` cacheável | aprovado |
| Busca em URLs de rota pública | Unit | Mantém `aculta_portal.wiki_search` e `aculta_portal.wiki_search_preview` | aprovado (form builder) |
| Discovery das rotas no Runtime | Runtime | Rotas disponíveis após gate de ativação e configuração | pendente; não validado no Runtime |

## Integração ACULTA Content Index — IDX 0.1

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Provider Wiki | Unit | Metadados allowlisted, revisão padrão, rejeição de bundles não suportados e propósito canônico `wiki` | aprovado |
| Relações `field_wiki_related`, `field_wiki_categories`, `field_wiki_image` | Unit | `related_entry`, `wiki_category`, `wiki_image` deduplicadas, com tipos-alvo allowlisted | aprovado |
| Provider de categoria Wiki | Unit | Só `taxonomy_term:wiki_category`, metadados mínimos e purpose `wiki` | aprovado |
| Provider Wiki no ciclo do Index (harness) | Kernel | Classe de produção via tag fixture; backfill, relação deduplicada, update e delete | aprovado (1 teste / 17 assertions) |
| Declaração/tag owner `aculta_index.provider` | Kernel | Boot da closure real Wiki + Index, discovery dos providers via tags e registry, sem importar a configuração do Runtime | aprovado anteriormente (1 teste / 8 assertions); não repetido nesta sessão, sem `SIMPLETEST_DB` |
| Consulta do Index e relações sob acesso Wiki | Kernel | Mesmo usuário, Entity Access e policy Portal em purpose `wiki`; alvo não publicado filtrado; Domain original restaurado | aprovado (2 testes / 32 assertions; MariaDB e SQLite) |
| Relações categoria e imagem | Kernel | `wiki_category`/`wiki_image` consultam os providers owners; Entity Access filtra alvos unpublished | aprovado (3 testes / 84 assertions em MariaDB; 5 depreciações) |
| Ativação e reconstrução em Runtime | Runtime | Sem alteração de estado antes de homologação explicitamente agendada | pendente |

Os adapters estão em código, mas `aculta_wiki` permanece desabilitado. O harness
Kernel lê as declarações/tag dos YAMLs owners e usa bridge fixture para a policy
Portal; comprova consulta com entidades reais, sem habilitar ou instalar toda a
configuração da Wiki. Runtime permanece pendente.

O boot transversal `ContentIndexOwnerIntegrationKernelTest` também resolve e
habilita a closure real de dependências de `aculta_wiki` e `aculta_index`, compila
o container e confirma o registry via tags owner (1 teste / 8 assertions,
registrado em 2026-10-23). Registro histórico de execução: naquela rodada, o
teste Unit passou com 61 testes / 277 assertions e a repetição do Kernel não
ocorreu porque `SIMPLETEST_DB` não estava configurada. Nesta atualização dev.20,
o Kernel foi executado com MariaDB isolada (resultados acima); nenhum Runtime
ou banco persistente foi alterado.

## Navegador

Revisão visual com Firefox ESR visível em `DISPLAY=:0` via geckodriver, nos temas
ACULTA420 e Olivero, em claro e escuro, a 390, 768 e 1024 CSS px, depois que o
módulo estiver habilitado no servidor de testes.
