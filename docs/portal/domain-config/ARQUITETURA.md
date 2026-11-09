# Arquitetura — Domain Config no ACULTA

## Resolução em runtime

O `domain_config` registra o override de configuração
`domain.config_factory_override` (prioridade -253). O domínio negociado vem do
`DomainNegotiationContext`; quando ele existe, o valor final é:

```
configuração base (coleção default)
  + override do domínio (coleção domain.{domain_id})
  = valor efetivo no domínio
```

Sem domínio ativo (CLI, instalação) ou sem override, vale a base. O domínio
padrão `aculta_org` é o MAIN/global; os demais domínios herdam a base quando não
têm override.

As coleções são `domain.{id}`. Exemplo: o override de um bloco para a Conta fica
em `domain.conta_aculta_org` como `block.block.<id>`, com apenas as chaves
alteradas (diff esparso) — região, peso e `settings.label`, por exemplo.

## Leitura de entidade de configuração

Blocos são entidades de configuração. O caminho administrativo do Drupal carrega
essas entidades com `loadOverrideFree()` para não aplicar override na tela de
administração. O `domain_config_entity_ui` troca o storage de `block` por um
storage domain-aware e, em `loadOverrideFree()`, dobra o override do domínio
ativo.

O ParamConverter experimental do `domain_config_entity_ui` chamava `load()`,
que devolve a entidade base, então o formulário de edição mostrava os valores
globais mesmo com override. A ponte do ACULTA decora esse converter
(`aculta_domain_config.paramconverter.configentity_admin`) e usa
`loadOverrideFree()` na ramificação de override. A gravação já funcionava pelo
`ConfigFactory` decorado pelo `domain_config_ui`.

## Ponte ACULTA (`aculta_domain_config`)

Módulo em `web/modules/custom/aculta_portal/modules/aculta_domain_config`.

1. **`DomainConfigEditingSubscriber`** (`kernel.request`, prioridade 100, antes
   do `RouterListener`):
   - lê o domínio escolhido da query `config_domain` pelo
     `DomainConfigSwitcher`;
   - valida que o domínio existe, está publicado e não é o padrão;
   - reconhece os caminhos da administração de blocos a partir da rota
     `block.admin_display`;
   - registra no `DomainConfigEditContext` o domínio e todos os nomes
     `block.block.*` do site.
   O contexto vale somente para essa requisição; a negociação real do request e
   o roteamento não mudam.
2. **`BlockDomainConfigFormHooks`** (`form_alter`, antes de
   `domain_config_ui`/`domain_config_entity_ui`):
   - adiciona o select "Configurar domínio" nas telas de bloco
     (`BlockListBuilder` e `EntityForm` de bloco);
   - a opção vazia é "Global (sem override)"; o domínio padrão não aparece;
   - o botão "Alternar domínio" recarrega a mesma tela com `config_domain` na
     URL, descartando alterações não salvas;
   - mostra o aviso "Editando o override de X".
3. **`BlockDomainOverrideConfigEntityConverter`** decora o converter do
   `domain_config_entity_ui` e lê o override com `loadOverrideFree()`.

O toggle nativo "Enable/Remove domain configuration" do `domain_config_ui`
continua sendo o registro formal do nome de configuração para o domínio. A
ponte apenas garante que ele aponte para o domínio escolhido.

## Por que não editar visitando o subdomínio

As rotas administrativas dos subdomínios respondem 404 por política de domínio,
e a administração é centralizada no MAIN. O `domain_config_switcher` do Domain
Extras só cobre `ConfigFormBase` com `#config_target`; por isso a ponte cobre
`EntityForm` e `BlockListBuilder` com a mesma API de contexto de edição.

## Cache e isolamento

- A fábrica de configuração usa sufixo de domínio na chave de cache; o metadata
  inclui o contexto `domain`.
- O storage domain-aware adiciona a dependência cacheável do override.
- Formulários administrativos não são cache público e são sempre renderizados
  com o contexto da requisição.
- Aquece-se um domínio e depois outro nos testes; nenhum valor vaza.

## Permissões

- `administrator` tem `is_admin: true` e já acessa tudo.
- Para administradores delegados, conceder:
  - `use domain config switcher`;
  - `use domain config ui`;
  - `administer domain config ui`;
  - `administer domain config entity ui`;
  - `administer domains` (ou domínios atribuídos no campo de administração).
