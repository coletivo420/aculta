# Padrão Drupal 11+ e Drupal 12 ready — aculta_portal

Status: **normativo**. Revisado em 2026-11-05.

Vale para desenvolvedores humanos e para agentes de IA. Declarar compatibilidade com Drupal 12 exige validação do Core 12 instalado e dos módulos contrib, não apenas o `info.yml`.

## 1. Linha de versão

- Portal: `core_version_requirement: ^11 || ^12` (`aculta_portal.info.yml`; o gate confere). Tema V2: `^11.5 || ^12`.
- Core de desenvolvimento: `drupal/core` 11.5.0-beta1 (`composer.json`). O gate confere que o `composer.lock` mantém o Core na linha suportada; o número travado é evidência do ambiente, não parte deste padrão.
- PHP: o `composer.json` exige `>=8.3`. O plano de migração para o Drupal 12 pede PHP 8.5, ainda não instalado no ambiente de teste (DT-P04). Código novo roda em 8.3; a validação em 8.5 é pendente.
- Drupal 12 está em beta. Datas de release divergem entre fontes públicas. Não informe data de lançamento como fato; confirme em drupal.org antes de usar.

## 2. Drupal 12 ready

"12 ready" é meta, não estado. Só se declara depois de:

1. Gate do Portal (`php scripts/validate-aculta-portal-drupal11.php`) sem falha, com o Core 12 instalado no ambiente de teste.
2. Upgrade Status e Drupal Rector executados em worktree isolado, com resultado registrado.
3. Análise de deprecações do código do Portal e do tema V2, sem chamadas a APIs que os change records marcam como removidas.
4. Módulos contrib e patches em `patches-aculta.json` compatíveis com a versão 12 testada.
5. Checklist de compatibilidade do tema V2 (ADR-045, seção 4) concluído.

Pontos que o Core 12 afeta, conforme fontes públicas (confirmar em drupal.org antes de agir):

- **Módulos e temas que saem do Core:** notícia de terceiros indica a remoção de Search, Claro e Olivero do Core. Não crie dependência nova em nenhum deles. O tema administrativo atual é Claro; a troca é decisão do responsável (plano de migração, seção 3).
- **Olivero:** já vedado para gates e testes do projeto (`AGENTS.md`, "Arquitetura e validação").
- **Dívida Drupal 11 a eliminar antes do Core 12:** chamadas procedurais `drupal_*` e `drupal_static` em scripts e testes. Código de produção novo não usa essas funções.
- **Senhas:** segundo o plano de migração, o hash padrão do Core 12 passa a ser Argon2id. Hashes antigos continuam válidos e são recalculados no login. Teste o login após a troca.
- **Htmx:** o Core 12 usa htmx 4, com API e atributos alterados. Ver seção 5.

## 3. Arquitetura e fonte de verdade

Fluxo obrigatório:

```text
Core/contrib
    ↓
aculta_portal
    ↓
contrato neutro
    ↓
ACULTA420
```

- Core e contrib são a fonte de verdade para entidades, autenticação, Commerce, LMS/Group e demais capacidades que já fornecem.
- `aculta_portal` integra, orquestra e adapta. Não cria storage paralelo quando a capacidade já existe.
- ACULTA420 apresenta dados preparados. Não recebe `DomainInterface`, entity storage, services do Portal, decisão de hostname, access ou regra de negócio.
- `DomainPurposeManager` é a autoridade para purpose e URLs multidomínio.
- Administração Drupal, carrinho, checkout e pagamento ficam no purpose MAIN, conforme `PAYMENT-DOMAIN-POLICY.md` e `ADMIN-DOMAIN-POLICY.md`.
- Requisições mutáveis entre domínios não são redirecionadas nem reenviadas.

## 4. Hooks

- Código runtime novo ou refatorado usa hooks OOP: classes em `src/Hook/` com `#[Hook('hook_name')]`. O Core aceita esse padrão desde 11.1.
- `#[FormAlter]` não é API válida. Forms usam `#[Hook('form_alter')]`, `#[Hook('form_BASE_FORM_ID_alter')]` ou `#[Hook('form_FORM_ID_alter')]`, confirmando no Core instalado a assinatura do hook.
- Não adicione hook runtime a `aculta_portal.module`. Esse arquivo não deve voltar para abrigar hooks migráveis. Um `.module` novo só se justifica para API procedural exigida pelo Core ou por contrib, com justificativa documentada.
- Práticas runtime contrárias a este padrão (hook procedural quando o OOP é suportado, service locator estático, callback global novo) são deprecadas no projeto, mesmo quando o Core ainda as aceita. Exceções exigem API upstream ou necessidade comprovada.
- Preserve ordering e module weight. Não altere ordem apenas por modernização.
- Dependências entram por DI com autowiring compatível. Não crie definição YAML redundante para classes `#[Hook]` que o Core já registra.
- Continuam procedurais quando o Core exige: `hook_install()`, `hook_schema()`, `hook_update_N()`, `hook_post_update_NAME()`, uninstall e lifecycle equivalente. Não crie wrapper OOP artificial para lifecycle.
- Requisitos: `hook_requirements()` está deprecado desde 11.3. Use `InstallRequirementsInterface` no install e `hook_runtime_requirements()` / `hook_update_requirements()` no runtime e no update. Não crie novo `aculta_portal_requirements()`.
- Contratos OOP protegidos pelo gate, uma vez cada: `form_alter`, `entity_access` e `entity_presave`.

Referência: https://www.drupal.org/node/3442349 e https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Hook%21Attribute%21Hook.php/class/Hook/11.x

## 5. HTMX

HTMX é o padrão para interação assíncrona nova no Portal, quando há ganho real sobre a página completa. A base é o Core: biblioteca `core/drupal.htmx` e a classe `Drupal\Core\Htmx\Htmx`, presentes desde 11.3. A integração foi confirmada no Core 11.5 do ambiente de teste (`docs/htmx-confirmacao.md`).

Regras:

1. **Não crie uso novo de Drupal AJAX.** `#ajax`, `core/drupal.ajax`, `use-ajax` e jQuery são legado. Até o momento, nenhum change record encontrado anuncia a remoção do `#ajax` do Core; ele continua funcional. Usos existentes, inclusive os de contrib (Commerce, Views e modais), não entram na migração sem decisão própria (ADR-050, item 2).
2. **Use o builder `Htmx`** aplicado a render arrays ou respostas. Não escreva `hx-*` nem `HX-*` à mão em código novo, salvo quando o builder não cobrir o caso e a exceção estiver justificada no código.
3. **Isole a classe `Htmx` em um ponto do módulo**, um serviço ou função de apresentação. O change record de 11.3 descreve a classe como interna por política; o código não tem anotação `@internal`. Mudanças de compatibilidade podem chegar sem aviso, e o ponto único limita o impacto.
4. **Não dependa de nomes de evento htmx nem de cabeçalhos `HX-Trigger`.** No htmx 4 (Core 12), `HX-Trigger` passa a ser `HX-Source`, `HX-Trigger-After-Swap` e `HX-Trigger-After-Settle` saem, e os nomes de evento mudam.
5. **Respostas de fragmento usam `onlyMainContent()`.** Sem JavaScript, a mesma rota responde a página completa. Esse é o caminho de progressão: a funcionalidade não depende do fragmento.
6. **Cacheability de fragmento.** Resposta de fragmento difere da página completa. Declare o contexto correto (cabeçalho de requisição htmx) ou torne a resposta não cacheável. Telas privadas de conta e Studio usam `Cache-Control: private` e `Vary: Cookie`.
7. **Proxies e filtros de cabeçalho** devem permitir os cabeçalhos `HX-*` de requisição (`HX-Request`, `HX-Target` e outros).
8. **Formulários** são tratados pela Form API, inclusive `form_build_id` em requisição htmx. Submissão com token adulterado deve ser rejeitada e não executar o submit handler. A fixture de F0 (`HtmxF0DialogTest`) é o modelo para esse teste.
9. **Testes:** o teste do Core cobre o Core, não a rota do projeto. Rota htmx nova exige teste funcional próprio, em navegador ou FunctionalJavascript, além de checagem sem JavaScript.

### Diferenças do htmx 4 (Core 12)

Fonte: change record "htmx now uses version 4", publicado em 2026-09-03, presente em 12.0.0-beta1.

- `Htmx::disable` passa a ser `Htmx::ignore`; `Htmx::disabledElt` passa a ser `Htmx::disable`.
- Removidos: `hx-params`, `hx-prompt`, `hx-ext`, `hx-disinherit`, `hx-inherit`, `hx-request`, `hx-history`, `HX-Prompt` e `getHtmxPrompt`.
- Adicionados: `hx-query`, `hx-action`, `hx-method`, `hx-status`, `HX-Request-Type`, `HX-Source` (com `getHtmxSource()`).
- Herança passa a ser explícita, com as flags `Htmx::NO_MODIFIER`, `Htmx::INHERITED` e `Htmx::APPEND`.
- `hx-on` e `Htmx::on` mudam de sintaxe.
- `FormBuilderInterface::HTMX_REQUEST` está deprecado e sai no Drupal 13.

Consequência: código novo do Portal deve usar apenas o builder, isolado, sem atributos removidos. A migração de código existente segue ADR-050 e a fase de Drupal 12 do plano de migração.

## 6. Form API

Desde Drupal 11.3, callbacks de Form API podem ser resolvidos pelo `CallableResolver` e viver em serviços com DI.

```php
$form['#validate'][] = 'aculta_activity.form_callbacks:validateActivity';
```

- Não crie callbacks globais `aculta_portal_*`.
- Evite `[$this, 'callback']` quando o formulário puder ser serializado.
- Agrupe callbacks em um serviço coeso.
- Antes de migrar callback legado, valide assinatura e fluxo no Core ou contrib instalado.
- Prefira `form_FORM_ID_alter` ou `form_BASE_FORM_ID_alter` quando o alvo for específico e a mudança não alterar ordering nem semântica. Não fragmente um `form_alter` funcional por estética sem provar paridade.
- Callbacks migrados aparecem **exatamente uma vez** no pipeline. Duplicar `#submit`, `#validate` ou `#after_build` repete efeitos colaterais.
- O gate verifica a existência do método, o registro único `service.id:method` e as dependências explícitas do serviço.

Referência: https://www.drupal.org/node/3548821

## 7. Injeção de dependência

Em `web/modules/custom/aculta_portal/src/`:

- Não use `\Drupal::service()`, `\Drupal::entityTypeManager()`, `\Drupal::database()`, `\Drupal::request()`, `\Drupal::routeMatch()`, `\Drupal::currentUser()`, `\Drupal::config()`, `\Drupal::messenger()` nem `\Drupal::entityQuery()`.
- Services, controllers, forms, plugins, subscribers e hooks recebem dependências explicitamente.
- `ControllerBase` e `FormBase` podem permanecer quando úteis, mas não justificam helpers lazy que escondam dependências.
- Não habilite autowiring global só para reduzir YAML. Mantenha definitions previsíveis quando o contrato do service importa.
- Replacement ou decorator de service exige justificativa arquitetural e teste.

Referência: https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal

## 8. Event subscribers

- Implemente `EventSubscriberInterface` e registre com a tag `event_subscriber`.
- Use `isMainRequest()` quando o comportamento vale só para a request principal.
- Prioridade fica em `getSubscribedEvents()` quando a ordem importa.
- Não mude prioridade sem análise de `RouterListener`, `RedirectResponseSubscriber` e demais listeners relevantes.
- DI é explícita.

Referência: https://api.drupal.org/api/drupal/core%21core.api.php/group/events/11.x

## 9. Entity API, access e lifecycle

- Prefira storage injetado: `$entityTypeManager->getStorage('node')->getQuery()`.
- Toda EntityQuery de conteúdo declara `->accessCheck(TRUE)` ou `->accessCheck(FALSE)`. A escolha reflete a finalidade da query. `FALSE` não é atalho de performance.
- Entity access, route access, permissões, ownership e purpose de Domain resolvem antes da apresentação, com cacheability correta.
- Em `hook_entity_access()`, retorne `AccessResultInterface` com metadata proporcional às condições usadas. Resultado que depende de Domain varia por `domain`. Resultado que depende de rota, usuário, request ou session declara os contexts correspondentes. Quando depende de token de uso único ou estado efêmero, use `max-age: 0`. Não adicione `cachePerPermissions()` quando a decisão não depende de permissões.
- Formulários que consultam gateways ou config entities injetam `EntityTypeManagerInterface`. Block plugins usam `BlockManagerInterface` injetado.
- Evite static entity loads em código custom quando houver storage injetado. Não consulte tabelas internas de contrib quando houver API pública.
- Guards de persistência usam hooks OOP. `hook_entity_presave()` recebe `EntityInterface` e roda imediatamente antes do save. Validações de segurança são fail-closed: antes do save, lançam exceção explícita quando o estado solicitado não é seguro.
- Segredos não vão para Configuration Sync nem são persistidos apenas para facilitar formulário administrativo.

Referência: https://www.drupal.org/node/3201242

## 10. Views

- `views_embed_view()` e `Views::getView()` existentes são dívida a revisar, não motivo para refactor cego.
- Quando apropriado, prefira o storage da entidade `view` e `ViewExecutableFactory`/`views.executable`.
- Antes de substituir um wrapper funcional, prove equivalência de argumentos, display, cache, access, attachments e exposed inputs.

## 11. Render API e cacheability

- Render arrays permanecem render arrays. Não pré-renderize HTML para atravessar a fronteira Portal → tema.
- Cache contexts, tags e max-age acompanham a decisão que exige variação.
- Prefira `CacheableMetadata`, `BubbleableMetadata` e contratos cacheáveis.
- `max-age: 0` é válido quando o conteúdo é realmente não cacheável. Não use no lugar de contexts e tags corretos.
- Access e cacheability fazem parte do mesmo contrato funcional.
- Token API propaga `BubbleableMetadata` para configuração, entidade relacionada, Domain ou alias usados fora de `$data`. URLs que dependem de host ou scheme exigem revisão explícita de contexts e dependencies.

Referência: https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays

## 12. Commerce, pagamentos e integrações

- Commerce é a fonte de verdade para cart, order, checkout e payment. Não crie estado paralelo nem reimplemente Commerce.
- MAIN centraliza carrinho, checkout, pagamento, callbacks voltados ao navegador e webhooks, conforme as políticas do Portal.
- Segredos ficam fora de Configuration Sync e entram por Key com provider de ambiente.
- Mercado Pago preserva os guards e o comportamento fail-closed já definidos.
- LMS/Group, Social Auth e demais contrib continuam fonte de verdade de seus domínios.
- Banco de dados: o `aculta_portal` usa as APIs de entidade e storage do Drupal e **não implementa portabilidade, migração ou conversão de banco**. Esse trabalho pertence ao projeto independente DBTNG-2. Não adicione ferramentas ou gates de migração entre motores neste módulo.

## 13. PHP

- Classes runtime novas em `src/` começam com `declare(strict_types=1);`.
- Aplique type hints quando compatíveis com as assinaturas extensíveis do Core e de contrib. Não altere assinatura upstream válida apenas por estilo.

## 14. Deprecações e mudanças de versão

Antes de qualquer refactor estrutural, registre quando aplicável:

```text
RECOMENDADO NO D11:
DEPRECADO NO D11:
REMOVIDO OU ALTERADO NO D12:
ANUNCIADO PARA D13:
```

Consulte a API do Core instalado e os change records. Não faça mudança "para Drupal 12" por suposição. Upgrade Status e Rector entram como fase própria, em worktree, e não se misturam com entrega funcional. Atualização major do Core não pertence à modernização corrente.

## 15. Validação

Nunca declare PASS sem executar.

Mudança estática ou documental:

```text
STATIC: PASS/FAIL
RUNTIME HOMELAB: DEFERRED quando não houver alteração funcional
```

Quando o runtime Drupal mudar, execute no mínimo: lint dos PHP modificados, `git diff --check`, cache rebuild, `composer validate`, `composer audit`, `updatedb:status` e `config:status`, além dos gates do escopo. Não execute `drush updb`, `cim` ou `cex` automaticamente.

Gate do Portal:

```sh
php scripts/validate-aculta-portal-drupal11.php
```

Os tetos de dívida (contagens de service locators, wrappers de Views, arquivos sem `strict_types`, registros de subscriber) ficam no gate, não aqui. Reduza o teto no mesmo commit que eliminar a dívida. Arquivo novo nasce conforme este padrão.

## 16. Fontes oficiais

- Hooks OOP: https://www.drupal.org/node/3442349
- Hook attribute: https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Hook%21Attribute%21Hook.php/class/Hook/11.x
- Form API, CallableResolver: https://www.drupal.org/node/3548821
- EntityQuery e access: https://www.drupal.org/node/3201242
- Events e subscribers: https://api.drupal.org/api/drupal/core%21core.api.php/group/events/11.x
- Services e DI: https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal
- Render cacheability: https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays
- Ajax subsystem inclui HTMX (Core 11.3): https://www.drupal.org/node/3539472
- htmx 4 no Core 12 (change record): https://www.drupal.org/node/3583674
- Plano de migração Drupal 12 do projeto: `docs/portal/MIGRACAO-DRUPAL-12-PLANO.md`
- Compatibilidade do tema V2: `docs/decisions/ADR-045-compatibilidade-drupal-12-tema-v2.md`
- Migração do AJAX para htmx: `docs/decisions/ADR-050-migracao-ajax-para-htmx.md`
