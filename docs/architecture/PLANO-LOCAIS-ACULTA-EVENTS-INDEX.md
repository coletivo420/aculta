# Plano de implementação - Locais no ACULTA Events e integração com Content Index

Data: 2026-10-29. Estado: **PLANEJADO, sem implementação ou homologação nesta revisão**.
Pedido do responsável: elaborar o plano, publicá-lo na PR aberta e alinhar roadmaps/documentação, incluindo `aculta_index`.
Repositório: `coletivo420/aculta`; branch `refactor/opencultures-codigo-visual`; PR #299.
Base revalidada: `3250e4b4f940a9a33008206885bdb6e33125f7a2`.
Não criar branch/PR, executar merge, force-push, deploy, rebuild do Index ou escrita de Runtime por efeito deste documento.

## 1. Resultado esperado e escopo

A pessoa poderá cadastrar um local independentemente, pesquisar e selecionar um existente durante a criação/edição de um evento/data, ou criar um local no mesmo fluxo sem perder os dados do evento. O local terá identidade reutilizável, página pública quando autorizado, agenda associada, presença no diretório, no mapa, na navegação e na descoberta cultural via Content Index.

Preservar `aculta_event_location` e a relação **Evento > Data > Local**. Um evento pode ter datas em locais distintos; não mover `field_local` para Evento nem duplicar o cadastro em `node:activity`, novo content type, tabela Studio ou Search API.

Referências de comportamento: [Creating Events](https://dev.theeventscalendar.com/knowledgebase/creating-an-event/) e [Venue and Organizer Pages](https://dev.theeventscalendar.com/knowledgebase/venue-and-organizer-pages/). Seleção/reutilização/criação no editor são referência do TEC; páginas individuais com agenda e múltiplos venues por evento são recursos Pro. Diretório completo é requisito ACULTA, sem presumir paridade gratuita. Não portar código, post types, JS ou arquitetura WordPress.

Fora deste incremento: ingressos/pagamentos, novos papéis, novo shell, migração automática de activity, criação de um provider por ocorrência, novo motor de recorrência, dependências novas, mudança de provedor Maps e escrita externa OSM.

## 2. Baseline: código presente versus experiência pendente

Caminhos relativos a `web/modules/custom/aculta_portal/modules/`, salvo indicação.

| Evidência no HEAD-base | Interpretação e trabalho necessário |
| --- | --- |
| `aculta_events/src/Entity/EventLocation.php`: storage/access/views_data, sem handlers de formulário ou links próprios | Criar superfícies nativas de CRUD e página canônica reutilizando a entidade |
| `aculta_events/src/Entity/EventDate.php::baseFieldDefinitions()`: `field_local` referencia Local | A associação reutilizável existe; é a Data que escolhe o local |
| `EventDateCreateForm::buildForm()/submitForm()`: evento, título, início e fim | Conectar seleção/criação e persistência do local; autocomplete atual seleciona evento |
| `LocationListController::page()` e `LocationCatalogue::busca()` | Diretório textual sem link individual, busca textual ou paginação; acesso por item já existe e não deve ser reimplementado como se ausente |
| `views.view.eventos_locais.yml` em config/install | Lista administrativa por papel administrator, título sem link e menu desativado; instalação ativa não comprovada |
| `EventPageController::page()` | Nome do local em texto e JSON-LD; falta ligação à página individual e revisão explícita de acesso ao local |
| `EventContentIndexProvider` v2 | Relações de tipo/organizador e relação `event_location` derivada de Datas; implementados canônico purpose/Domain, allowlist estrita, DI sem \Drupal estático, validação de status publicado e deduplicação |
| `LocationContentIndexProvider` v2 | Provider real com allowlist mínima (sem endereço exato, coordenadas ou contatos privados), DI sem \Drupal estático, validação de status publicado e propósito 'eventos' |
| `ContentIndexRelationQuery` | Consulta de relações outgoing autorizadas e isolamento por conta/purpose; corrigida junção para alvos de relação sem idioma específico (und) |
| `config/sync/core.extension.yml` | Não lista Events/Calendar/Maps/OSM; confrontar com Runtime, sem inferir desativação ativa nem importar sync global |
| `node:activity` e form display em config/sync | Modelo editorial paralelo com nome/endereço em texto; inventariar consumidores e conteúdo antes de escolher migração |
| `composer.lock` e core.extension | Inline Entity Form 3.0.0 disponível no projeto; validar integração no tipo customizado |
| Registro MarleyTab do Studio | Sem aba de Eventos/Locais no inventário inspecionado; compor somente operações reais do owner |

Auditoria anterior: `b74d81c1d32ec2ab529b3cced5dd554e936ea869`; revalidação dos pontos centrais no HEAD-base acima. Testes e RC históricos de outros candidatos continuam evidências de seus escopos, não de CRUD/inline/agenda/indexação de Locais. Nenhum Runtime foi acessado nesta sessão.

## 3. Ownership e coordenação

| Dono | Entrega | Fronteira |
| --- | --- | --- |
| aculta_events | Entidade, formulários, operações, políticas de Local/Data, diretório, canônico e adapters do Index | Não recriar calendário, mapa, conta/perfil ou índice |
| aculta_index | Projeções derivadas, relações, consulta paginada, fila, fingerprint, gerações e manutenção | Entity Access permanece no owner; sem conteúdo mestre duplicado |
| aculta_calendar | Expansão/agenda/ICS pelos contratos atuais | Não importar classes de Events no núcleo independente |
| aculta_maps | Renderer, geocodificação e links de rota | Consumidor envia somente dados já autorizados/minimizados |
| aculta_portal + CEP | Purpose, URLs e formulário/adaptador postal central | BR fixo; mesmo contrato de número/S/N/complemento; sem endpoint CEP paralelo |
| aculta_user | Organização responsável, vínculos e reivindicação existentes | Conta, Perfil público e Organização são conceitos separados |
| aculta_studio | Área editorial Eventos, seções e ações autorizadas | Abre formulários do owner; sem CRUD ou permissões paralelos |
| aculta420 / frente visual | SDCs elegíveis, tokens, mobile-first e acessibilidade | Handoff Pede-B antes de alterar tema ou Views reservadas |

Esta revisão documental registra dependências, não reserva arquivos de implementação nem prova coordenação com agentes locais. Antes de editar código, conferir HEAD, arquivos alterados pelas outras frentes e ownership com o responsável/registro vigente. Não enviar mensagens nem iniciar novas frentes automaticamente.

## 4. Microfases, dependências e gates

Todos os itens abaixo permanecem abertos.

| Fase | Dependência | Entrega concreta | Gate de saída |
| --- | --- | --- | --- |
| LOC-00 / P0 | Nenhuma | Inventário ativo de extensões, schema, Views/displays, rotas e formulário realmente usado; diff active/sync; responsáveis por arquivos | Evidência por SHA/host/URI/banco, sem alteração persistente; mapa de consumidores activity versus Events |
| LOC-01 / P0 | LOC-00 | Contrato de acesso/publicação/precisão e organização; fechar cache e todas as saídas | Anônimo/contas A e B/editor vinculado/admin; HTML/JSON-LD/ICS/índice/mapa/autocomplete sem dados negados |
| LOC-02 / P1 | LOC-01 | Formulário nativo de Local, rotas add/edit/delete/canonical e gestão; dados próprios mínimos e migração idempotente | CRUD válido, validação Entity API, CSRF, autorização e referências compartilhadas preservadas |
| LOC-03 / P1 | LOC-02 | Seletor de Local + criação inline no editor de Data/evento e no nó Activity; modal AJAX nativo Drupal (`EventLocationModalWidget` e `EventLocationModalForm`) | Concluído e testado no Kernel (`EventLocationModalAndAdminKernelTest`): seleção/criação em 2 etapas, BR fixo (ADR-034), campos soltos removidos de `EventForm` e `node:activity`, sincronização legada em `ActivityHooks` |
| LOC-04 / P0-P1 | LOC-01 + canônico de LOC-02 | Contrato e implementação Events > Index descritos na seção 6: providers v2, URLs canônicas via DomainPurposeManager, relação event_location derivada de Datas físicas publicadas, enfileiramento por hooks e correção de joins | Concluído e testado no Kernel (EventContentIndexProviderKernelTest: 6 testes, 141 asserções PASS); sem rebuild ou escrita no Runtime |
| LOC-05 / P1 | LOC-02 + LOC-04; LOC-01 para saída pública | Detalhe do Local com agenda; diretório paginado e busca; área administrativa própria em `/painel-administrativo/eventos` e links | Concluído e testado: canônico `/locais/{id}`, diretório `/locais`, painel `/painel-administrativo/eventos` (`EventAdminOverviewController`) e menu sob `system.admin` |
| LOC-06 / P1 | LOC-02/03/04 + operações disponíveis | Aba Eventos no Marley com seções Eventos, Datas e Locais; listas por escopo e ações do owner | Owner desativado oculta área; acesso real das rotas; retorno ao Studio e fronteira Conta/Perfil |
| LOC-07 / P1 | LOC-00 a LOC-06 | Reconciliação config/migração e piloto; tratamento explícito de activity legado | Snapshot/hash antes de escrita autorizada, migração ensaiada/idempotente, contagens antes/depois e rollback |
| LOC-08 / gate | Todas anteriores | Homologação integrada, revisão e atualização de release | Sem PASS com DEFERRED; evidências do HEAD consolidado, RC somente após fechar escopo obrigatório |

LOC-04 começa pelo contrato após LOC-01, sem aguardar aparência final. A entrega pública completa depende da integração Index, não apenas da existência de provider. Executar em microcommits por contrato/owner e testar cada alteração proporcionalmente.

## 5. Formulários, dados e acesso

- Usar Form API/Field API e `ContentEntityForm` da entidade existente. Configurar form/view displays e operações; o formulário independente e o inline compartilham validação e persistência.
- Avaliar primeiro IEF Complex 3.0.0 instalado. Prova técnica: tipo customizado, render dos campos base, escolha de existente, criação, erros AJAX e save do pai. Alternativa somente por impedimento comprovado: autocomplete Core + diálogo AJAX do mesmo formulário de entidade. Não selecionar biblioteca JS nova.
- Não usar autocreate apenas por nome quando endereço, escopo e validação forem necessários. Recomendar locais semelhantes sem revelar registros negados; nomes iguais não autorizam fusão automática.
- Local escolhido pertence à Data. A interface pode apresentar “Local” junto das datas do evento sem criar segundo campo mestre. Sem criar manualmente novo evento dentro de um editor paralelo.
- Preservar nome, tipo, endereço, precisão, coordenadas, horário, identificador OSM e responsável existentes. Mapear descrição/telefone/site/contatos contra contratos existentes antes de adicionar campos; não publicar contato privado por inferência.
- Aplicar `AccountAddressForm`/adaptador central e `aculta_portal_cep/cep-address`, BR fixo, número/S/N/complemento conforme contrato vigente. Busca por logradouro continua planejamento separado.
- Não disparar geocodificação por tecla. Reutilizar Maps no salvamento, limites/cache/atribuição existentes; falha externa deve preservar os dados e oferecer recuperação.
- Reutilizar a política de organização, sem papel novo. Autorização de leitura, seleção, criação, atualização, publicação e exclusão devem ser diferenciadas; não usar nome de papel como única política da nova UI.
- Remover referência não exclui Local. Exclusão de Local referenciado deve ser bloqueada ou usar substituição explícita validada; nunca cascata silenciosa sobre eventos/datas.
- Separar visibilidade do registro de precisão do endereço. Contrato proposto: novo Local em rascunho até publicação autorizada; Local público pode ter endereço aproximado; Local privado não entra na descoberta pública. Confirmar semântica com owner em LOC-01, sem alterar retroativamente todos os locais.
- Migração de dados existentes preserva estado atual até classificação/inventário autorizado. Não presumir que todo imóvel privado requer registro privado nem que “aproximado” é uma ACL.
- Propagar `AccessResult` e cacheability das entidades e candidatos negados: contexts/tags/max-age, organização e alterações de vínculo. Checar local ao renderizar evento, schema.org, mapa, links e exportações.

## 6. Integração obrigatória com aculta_index

### 6.1 Fonte da verdade e projeção

Manter os adapters `EventContentIndexProvider` e `LocationContentIndexProvider` no Events. Index é opcional e derivado, sem dependência circular, novo índice próprio, tabela de pesquisa no Studio ou duplicação de locais em Search API. O consumidor pode usar a busca transversal existente; Search API permanece adapter quando presente, sem segunda fonte mestre.

| Contrato | Entrega planejada |
| --- | --- |
| Identidade | Uma projeção por entidade/idioma, provider/bundle/version/fingerprint e domínio estável existentes |
| URL | URL canônica de Local e Evento via contratos de purpose/Deployer; nunca hostname fixo, rota inexistente ou URL de edição |
| Indexabilidade pública | Somente registros publicamente descobríveis conforme LOC-01; rascunho/privado/LMS restrito não podem revelar sequer título por filtros/totais/relações |
| Campos | Allowlist mínima: título, tipo, identidade e demais metadados já suportados; cidade/UF apenas com necessidade real e contrato neutro aprovado |
| Dados excluídos | Rua/CEP/coordenada exata privada, telefone/e-mail privado, corpo integral, dados cadastrais e credenciais |
| ACL | Index revalida origem/alvos via Entity Access no purpose canônico e restaura Domain; publicado no índice não é grant de acesso |
| Ownership | `owner_uid` somente por resolução explícita existente; não confundir conta responsável com Perfil ou organização |

Qualquer mudança de projeção, relações ou indexabilidade exige versionamento do provider e fingerprint conforme a infraestrutura existente. Não alterar versão do módulo apenas por este plano.

### 6.2 Relações e consultas

- Preservar `location_type`, `event_type` e `organizer` já existentes e seus contratos de acesso.
- Relação proposta `event_location`: Evento > Local, conjunto distinto derivado das Datas reais do evento. O owner define a política de quais datas contribuem; datas privadas nunca geram aresta pública. Não duplicar um Local por Data.
- Para agenda temporal, consultar datas/ocorrências no owner e expandir via Calendar. Relação de descoberta não afirma que um evento ainda é futuro e não substitui RRULE/overrides.
- Validar consultas de relação recebida e paginação pela API atual do Index. Se faltar capacidade genérica, estender contrato neutro no Index com teste; nunca ler tabelas internas no controller.
- Não introduzir provider público de Data ou de cada ocorrência sem consumidor e política específicos. A derivação Evento > Local resolve a navegação inicial, preservando Data como fonte da associação.
- Criar/alterar/excluir/revincular Data enfileira projeção dos eventos antigo e novo afetados; criar/editar/excluir Local atualiza sua projeção e invalida consumidores relacionados. Reutilizar fila/deduplicação atuais e testar alterações concorrentes.
- Diretório/busca cultural consomem API paginada com filtros allowlisted e conta/purpose; nenhum `loadMultiple(all)` seguido de fatia para simular paginação.
- Autocomplete transacional deve revalidar a entidade no envio, mesmo com candidato vindo do Index. Latência de indexação não pode impedir selecionar um local recém-criado: usar contrato de seleção do owner/Entity API, sem catálogo persistente paralelo.
- Se Index estiver indisponível, CRUD/seleção continuam por Entity API. Descoberta transversal informa indisponibilidade ou usa catálogo do owner com ACL equivalente, comportamento documentado; não retorna dados sem acesso como fallback.
- Cursors/cache não podem ser reutilizados entre contas, filtros, gerações ou fingerprints. Totais e páginas devem refletir o contrato de filtragem autorizado, sem revelar candidatos negados.

### 6.3 Manutenção e gate IDX-LOC

Reutilizar worker, backfill/rebuild por geração e auditoria de integridade. Mudança de visibilidade ou revogação de vínculo nega leitura imediatamente, mesmo antes da fila atualizar a projeção. Remoção de Local elimina projeção e arestas conforme integridade; não apagar entidade fonte pelo Index.

O status do Index registra auditoria Runtime anterior `running`/stale/valid=false; LOC-00 deve verificar seu estado atual. Não iniciar rebuild, avançar auditoria existente, trocar geração ou “corrigir” registro de outro operador por conta deste plano. Operação persistente exige escopo autorizado, baseline, snapshot e rollback.

Gate obrigatório com serviços/providers reais do owner: create/update/delete, backfill duas vezes, fingerprint/ativação, renomear Local, mudar tipo/URL/precisão/visibilidade, revogar vínculo, Data migrando entre eventos/locais, remover última referência, alvos negados, módulo opcional desativado, busca/paginação/cursor A/B e restauração de Domain.

## 7. Página pública, mapa e Studio

- Implementar canônico de Local em `eventos`; proposta `/locais/{id}` com requisito numérico. Registrar path e route ID definitivos no sitemap de rotas antes do código, evitando novo campo slug se ID bastar. Nenhuma rota é criada por este plano.
- Diretório atual `/locais`: busca, tipo e paginação; nome clicável, estado vazio, link “Cadastrar local” só quando permitido. Agenda do Local mostra futuras/em andamento e oferece histórico somente conforme contrato de acesso.
- Reutilizar Maps para coordenadas já minimizadas; testar 0/0.0/ranges, exato/aproximado e links de rota sem reconstruir o endereço oculto. OSM é opcional e não requisito para cadastro manual.
- Ligar evento, diretório, marcador, descoberta Index e Local pelo mesmo canônico; sitemap/JSON-LD só de dados publicáveis, sem URLs duplicadas entre purposes.
- Studio usa plugin/registry vigente com disponibilidade do owner e acesso real da operação. A seção Locais lista escopo próprio/organização, distinguindo administração autorizada de conteúdo de terceiros.
- Operações do owner permanecem no seu purpose; Studio avalia `runInPurpose()` e gera links pelo `DomainPurposeManager`. O hub de ACCOUNT e `/conta-legada` não mudam.
- Tema apresenta dados neutros com SDCs existentes elegíveis; encaminhar Pede-B para mudanças compartilhadas, respeitar OCS-F11/THEME-MF3 sem afirmar conclusão desses gates.

## 8. Legado, configuração, implantação e rollback

1. Inventariar `node:activity`, campos textuais de local, referências editoriais e catálogo ativo. Entregar decisão por consumidor: permanecer, adaptar ou migrar. Não excluir bundle/campos/conteúdo por dedução.
2. Definir updates idempotentes para campos/handlers/config necessários, sem UUIDs upstream. Não presumir que adicionar YAML em config/install atualiza sites existentes.
3. Comparar active/sync incluindo extensões Events/Calendar/Maps, Views/displays e integração Index. Reconciliar somente diff aprovado; não importar toda a configuração divergente nem desinstalar módulos.
4. Antes de escrita Runtime autorizada: confirmar host/checkout/URI/banco/contagem de tabelas, snapshot com SHA-256 e plano de restauração. Kernel usa `aculta_simpletest`, nunca Runtime.
5. Piloto com dois eventos compartilhando Local e um evento com datas em locais distintos. Registrar contagens/IDs e classificação sem dados pessoais.
6. Rollback de código/config preserva entidades de origem e referências. Para Index, reutilizar geração anterior compatível e/ou reprocessar fila conforme contrato; não assumir compatibilidade entre fingerprints. Sem DROP/uninstall.
7. Registrar SHA, comandos, resultados, rollback e pendências em `ACULTA-CONTINUOUS-EXECUTION.md`. Esta sessão não sincroniza o checkout servido nem executa Drush.

## 9. Critérios de aceite e evidência

| Camada | Cenários obrigatórios | Evidência |
| --- | --- | --- |
| Entidade/Forms | CRUD, validação, IEF/erros, duplicação acidental, referências compartilhadas, permissão de criar versus editar | Unit/Kernel e Functional AJAX no banco dedicado |
| Acesso/cache | Anônimo/A/B/admin/editor vinculado, revogação, outro purpose, registro privado e precisão aproximada | HTML/JSON/JSON-LD/autocomplete/ICS/mapa/Index e caches cruzados |
| Index | Todos os cenários IDX-LOC da seção 6.3, consultas via API, filas, gerações e módulo opcional | Kernel integrado real e auditoria de integridade; harness não prova Runtime |
| Agenda | Futuro/em andamento/passado, online/híbrido, recorrência, cancelamentos e reagendamentos | Suítes do owner e Calendar sem dependência invertida |
| Experiência | Cadastro inline/independente, retorno Studio, links reais, vazios e falhas externas | Firefox ESR visível/X11, 320/390/768/1280, teclado, zoom 200%, claro/escuro e no-JS; ACULTA420 e Olivero |
| Volume | Diretório, agenda local e busca cultural com muitos locais/datas/negações | Medir consultas, memória e p50/p95; definir SLO e dataset antes de declarar aprovação |
| Implantação | Migração repetível, diff ativo/sync, snapshot/rollback, canônicos e sitemap | SHA/ambiente/resultados reais; nenhum teste escrito apresentado como executado |

Executar gates pertinentes: diff --check, lint/PHPCS/PHPUnit dos códigos alterados, fronteira módulo-tema, Portal Drupal 11, rotas sem aliases temporários, schemas/design do tema quando afetados e índice de status. Scripts browser reutilizáveis em `tests/browser/`, artefatos fora do repositório.

## 10. Estado de release e documentação

A evidência RC anterior do Events permanece histórica e limitada ao candidato registrado. O escopo ampliado de Locais está **Dev/planejado** até LOC-08; manifesto `0.1.0-rc.1` permanece inalterado nesta publicação documental e não comprova o novo fluxo. Calendar mantém seu RC anterior para o motor existente; a integração por Local é incremento pendente. Maps, Studio e Index mantêm versões e evidências atuais.

Atualizar roadmap do owner a cada microfase e status por componente com estado comprovado. README raiz deve ser regenerado a partir de `docs/operations/status/`, nunca editado manualmente na tabela. Nenhuma promoção, nova versão ou release é autorizada pela publicação deste plano.


## Aditivo de experiência editorial e endereços (2026-10-30)

- A substituição dos campos de endereço de `node:add/activity` por referência a Local é futura e depende de inventário/mapeamento dos valores legados.
- Planejar seletor Drupal/AJAX: pesquisa de Locais existentes por nome semelhante, seleção explícita, ação de cadastrar Local e retorno ao formulário pai mantendo seus valores. A autorização deve ser verificada no servidor em cada operação.
- O cadastro abre modal Drupal/AJAX com CEP como primeira etapa, reaproveitando o adaptador central e `aculta_portal_cep/cep-address`. Brasil fixo, sem seletor. Busca por logradouro e resultados próximos ficam restritos a Events/Maps e aguardam provedor, contrato, termos, privacidade e limites aprovados.
- O módulo CEP conserva seu comportamento por CEP; Conta/checkout não ganham busca por rua. Geocodificação de Maps não é autocomplete postal.
- Incluir no roadmap Events uma área administrativa própria em `/painel-administrativo`, com padrão equivalente às áreas de LMS e Fórum, incluindo permissão, navegação, listagem e ações.
- Manter `aculta_index` como contrato transversal do Local, sem indexar endereço exato/coordenadas privadas. O modal não pode contornar access, status editorial, ACL ou cache.

Consulte [ADR-033](../decisions/ADR-033-endereco-busca-e-locais.md). Este aditivo documenta trabalho futuro; não altera estado de release nem afirma implementação.


## Especificação do popup e fronteira Events ↔ Maps (2026-10-30)

“Modal” neste plano é um popup Drupal sobre a tela de Activity, não conteúdo inline nem página inteira. O fluxo terá popup de pesquisa/seleção de Local e, sob ação explícita, popup de cadastro. O retorno da seleção atualiza a referência no formulário pai por AJAX, mantendo os valores preenchidos.

A entidade `aculta_event_location` e seu ciclo de vida são de `aculta_events`. Activity/evento referencia Local. `aculta_maps` consome os Locais autorizados por serviços/contratos estáveis para renderizar mapas, geocodificar endereço salvo e criar links. Não duplicar entidade, CRUD, endereço ou catálogo em Maps. Permissões, status, precisão e cache são verificados na fronteira de cada módulo. Ver [ADR-033](../decisions/ADR-033-endereco-busca-e-locais.md).

## Fase 1 — Fechamento do contrato e inventário de endereços e Locais (2026-10-30)

### 1. Contrato arquitetural fechado
1. **Titularidade única do endereço:** o endereço completo pertence exclusivamente ao cadastro da entidade `aculta_event_location`. Events e Activity não mantêm campos próprios de endereço, mantendo unicamente a referência de entidade (`field_local` em `aculta_event_date` e `field_event_location` em `node:activity`) ao Local selecionado.
2. **Eliminação dos campos avulsos no editor:** no cadastro e edição de Events e Activity, CEP, logradouro, número, complemento, bairro, cidade, UF, país e nome do local não devem continuar como campos editáveis independentes no formulário do evento/atividade.
3. **Fronteira Events ↔ Maps:** `aculta_events` é o único proprietário da entidade Local, seu cadastro, permissões, ciclo editorial e relações. `aculta_maps` é consumidor puro via serviços/contratos neutros (`MapRenderer`, `RouteLinkBuilder`, `GeocodingService`) e consome apenas dados autorizados/minimizados. Maps nunca cria catálogo, formulário, tabela ou entidade paralela de Local.
4. **Popup Drupal / AJAX:** a seleção e o cadastro de Local ocorrem em popups sobrepostos pelo sistema de diálogos do Drupal (`Drupal.dialog` / AJAX), preservando o contexto e os dados do formulário pai. É proibido o uso de formulário inline, expansão na mesma página ou tela cheia.
5. **Ordem dos campos com CEP primeiro:** em todo formulário de endereço completo (incluindo o cadastro do Local), o CEP é obrigatoriamente o primeiro campo visível (`#weight = -50`), com país fixo em Brasil (`BR`), sem seletor de país. O autopreenchimento é fornecido por `aculta_portal_cep`. Busca por logradouro permanece como capacidade futura restrita a Events/Maps.
6. **Persistência explícita:** o Local só é persistido após confirmação deliberada do usuário no modal. A referência selecionada e os valores do formulário pai sobrevivem a rebuilds AJAX e erros de validação.
7. **Privacidade e Index:** `aculta_index` indexa somente títulos, URLs canônicas e relações públicas (`event_location`). Endereço exato, CEP, coordenadas privadas ou Locais não publicados nunca são projetados no índice transversal.

### 2. Tabela de mapeamento de campos legados (Activity → EventLocation)

| Campo legado (`node:activity`) | Tipo / Armazenamento legado | Campo de destino (`aculta_event_location`) | Tipo de destino | Status do mapeamento | Regra de migração / Preservação |
|---|---|---|---|---|---|
| `field_place_name` | `string` (255) | `title` | `string` (255) | Confirmado | Mapeia para o nome do Local. Se Local correspondente já existir pelo mesmo nome e endereço, reutiliza; senão, cria novo Local. |
| `field_event_street` | `string` (255) | `field_endereco.address_line1` | `address` | Confirmado | Rua e número são normalizados pelo `StreetNumberField::split()` / `join()`. |
| `field_event_postal` | `string` (255) | `field_endereco.postal_code` | `address` | Confirmado | Normalizado para 8 dígitos sem máscara antes de gravar. |
| `field_event_city` | `string` (255) | `field_endereco.locality` | `address` | Confirmado | Mapeia para a cidade da subdivisão BR do Drupal Address. |
| `field_event_region` | `string` (255) | `field_endereco.administrative_area` | `address` | Confirmado | Mapeia para a sigla UF de 2 letras (ex.: SP, RJ). |
| `field_event_country` | `string` (255) | `field_endereco.country_code` | `address` | Confirmado | Fixo em `BR`. Valores vazios ou divergentes são normalizados para `BR`. |
| *(sem campo legado de bairro)* | — | `field_endereco.dependent_locality` | `address` | Sem correspondência | Preenchido via consulta ao CEP durante a migração assistida quando disponível. |
| *(sem campo legado de precisão)* | — | `field_precisao` | `list_string` | Confirmado | Assume default `'exato'` para registros migrados. |
| *(sem campo legado de coordenadas)* | — | `field_coordenadas` | `geofield` | Confirmado | Geocodificado no salvamento via `GeocodingService` quando houver endereço válido. |
| *(sem campo legado de status)* | — | `field_status` | `list_string` | Confirmado | Mapeia como `'publicado'` se a atividade estiver publicada (`status = 1`); senão `'rascunho'`. |
| `field_event_location` | `entity_reference` | `id` (chave primária) | ID numérico | Confirmado | Campo da Activity que armazena a referência direta ao Local criado ou reutilizado. |

### 3. Estratégia de migração segura e reversível

1. **Fase 1 (Atual - Não executiva):** inventário fechado e mapeamento formalizado. Nenhuma tabela alterada, nenhum campo removido e nenhuma migração executada nesta fase.
2. **Fase 2 (Preparação):**
   - Criação de comando Drush e update hook idempotente dedicado (`aculta_events_update_migrate_activity_locations`).
   - Verificação e registro prévio de contagens e tabela de hash de snapshot no banco Runtime.
3. **Fase 3 (Ensaio em lote / Dry-run):**
   - Execução com flag `--dry-run` para identificar potenciais duplicidades (ex.: duas atividades com mesmo nome de local e mesmo CEP).
   - Registros sem CEP ou com dados incompletos são agrupados em log auditável para revisão antes de persistência.
4. **Fase 4 (Execução reversível):**
   - Criação das entidades `aculta_event_location` correspondentes.
   - Atribuição do ID do Local a `node.field_event_location`.
   - Preservação temporária dos campos textuais antigos (`nodePresave` mantém os valores preenchidos para não quebrar integrações legadas).
5. **Fase 5 (Rollback garantido):**
   - Script de rollback capaz de desfazer o vínculo de `field_event_location` e remover exclusivamente as entidades de Local geradas pelo lote de migração (rastreadas por metadado de proveniência), restaurando o estado exato anterior.

### 4. Critérios de aceite verificáveis

1. **Seleção de Local:**
   - O formulário de Activity exibe o widget modal de seleção de Local (`aculta_event_location_modal`).
   - A pesquisa por nome retorna apenas Locais que a conta atual tem permissão de leitura (`access('view')`).
   - A seleção de um Local atualiza a referência na Activity via AJAX sem recarregar a página e sem perder nenhum dado já digitado nos demais campos da Activity.
2. **Popup de cadastro por CEP:**
   - O popup de cadastro abre em diálogo Drupal sobreposto e tem o CEP como **primeiro campo** (`#weight = -50`).
   - País é fixo em Brasil (`BR`), sem opção ou seletor de país.
   - Digitar um CEP válido de 8 dígitos preenche automaticamente rua, bairro, cidade e estado.
   - Todos os campos permanecem editáveis para ajustes manuais ou complemento em CEPs gerais.
   - A confirmação explícita no botão de salvar cria a entidade `aculta_event_location` e devolve seu ID ao formulário pai.
3. **Acessibilidade e Usabilidade:**
   - O popup retém o foco de navegação por teclado, suporta fechamento por `Escape` e botão de fechar, e retorna o foco ao elemento acionador do formulário pai.
   - Diálogo possui atributos ARIA adequados (`role="dialog"`, `aria-modal="true"`, `aria-labelledby`).
   - Anúncios de status de CEP utilizam `role="status"` ou `aria-live="polite"`.
4. **Permissões e Segurança:**
   - Usuário sem permissão de criar Local não visualiza a opção de cadastrar no modal.
   - Submissão valida autorização no servidor (rejeitando IDs forjados ou não autorizados).
5. **Integração Events ↔ Maps:**
   - Locais com coordenadas válidas aparecem no mapa `/mapa` respeitando a precisão (`exato` vs `aproximado`).
   - Coordenadas aproximadas são arredondadas para 2 casas decimais no `MapRenderer`, sem vazar rua ou CEP de locais privados.
6. **Integração com `aculta_index`:**
   - Locais publicados geram projeção com título e URL canônica.
   - Endereço físico exato, CEP e contatos privados permanecem estritamente fora do índice.

### 5. Revisão da Fase 1 e Fechamento Normativo (2026-10-30)

- **Separação estrita da ordem do formulário vs exibição:** O CEP é rigorosamente o primeiro campo visível nos formulários de entrada e edição (`#weight = -50`). Na exibição pública (cartões, páginas de detalhes, mapas, listagens e blocos), preserva-se integralmente a ordem postal brasileira convencional (logradouro/número, complemento, bairro, cidade/UF e CEP por último). Pesos do formulário não vazam para templates de exibição.
- **Correção do formulário institucional:** Em `block_content.aculta_institution`, o campo `field_postal_code` (peso 40.5) antecede todos os campos de endereço visíveis (`field_street_address`: 41, `field_locality`: 42, `field_address_region`: 43, `field_country`: 45, `field_address`: 46). Nos displays de exibição, campos individuais permanecem em `hidden: true` e apenas `field_address` é renderizado como texto consolidado.
- **Estado exato dos campos legados de Activity:** Os campos legados (`field_place_name`, `field_event_street`, `field_event_postal`, `field_event_region`, `field_event_city`, `field_event_country`) estão ocultos na interface do formulário em favor de `field_event_location`, mas continuam persistidos e intactos no banco de dados. Eles **não** foram removidos nem migrados; a preservação é mantida para migração segura e reversível na Fase 2.
- **Contrato Events ↔ Maps confirmado:** `aculta_events` é o proprietário único da entidade `aculta_event_location`, de seu catálogo e de seu ciclo de vida. `aculta_maps` é consumidor técnico neutro (`MapRenderer`, `RouteLinkBuilder`) que não duplica armazenamento nem entidade. Busca por logradouro continua uma capacidade futura restrita a Events/Maps, sem afetar Conta nem checkout.
- **Editabilidade e autopreenchimento validados:** Validado no navegador X11 Firefox ESR (`test-account-address-browser.py`, 33/33 verificações OK) que após a consulta de CEP específico e CEP geral (`06550-000`), todos os campos permanecem editáveis, a troca de CEP atualiza os dados preservando número digitado e complemento, e a submissão grava sem regressões.


## Decisão de fluxo: Eventos centraliza a gestão editorial (2026-10-30)

A interface administrativa deve tratar Evento como unidade editorial. A existência de aculta_event_date como entidade separada no modelo não significa que a Data seja um conteúdo ou tarefa administrativa independente.

- [x] Na criação de Evento, permitir cadastrar e ordenar uma ou mais Datas e escolher um Local para cada uma, no mesmo fluxo do Evento. (`EventForm::buildDateCreationSection()`, com “Adicionar outra data” por AJAX; a ordem de exibição segue o início de cada Data. Não há reordenação manual nesta entrega.)
- [x] Na edição do Evento, listar e administrar suas Datas, horários e Locais dentro do contexto desse Evento. (`EventForm::buildEventDatesSection()`.)
- [x] Remover do painel menus, botões e ações globais que apresentem “Adicionar data de evento” como operação independente. (Link de menu e ação da visão geral removidos.)
- [x] Se o formulário/rota técnica de Data for mantido, incorporá-lo ao fluxo do Evento (incluindo popup Drupal/AJAX quando adequado), exigir event_id válido e autorização no servidor, e negar acesso direto sem contexto autorizado. (`event_date_add/edit/delete` com `{aculta_event}`, `aculta_event.update` e `EventDateRouteAccess`; a rota global `date_add` foi removida.)
- [x] Remover links de “Adicionar data” que levem a uma tela independente; a partir de qualquer página administrativa, a pessoa deve entrar pelo Evento correspondente. (Link da página pública passou a abrir a edição do Evento.)
- [x] Integrar o seletor/cadastro de Local ao contexto da Data do Evento e preservar todos os valores não salvos do formulário pai. (`wrapper_id` por Data no `EventLocationModalWidget`; `EventLocationModalForm` revalida acesso.)
- [x] Configurar no painel de Eventos os papéis autorizados a criar Eventos e Locais, com content_editor como padrão inicial e checagens servidoras em todas as rotas/submissões. (`EventPermissionPolicy` + `EventSettingsForm` + `aculta_events.settings`.)

O item E4/F5 que registra a existência técnica de um formulário de nova Data descreve capacidade de código, não a experiência administrativa desejada. Ele não deve ser considerado concluído como UX enquanto a Data puder ser criada ou gerida como tarefa autônoma. Este aditivo prevalece sobre referências anteriores a criação inline/avulsa de Data e sobre o fluxo legado de Activity.

**Evidência (2026-10-30):** `EventDateManagementKernelTest` 6/88, `EventFormDatesKernelTest` 3/48, Kernel de Events 76/1216, Calendar 127/431 e Unit de Events 8/19 PASS. Nenhuma migração de dados, mudança de esquema ou escrita no Runtime foi executada.


## Fronteira de domínio para Locais e Eventos (2026-10-30)

- [x] Operações editoriais de Eventos, Datas e Locais restritas ao purpose `eventos`, com negação no servidor em outros domínios. (`_aculta_domain_purpose: eventos` + `_aculta_domain_purpose_authoritative: true`; `DomainPurposeRequestSubscriber` nega inclusão por URL/POST.)
- [x] Locais seguem o Evento proprietário: seleção/cadastro via modal revalidam `access('view')`/criação e vivem no contexto do Evento.
- [x] Exibição em outros domínios é somente leitura (bloco `aculta_events_upcoming`), sem ações de cadastrar Local, editar, excluir ou publicar.
- [x] Sem duplicação de entidade/catálogo por domínio e sem alias/redirect temporário.
- [x] Eventos de turma do LMS e Locais privados permanecem fora da exibição pública, busca e indexação cruzada.

Evidência: `EventDomainBoundaryKernelTest` e testes do `ContentPurposeResolver`/`DomainRoutePolicy` PASS. A colocação do bloco por domínio é configuração de site e não foi executada (sem escrita no Runtime).
