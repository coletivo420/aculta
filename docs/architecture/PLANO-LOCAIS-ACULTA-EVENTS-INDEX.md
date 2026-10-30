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
| LOC-03 / P1 | LOC-02 | Seletor de Local + criação inline no editor de Data/evento; integração IEF existente | Selecionar/criar/reabrir/cancelar sem perder dados; dois eventos reutilizam mesmo ID; no-JS funcional |
| LOC-04 / P0-P1 | LOC-01 + canônico de LOC-02 | Contrato e implementação Events > Index descritos na seção 6: providers v2, URLs canônicas via DomainPurposeManager, relação event_location derivada de Datas físicas publicadas, enfileiramento por hooks e correção de joins | Concluído e testado no Kernel (EventContentIndexProviderKernelTest: 6 testes, 141 asserções PASS); sem rebuild ou escrita no Runtime |
| LOC-05 / P1 | LOC-02 + LOC-04; LOC-01 para saída pública | Detalhe do Local com agenda; diretório paginado e busca; links evento/local/mapa e navegação | Acesso, filtros, resultados e paginação consistentes; mapa alternativo textual; canonical por purpose |
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
