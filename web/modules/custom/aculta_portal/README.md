# ACULTA Portal

`aculta_portal` é a camada de integração e customização da plataforma Drupal
da Associação Cultural Antiproibicionista.

> O Portal organiza, apresenta e conecta capacidades Drupal sem substituir suas
> fontes de dados e regras de negócio.

## Papel

O Portal integra a experiência de:

- Conta/User;
- Profile/Address/CEP;
- Social Auth;
- apoio/Commerce;
- cursos/LMS/Group;
- Wiki420;
- Fórum e comentários, quando a versão correspondente for implementada;
- participação do usuário;
- administração consolidada;
- Domain purposes.

Não é sistema próprio de autenticação, endereço, pagamento, LMS, matrícula,
progresso, Wiki, fórum ou comentários.

## Arquitetura

```text
Core + módulos contrib -> aculta_portal -> tema ACULTA420
```

O tema é apresentação. O Portal é integração. Os módulos funcionais continuam
fontes de verdade.

## Contratos

- trabalhar com Domain purpose, não hostname hardcoded;
- preferir DI, Entity API, Views e serviços públicos;
- hooks runtime novos/refatorados usam classes em `src/Hook/` com `#[Hook]` quando suportado pelo Core;
- guards de lifecycle runtime, como `entity_presave`, também usam Hook classes quando o Core suporta OOP; lifecycle de install/update permanece procedural somente quando exigido;
- no Portal, práticas runtime legadas contrárias ao padrão Drupal 11+ são dívida/depreciação do projeto; `#[FormAlter]`, novos hooks procedurais migráveis e novos service locators não são aceitos;
- callbacks Form API novos/refatorados usam serviços serializáveis no formato `service.id:method`; `aculta_portal.form_callbacks` concentra callbacks migrados progressivamente;
- em controllers novos/refatorados, dependências de runtime entram por DI explícita; não depender de helpers de `ControllerBase` que resolvam serviços de forma lazy;
- `ContainerInjectionInterface::create()` pode montar as dependências do controller, mas a lógica funcional não consulta o container;
- não consultar tabelas contrib diretamente quando houver API;
- não criar storage paralelo;
- respeitar entity access antes de expor metadata;
- decisões de entity access condicionadas por Domain/rota/usuário/request carregam cacheability explícita; tokens one-time de request/session não recebem cache persistente;
- dados privados variam por usuário e não usam cache compartilhado;
- segredos ficam fora de Configuration Sync e Git;
- AJAX usa preferencialmente APIs Drupal;
- toda feature identifica fonte de verdade, access, cache, Domain e testes.

### Breadcrumb público

`AcultaBreadcrumbBuilder` é a fonte de verdade para purpose público, rotas ocultas, raiz por domínio, hierarquia, cache metadata e resolução segura do título atual. O tema `aculta420` não replica essa política: consome os links Drupal e `currentTitle()` apenas para renderização.

## Documentação normativa

O padrão obrigatório para código humano e gerado/revisado por IA é [Padrão Drupal 11+](../../../../docs/portal/DRUPAL-11-STANDARDS.md).

Execute o gate progressivo antes de concluir alterações estruturais:

```sh
php scripts/validate-aculta-portal-drupal11.php
```

Consultar também [docs/portal](../../../../docs/portal/README.md), [Roadmap do Portal](../../../../docs/portal/ROADMAP.md) e [dívidas](../../../../docs/operations/DEBT-REGISTER.md).

O roadmap e decisões de arquitetura vivem lá. Desde a P4, os hooks runtime migráveis estão em `src/Hook/` e o módulo não mantém mais um `aculta_portal.module` vazio. Este README serve como entrada rápida para quem está no diretório do módulo.

## Changelog

Consultar [CHANGELOG.md](CHANGELOG.md).

## Credenciais do ambiente (0.2.0-dev.11)

Painel `/admin/config/aculta/segredos` para cadastrar as credenciais do contrato
(`config/secrets-contract.json`). Valores salvos ficam mascarados e podem ser revelados pelo botão de olho.
Em teste, são gravados em arquivo; em produção, ficam no banco criptografado, provisionado pelo
`aculta_deployer` após o deploy. Detalhes e limites em
[docs/operations/SECRETS.md](../../../docs/operations/SECRETS.md#credenciais-do-ambiente-painel-aculta_portal-0200-dev11).

## Código no Aculta Portal, Design no ACULTA420

> **Código no Aculta Portal, Design no ACULTA420.**

Este módulo entrega funções: rotas, controllers, forms, `#ajax`, validação, endpoints, serviços, access, dados e JS de
comportamento (com `data-portal-*` e `data-aculta-*`). Não entrega aparência: templates de apresentação, CSS, classes de
design (`btn`, `alert`, `bi-*`, `portal-account__*`) e ícones ficam no tema ACULTA420. Os templates do módulo são só
fallback neutro, sem classes, para o site continuar funcional sem o tema. Quem implementa uma função aqui pode alterar o
ACULTA420 na mesma entrega, cada parte no seu dono. Inventário e dívida: `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

Mecanismo: o módulo marca `data-portal-ui="token"`; o tema traduz o token em classes (`PortalUiHooks::TOKENS`). Links com
token usam `Render\PortalLinkElement`. Ver `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

