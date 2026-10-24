# Matriz de testes — ACULTA Wiki

Cobertura planejada e executada do submódulo. Os cenários abaixo listados como
`planejado` continuam pendentes; a seção da Fase 5 registra o que já roda.

## WE SATIVA no ACULTA Studio (dev.54)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Entrada `/studio/we-sativa` | Kernel | Rota única no purpose `account`; provider disponível só com Wiki | aprovado (focal: 1 teste / 37 asserções; classe: 15 / 612) |
| Meu trabalho | Kernel | Candidatos são limitados por UID; `view` Entity Access é revalidado por item no purpose Wiki | aprovado para verbetes; proposta sem fixture |
| Acesso e cache | Kernel | Conta confirmada com criação pode entrar; conta alheia sem itens não pode; cache varia por usuário/domínio | aprovado; 286 depreciações Drupal/contrib e 16 PHPUnit na classe |
| Rota de criação contextual | Kernel | `node.add` autoriza a conta confirmada; o link Studio conserva parâmetros e Domain Wiki quando iniciado em `main` | aprovado (dev.40; 1 teste / 41 asserções) |
| Construção do formulário Node | Kernel | Form API Core, conta confirmada, estado `draft` selecionado, transição `draft_to_review` e sem opção de publicar | aprovado (dev.42) |
| Edição própria e Domain | Kernel | Workspace só mostra edição após `update` Entity Access no purpose Wiki; link usa rota Core e Domain Wiki | aprovado (dev.44; incluso nos 2 focais) |
| Prévia privada | Kernel | Handler Core salva formulário temporário no tempstore; autora acessa, outra conta não; sem nova revisão persistida | aprovado (dev.44; focal combinado: 2 testes / 111 asserções; handler testado sem requisição HTTP) |
| Submissão inicial do Node | Kernel | FormBuilder nativo persiste verbete privado em `draft` | aprovado (dev.42; 1 teste / 54 asserções) |
| Envio para revisão | Kernel | Novo submit grava revisão `review` pela transição oficial `draft_to_review`, sem publicar | aprovado (dev.42; incluído no focal) |
| Paginação | Kernel | Cursores independentes para verbetes/propostas, ordem estável, acesso revalidado e cache por URL | aprovado (dev.47; focal combinado: 2 testes / 84 asserções; cursor posicional, links GET) |
| Composição SDC do Meu Trabalho | Kernel | Cabeçalho, seções, cards, grade, estado vazio e ações autorizadas usam componentes existentes | aprovado (dev.54; focal combinado: 2 testes / 89 asserções) |
| Layout sem-JS e mobile | Browser Firefox ESR visível | ACULTA420 e Olivero, 320px+, WCAG 2.2 AA | pendente |

## R2 — ciclo de vida da imagem (dev.53)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Media/File acompanham publicação | Kernel | Imagem de rascunho fica privada; publicação torna Media e download acessíveis | aprovado previamente |
| Falha/rebuild e retry do mesmo arquivo | Kernel | Rollback limpa caches/chave; retry grava um único Media ligado ao Node | aprovado (focal: 1 / 33; classe: 10 / 447; Unit: 12 / 79) |
| Falha no Node save | Kernel | Media removida e File volta a temporário | aprovado previamente |
| Remoção com revisão antiga | Kernel | Media/File permanecem enquanto revisão ainda referencia a imagem | aprovado previamente |
| Limpeza de órfã após exclusão da última origem | Kernel | Retém referências em revisão histórica; remove Media/File sem referências globais | aprovado (dev.51; 1 teste / 46 asserções) |
| Retenção de uma hora | Kernel | Aguarda o grace period antes de considerar arquivo | aprovado (dev.53; incluído em 4 testes / 169 asserções) |
| Limite de 25 e cursor rotativo | Kernel | Avança além dos primeiros 25 candidatos, inclusive se eles permanecerem em uso | aprovado (dev.53; teste alcança e remove o 26º arquivo liberado) |
| Reuso por outro bundle e falha de inspeção global | Kernel | Mantém Media/File se Article referenciar a Media ou a inspeção falhar | aprovado (dev.53; incluído no focal) |
| Alt obrigatória, formatos/tamanho e validação central | Unit | Envio inválido recebe erro sem criar Media | aprovado previamente |

Classe `WikiLeadImageLifecycleKernelTest` completa: MariaDB, 21 testes / 918
asserções, 291 depreciações Core/contrib/PHPUnit; sem falhas.

## R2 — rotas, domínio e acesso (dev.30)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Rotas públicas | Kernel | `/wiki`, busca e prévia têm exatamente um owner e permissão pública definida | aprovado (2 testes focais / 93 asserções) |
| Listagem de verbetes | Kernel | `/wiki/verbetes` resolve uma View, purpose `wiki` e acesso autenticado | aprovado |
| Grants divergentes | Kernel | Contas do mesmo papel recebem apenas verbetes autorizados | aprovado |
| Cache vazio/negativo/malformado | Kernel | Cache varia por node grants, incluindo termo vazio e `q[]` | aprovado |
| Home do domínio Wiki | Runtime HTTP | `/` retorna 200; `/wiki` retorna 404 como caminho interno conforme política multidomínio | aprovado em preflight read-only (2026-10-23) |
| Listagem, busca e prévia | Runtime HTTP | `/wiki/verbetes`, `/wiki/busca` e `/wiki/busca/previa` retornam 200 | aprovado em preflight read-only (2026-10-23) |
| Ativação e homologação integral | Operacional | Index e dependências necessários ativos; configuração e fluxos homologados | pendente; Index estava desativado e nenhum estado foi alterado |

## Aplicação editorial de propostas (dev.29)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Persistir campos estruturados | Kernel | Submissão via Form API guarda título selecionado na entidade privada | aprovado no teste focal existente; repetir classe após aceite |
| Aprovar subconjunto e publicar | Kernel | Revisão-base atual recebe campo aprovado e publica por transições oficiais | aprovado (focal: 1 teste / 119 asserções; classe: 9 / 393) |
| Revisão-base concorrente | Kernel | Aceite conflita sem mudar proposta ou verbete | aprovado (focal: 1 teste / 144 assertions; classe: 10 / 459) |
| Escolha substituir/acrescentar em referências | Kernel | `append` preserva lista e deduplica URLs; `replace` substitui integralmente | aprovado (focal: 1 teste / 122; classe: 9 / 404) |
| Escolha substituir/acrescentar em categorias/relações | Kernel | Listas validam destino e substituem/acrescentam sem duplicatas | aprovado (focal: 1 / 132; classe: 9 / 414) |
| Moderador sem permissão de editar o verbete | Kernel | Aceite negado sem alterar proposta, revisão ou campos | aprovado (incluído no focal: 1 / 144; classe: 10 / 459) |
| Sem permissão da transição oficial | Kernel | Operação negada sem persistência parcial | aprovado (incluído no focal: 1 / 144; classe: 10 / 459) |
| Migração `proposed_fields` | Update hook | Instala armazenamento revisionável em proposta já existente | implementada; não aplicada ao Runtime |

## Propostas privadas — status e histórico (dev.24)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Histórico da proposta | Kernel | Revisões autorizadas mostram data, estado e retorno, sem identidade de moderador | aprovado (1 teste / 93 assertions) |
| Paginação do histórico | Kernel | 21 revisões são exibidas em páginas de 20 e 1, com cursor estável | aprovado |
| Acesso à revisão | Kernel | Cada revisão passa por `view revision` antes de seus campos serem renderizados | aprovado |
| Histórico de outra conta | Kernel | Acesso à proposta continua negado pelo Entity Access existente | aprovado em dev.22 |

## Retirada de proposta pela autoria (dev.28)

| Cenário | Tipo | Resultado esperado | Estado |
| --- | --- | --- | --- |
| Retirar estado `submitted` | Kernel | Form API confirma e grava nova revisão `withdrawn`; anterior permanece | aprovado (focal: 1 teste / 110 assertions; classe: 9 / 384) |
| Retirar estado `needs_changes` | Kernel | Nova revisão encerra a proposta e preserva o retorno na revisão anterior | aprovado |
| Outra pessoa autora | Kernel | Serviço nega retirada por Entity Access/ownership | aprovado |
| Revisão-base obsoleta | Kernel | Conflito sem revisão extra | implementação; teste direto pendente |
| Reabrir proposta retirada | Kernel | Estado final impede nova decisão editorial | coberto pelo gate de estado da decisão; teste específico pendente |

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
| Media existente em mudança posterior de publicação | Kernel | status e download seguem referências atuais publicadas; referências compartilhadas mantêm acesso | aprovado (dev.43; focal e regressões no subconjunto: 8 testes / 237 asserções) |
| Exclusão do último Node que publica a Media | Kernel | hook de exclusão despublica a Media após apagar o último Node atual que a referencia | aprovado (dev.37; focal: 1 / 27; classe: 13 / 548) |
| Falha após persistência do Node | Kernel | rollback remove Node e Media e restaura File temporário | aprovado (MariaDB) |
| Remoção em nova revisão com referência antiga | Kernel | revisão antiga preserva Media/File no armazenamento, mas download é negado sem referência publicada atual | aprovado (dev.43; incluído nos 8 testes focais) |
| Reuso da Media em outro conteúdo | Kernel | preservar arquivo até não haver usos em nenhuma entidade/revisão | aprovado para outro bundle Node (dev.53); outros entity types seguem cobertos pelo inspetor central |
| Validador de upload inválido | Kernel | `alt` vazio produz erro; nenhuma Media é criada e o File permanece temporário | aprovado (dev.38; focal 1 / 27; classe 14 / 575) |
| Arquivo privado após despublicar com revisão publicada anterior | Kernel | Core pode autorizar pela revisão antiga; hook da Wiki nega e o endpoint bloqueia o download | aprovado (dev.43; invocado por ModuleHandler no Kernel) |

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
| Confirmação pelo Mail Confirmer real | Kernel | Confirmação `aculta_registration` aciona hook Portal, atribui `email_confirmed` e permite `createAccess('wiki_entry')` | aprovado (dev.36; focal: 1 / 33; classe: 11 / 492; 286 depreciações Core/contrib, 12 PHPUnit) |
| Fila de revisão com candidatos negados | Kernel | 26 VIDs recentes sem grant não ocultam uma revisão autorizada mais antiga | aprovado (dev.21) |
| Status privado da proposta | Kernel | Autoria lê status/nota; outra conta é negada pelo Entity Access da entidade | aprovado (dev.22) |
| Aviso de decisão | Kernel | Fila em memória recebe estado, nota e URL privada; sem texto sugerido ou envio real | aprovado (dev.22) |
| Redirect da submissão | Kernel | Abre a página privada do registro recém-persistido | aprovado (dev.23; 1 teste / 85 assertions) |

Resultado Kernel dev.22: 9 testes / 355 assertions; 286 depreciações
Drupal/contrib e 10 do PHPUnit. A suíte Unit passou com 66 testes / 338
assertions e 3 depreciações PHPUnit.

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
| Limite de abuso | Kernel | A 11ª proposta em uma hora recebe HTTP 429 e não persiste | aprovado (focal: 1 teste / 94 assertions) |
| Reenvio com o mesmo token | Kernel | POST direto repetido e replay Form API retornam a proposta existente | aprovado (focal: 1 teste / 99 assertions; classe: 9 / 371) |
| Contenção entre conexões idempotentes | Kernel MariaDB | Segundo DB connection aguarda, não persiste duplicata e replay posterior lê a proposta | aprovado (focal: 1 teste / 99 assertions) |
| Concorrência entre processos PHP | Kernel | Workers independentes submetem o mesmo token e produzem uma entidade | pendente |
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
| Provider Wiki | Unit/Kernel | Metadados allowlisted, revisão padrão, rejeição de bundles não suportados, purpose `wiki` e ausência de `owner_uid` | aprovado (dev.50; provider v4) |
| Compatibilidade de fingerprint | Kernel | Geração vinculada a inventário antigo é recusada no backfill e na ativação | aprovado (2 testes / 16 asserções; gate genérico do Index) |
| Relações `field_wiki_related`, `field_wiki_categories`, `field_wiki_image` | Unit | `related_entry`, `wiki_category`, `wiki_image` deduplicadas, com tipos-alvo allowlisted | aprovado |
| Provider de categoria Wiki | Unit | Só `taxonomy_term:wiki_category`, metadados mínimos e purpose `wiki` | aprovado |
| Provider Wiki no ciclo do Index (harness) | Kernel | Classe de produção via tag fixture; backfill, relação deduplicada, update e delete | aprovado (1 teste / 17 assertions) |
| Declaração/tag owner e bootstrap real | Kernel | Closure Wiki + Image + Portal + Index, providers tagueados descobertos e container compilado sem fixture | aprovado (dev.46; classe focal: 6 testes / 185 asserções) |
| Projeção e filtro sob acesso real | Kernel | Provider v3, relação deduplicada, `EntityHooks` real, `main` nega, `wiki` autoriza, alvos privados filtrados e Domain restaurado | aprovado no teste owner real |
| Consulta outgoing real sob acesso Wiki | Kernel | `ContentIndexRelationQuery` consulta `related_entry`, `wiki_category` e `wiki_image`; Entity Access filtra destinos por grants e Domain original restaura | aprovado (dev.46; geração semeada só com entidades necessárias; sem rebuild integral) |
| Categoria sem provider de destino | Kernel | Destino não indexado não aparece na consulta outgoing | aprovado (dev.52; incluído na classe owner real) |
| Media publicada ligada a verbete em rascunho | Kernel | A consulta não revela a relação se a origem não está autorizada | aprovado (dev.52; incluído na classe owner real) |
| Revisão pendente não padrão | Kernel | Provider não projeta revisão pendente; revisão padrão publicada continua disponível | aprovado (dev.46) |
| Domain Wiki ausente | Kernel | Access filter falha fechado sem recorrer ao Domain ativo | aprovado (dev.46) |
| Regressão direcionada de providers e acesso Wiki | Kernel | Bootstrap owner, discovery real, providers Wiki e consulta de relações | aprovado (dev.46; 6 testes / 185 asserções; 201 depreciações upstream) |
| Backfill, fingerprint, update e delete Wiki reais | Kernel | Geração real recebe projeção v4 sem UID; edição atualiza hash/rótulo e troca relações; exclusão remove entrada e outgoing | aprovado (dev.50; focal: 1 teste / 38 asserções; classe owner: 7/238; 201 depreciações upstream) |


| Ativação e reconstrução em Runtime | Runtime | Sem alteração de estado antes de homologação explicitamente agendada | pendente |

Os adapters estão em código. O teste owner consulta as três relações pela API real
com os providers descobertos pelo container. O cenário dev.50 executa um backfill
e ativa uma geração isolada com dados da fixture; isso não equivale a reconstruir
ou auditar o inventário completo instalado. No preflight read-only de 2026-10-23,
`aculta_wiki` estava ativo e `aculta_index` desativado no Runtime de teste; não
houve rebuild nem alteração de configuração. Runtime segue sem homologação integral.

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
