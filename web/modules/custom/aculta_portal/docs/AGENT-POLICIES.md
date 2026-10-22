# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## CTools como implementação do Portal

CTools é uma implementação suportada do projeto, não uma dependência a evitar
por padrão. Antes de criar configuração ou código para uma necessidade que ele
cobre, leia [`web/modules/custom/aculta_portal/docs/CTOOLS.md`](web/modules/custom/aculta_portal/docs/CTOOLS.md) e verifique o código
instalado e a documentação upstream. Use `ctools_views` para blocos de Views
quando for necessário configurar por colocação, por exemplo quantidade de itens
e pager; use a API de plugins/condições do CTools para integrações contextuais
compatíveis. Prefira essa implementação existente a recriar no Portal controles
equivalentes.

CTools não é um construtor geral de formulários nem substitui Views, Forum,
Form API, Webform, Entity Access, políticas de domínio ou cache. Mantenha filtros
de acesso e domínio fixos na View. Não libere `configure_filters`,
`disable_filters`, `hide_fields` ou `sort_fields` em blocos sem revisão explícita
de segurança e governança editorial. A integração documentada do Fórum
complementa a listagem nativa; não a substitui.

Regras resumidas:
- Core/contrib continuam fonte de verdade; não criar storage paralelo quando a capacidade já existir;
- nomes machine name de campos e IDs de bundles Drupal devem ter no máximo 32 caracteres; validar o comprimento antes de criar configuração ou instalar entidades, pois o Core rejeita valores maiores;
- preferir dependency injection em classes; não introduzir novos service locators `\Drupal::...` em `src/`;
- hooks runtime novos/refatorados usam OOP `#[Hook]` quando suportado pelo Core instalado; lifecycle permanece procedural quando exigido;
- tratar como deprecada no projeto qualquer prática runtime legada contrária ao padrão moderno Drupal 11+ (por exemplo `#[FormAlter]`, hook procedural migrável, novo callback global ou novo service locator), mesmo quando ainda tolerada por compatibilidade; exceções precisam de evidência da API upstream;
- EntityQuery declara `accessCheck(TRUE|FALSE)` conscientemente;
- Form API em Drupal 11.3+ prefere callbacks resolvidos pelo `CallableResolver` e serviços DI;
- subscribers usam `EventSubscriberInterface` e o tag `event_subscriber`; prioridades funcionais não mudam por estética;
- Render API, access e cacheability fazem parte do contrato funcional;
- nenhuma mudança é chamada de “Drupal 12/13 ready” sem verificar Core, change records e módulos contrib;
- execute `php scripts/validate-aculta-portal-drupal11.php` em mudanças do Portal e elimine, não expanda, a dívida técnica registrada pelo gate.

A documentação deve registrar o motivo arquitetural das regras, não apenas sua forma.

## Uploads de arquivos enviados por usuários

- A política reutilizável para uploads de usuários pertence ao módulo central
  `aculta_portal`; consumidores devem usar `aculta_portal.user_upload_policy`
  e não duplicar listas de extensões, limites ou validadores.
- A configuração central mantém uma política global e abas de override por
  consumidor integrado. Cada consumidor pode herdar a regra global ou definir
  opções próprias sob a mesma governança administrativa.
- O Portal fornece a política comum; cada domínio continua dono da associação
  do arquivo à entidade Core correspondente, publicação, retenção, acesso e
  cache. Respeite as regras privadas do conteúdo hospedeiro e `aculta_image`.
- Wiki e LMS devem integrar-se à mesma política central depois do Fórum. A
  integração visual e funcional deve reutilizar `aculta_gallery`, `aculta_image`,
  Document Viewer e componentes ACULTA pertinentes, sem criar leitores ou
  storage paralelos.
- Contrato vigente e sequência: [`docs/portal/USER-UPLOADS.md`](docs/portal/USER-UPLOADS.md).

## Fronteira de autenticação e anti-bot

- Core/contrib implementam protocolos e desafios; `aculta_portal` mantém política e integração; ACULTA420 cuida apenas da apresentação.
- Não mover OAuth/CAPTCHA para o tema, consultar configuração de integração no tema, nem adicionar dependências ou segredos de autenticação ao tema.
- Credenciais ficam no arquivo de segredos do servidor, lidas pelo `aculta_portal.secrets_manager`; a exceção de envio de e-mail no banco do Runtime está na regra de segredos acima. Consulte `docs/integrations/AUTHENTICATION.md` e `docs/integrations/CAPTCHA.md`.

- Para detalhes do contrato neutro, Bootstrap/SDC, tokens semânticos, modos de cor e gates, consulte `docs/portal/COMPONENT-DESIGN-SYSTEM.md` e `web/themes/custom/aculta420/docs/design-system.md`.
- Novos componentes usam tokens semânticos e SDC quando houver contrato reutilizável; não criam lógica de domínio no tema nem seletores de modo fora da política documentada.
- Antes de criar markup/CSS, verifique Bootstrap, SDC e componentes existentes. Não adotar uma suíte concorrente sem decisão do responsável.
- O Portal prepara dados, URLs, acesso e cache; ACULTA420 seleciona e renderiza a apresentação. O Portal não referencia componentes SDC do tema.
- Gates de tema e da fronteira Portal–tema permanecem obrigatórios conforme os documentos e scripts de validação correspondentes.

### Integração total orientada por contexto

- Antes de criar uma capacidade, faça inventário de integração no Core/contrib,
  `aculta_portal`, todos os seus submódulos (inclusive aninhados) e `aculta420`.
  Identifique dono dos dados e da política, APIs/serviços existentes, campos e
  entidades, eventos, Views, formatters, cartões, componentes UI, cache, acesso,
  Domain purpose e consumidores atuais. Consulte código instalado, contratos,
  roadmaps, CHANGELOGs e frentes/PRs abertas antes de propor código.
- Reutilize funções existentes quando seus contratos forem compatíveis. Não
  duplique entidades, storage, processamento, política de upload, renderers,
  notificações, cartões ou componentes visuais. Quando uma lacuna justificar
  capacidade nova, construa-a sobre os contratos existentes e declare o dono,
  consumidores e fronteira. Reuso não pode contornar access/cache, moderação,
  privacidade, domínio ou política do módulo responsável.
- Integre por contexto: conecte conteúdo ao purpose/Domain, entidade hospedeira
  e estado de publicação/moderação corretos. Mantenha isolados dados e ações de
  outros purposes, grupos/turmas, Wiki, Revista, LMS, Fórum e Loja quando os
  contratos exigirem essa separação. Dependências opcionais permanecem
  unidirecionais, não quebram o consumidor quando desabilitadas e não criam
  ciclos entre módulos.
- Para apresentação, procure primeiro cartões SDC, slots, UI Patterns/UI Styles,
  Bootstrap, tokens, diálogos Core, componentes e padrões do tema existentes.
  Use VVJ somente em Views e finalidades compatíveis com o engine (por exemplo,
  VVJB para carrossel editorial e VVJS para sequência fotográfica); não o trate
  como substituto geral de SDC, formulário, view mode ou navegação. Componentes
  próprios devem ser neutros e reutilizáveis por sessão/contexto quando houver
  contrato real, preservar render arrays e metadados de access/cache e deixar a
  escolha visual ao ACULTA420.
- Ao concluir uma fase, audite a matriz de integrações afetadas e o histórico de
  trabalho para localizar capacidades anteriores ainda sem consumidor, contrato
  quebrado ou dívida explicitamente pendente. Integre apenas o que cabe na fase;
  registre dependências, lacunas e sequência restante no roadmap/CHANGELOG dono.
  Não misture alterações de outras frentes nem marque como integrada uma
  capacidade apenas por haver código instalado.

## Política de domínio administrativo

- O painel único pertence ao purpose `main`. Canonicalização e exceções de rotas estão em `docs/portal/ADMIN-DOMAIN-POLICY.md`.
- Requests mutáveis administrativos, de checkout ou de pagamento no purpose errado falham fechados; não redirecionar escrita entre hosts. Requests públicos de purpose errado continuam 404.
- Redirects intencionais entre hosts usam `DomainPurposeManager` e `TrustedRedirectResponse`; não usar `Symfony RedirectResponse` cru para outro host nem listas paralelas de rotas.
- Requisições AJAX/fetch do Portal são same-origin. Não habilitar CORS para transportar sessão ou formulários entre purposes.
- Carrinho, checkout e pagamento pertencem ao purpose `main`; a seleção de valor de apoio pertence a `support`. Regras e exceções vigentes: `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md` e `docs/portal/PAYMENT-DOMAIN-POLICY.md`.
- ACULTA420 não participa da política de domínio; ele consome a apresentação preparada pelo Portal.

## Integração transversal dos módulos ACULTA (regra permanente)

- Antes de implementar uma capacidade, inventariar os módulos ACULTA, submódulos,
  configuração e tema que já atendem ao mesmo domínio. Consultar seus contratos,
  APIs, componentes SDC, cartões, view-models, catálogos e políticas antes de
  criar uma solução local.
- Reutilizar o dono já estabelecido: `aculta_image` para ingestão e política de
  imagens; `aculta_gallery` para `media_gallery`, galerias e PhotoSwipe;
  `aculta_pdf_viewer` para leitura de PDF; `aculta_user` para identidade e
  autoria; `aculta_messenger` para avisos; ACULTA420 para apresentação. Cada
  consumidor integra os contratos existentes e preserva acesso, cache, domínio,
  publicação e retenção do seu próprio contexto.
- Não duplicar storage, upload, processamento, cartões, identidade visual ou
  permissões quando já houver componente dono. Capacidades novas devem ter
  contrato neutro e reutilizável no módulo apropriado; comportamento específico
  fica no submódulo consumidor.
- Toda mudança de integração registra a relação entre produtor e consumidor,
  a política de acesso/publicação e os testes de cada fronteira. Ao concluir uma
  frente, pesquisar integrações anteriores pendentes e atualizar seus roadmaps.
- No ACULTA Forum, fechar primeiro upload contextual de imagens, integração
  Gallery e Office (DOCX e planilhas, conforme extensões habilitadas). A
  visualização de PDF pelo ACULTA Document Viewer é a última etapa dessa frente;
  não duplicar leitor ou conversão de PDF no Fórum.

## Código no Aculta Portal, Design no ACULTA420 (regra permanente)

> **Código no Aculta Portal, Design no ACULTA420.**

- `aculta_portal` implementa função, integração, dados, access, cache e comportamento. ACULTA420 implementa apresentação. O Portal não emite classes de design nem referencia SDCs do tema; o tema não implementa rotas, serviços ou regras de negócio do Portal.
- A apresentação cruza a fronteira por dados neutros e tokens `data-portal-ui`; acesso e cache devem continuar corretos sem depender do tema. Contratos: `docs/portal/FRONTEIRA-MODULO-TEMA.md`, `docs/portal/DOMAIN-PRESENTATION-CONTRACT.md` e `docs/portal/COMPONENT-DESIGN-SYSTEM.md`.
- Antes de alterar um módulo, consulte `docs/portal/MAPA-MODULO-TEMA.md`, a documentação/código do tema e `docs/portal/TOKENS-E-ATRIBUTOS.md`. Atualize o mapa no mesmo commit quando a contraparte mudar; o gate é `php scripts/validate-module-theme-map.php`.
- Gates de fronteira e arquitetura do Portal: `php scripts/validate-module-theme-boundary.php` e `php scripts/validate-aculta-portal-drupal11.php`. O inventário de helpers e integrações aplicadas está em `docs/portal/IMPLEMENTATIONS.md`.
- Alterar `web/themes/custom/aculta420/**` exige autorização explícita na solicitação. Uma tarefa de Portal, por si só, não autoriza mudanças no tema; quando autorizadas, mantenha cada camada no seu dono e atualize o mapa.

## Papéis e hierarquia (regra permanente)

Não crie hierarquia paralela nem conceda acesso por nome de papel. Para funcionalidade nova, conceda a permissão adequada e valide por `hasPermission()`/Entity Access. A configuração e as decisões vigentes estão em `config/sync/aculta_portal.role_hierarchy.yml` e `docs/portal/PAPEIS-PARTICIPACAO.md`; não duplique aqui a lista de papéis. Mudanças na hierarquia exigem avaliação de herança, atualização da decisão e execução de `php scripts/validate-role-hierarchy.php`.
