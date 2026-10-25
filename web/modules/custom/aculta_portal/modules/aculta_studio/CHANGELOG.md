## 0.1.0-dev.19 — revisão S-R3-B1: editor CPF/CNPJ opcional (2026-10-25)

- Rota /studio/conta/documentos agora verifica a ausência do owner após autorização e retorna 404 antes de resolver o Portal. Quando o serviço está instalado mas indisponível, mantém a mensagem segura do Form API original e oculta o link.
- Testes Unit novos para 404/403, disponibilidade, formulários mascarados e destinos checkout/Studio/legado, sem duplicar dados, schema ou shell.
- Auditoria: AccountDocumentStore verifica duplicidade por HMAC antes do save; ainda não há unicidade transacional sob concorrência. Esse limite é do módulo aculta_id_cpf.
- **Não executado:** PHPUnit, lint, gates, Kernel/Functional, HTTP, Firefox ESR/X11 e Runtime. Sem merge/deploy. Minha Conta legada /conta-legada continua permanente.

## 0.1.0-dev.18 — S-R3-B2: Minha Conta e Meu Perfil editam campos separados (2026-10-25)

- Nova `/studio/conta/dados`: formulário Portal/Profile original, limitado por route ID a dados cadastrais privados, sem apelido.
- Nova `/studio/perfil/apelido`: editor proprietário `aculta_user\Form\NicknameForm` sobre o `field_nickname` do mesmo Profile, com validação e unicidade pelo ACULTA User.
- Navegação e testes Unit ajustados; sem aliases, storage paralelo, alteração no checkout, mudança do hub `/` nem remoção da Minha Conta original `/conta-legada`.
- Revisão estática realizada no GitHub. Testes de PHPUnit, PHPCS/lint, Kernel/Functional, gates, HTTP e Firefox ESR/X11 **não executados**; nenhuma homologação ou deploy.

## 0.1.0-dev.17 — S-R3-B1: Documentos privados na Minha Conta Studio (2026-10-24)

- Nova rota canônica `/studio/conta/documentos` (purpose `account`,
  login e permissão Portal), delegada ao **mesmo**
  `PortalController::otherData()` e `AccountOtherDataForm` já usados
  no legado em `/dados/outros-dados`. O serviço
  `aculta_id_cpf.account_document` mantém sozinho validação,
  criptografia, leitura mascarada, gravação única e regras de CPF/CNPJ.
- `AccountExperienceProvider` e a navegação contextual só exibem
  Documentos se o serviço opcional responder `isAvailable()`. Sem
  owner, a rota continua sujeita ao 404 original de `PortalController`.
- O retorno pós-gravação em `AccountOtherDataForm` usa a rota de origem:
  `aculta_studio.account_documents` no Studio, ou
  `aculta_portal.my_data_other` no legado. A prioridade do destino
  legítimo de checkout permanece inalterada; sem redirects temporários.
- Testes Unit de controller, provider e retorno contextual adicionados/
  estendidos, **ainda não executados para a dev.17**. É obrigatória a
  verificação Kernel/Functional do serviço opcional habilitado/ausente,
  documentação sensível, acesso, submit sem JS e checkout.
- O hub geral continua em **`/` de ACCOUNT**; Minha Conta original
  permanece permanentemente em **`/conta-legada`**, e `/studio`
  é o caminho interno não público. Conta e Perfil seguem experiências
  independentes. Nenhum schema, dado ou shell antigo foi modificado
  por esta etapa.

## 0.1.0-dev.16 — testes Unit do hub e das seções da Conta com container de tradução (2026-10-24)

- `StudioHubControllerTest` e `StudioAccountSectionControllerTest` registram `string_translation` no `setUp`.
  Os 4 erros `ContainerNotInitializedException` desapareceram. Suíte Unit do Studio: 48 testes OK.

## 0.1.0-dev.15 — hub do Studio na raiz da Conta; link para a Minha Conta original em /conta-legada (2026-10-24)

- A raiz `/` do Domain ACCOUNT é o hub do Studio (`aculta_studio.hub`).
- O link "Abrir Minha Conta original" usa a rota `aculta_portal.dashboard` (`/conta-legada`), não `<front>`.
- Testes Unit `AccountExperienceProviderTest` e `StudioIdentityRoutingTest` atualizados ao novo contrato.

## 0.1.0-dev.14 — S-R3-A: sections de Conta integradas sem substituir o legado (2026-10-24)

- `/studio/conta/endereco`, `/studio/conta/seguranca`,
  `/studio/conta/seguranca/senha` e `/studio/conta/conexoes`
  usam `StudioAccountSectionController` para compor os render arrays e
  Form API originais de `PortalController` no contexto Studio.
- Navegação simples e acessível com URLs próprias do Studio; somente as
  tabs legadas são omitidas do conteúdo composto, sem alterar owners,
  handlers ou cache dos formulários.
- **Dados cadastrais permanecem no Portal original** `/dados` enquanto
  `field_nickname` não estiver isolado: incorporar aquele formulário
  agora misturaria Conta e Perfil. Nenhum campo novo foi criado.
- Minha Conta legada segue na raiz `/` do Domain ACCOUNT, com
  `/minha-conta` técnico 404; seus menus, shell e formulários não foram
  removidos nem serão retirados após o aceite.
- Testes Unit escritos para acesso, dispatch por método, links e
  preservação do formulário; testes de rota e endereços atualizados.
  **Execução PHPUnit/Kernel/Runtime/Firefox ainda pendente**. Fluxos
  reais de upload, e-mail, OAuth e endereço ainda precisam homologação.
- S-R3-B: dados privados sem apelido, documentos opcionais, preferências,
  pedidos/apoio e comparação de formulário com o legado.
  Contrato: `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`.

## 0.1.0-dev.13 — separação definitiva Conta/Perfil no Studio (2026-10-24)

- `/studio/conta` e `/studio/perfil` passam a ser **experiências independentes**
  (`conta` e `perfil`), com providers, controllers, ACL e entradas separadas
  no hub. `/studio/pessoal/*` foi removido sem aliases.
- Conta Studio tem navegação inicial para os formulários existentes do Portal;
  nenhum formulário ou entidade foi copiado. Perfil Studio mantém apenas bio,
  redes, foto e privacidade, preservando `/u/{apelido}` em MAIN.
  Apelido ainda requer formulário público isolado; **não** renderizar
  o formulário cadastral completo na experiência Perfil.
- Formulários do ACULTA User foram reaproveitados em rotas de Perfil com
  Form API completo, sem JS obrigatório; editores e handlers da Minha Conta
  original permanecem inalterados no próprio contexto.
- **Minha Conta original deve existir permanentemente em `/` no purpose
  ACCOUNT** para comparação, incluindo shell, menus e dados.
  `/minha-conta` direto permanece rota técnica bloqueada em 404.
- `StudioProfileAccess` verifica módulo User instalado e autorização.
  Testes Unit e de rotas atualizados; PHP/Unit/Kernel/Functional/Runtime/
  Firefox **não executados** neste incremento.
- Contrato: `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`.
  Logs dev.8–12 abaixo registram apenas o histórico da arquitetura superada.

## 0.1.0-dev.12 — STUDIO-P2-A: editor de Meu Perfil por serviços User (2026-10-24)

- Nova rota privada e canônica `/studio/pessoal/perfil` (purpose `account`,
  login e `access aculta portal`), ligada em Meu Espaço.
- `PersonalProfileController` carrega **somente o User da sessão** e reutiliza
  `aculta_user.account_bio_card` e `aculta_user.social_links_card` — os
  botões desses cards abrem os formulários originais por AJAX modal, com
  gravação User e recarga da página corrente. Editor de foto usa a rota
  existente `aculta_user.foto` e biblioteca de recorte original.
- "Ver perfil público" usa **`aculta_user.public_profile_link`**, que consulta
  os campos de perfil, regras de visibilidade, apelido canônico e purpose
  `main`; nunca criar link público por inferência de apelido privado.
  O controller não exibe dados/documentos privados em área pública e
  mantém cache personalizado/max-age 0.
- O editor de apelido continua apontando a `aculta_portal.my_data`; os
  controles completos de privacidade apontam ao formulário de
  `aculta_user.configuracoes_privacidade`. P2-B deverá integrar esses
  formulários ao Studio sem duplicar Form API, tabs ou shell.
- Se os serviços opcionais do User não estiverem disponíveis, a tela
  apresenta os links básicos sem dar acesso indevido e sem exigir services
  inexistentes. **Sem alias temporário**, nenhum storage/schema alterado.
- Testes Unit adicionados para negação de visitante/permissão, conta ausente,
  render básico privado e link público autorizado; atualizados tests de rota
  e link de Meu Espaço. Testes **não executados** nesta edição por GitHub:
  PHPUnit, Kernel/Functional, Drush, Firefox ESR/X11, AJAX/fallback sem JS.
  O acesso direto sem JS aos modais bio/redes ainda precisa de solução
  progressiva na fase P2-B, pois os formulários originais restringem GET
  a requisições de modal.

## 0.1.0-dev.11 — remoção de aliases temporários em desenvolvimento (2026-10-24)

- Remoção dos aliases de desenvolvimento do Studio. /studio é o hub e /studio/marley é o único início editorial; excluídas as antigas rotas /studio/inicio, /studio/Marley, /ganjalab e demais árvores antigas. Controllers de redirecionamento não usados removidos. Teste de rotas atualizado e gate transversal criado. Sem homologação Drupal/Runtime.
- Controller de hub renomeado para `StudioHubController` e serviço `aculta_studio.hub_controller`; classe/testes de prévia removidos, sem modificar a autorização.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.

## 0.1.0-dev.10 — STUDIO-P1-C: Hub canônico e Marley minúsculo (2026-10-24)

- **Decisão vigente:** `/studio` passa a apresentar o hub global para contas
  autenticadas autorizadas; **`/studio/marley` é a entrada inicial canônica
  do Marley**, preservando o route ID `aculta_studio.home`, controller, abas,
  seções e subrotas minúsculas.
- `/studio/Marley` (capitalização rejeitada como URL canônica) passa a alias
  temporário com redirecionamento HTTP 302 somente GET/HEAD para
  `/studio/marley`. `/studio/inicio`, a prévia da dev.9, redireciona
  apenas GET/HEAD para `/studio`.
- O antigo route ID `aculta_studio.legacy_home` permanece para consumidores,
  agora atendendo o controller do hub. Links internos editoriais gerados pelo
  route ID `aculta_studio.home` apontam para a URL minúscula. O catálogo
  continua filtrando experiências por autorização e disponibilidade.
- Atualizados os testes Unit de rotas e hub para a URL canônica minúscula,
  o alias maiúsculo e a nova apresentação do hub. Não modificar
  `/studio/pessoal`, `/studio/ganjalab`, `/studio/we-sativa`,
  home `/` da Conta ou perfil público `/u/{apelido}`.
- **Limitação:** execução de PHPUnit, Kernel/Functional, verificação de
  subscribers de Domain, testes HTTP autenticados, Runtime/Drush e
  Firefox ESR/X11 **pendentes**. A mudança está no código Git, não
  homologada; nenhum deploy, migração ou merge foi feito.

## 0.1.0-dev.9 — STUDIO-P1-B: ponte Marley e prévia do hub (2026-10-24)

- `MarleyExperienceProvider` registra Marley no `StudioExperienceManager`,
  sem substituir `MarleyTabManager` nem alterar seu controller, abas e rotas.
  A visibilidade do provider exige autenticação, `access aculta portal` e ao
  menos uma área com ações efetivamente autorizadas pelo `MarleyPresenter`.
  Como o presenter não fornece cacheability agregada de plugins, a decisão
  dinâmica possui `max-age: 0` até existir contrato específico seguro.
- O manager expõe `all()` para o inventário ordenado dos providers registrados,
  **sem conceder permissões**. Configuração inclui pessoal, Marley, Ganjalab
  e WE SATIVA. Disponibilidade, acesso e existência de rota continuam
  obrigatórios na apresentação.
- Nova prévia segura em `/studio/inicio` (purpose account, GET/HEAD,
  autenticado). `StudioHubPreviewController` lista links reais de
  experiências autorizadas e ignora provider de módulo opcional cujo destino
  não existe. A página não contém dados pessoais nem substitui o shell da
  Conta.
- A rota `/studio` **permanece exatamente no controller de compatibilidade do
  Marley**. As rotas existentes de Ganjalab, WE SATIVA e Meu Espaço permanecem.
- Testes Unit adicionados para ponte Marley, descoberta/ACL do hub,
  enumeração do registro e metadados/precedência das rotas.
  **Testes PHPUnit, Kernel, Runtime e Firefox ESR/X11 não executados por esta
  edição no GitHub**. Não afirmar homologação ou RC.
- Próximo passo: validar PHPUnit/Kernel/HTTP autenticado e decidir a entrega
  controlada da nova home `/studio`, com inventário dos links legados;
  a migração dos formulários permanece P2/P3.

## 0.1.0-dev.8 — STUDIO-P1-A: entrada pessoal inicial — 2026-10-24

- Provider `PersonalExperienceProvider` (`pessoal`) registrado no
  `StudioExperienceManager`, com purpose `account`, autenticação e
  permissão `access aculta portal` revalidadas pelo controller.
- Nova rota explícita `/studio/pessoal` oferece entrada mínima e links
  para os formulários **existentes** da Conta. `/studio` continua sendo a
  entrada de compatibilidade do Marley; `/` da Conta e `/u/{apelido}`
  permanecem inalterados. Nenhum dado, formulário ou shell foi migrado.
- Testes Unit adicionados para provider, controller e proteção/precedência
  da rota; execução PHPUnit/Kernel/Runtime e teste visual ainda pendentes
  neste incremento via GitHub. Inspeção de código e metadados da branch
  não substituem esses gates.
- Próxima P1-B: registro/ponte de Marley no catálogo de experiências e
  preparação do hub, após confirmar acesso/cache e executar os testes;
  não alterar a rota `/studio` sem inventário e testes.

## 0.1.0-dev.6 — início da WE SATIVA

- Rota `/studio/we-sativa/inicio` no purpose `account`, com provider de página
  inicial opcional para que o owner forneça o conteúdo e autorização.
- Integração delega dados, acesso e cache a `aculta_wiki`; Kernel focal integrado
  passou em 2 testes / 208 asserções, incluindo render no ACULTA420. Unit do controller cobre delegação,
  provedor sem contrato de início e acesso negado (3/3; suíte Studio 20/61).
  Runtime e validação visual pendentes.
## 0.1.0-dev.7 — identidades Marley e Ganjalab

- A experiência editorial passa a ACULTA Studio Marley (`marley`) e a
  experiência de aprendizagem ACULTA Studio Ganjalab (`ganjalab`); o módulo
  compartilhado `aculta_studio` e a infraestrutura LMS permanecem.
- Rotas canônicas: `/studio/marley` com seus níveis e `/studio/ganjalab` para
  estudantes. Entradas anteriores não colidentes recebem 302 somente em
  GET/HEAD; `/studio/ganjalab` pertence exclusivamente à aprendizagem.
- Contratos, controllers, services e descoberta de plugins específicos de
  Marley recebem nomenclatura própria; IDs funcionais das áreas não mudam.
- Unit Studio passou em 22 testes/83 asserções e Unit LMS em 1/7. O Runtime de
  teste passou a expor `/studio/marley` e `/studio/ganjalab` após rebuild de
  cache. Firefox ESR/X11 passou em 29/29 verificações aplicáveis; a checagem de
  link de edição foi ignorada porque não há publicação própria de teste.

