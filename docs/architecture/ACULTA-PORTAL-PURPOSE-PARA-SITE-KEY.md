# ACULTA - Renomeação funcional de Purpose para Site Key no Portal

**Estado:** PLANEJAMENTO APROVADO; IMPLANTAÇÃO NÃO EXECUTADA.  
**Data:** 2026-11-10. **Branch:** `dev`. **Escopo:** ADR-054 + ADR-055, subfases RN-00 a RN-05.  
**Plano visual complementar:** `ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md` e `ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`.  
**Fonte da análise:** leitura do código de `aculta_portal`, tema `aculta420_v2` e documentos versionados em `origin/dev`; não há teste Runtime desta refatoração.

## 1. Decisão e razão semântica

O responsável aprovou **renomear os identificadores internos funcionais de 'purpose'**, além de extinguir o *motor visual de purpose* em favor do UI Skins.

**Nome canônico proposto: `site_key`.** Uma site key é a chave lógica estável de um dos dez sites/frentes reconhecidos pelo Portal, **não** um hostname, URL, skin ou identificador do Drupal Domain.

- `site_key`: identidade funcional/roteamento de site: `main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `forum`, `links`, `eventos`. **Manter valores inalterados**, mesmo quando a apresentação pública usa outro nome.
- `domain_id`: ID da entidade Drupal Domain, como `aculta_org` ou `forum_aculta_org`. **Não alterar** IDs, registros, aliases, paths públicos, relações Domain nem `field_domain_source`.
- `skin_id`: identidade visual, resolvida por **UI Skins**; pode haver skins diferentes por domain_id, modo claro/escuro e configurações legítimas. Uma skin nunca define controle de acesso, proprietário de conteúdo ou rota.
- `site_key` não é propriedade da UI Skins. O Portal resolve regras funcionais e passa o `domain_id` à ponte de skins; o tema só recebe dados neutros para apresentação e tokens UI Skins.
- Não chamar tudo de `surface`: `DomainSurfaceMap` e `aculta_portal.superficies` atualmente descrevem entradas funcionais, enquanto os SDCs `surface` descrevem superfícies visuais. Padronizar em **site** no contrato funcional, sem reaproveitar o conceito para aparência.

### Arquitetura final

`Drupal Domain (domain_id) -> Portal SiteKeyRegistry / DomainSiteManager (site_key, rotas, ACL, URLs, ownership) -> Portal SkinResolver (domain_id -> skin_id, modo efetivo) -> UI Skins (valores visuais, themes/schemes e CSS vars) -> UI Patterns, UI Styles, UI Icons e SDCs`.

**Limite vinculante:** UI Skins substitui todos os mecanismos **visuais** anteriormente chamados purpose. A renomeação `purpose -> site_key` substitui os **contratos funcionais** remanescentes. São duas migrações coordenadas e **nenhum dos dois motores novos substitui a função do outro**.

## 2. Inventário inicial e correspondência dos nomes

| Atual no código origin/dev | Contrato-alvo planejado | Risco / consumidor que exige migração |
| --- | --- | --- |
| `DomainPurposeManager` | `DomainSiteManager` | Service ID, type hints, URL/alias/deployer, rota, cache, e-mails, outros módulos |
| `getCurrentPurpose()`, `getPurposeForDomainId()` | `getCurrentSiteKey()`, `getSiteKeyForDomainId()` | Todos os callers, guards, resolução de conteúdo |
| `runInPurpose()` | `runInSite()` | Troca temporária de Domain em cálculos de acesso; garantir restauração em `finally` |
| `aculta_portal.domain_purpose` | `aculta_portal.domain_site` | Container, injeção/autowire, testes e services YAML |
| `ContentPurposeResolver` | `ContentSiteResolver` | Prioridade de rota administrativa e ownership do conteúdo |
| `DomainPurposeRequestSubscriber` | `DomainSiteRequestSubscriber` | Políticas 403/404, canonicalização e login |
| `DomainRoutePolicy::allowsSharedPurpose()` | `DomainRoutePolicy::allowsSharedSite()` | Rotas compartilhadas, inclusive mutações, não ampliar compartilhamento |
| `DomainRoutePolicy::purposeForDonationRoute()` | `siteKeyForDonationRoute()` | Checkout, apoio e MAIN continuam com owners existentes |
| `PurposeFrontPathProcessor` | `SiteFrontPathProcessor` | Core path processor e cache de rotas/frentes |
| `ActivePurposesState` / `active_purposes` | `ActiveSiteKeysState` / `active_site_keys` | Drupal State persistido; reconstrução segura e rollback |
| `DomainSurfaceMap` (`DOMAIN_IDS`, `FRONT_PATHS` etc.) | `DomainSiteMap` ou `SiteKeyRegistry`, sem alterar os valores | Consumidores do mapa central, inclusive `aculta_paths` e Studio |
| `DomainPresentationBuilder::PURPOSE_IDENTITIES` | `SITE_IDENTITIES` | Rótulos, navegação e geração de URL absoluta |
| `domain_presentation.identity.purpose` | `domain_presentation.identity.site_key` | Twig/SDC, cache por domínio, páginas privadas |
| `domain_sites[*].purpose` / `regions.showcase[*].purpose` | `site_key` em ambos | Barra multidomínio, vitrine e links de sites |
| `aculta_purpose` no render array | `aculta_site_key` | Apresentação funcional; não usar para CSS |
| `_aculta_domain_purpose` | `_aculta_site_key` | YAML de rotas; políticas de MAIN/Wiki/Forum, acesso incorreto pode causar vazamento |
| `_aculta_domain_purposes` | `_aculta_site_keys` | Exceções cross-domain e verbos não-GET; conflito deve falhar fechado |
| `_aculta_cross_domain_canonical_purpose` | `_aculta_cross_domain_canonical_site_key` | Canonicalização e redirecionamentos, preservar 404/301/302 |
| `login_destination['purpose']` em sessão | `login_destination['site_key']` | Retorno ao login, abas e sessões antigas |
| `SurfaceConfigReader` - variável `$purpose` e `aculta_portal.superficies` | Internamente `$siteKey`, planejar schema de `site_registry` se necessário | Configuração persistida; não renomear config sem migração explícita |
| `Purposes::ALLOWED_PURPOSES` do tema | Eliminar lista visual duplicada; validar `site_key` no owner Portal | A lista é funcional, não um catálogo de paletas do tema |
| `data-purpose` / `data-purpose-scheme` e `--aculta-purpose-*` | `data-site` somente se necessário semanticamente; `data-skin` e CSS vars UI Skins para aparência | Evitar trocar atributo por CSS que recrie o acoplamento |

**A tabela é ponto de partida verificado por leitura focal. Não é inventário completo:** RN-00 deve descobrir todas as ocorrências em módulos/submódulos, arquivos `*.routing.yml`, `*.services.yml`, config/sync, Drush, testes, renderizadores, páginas privadas, state, sessões e código JavaScript. Valores de `site_key` acima são fatos do mapa atual; nomes-alvo são propostas normativas para implementação.

## 3. Critérios de compatibilidade e não-regressão

- **Não renomear as dez chaves em si**, entidades de Domain, `field_domain_source`, os aliases públicos, rotas públicas (nome/path), view modes, configurações de terceiros, IDs de conteúdo ou identificadores de pagamento. Renomear somente os contratos internos necessários, preservando estabilidade para URLs, dados e integrações.
- Serviços internos antigos podem ser aliases de compatibilidade **temporários e rastreados**, sem criar **aliases públicos de rota** (proibidos pelo gate `validate-no-temporary-route-aliases.php`). Não manter compatibilidade indefinida nem dar novo comportamento aos aliases.
- **Decisão atualizada pelo responsável em 2026-11-10:** o projeto não possui configurações de rota persistidas que exijam compatibilidade. Fazer corte direto para `_aculta_site_key`, `_aculta_site_keys` e `_aculta_cross_domain_canonical_site_key`; não ler, espelhar ou reter opções antigas. Se uma opção removida ou um valor novo inválido aparecer em uma rota, responder 404 (falha fechada). Nenhuma rota sem metadados válidos deve cair automaticamente em MAIN ou tornar-se compartilhada.
- Transição do `active_purposes` do State: gerar/validar `active_site_keys`, manter fallback de leitura apenas no prazo de migração, registrar integridade 10/10 e garantir reconstrução/restauração; não apagar estado antes do rollback.
- Sessões que já possuem `login_destination['purpose']` continuam legíveis apenas durante janela de transição; escrever somente `site_key` novo após corte e verificar domínio/path/método antes de redirecionar. Não reintroduzir open redirect.
- `domain_presentation` e barra multidomínio: fase dual-read curta com contrato tipado, tags e contexts; remover campos duplicados após migração de consumidores e integração visual UI Skins. Não duplicar H1, <main>, links ou barras.
- Em config `aculta_portal.superficies` as chaves `main/account/...` continuam iguais, mesmo que o nome da configuração seja antigo. Se houver troca de nome de config, fornecer migração idempotente, ensaio de import/export, backup e downgrade. **Não fazer migração destrutiva de config ou state só para satisfazer a renomeação.**
- A troca de símbolos PHP não altera FQCN de plugins de contrib nem arquivos no `web/core`; inspecionar dependências, container, route rebuild, implementações de interfaces, indexadores, sitemap e event subscribers.
- Não esconder código antigo pela troca de nome: preservar a classificação `LEGACY_DEPRECATED`/`LEGACY_OBSOLETE` do visual, e registrar APIs antigas como `API_DEPRECATED`/`API_REMOVED` em inventário de refatoração próprio; não misturar depreciação de API com status SDC.

## 4. Plano de execução coordenado com a migração UI Skins

### RN-00 - Inventário e congelamento de contratos

1. Na `dev` atual, mapear todas as referências textuais e semânticas a `purpose`, além de buscar combinações de rota `_aculta_*purpose*`, services, controllers, access, State, sessões e consumers de `domain_presentation`; executar buscas delimitadas em `web/modules/custom`, `web/themes/custom`, `config/sync`, `scripts` e testes.
2. Registrar caminhos, classe/método/contrato, owner, risco, tipo de persistência, consumidores, testes existentes, rollback; separar nomes de código, chaves persistidas, registros de Domain e descrições históricas.
3. Fotografar as rotas e respostas dos 10 domínios, como 403/404, cross-domain canonical, redirects, URLs absolutas com Alias e Deployer; registrar estado das configs/ActiveState e login.
4. Publicar matriz de antes/depois, testes representativos e bloqueios; garantir que a UI Skins não começará de um mapeamento ambíguo.

**Gate RN-00:** inventário real, zero consumidores desconhecidos nas integrações críticas, status PASS/DEFERRED registrado.

### RN-01 - API funcional nova (sem alterar comportamento)

1. Introduzir registro tipado de `site_key` no Portal, com mapa para Domain IDs, paths frontais e configurações existentes. Reutilizar dados/métodos atuais, sem inventar hosts.
2. Introduzir `DomainSiteManager` com interface e métodos de nome claro, cobrindo URL canônica, aliases, resolução atual, Domain temporário em contexto e restauro.
3. Manter ponte de compatibilidade nas classes/serviços internos até migrar consumidores, preservando `DomainPurposeManager` enquanto necessário; evitar duplicação de dois resolvedores com lógicas diferentes.
4. Mapear testes Unit/Kernel existentes para a nova API; verificar o mesmo resultado para cada site.

**Gate RN-01:** respostas e Domain IDs bit a bit equivalentes, chamadas antigas ainda corretas durante transição, zero mutação de config.

### RN-02 - Roteamento, segurança, ownership e destinos

1. Renomear o Content resolver, Subscriber, RoutePolicy, PathProcessor, parâmetros de métodos, opções de rota e serviços; migrar os `*.routing.yml` **por lotes** (MAIN, conteúdo, Fórum, Wiki, Eventos, Studio, loja, compras, apoio e rotas transversais).
2. Remover options de rota `purpose` diretamente em todos os módulos customizados e usar somente os nomes `site_key`; validar fail-closed para qualquer option removida/valor inválido, a prioridade de `_admin_route` e ausência de compartilhamento por default.
3. Migrar `login_destination` na sessão; testar login/logout, destino válido, open redirects, método POST bloqueado fora do host e regeneração de URLs.
4. Revisar sitemap, Domain Alias, Domain Config, busca, Index, Messenger e callbacks que resolvem destino.

**Gate RN-02:** matriz HTTP de hosts/rotas e controles de acesso idêntica ao baseline; 403/404/canonical e pagamentos preservados.

### RN-03 - Contratos e persistência (migração reversível)

1. Atualizar `DomainPresentationBuilder`, `domain_presentation.identity.site_key`, entradas de `domain_sites` e `regions.showcase`, estruturas de blocos e variáveis `aculta_site_key`.
2. Atualizar `ActiveSiteKeysState`, mantendo leitura temporária e segura de `aculta_portal.active_purposes`; preservar `aculta_portal.superficies` e IDs canônicos até haver atualização de configuração aprovada e testada.
3. Transferir validação de chaves canônicas do tema `Purposes` para o contrato funcional do Portal; tema não resolve site_key pelo hostname. Integração com a ponte UI Skins recebe `domain_id`, `skin_id` e modo.
4. Gerar configuração de schema, testes Kernel e contratos para Twig/SDC/JS, sem qualquer acesso a dados que não estava autorizado.

**Gate RN-03:** reprodutibilidade da configuração, 10 chaves preservadas, sem duplicação de navegação, cache correto.

### RN-04 - Remover nomes legados após migração comprovada

1. Migrar todos os consumidores e testes para o novo namespace; adicionar gate estático que rejeita **novos** símbolos `purpose` fora de arquivos históricos, docs e exceções versionadas.
2. Quando não houver consumidores, marcar APIs legadas como removíveis e eliminar aliases internos; não manter duas APIs públicas permanentes.
3. Remover atributos antigos como `data-purpose` só após mapear todos os usos de CSS, JS, analytics e testes. Não remover `domain_id` nem chaves `main/account/...`.
4. Coordenar com US-02: `UI Skins` agora é única fonte de aparência, `site_key` apenas funcional, `skin_id` apenas visual. Nada de CSS `[data-site]` determinando cores.

**Gate RN-04:** grep de produção sem contratos antigos ativos, zero alteração de comportamento e rollback testado.

### RN-05 - Homologação e encerramento

1. Smoke e testes Unit/Kernel/Functional/Browser, inclusive URL alias, cross-domain response cache quente/frio, login e session, opções de rota ambíguas, Domain ativo temporário, acesso do admin MAIN, Form API, pagamento e páginas privadas.
2. Validar o contrato do frontend com `site_key` (não cor) e a aparência com UI Skins (não rota); rodar visual 10 sites × light/dark em 320/390/768/1024/1440, zoom 200% e teclado.
3. Demonstrar import/export e rollback de config/State em staging isolado e autorizado; não alterar Runtime destrutivamente sem ordem explícita.
4. Publicar inventário final, changelogs, gates, snapshots, hashes e status; integrar ao aceite da US-14. Se qualquer gate faltar, manter DEFERRED, não concluir ADR-054/055.

**Gate RN-05:** nenhum contrato `purpose` ativo em código de produção fora de exceções justificadas, nenhum domínio/URL/ACL alterado, nenhuma duplicação visual ou vazamento de cache; aceite funcional e visual registrado.

## 5. Dependências com US-00 a US-14 (ADR-055)

- `US-00` e `RN-00` compartilham baseline, mas possuem inventários separados (componentes visuais × identificadores/contratos funcionais).
- `RN-01` precede a introdução definitiva de `site_key` na ponte `domain_id -> skin_id` de `US-02`. O piloto Links pode usar a ponte provisória só enquanto RN-01 não completar, sem criar nova dívida permanente.
- `US-02` migra tokens, paletas e atributos para UI Skins; `RN-03` migra contratos de site e Twig. Coordenar as duas branches de código na mesma `dev` por commits atômicos, evitando alterações concorrentes nos mesmos arquivos.
- `RN-02` e `RN-03` podem ser realizados em lotes por owner, preservando comportamento entre microfases. Os submódulos consumidores não precisam de alteração global e instantânea.
- `US-12/US-13` classificam e substituem visuais legados; `RN-04` trata nomes funcionais e descontinuação das APIs, sem usar o status SDC para arquivos PHP.
- **US-14 só encerra ADR-054 e ADR-055 quando RN-05 também estiver PASS**, além dos gates UI Suite/UI Skins, Field Group dev, cache, segurança, documentação e aceite visual humano. UI Patterns CKEditor 5 permanece fora do escopo.

## 6. Proibições, segurança e governança

- Não refatorar via busca/substituição cega. Não editar Core/contrib, não atualizar `Domain IDs`, `field_domain_source` ou slugs públicos; não transformar nomes `main, account, ...` em skins.
- Não alterar comportamento de pagamento, endpoints de notificação, fluxos de autenticação, navegação entre sites, índices e feeds sem teste funcional próprio.
- Não desinstalar, importar config, executar updatedb, limpar estados/dados ou alterar rotas em Runtime sem autorização aplicável. `drush cr` após sync seguro de código conforme AGENTS.md.
- Sem novas PRs/branches; commits atômicos na dev, preservar sessões/agentes paralelos, não sobrescrever alterações não commitadas.
- A marcação de API antiga como `@deprecated` é transitória e rastreada; **rota pública temporária de alias é proibida**. A migração de metadados de rota não cria nova URL.
- Toda exceção tem ID, owner, motivo, plano de remoção, gate e evidência; não declarar concluído a partir de lint ou documentação.
