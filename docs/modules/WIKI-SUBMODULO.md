# Wiki420 como submódulo do Portal — planejamento

Status: **planejado**. Esta entrega é só documentação. Nenhum código foi movido,
nenhuma rota foi alterada e nenhum módulo foi habilitado.

Este documento é a fonte única da frente. Cada fase termina antes da próxima começar.

## Objetivos da frente

1. **Submódulo:** criar `aculta_wiki` em
   `web/modules/custom/aculta_portal/modules/aculta_wiki` e mover para ele o código inteiro
   da Wiki (`WikiController`, `WikiSearchFormBuilder` e as rotas `aculta_portal.wiki_*`),
   sem alterar URLs.
2. **Estatísticas prontas para a Wiki:** preparar o `aculta_statistics` para receber a Wiki,
   com `WikiProvider`, métricas de publicação e atualização, e ranking por verbete.
   O Statistics continua só lendo conteúdo; o submódulo `aculta_wiki` não depende dele.
3. **Construção contínua da Wiki:** depois dos objetivos 1 e 2, seguir com upload
   contextual, adaptador de discussão do Fórum, busca e notificações.

## Estado de partida (verificado no repositório)

**Conteúdo e configuração** (em `config/sync`):

- Tipo `wiki_entry`, fluxo `wiki_editorial` (rascunho, em revisão, publicado).
- Views `wiki_entries` (`/wiki/verbetes`) e `wiki_categories`.
- Domínio `wiki420` (produção `wiki420.aculta.org`).
- Formato de texto `wiki`, com mídia `document` no CKEditor.

**Código** (fora de `aculta_wiki`, ainda sem submódulo):

- `aculta_portal/src/Controller/WikiController.php` (287 linhas).
- `aculta_portal/src/Presentation/WikiSearchFormBuilder.php`.
- Rotas `aculta_portal.wiki_home` (`/wiki`), `aculta_portal.wiki_search` (`/wiki/busca`) e
  `aculta_portal.wiki_search_preview` (`/wiki/busca/previa`) em `aculta_portal.routing.yml`.

**Estatísticas** (`aculta_statistics`, 0.9.0-dev.5):

- Existe: `wiki.entries.read`, gravada pelo beacon `WikiReadController` e pelo
  `WikiReadHooks`, com antiabuso por `flood` e retenção de 90 dias. Há testes unitários e de kernel.
- Não existe: `wiki.entries.published`, `wiki.entries.updated`, `WikiProvider` e ranking por verbete.
  Esses itens aparecem no `INTEGRATIONS.md`, mas não estão no código.

**Divergências corrigidas nesta entrega:** `CURRENT-STATE.md` dizia que o `aculta_statistics`
não existia e que o contador de leitura não estava decidido. Ambos estavam desatualizados.

## Regras que valem para esta frente

- `docs/operations/AGENT-CONTENT.md`: mudança de escopo da Wiki (módulo próprio, busca,
  notificações, perfis) exige atualizar, no mesmo commit, o inventário LGPD, a política de
  privacidade e o `AGENTS.md` da Wiki.
- A Wiki não é reimplementada no Fórum. O Fórum só oferece o adaptador de discussão por verbete.
- O `aculta_statistics` só lê contagens e conteúdo. Nunca lê autoria privada.
- Não abrir PR nem fazer merge sem pedido explícito do responsável.
- Nenhuma métrica nova entra em produção antes de estar descrita no inventário LGPD.

## Fase 0 — Planejamento e documentação (esta entrega)

- Este documento.
- Correção das divergências em `aculta_statistics` (`CURRENT-STATE.md`, `ROADMAP.md`,
  `INTEGRATIONS.md`, `CHANGELOG.md`).
- Referências atualizadas em `AGENT-CONTENT.md`, `LGPD-INVENTARIO.md`, `docs/modules/README.md`
  e no adaptador de Fórum (`aculta_forum/docs/CONTEXTS.md`).

**Verificação:** `git diff --stat` mostra só arquivos `.md`. Nenhum YAML, PHP ou config alterado.

## Fase 1 — Casca do submódulo

- Criar `aculta_wiki/aculta_wiki.info.yml`, `AGENTS.md`, `README.md`, `CHANGELOG.md`,
  `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, seguindo o padrão dos outros submódulos do Portal.
- Dependências declaradas: `aculta_portal`, `domain`, `views`, `node`, `content_moderation`.
  Sem dependência de `aculta_statistics`.
- Ainda não habilitado nesta fase.

**Verificação:** validadores do repositório (`validate-own-module-packages` e
`validate-public-slugs`, já citados no CHANGELOG do Portal) passam.

## Fase 2 — Mover o código inteiro

- Mover `WikiController`, `WikiSearchFormBuilder` e as três rotas `aculta_portal.wiki_*` para
  `aculta_wiki`. Os caminhos e os nomes das rotas não mudam.
- Os demais pontos de `aculta_portal` que citam `wiki420` (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`,
  `DomainPresentationBuilder`) tratam de domínio e purpose, não da Wiki. Ficam no Portal, com
  revisão de cada um para confirmar que não há código da Wiki escondido.
- Configuração: manter os UUIDs e IDs de `config/sync`. A decisão de quem é dono de cada item
  está em "Decisões pendentes".
- Habilitar `aculta_wiki` só quando as rotas estiverem movidas e testadas.

**Verificação:**

- Unit e kernel do controlador e da busca.
- `drush cr` sem erro.
- Config Sync sem diferenças.
- HTTP 200 em `/wiki`, `/wiki/verbetes`, `/wiki/busca` e `/wiki/busca/previa` no domínio `wiki420`.
- Backup privado antes de qualquer `updb`, como no procedimento atual.

## Fase 3 — Documentação obrigatória da mudança de escopo

- `AGENT-CONTENT.md`: atualizar "Estado atual" e "Mudança de escopo" para refletir o módulo.
- `LGPD-INVENTARIO.md`: pendência 3 (base legal e retenção da Wiki) e a linha de
  `aculta_statistics`, que já cita `wiki.entries.read`. Incluir `wiki.entries.published` e
  `wiki.entries.updated` antes de criá-las.
- Política de privacidade: seção "Bases legais por uso do site".
- `aculta_wiki/AGENTS.md`: regras do submódulo.

## Fase 4 — `aculta_statistics` pronto para receber a Wiki

4.1. **Documentação corrigida** (feito na Fase 0).

4.2. **`WikiProvider`** com duas métricas diárias agregadas, sem autoria:

- `wiki.entries.published`: primeira publicação de um verbete. Seguir o padrão do Fórum (dev.5,
  com a correção de primeira publicação de revisão), para não contar revisões como novos verbetes.
- `wiki.entries.updated`: nova revisão publicada de um verbete já publicado.
- Conteúdo sincronizado ou importado não gera evento.

4.3. **Fronteira:** o provider lê entidades `wiki_entry` e eventos de publicação. Não importa
classes do `aculta_wiki`. Se o submódulo não estiver habilitado, o provider não gera dados.

4.4. **Ranking por verbete** no painel da área Wiki: os verbetes mais lidos no período, com
título resolvido na renderização. Verbetes excluídos saem do ranking sem erro. Exportação CSV
com o mesmo recorte.

4.5. **Catálogo e retenção:** registrar as duas métricas no `MetricCatalog` com sensibilidade
pública e retenção de 90 dias, como `wiki.entries.read`.

4.6. **Testes:**

- Unit: `WikiProvider` e o cálculo de primeira publicação e atualização.
- Kernel: agregado diário e ranking, incluindo verbete excluído.
- Runtime: beacon responde 204 em verbete publicado; ranking exibe dados reais.

**Gate:** nenhuma métrica nova é criada antes da atualização do inventário LGPD (Fase 3).

## Fase 5 — Construção contínua da Wiki

Ordem sugerida, depois das Fases 1–4:

1. Contagem de verbetes na view `wiki_entries` e campos faltantes.
2. Upload contextual pela política central `aculta_portal.user_upload_policy`, com aba própria.
3. Adaptador `wiki` do Fórum: uma discussão por verbete, em Comment no Node. A revisão
   editorial continua separada.
4. Busca e notificações. Mudam a retenção e exigem atualizar o inventário LGPD de novo.
5. Estatísticas de edição e revisão por verbete, a partir do histórico do Node.

## Decisões pendentes (responsável)

- **Dono da configuração:** manter os itens de `config/sync` no núcleo ou mover para
  `aculta_wiki/config/install`? Recomendação: manter os UUIDs e mover só depois de confirmar
  o Config Sync sem diferenças.
- **Ranking:** só no painel administrativo ou também público? Padrão adotado: só administrativo,
  como os demais painéis do Statistics.
- **Contagem de atualizações:** contar todas as revisões ou só revisões publicadas?
  Padrão adotado: só revisões publicadas.

## Riscos

- **Produção ativa:** `wiki420.aculta.org` está no ar. A mudança precisa ser invisível ao visitante.
- **URLs:** nenhuma URL pode mudar. Redirecionamentos não são previstos nesta frente.
- **Dependência circular:** `aculta_wiki` não depende de `aculta_statistics`, e o Statistics
  não importa classes do submódulo.
- **Configuração:** mover configuração entre módulos pode gerar diferenças no Config Sync.
  A Fase 2 cobre isso.

## Mapa das tarefas desta sessão

O briefing de 2026-10-22 organiza a execução da Fase 1 à Fase 4 em tarefas
numeradas. Este mapa liga cada tarefa à fase correspondente e registra o estado
ao fim da sessão.

| ID | Tarefa | Fase do plano | Commit | Estado |
| --- | --- | --- | --- | --- |
| A1 | Inventário da Fase 2 e anexo neste documento | Fase 2 | `909663c4` | concluída |
| A2 | Casca do submódulo (`info.yml`, AGENTS, README, CHANGELOG, ROADMAP, TEST-MATRIX) | Fase 1 | — | pendente |
| B1 | Mover `WikiController` e `WikiSearchFormBuilder` | Fase 2 | — | pendente |
| B2 | Mover as três rotas com nomes e caminhos idênticos | Fase 2 | — | pendente |
| B3 | Revisar pontos `wiki420` de domínio/purpose | Fase 2 | — | pendente |
| B4 | Testes unitários dos componentes movidos | Fase 2 | — | pendente |
| C1 | Atualizar regras de conteúdo e `AGENTS.md` do submódulo | Fase 3 | — | pendente |
| C2 | Conferir a seção de bases legais da política | Fase 3 | — | pendente |
| D1 | Inventário LGPD com as duas métricas planejadas | Fase 3 (gate) | — | pendente |
| D2 | Catálogo e retenção instalada | Fase 4 | — | pendente |
| D3 | `WikiProvider` e testes de publicação/revisão/importação | Fase 4 | — | pendente |
| D4 | Teste de kernel do agregado diário (opcional) | Fase 4 | — | pendente |
| E1 | Status final deste plano | Encerramento | — | pendente |
| E2 | `git push` da branch | Encerramento | — | pendente |

> **Retenção das métricas.** O briefing pede 90 dias para
> `wiki.entries.published` e `wiki.entries.updated`. A documentação da
> `aculta_statistics` descreve a primeira como instantânea. Vale o briefing, e a
> correção da documentação fica registrada em E1.

## Anexo — Inventário da Fase 2

Levantamento feito com Grep em `web/modules/custom`, `web/themes/custom` e
`config/sync`, excluindo `node_modules`, `vendor`, core e contrib. Classificação:
**move** (vai para `aculta_wiki`), **fica no Portal** (domínio, purpose, tema ou
configuração) e **revisar** (precisa de ajuste de contrato).

### `WikiController`

| Arquivo:linha | Ocorrência | Classificação | Ação |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_portal/src/Controller/WikiController.php:28` | Declaração da classe | move | `git mv` para `aculta_wiki/src/Controller/` e namespace `Drupal\aculta_wiki\Controller` |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:192` | `_controller` da rota home | move | B2 move a rota inteira |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:226` | `_controller` da rota de busca | move | B2 move a rota inteira |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:234` | `_controller` da prévia | move | B2 move a rota inteira |
| `web/modules/custom/aculta_portal/src/Controller/WikiController.php:162` | `Url::fromRoute('aculta_portal.wiki_search')` | move | Nome de rota não muda |
| `web/modules/custom/aculta_portal/src/Controller/WikiController.php:67` | `Url::fromUserInput('/wiki/verbetes')` | move | Caminho servido por View; não muda |
| `web/modules/custom/aculta_portal/src/Controller/ShopController.php:16` | Comentário cita `/wiki` como caminho de purpose | fica no Portal | Comentário de domínio; não é código da Wiki |

### `WikiSearchFormBuilder`

| Arquivo:linha | Ocorrência | Classificação | Ação |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php:15` | Declaração da classe | move | `git mv` para `aculta_wiki/src/Presentation/` |
| `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php:34` | Rota `aculta_portal.wiki_search` no formulário | move | Nome de rota não muda |
| `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php:52` | Rota `aculta_portal.wiki_search_preview` | move | Nome de rota não muda |
| `web/modules/custom/aculta_portal/aculta_portal.services.yml:211` | Definição do serviço | revisar | Definição vai para `aculta_wiki.services.yml`; o Portal passa a injetar a interface de forma opcional |
| `web/modules/custom/aculta_portal/src/Presentation/DomainPresentationBuilder.php:64` | Tipo do construtor | revisar | Injetar `?WikiSearchFormBuilderInterface` com `@?aculta_wiki.presentation.wiki_search_form`; `wiki_search` fica `NULL` sem o submódulo |
| `web/modules/custom/aculta_portal/tests/src/Unit/DomainPresentationContractTest.php:20` | Dados de purpose `wiki` | fica no Portal | Teste de contrato de apresentação |

### `aculta_portal.wiki_` (nomes de rota)

| Arquivo:linha | Ocorrência | Classificação | Ação |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:189` | Definição `aculta_portal.wiki_home` | move | B2 move com o mesmo nome |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:223` | Definição `aculta_portal.wiki_search` | move | B2 move com o mesmo nome |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:231` | Definição `aculta_portal.wiki_search_preview` | move | B2 move com o mesmo nome |
| `web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php:191-193` | Atribui `_aculta_domain_purpose: wiki` às rotas | fica no Portal | Concern de domínio; os nomes são estáveis |
| `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php:65` | `aculta_portal.wiki_home` em `PUBLIC_PORTAL_ROUTES` | fica no Portal | Concern de acesso público do Portal |
| `web/modules/custom/aculta_portal/tests/src/Unit/DomainRoutePolicyTest.php:66` | Teste da política de rotas | fica no Portal | Cobre nomes estáveis, que não mudam |

### `/wiki` (caminhos)

| Arquivo:linha | Ocorrência | Classificação | Ação |
| --- | --- | --- | --- |
| `web/modules/custom/aculta_portal/aculta_portal.routing.yml:190,224,232` | Caminhos `/wiki`, `/wiki/busca`, `/wiki/busca/previa` | move | Caminhos idênticos em `aculta_wiki.routing.yml` |
| `web/modules/custom/aculta_portal/src/PathProcessor/PurposeFrontPathProcessor.php:30,37` | `FRONT_PATHS['wiki'] = '/wiki'` | fica no Portal | Concern de domínio/purpose |
| `web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php:237` | Comentário cita `/wiki` | fica no Portal | Comentário de domínio |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/aculta_statistics.routing.yml:10` | `/estatisticas/wiki/{node}/leitura` | fica | Beacon de leitura da estatística; URL própria, não é rota da Wiki |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Hook/WikiReadHooks.php:32` | Anexa biblioteca de leitura | fica | Estatística de leitura já existente |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/tests/src/Kernel/WikiReadKernelTest.php:106` | URL do beacon | fica | Teste da estatística |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/tests/src/Unit/WikiReadControllerTest.php:128` | URL do beacon | fica | Teste da estatística |
| `web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php:244` | Host `wiki420.aculta.org` | fica | Lista de hosts esperados; domínio não muda |
| `config/sync/views.view.wiki_entries.yml:148` | Caminho `wiki/verbetes` | fica | View de configuração; `config/sync` não muda de dono nesta sessão |
| `web/themes/custom/aculta420/src/Hook/ThemeHooks.php:260-274` | Marca `wiki420` no cabeçalho | fica | Tema; consome a chave neutra do Portal |
| `web/themes/custom/aculta420/components/patterns/wiki-entry/wiki-entry.default.story.yml:5` | URL de exemplo | fica | Story do tema |

### Fora dos módulos (documentação a revisar)

| Arquivo:linha | Ocorrência | Classificação | Ação |
| --- | --- | --- | --- |
| `docs/operations/AGENT-CONTENT.md:83` | “Não existe um módulo de Wiki habilitado” | revisar | C1 atualiza o estado real: o código existe, mas o módulo não está habilitado |
| `docs/operations/AGENT-CONTENT.md:87` | Regra de mudança de escopo | revisar | C1 registra a casca e a pendência de habilitação |
| `scripts/institution/politica-de-privacidade.html:17` | Base legal da Wiki | fica | C2 confere a redação; não inventar base |
| `docs/compliance/LGPD-INVENTARIO.md:53,67` | Métricas agregadas e pendência da Wiki | revisar | D1 lista as duas métricas; a base legal continua com o responsable |
| `docs/portal/WIKI.md` | Apresentação e limites da Wiki420 | revisar | Atualizar apenas se o caminho dos componentes mudar |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/INTEGRATIONS.md` | “a Wiki não é módulo”; provedor `WikiProvider` | revisar | C1/E1 corrigem o estado e registram a divergência de retenção |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/METRIC-CATALOG.md` | Retenção de `wiki.entries.published` como “instantâneo” | revisar | Divergência com o briefing (90 dias para ambas); registrar em E1 |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/CURRENT-STATE.md` | “a Wiki não é módulo” | revisar | C1/E1 corrigem o estado |

## Anexo — Fronteira Portal ↔ aculta_wiki (revisão da tarefa B3)

Revisão dos cinco pontos do Portal que citam a Wiki420. Nenhum deles é
funcionalidade da Wiki: todos resolvem domínio, purpose ou apresentação neutra.
Ficam no Portal, com a justificativa abaixo.

| Arquivo:linha | O que faz | Decisão |
| --- | --- | --- |
| `src/Domain/DomainPurposeManager.php:24` | Mapeia o purpose `wiki` para o Domain `wiki420_aculta_org` | fica — é o mapa de domínios da plataforma, não da Wiki |
| `src/Domain/DomainPurposeManager.php:59` | Comentário sobre o sitemap e o host da Wiki | fica — comentário de contexto do domínio |
| `src/Domain/ContentPurposeResolver.php:21,66-77,137` | Reconhece `wiki_entry` e rotas de nó da Wiki para atribuir o purpose | fica — resolução de purpose é do host; mover inverteria a dependência |
| `src/PathProcessor/PurposeFrontPathProcessor.php:37` | Purpose `wiki` serve `/wiki` como raiz | fica — caminho público do domínio, contrato que não muda |
| `src/EventSubscriber/DomainRouteSubscriber.php:191-197` | Marca `_aculta_domain_purpose: wiki` nas rotas da Wiki e na view | fica — usa nomes de rota e id de view, não classes do submódulo |
| `src/Presentation/DomainPresentationBuilder.php:105,119,128` | Marca do cabeçalho, formulário de busca e cabeçalho do site | fica — apresentação neutra; o formulário entra por `WikiSearchFormBuilderInterface` opcional (B1) |

Consequência: o submódulo depende do Portal; o Portal não importa nenhuma classe
de `Drupal\aculta_wiki`. Se a Wiki deixar de existir, o Portal continua
funcionando com `wiki_search` nulo, e o purpose/domínio permanecem inofensivos.

## Anexo — Política de privacidade (verificação da tarefa C2)

A seção “Bases legais por uso do site” existe em
`scripts/institution/politica-de-privacidade.html` e já traz a linha da Wiki:

> **Wiki**, quando ativa: consentimento na contribuição (art. 7º, I), e interesse
> legítimo na curadoria editorial (art. 7º, IX).

Não há lacuna de base legal. Nada foi inventado e nada foi alterado na política:
o texto atual cobre contribuição e curadoria. As métricas planejadas
(`wiki.entries.published`, `wiki.entries.updated`) são contagens diárias
agregadas, sem autoria e sem IP, e não acrescentam uso de dado pessoal que exija
nova base; a decisão de mencioná-las na política fica com o responsável.
