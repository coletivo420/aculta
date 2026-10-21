# Auditoria de portabilidade — Drupal Linky

## Referência reproduzível

- Projeto: [Drupal Linky](https://www.drupal.org/project/linky).
- Tag estudada: `2.0.0-beta1`, commit
  `2d70712a854065428faf0228aef96066bd6812f2`.
- Branch `2.x` observada em 2026-10-20:
  `5bcb1e317bb401492aa99629b37c09aada2bcc75`.
- A release informa Drupal >=10.1 e a página do projeto indica que não há
  release estável suportada. Compatibilidade no Drupal 11 do ACULTA não foi
  executada.
- Licenciamento Drupal.org: GPL-2.0-or-later; confirmar cabeçalhos/arquivos da
  tag antes de reutilizar código.
- Documentação API consultada: [EntityAccessControlHandler 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityAccessControlHandler.php/class/EntityAccessControlHandler/11.x)
  e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).

## Arquitetura e avaliação

Na tag estudada, `src/Entity/Linky.php` declara entity `linky` com tabelas
`linky`/`linky_revision`, revisão padrão e metadados de revisão. Os campos base
são proprietário Drupal, campo `link` limitado a destinos externos, created,
changed e checked. `Linky::toUrl('canonical')` redireciona a URL canônica da
entidade ao destino; a rota interna de administração é transportada em option
própria do `Drupal\linky\Url`. `LinkyAccessControlHandler` exige permissão
genérica para view e oferece edit/delete próprios pelo proprietário com cache
por usuário/permissões. Isso requer revisão: não copiar sem adaptar o contrato de
visualização pública e access/cache.

O `.info.yml` depende de Dynamic Entity Reference e Core Link/User. O projeto
descreve Linkit e widget DER/IEF como integrações. Isso informa destino
reutilizável, histórico, referências e formulários, mas não resolve propriedade
de micropágina, composição nem analytics.

**Decisão:** PORTAR conceitos e avaliar arquivos selecionados; não adicionar
`drupal/linky` como dependência. Usar Entity API, Revision API e Field API
instaladas no Drupal. Validar dependências opcionais (Linkit/DER/IEF) e APIs
Drupal 11 na fase de implementação.

| Área upstream | Avaliação ACULTA |
| --- | --- |
| Content Entity de link e proprietário | PORTAR após comparar necessidade com Core; restringir tipo de URI conforme decisão. |
| Revisões e metadados | PORTAR o desenho se a política editorial precisar; validar UI/API Drupal 11. |
| Referência e widget DER/IEF | PORTAR ou adaptar; verificar módulos opcionais e licença. |
| Integração Linkit/filtro de texto | ADIAR; não necessária para a landing inicial. |
| Listagem/formulário administrativo | PORTAR comportamento em Form API/Views Drupal. |
| Resolução via `toUrl('canonical')` | DESCARTAR como implementado: canônico aponta ao destino e requer URL subclass própria; avaliar Redirect API/rota segura Drupal. |
| Access do Linky | PORTAR conceitos, reimplementar grants para página/link conforme ownership e visibilidade pública. |

## Auditoria por arquivo da tag `2.0.0-beta1`

Todos os caminhos abaixo são do commit fixado na seção de referência. O
`composer.json` declara GPL-2.0+; a árvore oficial Drupal.org/DrupalCode.org
rege como projeto Drupal. Não copiar um arquivo sem conferir cabeçalho e
dependências diretas. O módulo declara Drupal `>=10.1`, mas não foi instalado
no Runtime ACULTA/Drupal 11.

| Arquivo upstream | Responsabilidade observada | Decisão ACULTA |
| --- | --- | --- |
| `linky.info.yml`, `composer.json` | Metadata, versão e dependências Core Link/User + Dynamic Entity Reference | CONSULTAR; não adicionar como dependência; DER não foi aprovado nem é necessário para a referência Node atual. |
| `src/Entity/Linky.php`, `src/LinkyInterface.php` | Content Entity link, owner, campo Link, metadados e revisões | PORTAR conceitos para `aculta_link`; Linky é destino reutilizável, não página nem composição. Nossas entidades já nascem revisionáveis. |
| `src/LinkyAccessControlHandler.php` | View/edit/delete por permissões e owner, com cache | DESCARTAR como implementação: view depende de permissão genérica; ACULTA publica somente destino referenciado a partir de composição publicada e acessível. Preservar cacheability/invalidação como requisito. |
| `src/Form/LinkyForm.php` | Salvar entidades e tornar revisões padrão | PORTAR o uso de `ContentEntityForm`/Revision API; não copiar callbacks ou defaults até validar os próprios formulários. |
| `src/Form/LinkySettingsForm.php`, `config/install/linky.settings.yml`, `config/schema/linky.schema.yml` | Configuração de link e sua schema | DESCARTAR configuração de serviço duplicada; comparar cada opção antes de criar config ACULTA. |
| `src/LinkyListBuilder.php`, `src/Entity/LinkyViewsData.php`, `config/optional/views.view.managed_links.yml` | Lista administrativa e integração Views | PORTAR comportamento via Views/Admin route existente, com owner/access fixos e UI Drupal; não importar View beta. |
| `src/LinkyHtmlRouteProvider.php`, `src/Controller/LinkyController.php`, `linky.routing.yml` | Rotas admin e collection | DESCARTAR URL `/admin/content/linky`; gerar URLs da entidade e encaixar menu/permissões próprios do ACULTA. |
| `src/Url.php` | Subclasse Core URL para fazer canonical da entidade navegar ao destino | DESCARTAR; mistura identidade da entidade com redirecionamento. ACULTA conserva entidade e usa resolver/rota segura no purpose correto. |
| `src/LinkyEntityViewBuilder.php`, `templates/linky.html.twig` | Render da entidade e links contextuais | DESCARTAR render público da entidade; micropágina e SDC consomem dados preparados pelo módulo. |
| `src/Plugin/Field/FieldWidget/LinkyWidget.php`, `src/Element/LinkyEntityAutocomplete.php`, `js/linky-widget.js` | Widget e autocomplete DER com criação/seleção de Linky | ADIAR; formulário Core `entity_reference_autocomplete` já atende Nodes. Não adicionar JS/DER até validar necessidade para tipos adicionais. |
| `src/Plugin/EntityReferenceSelection/LinkySelection.php` | Seleção de entidades Linky com consulta/access | PORTAR somente critérios necessários para os formulários de referência; Query deve declarar accessCheck e ownership. |
| `src/Plugin/Field/FieldFormatter/LinkyFormatter.php` | Formatar entidades/link como destino direto | DESCARTAR redirect automático por field formatter; avaliar uso editorial de Fields Core sem sobrescrever comportamento existente. |
| `src/Plugin/Linkit/Matcher/LinkyMatcher.php` | Buscar Linky em Linkit | ADIAR; integração de editor de conteúdo não é necessária aos formulários de micropágina atuais. |
| `src/Plugin/Filter/DirectLinkFilter.php` | Transformar marcação textual em destino Linky | DESCARTAR; filtros de texto podem ampliar resolução semântica e escopo de redirect. |
| `src/Plugin/Validation/Constraint/LinkyLinkConstraint.php`, `src/Plugin/Validation/Constraint/LinkyLinkConstraintValidator.php` | Validar URLs permitidas | PORTAR os casos de validação que faltarem após comparar com `LinkDestinationConstraint`; conservar validação atual até testes de equivalência. |
| `src/Plugin/DsField/LinkyTitle.php` | Campo para Display Suite | DESCARTAR; Display Suite não é dependência ACULTA para esse dashboard. |
| `linky.permissions.yml`, `linky.links.menu.yml`, `linky.links.task.yml`, `linky.links.action.yml` | Permissões e links de admin/menu | PORTAR apenas as capacidades equivalentes para permissões e navegação ACULTA, sem os IDs/paths do contrib. |
| `linky.libraries.yml`, `phpcs.xml` | Biblioteca JavaScript e padrão PHPCS | Não copiar; código ACULTA usa biblioteca própria apenas se widget futuro precisar e as regras Drupal locais. |
| `linky.install`, `linky.module`, `linky.page.inc` | Lifecycle e hooks procedurais da release | DESCARTAR callbacks antigos; avaliar lifecycle e migrar hooks ao padrão OOP `#[Hook]` do Core instalado se houver requisito equivalente. |
| `linky.post_update.php` | Adiciona revisão retroativamente, cria campos/tabelas e copia dados por SQL | DESCARTAR; não importar a estratégia de migração. Se necessário, usar update API/EntityDefinitionUpdateManager para dados ACULTA e rollback documentado. |
| `tests/src/Kernel/LinkyKernelTest.php`, `LinkyKernelTestBase.php`, `LinkyLinkConstraintTest.php`, `LinkyDirectLinkFilterTest.php`, `LinkyMenuIntegrationKernelTest.php`, `LinkyUrlTest.php` | Contratos de entidade, segurança, filtro, URL e menu | CONSULTAR casos; não copiar testes de APIs ou caminhos que não existem em ACULTA. Criar testes próprios do contrato Links. |
| `tests/src/Functional/LinkyFunctionalTest.php`, `tests/src/FunctionalJavascript/LinkyFunctionalTest.php` | CRUD e interação JS do widget | CONSULTAR cobertura; reescrever testes de usuário conforme campos, permissions, Domains e fallback de formulário ACULTA. |
| `.gitlab-ci.yml` | CI do projeto contrib | DESCARTAR; CI/gates ACULTA são próprios. |

### Decisão da auditoria

O inventário cobre cada arquivo versionado pela tag, agrupando somente os
artefatos de teste da mesma camada. A entidade Linky é referência útil para
revisões e ownership, mas a versão beta exige adaptações em acesso, publicação,
resolver e integração multidomínio. Nenhum arquivo/código foi copiado e não há
dependência contrib adicionada. A validação final deve continuar referenciando
o código Core Drupal 11.4.10 instalado, pois o requisito `>=10.1` não comprova
compatibilidade atual nem compatibilidade futura.
