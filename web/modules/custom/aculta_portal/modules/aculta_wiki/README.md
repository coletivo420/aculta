# ACULTA Wiki — Wiki420 como submódulo

Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- `0.1.0-dev.35`: Kernel comprova que aceite exige transições oficiais e não
  grava parcialmente em caso de conflito ou falta de acesso editorial. Focal:
  1 teste / 144 assertions; classe Kernel: 10 testes / 459 assertions,
  aprovada em MariaDB isolada; 286
  depreciações Core/contrib e 11 do PHPUnit.
- `0.1.0-dev.34`: Kernel cobre conflito de revisão-base e moderador sem
  permissão de editar o verbete (focal: 1 / 140; classe: 10 / 455).
- `0.1.0-dev.33`: retry de imagem após rebuild limpa caches e estado temporário
  revertido; Kernel confirma novo envio sem órfãs. Focal: 1 teste / 33
  asserções; classe Kernel: 10 testes / 447 asserções; Unit do uploader: 12
  testes / 79 asserções.
- Preflight read-only de 2026-10-23: Wiki ativa e Index desativado no Runtime de
  teste. Na URI `wiki420`, `/` entrega a home (200); `/wiki` responde 404
  intencionalmente por ser o caminho interno da raiz. Listagem, busca e prévia
  retornam 200. Runtime ainda não está homologado integralmente.
- `0.1.0-dev.32`: Kernel cobre `append`/`replace` em categorias e relações, com
  deduplicação e destinos visíveis no domínio Wiki. Focal: 1 teste / 132
  asserções; classe Kernel: 9 testes / 414 asserções. Runtime desabilitado.
- `0.1.0-dev.31`: Kernel prova `append` e `replace` para referências em propostas
  sucessivas; append deduplica URLs e replace remove valores anteriores.
  Focal: 1 teste / 122 asserções; classe Kernel: 9 testes / 404 asserções.
  Categorias/relações ainda precisam de casos Kernel específicos.
- `0.1.0-dev.30`: R2 reforça unicidade das rotas e acesso/purpose da listagem
  com Kernel real; busca confirma isolamento por grants e cache em entradas
  negativas, vazias e malformadas. Focal: 2 testes / 93 asserções.
- `0.1.0-dev.29`: propostas guardam valores estruturados nos campos editoriais
  permitidos. Na aceitação, a equipe escolhe os campos aprovados e, para cada
  lista de referências, categorias ou relações, escolhe substituir ou
  acrescentar. A aplicação exige revisão-base atual, acesso editorial e as
  transições oficiais do workflow até `published`; a atualização é transacional.
  Focal: 1 teste / 119 asserções. Classe Kernel: 9 testes / 393 asserções.
  Runtime desabilitado.
- `0.1.0-dev.28`: autoria pode retirar propostas enviadas ou devolvidas, com
  confirmação Form API, nova revisão privada e preservação do histórico.
  Kernel focal: 1 teste / 110 assertions; classe completa: 9 testes / 384
  assertions antes do dev.29.
- `0.1.0-dev.27`: Kernel usa uma segunda conexão MariaDB para testar lock
  concorrente do token, recusa sem duplicar e replay posterior. Focal: 1 teste /
  99 assertions; classe completa: 9 testes / 371 assertions. Processos PHP
  separados ainda não foram testados.
- `0.1.0-dev.26`: reenvios do mesmo formulário retornam a proposta criada por
  até 24 horas; lock e transação protegem a gravação. Kernel focal: 1 teste / 97
  assertions; concorrência multiprocesso segue pendente.
- `0.1.0-dev.25`: submissões limitadas a 10 por hora por conta via Flood Core;
  excedentes recebem HTTP 429 sem persistência. Kernel focal: 1 teste / 94
  assertions; classe completa antes da idempotência: 9 testes / 366 assertions.
- `0.1.0-dev.24`: o status privado apresenta histórico paginado de revisões
  autorizadas, com estado e retorno editorial; nomes de moderadores não são
  expostos. Kernel focal: 1 teste / 93 assertions.
- `0.1.0-dev.23`: após enviar, a autoria segue diretamente para a página privada
  da proposta recém-criada. Kernel focal do fluxo de submissão: 1 teste / 85
  assertions.
- `0.1.0-dev.22`: pessoas autoras podem abrir status e retorno da própria
  proposta em `/wiki/propostas/{proposal}`. Decisões de ajustes/recusa enfileiram
  um e-mail mínimo para contas ativas e confirmadas, com link canônico privado.
  Entity Access agora usa o parâmetro `{proposal}` nas duas rotas editoriais.
  Kernel completo: 9 testes / 355 assertions.
- `0.1.0-dev.21`: fila de revisões percorre candidatos em lotes limitados e
  mantém cursor ao filtrar itens sem acesso. Kernel prova que 26 revisões
  recentes sem grant não escondem a revisão autorizada anterior. Kernel MariaDB
  completo: 9 testes / 344 assertions.
- `0.1.0-dev.20`: rotas e acesso foram repetidos em Kernel com bootstrap real.
  O CTA de colaboração usa `createAccess('wiki_entry')` efetivo: conta
  confirmada com permissão pode abrir; conta sem confirmação segue negada.
  Suíte MariaDB: 9 testes / 342 assertions; Runtime segue desabilitado.
- `0.1.0-dev.19`: a `WikiTab` existente do GanjaLab consome as filas proprietárias
  da Wiki. Revisões mostram ação para o Form API nativo do Node; propostas abrem
  a decisão Wiki em contexto account. Acesso a cada seção é distinto, e
  moderador de proposta não recebe listagem genérica de verbetes. Runtime e
  revisão visual Firefox/X11 pendentes.
- `0.1.0-dev.18`: o backend e Form API registram “pedir ajustes” e “recusar”
  como novas revisões privadas, com nota editorial, checagem de conflito e lock
  contra decisões simultâneas. Kernel MariaDB completo: 8 testes / 301
  assertions, sem warnings (284 depreciações Drupal/contrib e 9 PHPUnit).
  Aceite/aplicação e integração Studio seguem pendentes até decidir como o
  conteúdo entra no Node.
- `0.1.0-dev.17`: propostas exigem e-mail confirmado por `aculta_portal`.
  Verbetes publicados exibem link contextual apenas a colaboradores confirmados
  sem permissão de edição direta; o render varia cache por conta, permissões e
  domínio. Kernel completo: 8 testes / 290 assertions, sem warnings (284
  depreciações Drupal/contrib e 9 PHPUnit); verifica a regra e a recusa de conta
  não confirmada.
- `0.1.0-dev.16`: pessoas com `submit wiki proposals` podem abrir o Form API em
  `/wiki/verbetes/{node}/contribuir`. A rota exige acesso de visualização ao
  verbete e o serviço revalida estado e VID-base ao salvar. A proposta continua
  privada e não é aplicada automaticamente. Kernel verifica rota e callback.
- `0.1.0-dev.15`: serviço `aculta_wiki.editorial_queue` consulta propostas
  pendentes e revisões atuais em `review`. A fila de Nodes revalida acesso de
  leitura, revisão e atualização e exige transição válida sob o purpose `wiki`;
  proposta usa permissão editorial e paginação por cursor. Kernel completo da
  classe: 8 testes / 274 assertions; módulo permanece desabilitado no Runtime.
- `0.1.0-dev.14`: existe persistência privada revisionável para propostas de
  terceiros, com revisão-base e serviço que valida acesso, publicação e
  purpose `wiki`. Nenhuma expiração
  automática foi criada; retenção para propostas encerradas aguarda decisão.
- `0.1.0-dev.13`: rotas públicas e View da listagem são descobertas pelo
  bootstrap Kernel real; o acesso à busca é caracterizado com grants diferentes
  entre contas da mesma função. Busca vazia/negativa varia por
  `user.node_grants:view`. O ciclo de imagem e os contratos do Index continuam
  conforme dev.12.
- `0.1.0-dev.12`: upload da imagem de destaque usa armazenamento privado e
  transação compartilhada com o salvamento do verbete; a mídia só é publicada
  quando a revisão submetida do verbete está publicada. O incremento dev.11
  mantém cache da busca por grants e o CTA usa acesso real de criação. O
  provider opcional do Content Index permanece na versão de projeção 3.
- Kernel MariaDB também cobre a remoção da imagem na revisão atual: a Media e o
  arquivo são preservados enquanto uma revisão antiga do verbete os referencia.
- `WikiLeadImageLifecycleKernelTest` inicializa `aculta_wiki` e verifica
  `/wiki`, `/wiki/busca`, `/wiki/busca/previa`, a View `/wiki/verbetes` e o
  requisito `access content`. A busca real filtra grants de contas diferentes
  da mesma função e mantém contexto de cache nos resultados vazios/negativos.
- No preflight read-only de 2026-10-23, `aculta_wiki` estava ativo e
  `aculta_index` desativado no Runtime de teste. A configuração exportada e o
  estado efetivo do Runtime continuam sendo verificados separadamente.
- O estado do Runtime varia por ambiente; não inferir habilitação pela
  existência de código ou configuração exportada.
- As rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa` estão definidas neste
  submódulo com os nomes `aculta_portal.wiki_*`; `/wiki/verbetes` permanece
  contrato da listagem. A disponibilidade no site depende da ativação efetiva
  do módulo no Runtime.

## Limites

- Domínio e purpose (`wiki420` → `/wiki`) continuam no Portal. Este módulo não
  cria purpose, host nem alias.
- O submódulo não depende de `aculta_statistics`. As métricas agregadas de
  publicação são produzidas pelo `WikiProvider` da estatística, que observa
  nodes do bundle `wiki_entry` sem importar classes da Wiki.
- Conteúdo sincronizado ou importado não gera evento de publicação.
- Configuração continua em `config/sync`, com UUIDs e IDs preservados. A
  propriedade de cada item é decisão pendente do responsável.
- `WikiContentIndexProvider` projeta título, UUID, bundle, idioma, domínio,
  publicação, datas e relações allowlisted de `field_wiki_related` (`related_entry`),
  `field_wiki_categories` (`wiki_category`) e `field_wiki_image` (`wiki_image`).
  `WikiCategoryContentIndexProvider` projeta apenas metadados mínimos para
  `taxonomy_term:wiki_category`; imagens usam o provider `media:image` já
  registrado por `aculta_image`. Nenhum provider copia corpo, descrição de
  termo, URL, arquivo ou binário. Os serviços só são descobertos pelas tags
  quando Index e owners estão ativos; não há dependência obrigatória entre
  `aculta_wiki` e `aculta_index`.
- Os testes Unit cobrem metadados allowlisted, revisão padrão, deduplicação das
  relações nomeadas, purpose `wiki` e a projeção mínima da categoria.
- `ContentIndexWikiProviderKernelTest` cobre backfill e CRUD com a classe real
  do provider (1 teste / 17 assertions); usa tag de fixture e não habilita o
  módulo Wiki nem valida Domain/grants reais. A declaração/tag owner e a
  consulta autorizada são cobertas separadamente pelo teste seguinte.
- `ContentIndexWikiAccessKernelTest` consulta `related_entry`, `wiki_category`
  e `wiki_image` com providers dos YAMLs owners Wiki e Image, dados reais Node,
  Term e Media e a policy Portal via fixture. Entity Access filtra categoria e
  imagem não publicadas; a consulta outgoing revalida origem/alvos em purpose
  `wiki` e restaura `main` (MariaDB: 3 testes / 84 assertions, 5 depreciações).
- Em dev.166, `ContentIndexOwnerIntegrationKernelTest` com a closure real Wiki +
  Portal + Index testa projeção/deduplicação e `ContentIndexAccessFilter` com
  hooks e serviços reais, sem bridge de acesso. Acesso é negado em `main`,
  revalidado em `wiki`, alvos não publicados são filtrados e o Domain original
  é restaurado (1 teste / 34 assertions; 231 depreciações).
- Reexecução MariaDB isolada em 2026-10-23 dos três Kernel Wiki/Index: 6 testes /
  181 assertions, aprovados; 243 depreciações agregadas de Core/contrib/PHPUnit.
  A consulta outgoing end-to-end continua coberta pelo harness de acesso com
  fixture; não houve rebuild integral do inventário nem alteração no Runtime.

### Handoff para o ACULTA Content Index

O contrato Wiki está em `WikiContentIndexProvider` v3: `node:wiki_entry`,
revisão padrão, purpose `wiki`, metadados allowlisted e relações
`related_entry` → `node:wiki_entry`, `wiki_category` →
`taxonomy_term:wiki_category` e `wiki_image` → `media:image`. O provider dos
termos pertence à Wiki; o de Media pertence a `aculta_image`. Destinos seguem
sujeitos a Entity Access na consulta do Index. Alteração semântica futura exige
incremento da versão e rebuild de geração; esta entrega não dispara rebuild.
Termos/categorias e imagens não devem bloquear infraestrutura genérica ou
outros providers do Index. O Kernel owner agora consulta as três relações
outgoing por `ContentIndexRelationQuery` real no bootstrap Wiki + Portal + Index;
a geração é semeada apenas com as identidades relevantes e não valida rebuild
integral do inventário. A projeção não inclui corpo, revisão pendente, autoria,
descrição, URL ou arquivo. Homologação Runtime continua separada.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
