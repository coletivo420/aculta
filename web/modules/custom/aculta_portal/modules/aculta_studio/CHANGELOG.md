> Nota de 2026-11-02: os caminhos `/studio/we-sativa`, `/studio/green-stash`, `/wesativa`, `/greenstash`, `/wiki/busca`, `/wiki/verbetes` e `/wiki/propostas` citados abaixo foram substituídos por `/we-sativa`, `/green-stash`, `/busca-wiki`, `/verbetes` e `/propostas`. Registro histórico preservado; veja a retificação no ADR-038.

## Implementação pendente de validação — Conta Studio (2026-11-03)

- Shell privado com navegação AJAX própria, histórico, metadados do renderer Account, estados de espera/falha e preservação do menu aberto/fechado. Form API continua sob os owners atuais.
- Dashboard monta experiências autorizadas por provider e `DomainPurposeManager`; identidade pública só aparece quando resolvida pelo owner. Perfil público segue em rota e controlador separados.
- Marley remove destino de criação duplicado e liga “Criar” à rota de `aculta_magazine`; Culturas fornece seu provider no módulo owner de Eventos.
- Studio Unit 72/658 PASS. Runtime sincronizado em `b70e56dbec`: `updatedb:status` sem pendências, `config:status` limpo, `drush cr` PASS e rotas Conta/Perfil/Marley resolvidas. Firefox ESR visível no Runtime: comum 82/82 PASS (21 SKIP) e moderadora 99/99 PASS (5 SKIP), 0 falhas. Conta AJAX, Form API inválido/Preferências válida, Dashboard cinco providers, Perfil separado, responsividade, teclado, claro/escuro e Marley/moderação A/B verificados. Capturas em `$SUITE_OUT/studio-account-member-final-4-20261103/` e `$SUITE_OUT/studio-account-moderator-final-4-20261103/`. DEFERRED: senha/upload/anexos, escrita pessoal/endereço, módulos opcionais ausentes, zoom 200–400%, leitor de tela, conteúdo aguardando moderação e comparação integral com a prancha. Versão `0.1.0-dev.40` mantida: esta rodada ampliou o harness e os registros, sem feature nova.

## 0.1.0-dev.40 — Página WE SATIVA em /we-sativa

- As rotas `/wesativa` e `/wesativa/inicio` passam a `/we-sativa` e `/we-sativa/inicio`, no purpose `wiki`.
- Qualquer `/studio/*` segue 404. Testes: DEFERRED.

## 0.1.0-dev.39 — drop zone do Studio sobrevive ao cache de formulário e remove envios

- As dependências do `StudioUploadEmbedForm` passam a `protected readonly`: o `DependencySerializationTrait` do FormBase só serializa propriedades visíveis no escopo dele, então `private` promovidas se perdiam e o envio em duas etapas dava 500 ("must not be accessed before initialization").
- O `details` de envios recentes ganha `#tree`, para o tableselect chegar como `recentes[table][fid]`, e `#limit_validation_errors` inclui `integracao`; antes a ação de remover recebia seleção e integração vazias e não apagava nada.
- Versão 0.1.0-dev.39.

RUNTIME STATUS: VALIDATED no Runtime `aculta_runtime` (2026-11-02): PNG válido aceito
(fid temporário em `private://studio/envio/wiki/`), texto renomeado para `.png` recusado,
PNG de 11,47 MB recusado pelo teto de 10 MB, envio concluído com "Um arquivo recebido."
e remoção de envio sem uso apagando o arquivo (status Deleted, linha e arquivo ausentes).
Unit Studio 64/606 PASS.

## 0.1.0-dev.38 — apresentação sai das classes de design

- `LearnerDashboardComposer` e `CourseJourneyComposer` emitem `data-portal-ui` (e `data-state`/`data-variant`/`data-route-current`) em vez de classes `aculta-learning-*`; o ACULTA420 passou a selecionar por esses atributos.
- Unit Studio 64/606 PASS. Revisão visual no navegador pendente.

RUNTIME STATUS: DEFERRED. Mudança de marcação; visual não validado.

## 0.1.0-dev.37 — revisão do formulário de envio

- Imports do `StudioUploadEmbedForm` em ordem alfabética (padrão Drupal). Sem mudança de comportamento.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.36 — aviso de prazo logo após o envio

- Depois de enviar, a própria tela do Studio mostra o prazo de limpeza de envios sem uso. Sem e-mail nem notificação externa.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.35 — aviso de prazo na lista de envios

- Envio sem uso mostra quanto falta para a limpeza automática (prazo de temporário do File API, 6 horas por padrão).
- Aviso fica na própria página do Studio. Não há e-mail nem notificação de saída nesta fase.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.34 — só arquivos sem uso podem ser selecionados

- Linhas de envios em uso ou ligados a conteúdo ficam desabilitadas na lista. A checagem no servidor continua valendo.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.33 — remover envio sem uso pelo Studio

- A lista de envios recentes ganha seleção e o botão "Remover selecionados sem uso".
- Remoção passa pela limpeza do aculta_file_uploader (`UnusedFileCleaner`): envio em uso não é apagado. Só o dono remove o seu; administrador remove qualquer um sem uso.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.32 — lista de envios recentes na página de envio

- A página de envio do Studio lista os envios recentes da integração: os do usuário, ou todos se administrador.
- Cada envio mostra data e situação (em uso ou sem uso ainda). Aviso: envios sem uso são apagados após 6 horas.
- Dependências novas: RecentUploads, file.usage, current_user e date.formatter.

RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.31 — drop zone embutida no Studio

- Nova página `/estudio/envio/{integracao}` (wiki, fórum, galeria, revista, LMS), na casca do Studio. Só usuário logado; a regra de cada integração vale no salvar.
- `StudioUploadEmbedForm` monta a drop zone pela política `aculta_file_uploader.consumer_policy` (imagem e documento) e revalida o arquivo no envio. Não cria conteúdo.
- Arquivo recebido fica temporário até a integração referenciá-lo. Essa ligação ainda não existe: a página só recebe e confirma o envio.
- Usuário (foto) fica fora desta página.
- Teste Unit do controller: integração da lista e 404 fora dela.

RUNTIME STATUS: DEFERRED. Testes não executados (sem vendor/ nem Runtime).

## 0.1.0-dev.30 — correção de runtime no Ganjalab Educador (2026-11-01)

- `CourseAuthoringController::dashboard()` chamava `PortalLinkElement::build()`
  com dois argumentos; o helper exige atributos. A ação "Novo curso" passou a
  enviar `data-aculta-studio` e `data-portal-ui`, eliminando o
  `ArgumentCountError` registrado no Runtime.
- Arrays longos do dashboard foram divididos para o padrão Drupal.
- `CourseJourneyPresentationHooks` recebeu o import explícito de
  `Drupal\aculta_studio\Learning\CourseJourneyComposer`. Sem ele, o type hint
  resolvia para `Drupal\aculta_studio\Hook\CourseJourneyComposer` e o container
  lançava `TypeError` ao renderizar a jornada/sala. Corrigido e validado no
  Runtime.
- Regressão coberta em `CourseAuthoringControllerTest` (3 testes / 36
  asserções). Verificação estática com nikic/php-parser: 54 chamadas,
  0 com aridade insuficiente.
- Acompanha a fase B2: o Studio possui `LearnerDashboardComposer`, as rotas
  Ganjalab e `CourseJourneyPresentationHooks`; o LMS não é dependência.
  `LearnerDashboardComposerTest` passou ao Studio, o teste obsoleto de
  `LearningDashboardController::student()` no LMS foi removido e
  `StudioExperienceManagerTest` usa o composer real.
- Runtime: `/ganjalab/educador` e `/ganjalab/educador/cursos/novo` responderam
  200 para conta autorizada; jornada e sala do estudante 74/74 em 320–1440 px.
  Nenhum dado de curso, matrícula ou progresso foi alterado.

## 0.1.0-dev.29 — Studio sem import de aculta_lms (2026-11-01)

- `aculta_studio` declara dependência de `aculta_learning_contracts` e importa
  apenas os contratos neutros: `CourseJourneyReaderInterface`, `CourseJourney`,
  `CourseJourneyLesson` e `LearnerDashboardComposerInterface`.
- `LearnerDashboardComposer` deixou de pertencer ao Studio; a implementação
  vive no `aculta_lms`. `GanjalabExperienceProvider` consome a interface e a
  referência opcional `@?aculta_lms.learner_dashboard_composer`.
- O Studio não importa mais nenhuma classe do namespace LMS contrib nem de
  `Drupal\aculta_lms`. Comportamento visual, rotas e destinos preservados.
- Sem dados, storage, permissões ou configuração ativa alterados.
- **Runtime:** `aculta_learning_contracts` foi habilitado após snapshot com hash;
  `config:status` ficou sem diferenças e o namespace dos contratos passou a
  autoload. Jornada e sala 74/74; dashboard e formulário do Educador 200.

## 0.1.0-dev.28 — contrato da jornada em camada neutra (2026-11-01)

- Correção da fatia dev.27: `CourseJourneyReaderInterface`,
  `CourseJourney` e `CourseJourneyLesson` foram movidos para
  `Drupal\aculta_portal\Contract\Learning`, do qual o Studio já depende.
- `CourseJourneyComposer` passa a importar apenas o contrato neutro; não importa
  classes do namespace LMS contrib nem `aculta_lms`. Comportamento visual, destinos e cache não
  mudaram.
- Validação: PHPUnit Unit Studio 60/586 e LMS 92/482 PASS; smoke Firefox/X11 do
  Ganjalab a 390 px 29/29 OK. Nenhum dado ou configuração alterado.

## 0.1.0-dev.27 — jornada consumida por contrato ACULTA (2026-11-01)

- `CourseJourneyComposer` deixa de importar a interface Course do LMS contrib
  e consome apenas `CourseJourney`/`CourseJourneyLesson`, o modelo de
  apresentação do contrato `CourseJourneyReaderInterface`.
- `build()` e `buildRoom()` recebem o DTO; aula atual, posição, estados,
  progresso e destino continuam vindo do adaptador `aculta_lms`, que mantém a
  fonte de verdade no Group/Drupal LMS.
- O Studio não ganha armazenamento, permissão, rota ou dado paralelo.
- Validação: PHPUnit Unit Studio 60/586 PASS; PHP lint e PHPCS sem erros.
  Smoke Firefox ESR/X11 da experiência Ganjalab a 390 px: 29/29 verificações
  OK (início, jornada e sala), sem alteração de dados ou configuração.
  Inventário em `docs/modules/LMS-CONTRIB-DEPENDENCY-INVENTORY.md`.

## 0.1.0-dev.26 — atalhos entre experiências Ganjalab (2026-11-01)

- O dashboard de estudante mostra atalho ao Ganjalab Educador somente se a rota de Educador autorizar a conta.
- O dashboard Educador oferece retorno ao estudante somente se a rota de estudante autorizar a conta; ordenação posiciona o atalho junto ao cabeçalho e mantém “Novo curso” como ação principal.
- Sem papel novo, storage, mudança de membership ou mescla dos dashboards. A lista do Educador exige acesso update do Group para não confundir cursos somente frequentados com cursos gerenciáveis. Os destinos continuam em purpose courses e revalidam acesso no servidor.
- Testes automatizados, lint/PHPCS, browser e Runtime não foram executados nesta etapa.

## 0.1.0-dev.25 — fronteiras Marley/Ganjalab (2026-11-01)

- Ganjalab estudante mantém `/ganjalab`; a experiência educadora usa `/ganjalab/educador` e o formulário de criação foi movido para `/ganjalab/educador/cursos/novo`.
- Rótulos de dashboard e formulário agora identificam Ganjalab Educador; o retorno do formulário volta ao dashboard educador. O provider estudante passa a se identificar explicitamente como Ganjalab Estudante.
- A aba Marley Fórum foi limitada à moderação de denúncias via permissão `moderar denuncias do forum` e rota owner `aculta_user.denuncias_forum`; criação de tópicos e navegação geral de discussões não aparecem no Marley.
- Group/LMS e os módulos Forum/User continuam donos de ACL, conteúdo e persistência. Sem novos papéis, entidades ou armazenamento.
- A expectativa da rota de criação no teste de identidade foi alinhada. Nenhum teste automatizado foi executado nesta alteração; Runtime e revisão visual continuam pendentes.

## Retificação de caminho ACULTA STUDIO WE SATIVA — 2026-10-30

- O caminho canônico confirmado pelo responsável é `/studio/we-sativa` no purpose `wiki`, incluindo a entrada de contribuição.
- Os links Wiki passam a gerar a rota dentro do purpose `wiki`; homologação Kernel/Runtime após a retificação continua pendente.

## 0.1.0-dev.23 — qualidade S-R4/S-R5: PHPCS, testes de Conta e rotas verificadas (2026-10-25)

- PHPCS Drupal e DrupalPractice limpo em `aculta_studio` (src e tests). Documentação ausente foi escrita com descrições reais, não com blocos vazios; arrays longos foram divididos e nomes totalmente qualificados trocados por `use`.
- Os testes de navegação contextual da Conta passaram a localizar o item pela rota, não pela posição. A opção Documentos depende do owner opcional, então a posição mudava quando ele não estava instalado. Sem mudança no controller.
- Gates executados nesta rodada: PHPUnit Unit `aculta_studio` 60/60 OK; `ProfileSdcRenderKernelTest` 3/3 OK no banco de teste dedicado; PHP lint sem erros; `drush` confirmou no Runtime de teste as 16 rotas `aculta_studio.*` e as 10 rotas legadas da matriz, com `/pagamentos` ausente.
- Corrigido o validador de fronteira: a asserção de `/pagamentos` exigia um redirect removido de propósito em `e75437129`. Agora ela verifica a ausência do controller, conforme a política sem aliases temporários.
- **Pendente, não homologado:** o validador de fronteira ainda aponta módulos de Studio, Wiki e LMS que renderizam SDCs do ACULTA420 por `#component => 'aculta420:...'`. Corrigir exige decisão de arquitetura. Os testes de navegador de S-R4/S-R5 não foram executados nesta rodada.

## Nota de preparação S-R5 — paridade Conta/Perfil (2026-10-25)

- Inventário estático de rotas, owners e políticas para comparação de `/studio/conta` com a interface permanente `/conta-legada`, e do editor `/studio/perfil` com o perfil público `/u/{apelido}` em MAIN. Matriz de casos, estados, diferenças intencionais, evidências e critérios: [S-R5-MATRIZ-COMPARACAO.md](docs/S-R5-MATRIZ-COMPARACAO.md).
- Adicionada suíte de navegador somente leitura `tests/browser/aculta-studio/parity-s-r5.mjs`, com duas contas de teste independentes, acesso anônimo, GET sem JS, comparação estrutural de Form API, sentinelas de privacidade inclusive no HTML, screenshots dos viewports 320/375/390/768/1024/1440 px e registro PASS/FAIL/SKIP. Instruções e limites em `tests/browser/aculta-studio/README.md`.
- A Conta Studio omite intencionalmente o apelido cadastral; a Conta legada mantém o campo. O Portal não registra rota legada `/pedidos`; a suíte evita inventá-la e compara `/conta-legada` somente como referência visual. Perfil privado em MAIN segue `PublicProfilePolicy` (apelido e foto possíveis; sem bio/redes/e-mail).
- **Não houve execução no Firefox ESR/X11 nem Runtime Drupal**, pois não há checkout do Homelab neste ambiente. Apenas leitura estática e verificação de parse de código JavaScript com imports removidos para compilação. Todos os gates S-R4 e testes funcionais S-R5 continuam pendentes. **S-R4 não homologada; S-R5 iniciada como instrumentação, sem aceite.** Sem alteração de versão, contrato, Form API ou de `/conta-legada`; nenhum merge/deploy.

## 0.1.0-dev.22 — revisão estática S-R4 e handoff para S-R5 (2026-10-25)

- Corrigida a substituição insegura de apelido ausente: `StudioProfileController` deixa de mostrar `User::getDisplayName()` (que pode representar e-mail ou nome de cadastro) em `profile-identity`; usa texto neutro `Apelido não informado`, até o `AuthorIdentityResolver` retornar apelido real do User. O link público continua exclusivamente condicionado ao `PublicProfileLink`.
- Teste Unit `StudioProfileControllerTest` passou a usar um nome de conta deliberadamente privado para prevenir regressões de Conta versus Perfil. **Teste escrito, não executado.**
- Compatibilidade do formato Drupal 11.3+ `#type: component`, `#props`, `#slots` e `#attributes` revisada na documentação do Core. Não equivale a render testado, pois o checkout/Runtime não foi disponibilizado.
- Preparado plano de validação pré-S-R5 em `docs/HANDOFF-S-R4-S-R5-2026-10-25.md`: comandos, rotas, domínios, duas contas, avatar próprio, UI Patterns, regressão da Conta e browser Firefox ESR visível.
- Sem novos campos, schema, páginas, alias, Form API nem alterações na Conta legada. Não houve PHPUnit/Kernel/Functional, lint, PHPCS, Drupal Runtime, geckodriver ou deploy. Não homologar esta etapa sem os gates.

## 0.1.0-dev.21 — S-R4: Meu Perfil integrado aos SDCs (2026-10-25)

- `/studio/perfil` renderiza `aculta420:profile-editor`, `profile-identity`, `profile-bio` e `profile-social` via `#type: component` e slots do Drupal Core. Os quatro SDCs pertencem ao ACULTA420 (`0.7.0-dev.1`) e não decidem acesso ou armazenamento.
- O ACULTA User continua responsável por nickname/identidade via `AuthorIdentityResolver`, renderização dos cartões `AccountBioCard` e `SocialLinksCard`, links canônicos e `PublicProfileLink`. O avatar do próprio usuário passa pelo `aculta_image.render` mesmo quando oculto publicamente, após verificar autenticação e identidade do titular. O perfil público permanece filtrado por sua policy.
- O Studio preserva os valores read-only, estados não informados e cache tags dos cartões do User. Remove apenas botões modais originais na composição Studio, colocando links das rotas Form API dedicadas em slots de ações. A foto usa o modal AJAX existente; os outros editores continuam URLs normais. O legado em `/conta-legada` e os formulários originais não foram modificados.
- Testes Unit de composição, ACL, UID, slots, links, cache, cards de redes e biografia foram escritos, **não executados**. Pendem PHPUnit, lint, Kernel/Functional, SDC discovery/UI Patterns, no-JS, AJAX foto, duas contas, cache, Firefox ESR/X11 e Runtime. Não afirmar homologação, RC ou deploy.

## 0.1.0-dev.20 — S-R3-B3: pedidos, apoio e preferências na Conta Studio (2026-10-25)

- Três rotas canônicas exclusivas do ACCOUNT: `/studio/conta/pedidos`, `/studio/conta/apoio`, `/studio/conta/preferencias`. Todas exigem login, permissão `access aculta portal` e `no_cache`.
- Os pedidos delegam a `PortalController::orders()` e preservam filtro por `uid`, exclusão de rascunhos Commerce, pagamento e URL de detalhe com Entity Access original. Apoio delega a `SupportController::mySupport()`, mantendo regras de assinatura, ações originais e host SUPPORT para novas doações. Preferências executam o `AppearanceForm` original via `form_builder` no contexto Studio, sem recriar armazenamento, POST ou regras de modo de cor; abas internas do Portal ainda apontam às preferências proprietárias originais.
- O Studio controla só navegação contextual e composição. O `AccountExperienceProvider` abre essas três rotas, preservando `/conta-legada`, `/meu-apoio`, `/configuracoes`, checkout e actions de assinatura. Nenhum alias ou redirect temporário e nenhuma mudança em User/Perfil.
- Testes Unit de rotas, navegação, delegação do owner, links de assinatura intactos, Form API, 403 e 404 foram atualizados/escritos, mas **não executados** nesta rodada. PHP lint/PHPCS, PHPUnit, Kernel/Functional, gate de aliases/status, Runtime, duas contas, CSRF/no-JS e Firefox ESR/X11 seguem pendentes. Não houve merge/deploy.

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

## 0.1.0-dev.24 — entrada de gestão de planos Mercado Pago

- A experiência `mercado_pago_plans` oferece a administração no ACULTA Studio, delegando rota, operações remotas e autorização ao módulo Mercado Pago e à permissão proprietária `administrar doacoes`.
- Provider: 2 Unit / 19 assertions PASS. Suíte Studio: 62 testes / 502 assertions PASS. Fronteira módulo/tema e gate Portal Drupal 11 PASS (1.155 verificações) após alinhar contrato e testes à política de salvar config sem credenciais e negar edição direta. Firefox ESR/X11 verificou entrada, tela, labels acessíveis, caption da tabela e reflow em 390/1280px. O fluxo sandbox pelo formulário criou e atualizou um plano de teste; nenhum pagamento foi iniciado.

