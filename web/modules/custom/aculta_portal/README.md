# ACULTA Portal

`aculta_portal` é a camada de integração e customização da plataforma Drupal
da Associação Cultural Antiproibicionista.

> O Portal organiza, apresenta e conecta capacidades Drupal sem substituir suas
> fontes de dados e regras de negócio.

## Papel

O Portal integra a experiência de:

- Conta/User;
- Profile/Address/CEP;
- Social Auth, incluindo a criação da primeira senha em conta criada com Google
  (`docs/portal/SENHA-CONTA-OAUTH.md`);
- apoio/Commerce;
- checkout com endereço único, pagamento pelo Mercado Pago e envio pelo Commerce Shipping
  (pane "Envio", método de frete fixo; ver `docs/portal/CHECKOUT-BILLING.md`);
- cursos/LMS/Group;
- contrato opcional de eventos educacionais (`aculta_lms`), sem armazenamento paralelo;
- leitura compartilhada de PDF por `aculta_pdf_viewer` em Media e campos File; Group↔Media é opcional para documentos de turma;
- Wiki420;
- Fórum e comentários, quando a versão correspondente for implementada;
- participação do usuário;
- mensagens, notificações e avisos institucionais (`aculta_messenger`, em desenvolvimento);
- administração consolidada;
- normalização comum de slugs públicos e configuração da sanitização de nomes de uploads pelo Core Drupal;
- Domain purposes, incluindo o purpose `links` (página de redes sociais em `/redes-sociais`).

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
- endereço único: o cliente tem um só endereço (Meus dados > Endereço). Checkout, pagamento, envio, recibo e pedido usam
  esse mesmo endereço. Não criar perfil de cobrança ou de envio separado. O pane "Envio" não tem formulário de endereço
  próprio (`AcultaShippingInformation`). Número/S/N e Bairro existem só no pane "Endereço" (`BillingAddressPane`).
- pagamento: todo pagamento é feito no Mercado Pago. A conta não guarda nem exibe meios de pagamento salvos nem histórico
  de pagamentos. `/pagamentos` redireciona para `/pedidos`.
- segurança da conta: a aba Senha tem dois formulários. Conta com senha escolhida pede a senha atual. Conta criada pelo
  Social Auth, sem senha escolhida, mostra "Criar senha" (`AccountPasswordCreateForm`), sem senha atual.
- Minha conta: formulários salvam por AJAX pelo framework do Drupal (`#ajax`, `aculta_portal.form_callbacks`), exceto o
  formulário de criar senha, que recarrega a aba depois de gravar.

### Breadcrumb público

`AcultaBreadcrumbBuilder` é a fonte de verdade para purpose público, rotas ocultas, raiz por domínio, hierarquia, cache metadata e resolução segura do título atual. O tema `aculta420` não replica essa política: consome os links Drupal e `currentTitle()` apenas para renderização.

## Documentação normativa

### Capacidades e extensões ativas

Toda capacidade nova do módulo principal deve ser registrada neste README e na
documentação específica do seu contrato. Toda extensão em `modules/*/` deve
declarar nome/descrição no `.info.yml` e manter README ou documentação em
`docs/`. A tela administrativa **ACULTA Portal > Status** lista o módulo
principal e as extensões Portal habilitadas diretamente do registro do Drupal;
ela não mantém uma lista paralela de estados. O gate
`php scripts/validate-aculta-portal-drupal11.php` verifica esse contrato.

O Status também mantém o inventário **Implementações do ACULTA Portal**, com
seções de Helpers reutilizáveis e Extras aplicados (adaptações e integrações
runtime). Serviços são descobertos por tags do container e descritos nos
registros próprios. Ao adicionar um deles, atualize tags, metadados, documento
referenciado e gate conforme [Implementações](../../../../docs/portal/IMPLEMENTATIONS.md).

O slug de URL e o valor exibido são dados distintos: use
`Drupal\aculta_portal\Routing\PublicRouteSlug::normalize()` antes de gerar um
caminho público, valide depois com as regras da rota e preserve o original para
exibição. Para upload, o Core aplica `file.settings:filename_sanitization` aos
novos arquivos. Regras e limites: [guardrails](../../../../docs/portal/GUARDRAILS.md)
e [slugs públicos](../../../../docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md).

O padrão obrigatório para código humano e gerado/revisado por IA é [Padrão Drupal 11+](../../../../docs/portal/DRUPAL-11-STANDARDS.md).

Execute o gate progressivo antes de concluir alterações estruturais:

```sh
php scripts/validate-aculta-portal-drupal11.php
```

Consultar também [docs/portal](../../../../docs/portal/README.md), [Roadmap do Portal](../../../../docs/portal/ROADMAP.md) e [dívidas](../../../../docs/operations/DEBT-REGISTER.md).

O roadmap e decisões de arquitetura vivem lá. Desde a P4, os hooks runtime migráveis estão em `src/Hook/` e o módulo não mantém mais um `aculta_portal.module` vazio. Este README serve como entrada rápida para quem está no diretório do módulo.

## Fluxos documentados

| Assunto | Documento |
| --- | --- |
| Endereço único, cobrança, envio e Mercado Pago | [docs/portal/CHECKOUT-BILLING.md](../../../../docs/portal/CHECKOUT-BILLING.md) |
| Criar a primeira senha em conta criada com Google | [docs/portal/SENHA-CONTA-OAUTH.md](../../../../docs/portal/SENHA-CONTA-OAUTH.md) |
| Confirmação de e-mail | [docs/portal/EMAIL-CONFIRMATION-POLICY.md](../../../../docs/portal/EMAIL-CONFIRMATION-POLICY.md) |
| Pendências abertas | [docs/portal/historico/PENDENCIAS-PROXIMA-RODADA.md](../../../../docs/portal/historico/PENDENCIAS-PROXIMA-RODADA.md) |
| Dependências do módulo | `aculta_portal.info.yml`: `commerce_shipping` e `physical` (composer: `drupal/commerce_shipping` 3.0.5, `drupal/physical` 1.5.0) |

## Changelog

Consultar [CHANGELOG.md](CHANGELOG.md).

## Projetos de submódulos

- [ACULTA Fórum — Growroom420](modules/aculta_forum/README.md): acervo recuperado do
  Wayback, memorial coletivo com autoria individual e integração comunitária.
- [ACULTA Game](modules/aculta_game/README.md): conquistas e missões, reaproveitando
  a base atual de badges sem duplicar concessões e com pesquisa de licenças.

Projetos abertos por decisão do responsável em 2026-10-15. Fórum permanece
documental. Game é extensão opcional instalável desde G1: assume os gatilhos de
conquistas e preserva o backend no User. O Portal não passa a depender de Game.

## Submódulo de dados dos associados

`modules/aculta_associado_id` orquestra dados identificadores e cadastrais dos
associados para atas, relatórios e obrigações legais. Atualmente implementa
serviços e persistência protegida de CPF/CNPJ; outras finalidades ainda não
estão implementadas. O Portal continua dono da integração funcional e ACULTA420
da apresentação.

Consultar o [README](modules/aculta_associado_id/README.md) e o
[plano de migração/evidência](../../../../docs/modules/ACULTA-ASSOCIADO-ID.md).

## Submódulo ACULTA Messenger

O `aculta_messenger` integra Private Message, Message, Message Notify,
Message Subscribe e Site Alert sem substituir suas entidades, permissões ou
fluxos. Consultar a [documentação do submódulo](modules/aculta_messenger/README.md)
antes de implementar integrações funcionais.

## Credenciais do ambiente (0.2.0-dev.11)

Painel `/painel-administrativo/configuracoes/aculta/segredos` para cadastrar as credenciais do contrato
(`config/secrets-contract.json`). Valores salvos ficam mascarados e podem ser revelados pelo botão de olho.
Em teste, são gravados em arquivo; em produção, ficam no banco criptografado, provisionado pelo
`aculta_deployer` após o deploy. Detalhes e limites em
[docs/operations/SECRETS.md](../../../../docs/operations/SECRETS.md#credenciais-do-ambiente-painel-aculta_portal-0200-dev11).

## Código no Aculta Portal, Design no ACULTA420

> **Código no Aculta Portal, Design no ACULTA420.**

Este módulo entrega funções: rotas, controllers, forms, `#ajax`, validação, endpoints, serviços, access, dados e JS de
comportamento (com `data-portal-*` e `data-aculta-*`). Não entrega aparência: templates de apresentação, CSS, classes de
design (`btn`, `alert`, `bi-*`, `portal-account__*`) e ícones ficam no tema ACULTA420. Os templates do módulo são só
fallback neutro, sem classes, para o site continuar funcional sem o tema. Quem implementa uma função aqui pode alterar o
ACULTA420 na mesma entrega, cada parte no seu dono. Inventário e dívida: `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

Mecanismo: o módulo marca `data-portal-ui="token"`; o tema traduz o token em classes (`PortalUiHooks::TOKENS`). Links com
token usam `Render\PortalLinkElement`. Ver `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

