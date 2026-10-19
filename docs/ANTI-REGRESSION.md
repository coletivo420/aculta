# Camadas anti-regressão

Data da revisão: 2026-10-07.

Este documento consolida regras duráveis que antes estavam espalhadas por
snapshots de fases e runbooks históricos.

Para o índice de invariantes de comportamento já aceito pelo responsável (com
caso de teste no navegador), consulte `docs/portal/ANTI-REGRESSAO.md`.

## Arquitetura e ownership

- Drupal Core/contrib são a fonte de verdade das capacidades que já fornecem.
- `aculta_portal` integra/orquestra; não cria storage paralelo sem necessidade.
- o tema funcional `aculta420` é apresentação; não decide autenticação, access,
  Domain, Commerce, LMS ou persistência.
- SDC recebe dados preparados; não consulta storage/serviços/entidades diretamente.
- não criar segunda suíte de design system concorrente ao Bootstrap5 + ACULTA420.

## Dependency Injection, hooks e padrão Drupal 11+

O padrão normativo do Portal é `docs/portal/DRUPAL-11-STANDARDS.md` e vale para desenvolvimento humano e por IA.

- baseline arquitetural do `aculta_portal`: Drupal Core 11.3+;
- preferir DI explícita em classes e não introduzir novos service locators `\Drupal::...` em `src/`;
- hooks runtime novos/refatorados do módulo usam OOP em `src/Hook/` com `#[Hook]` quando o Core instalado suporta o hook;
- hooks de install/update/post-update/schema e outros lifecycle que o Core exige procedurais permanecem procedurais;
- não recriar ou expandir `aculta_portal.module` para novos hooks runtime; a dívida procedural existente só pode diminuir;
- callbacks Form API novos/refatorados em Drupal 11.3+ usam callables suportados pelo `CallableResolver`, preferencialmente serviços DI; evitar `[$this, 'callback']` persistível;
- EntityQuery de conteúdo sempre declara conscientemente `accessCheck(TRUE)` ou `accessCheck(FALSE)`;
- subscribers implementam `EventSubscriberInterface` e novos registros usam `event_subscriber`; prioridade só muda com justificativa funcional e teste;
- `max-age: 0` não é substituto automático para contexts/tags corretos; access e cacheability acompanham a decisão funcional;
- compatibilidade formal com Drupal 12/13 só é declarada após validação de Core e contrib; deprecations são tratadas pelo substituto publicado, não por suposição;
- o gate progressivo `php scripts/validate-aculta-portal-drupal11.php` congela a dívida atual e impede sua expansão até as fases que a removerão.

Refactor de infraestrutura não pode alterar comportamento, access, cache, Domain, Commerce, autenticação ou segurança como efeito colateral.

## Domain

- a administração Drupal é única e centralizada no purpose `main`; subdomínios não possuem painel administrativo próprio;
- redirects intencionais entre purposes usam URL gerada por `DomainPurposeManager` + `TrustedRedirectResponse`; nunca usar `Symfony RedirectResponse` cru para atravessar host;
- subscribers que retargetam redirects antes do Core devem usar prioridade explícita, não empate de priority/module weight;
- sessão cross-subdomain exige `session.storage.options.cookie_domain` compartilhado por ambiente e `cookie_samesite: Lax` como baseline para preservar retorno OAuth top-level GET;
- AJAX/fetch do Portal permanece same-origin; CORS não é mecanismo de comunicação entre purposes;
- carrinho, checkout e pagamento são centralizados em `main`, inclusive os de apoio e assinatura: `commerce_cart.*`, `commerce_checkout.*`, `commerce_payment.checkout.*` e `commerce_payment.notify` usam a política única `DomainRoutePolicy`; só a escolha do valor de apoio (`aculta_donation.donation.default` e `.quick`) é do purpose `support` (decisão de 2026-10-17); não duplicar zona transacional por SHOP/COURSES/SUPPORT;
- catálogos/origem comercial e o `Add to cart` nativo podem permanecer em seus purposes; a UI de carrinho, checkout e pagamento fica em MAIN. Requests mutáveis das rotas centrais wrong-host falham fechado, sem bloquear o AddToCartForm da página de produto;
- navegação GET/HEAD para `/painel-administrativo/**` em purpose secundário é canonicalizada para MAIN via `DomainPurposeManager`; métodos mutáveis em host errado permanecem fail-closed, sem redirect cross-domain;
- wrong-purpose público/funcional continua 404; canonicalização administrativa não vira redirect genérico de Domain;
- a exceção `entity.user.edit_form` usada pelo reset one-time do Core em ACCOUNT deve ser preservada nos dois estágios do `DomainPurposeRequestSubscriber` e nunca é interpretada como painel administrativo de ACCOUNT;
- URLs e hosts especializados são resolvidos por purpose/Domain, nunca por
  hostname hardcoded;
- wrong-host deve falhar conforme a política definida, normalmente 404;
- canonical público usa `*.aculta.org`; aliases Homelab não viram canonical;
- geração de URL não pode mutar persistentemente a entidade Domain;
- FORUM só entra no mapa quando sua feature/configuração forem realmente ativadas;
- apresentação por subdomínio usa **Domain purpose**, nunca hostname, como chave;
- `DomainPurposeManager` permanece no `aculta_portal`; o tema não replica nem move
  essa resolução.

## Autenticação

- Drupal User continua fonte de verdade para conta, senha, sessão e status;
- Social Auth continua fonte dos vínculos OAuth;
- Google OAuth não é um submit alternativo de formulário Drupal;
- `username_enumeration_prevention` permanece no baseline;
- `user_registrationpassword` foi removido e não deve ser reintroduzido enquanto
  houver conflito de responsabilidade;
- Turnstile é o único challenge CAPTCHA e falha fechado;
- não criar fallback para Math CAPTCHA/reCAPTCHA/outro challenge;
- usuário `authenticated` usa `skip CAPTCHA`; anônimo protegido vê Turnstile;
- segredos OAuth/Turnstile/SMTP não entram no Git.
- ACULTA420 só apresenta formulários e estruturas de autenticação com classes semânticas neutras;
- Social Auth, disponibilidade de providers, destinos/callbacks OAuth, account linking e política CAPTCHA pertencem ao `aculta_portal`;
- Core/contrib implementa protocolos e providers; credenciais pertencem à infraestrutura e chegam por Key/environment;
- é proibido consultar Social Auth config, construir rotas OAuth, escolher CAPTCHA ou implementar Turnstile no tema/Twig;
- uma integração nova segue: Core/contrib → Portal → contrato neutro de apresentação → ACULTA420.

## E-mail e verificação de conta

- Email Confirmer/Change Mail são o baseline enquanto atenderem ao projeto;
- ferramenta própria de verificação só nasce se não houver alternativa
  Core/contrib adequada;
- nunca manter dois sistemas concorrentes de confirmação;
- futura solução deve cobrir contas não-OAuth e mudança de e-mail com token
  único/expirável, flood control e proteção contra enumeração.

## AJAX e Conta

- AJAX é progressive enhancement; rotas normais/full-page continuam válidas;
- não transformar a Conta em SPA paralela;
- OAuth, checkout/pagamento e confirmação externa de e-mail ficam fora de AJAX genérico;
- CEP mantém integração específica enquanto upstream for fonte;
- VVJT pode ser usado em Views específicas, não como engine da navegação da Conta;
- remover infraestrutura de `account-navigation.js` apenas por fluxo e após
  paridade de URL/history/focus/a11y/behaviors/fallback.

## Commerce

- Commerce é fonte de verdade para order/payment/checkout;
- não criar ledger paralelo de apoio;
- validar entity access antes de expor itens, totais ou pagamentos;
- credenciais Mercado Pago nunca entram em config versionada;
- não remover hardening custom do gateway sem substituto comprovado.

## LMS / Group

- Group/LMS são fontes de matrícula, membership, progresso e avaliação;
- não copiar esses estados para User/Profile como storage primário;
- links de curso respeitam purpose COURSES;
- presenter/SDC só apresenta dados autorizados.

## Editorial / Wiki

- Wiki continua Node + Taxonomy + Views + revisions/workflow;
- não substituir Views/entidades por storage custom;
- acesso a unpublished/revisions precisa preceder exposição de metadata;
- busca antiga só é removida depois de substituto com paridade funcional.

## Tema e CSS

- o único provider público do tema é `aculta420`; não manter alias, shim ou provider legado de compatibilidade;
- libraries usam `aculta420/*` e SDCs usam `aculta420:*`;
- o tema não chama services/classes de `aculta_portal`; o Portal prepara contexto e o tema apresenta;
- o shell multidomínio usa um único contrato `domain_presentation`, preparado por um builder/presenter autoritativo no Portal; não espalhar `match ($purpose)` por hooks, controllers ou templates;
- ACULTA420 pode derivar de `domain_presentation.identity` somente o fallback mínimo realmente consumido (`label` + `home_url`); `purpose` e `logo_alt` não são expostos/repurposed sem consumidor real, e identidade parcial/ausente nunca dispara lookup funcional, hostname inference ou branch visual no tema;
- o branding Drupal existente continua prioritário durante 0.2-B.3: `page.header` é sempre preservado e o fallback textual só entra na ausência do plugin `system_branding_block`; não inferir branding por truthiness/“vazio visual” do render array;
- o analyzer `Aculta420ShellContractAnalyzer` é a fonte única das checagens estáticas da fronteira B.4 e deve ser reutilizado por fixtures e gate Runtime; ele bloqueia qualquer namespace/service `aculta_portal`, APIs Domain, service locator/request/hostname, hosts de ambiente hardcoded e branches concretas por purpose em Twig/PHP/JS nos dois sentidos; novas formas de leakage/branch exigem fixture negativa correspondente;
- a ponte oficial é `PortalHooks::preprocessPage()` → variável neutra `domain_presentation` → `ThemeHooks::preprocessPage()`/Twig; módulos preprocessam antes do tema e essa ordem é parte do contrato;
- `domain_presentation` separa identidade escalar (props-ready) de regiões renderizáveis (slots-ready); menu/actions/brand media não viram HTML/string prematuramente;
- logo, título, home URL, navegação e ações por purpose chegam ao tema como valores simples/render arrays já resolvidos pelo Portal; nunca escolher por hostname em Twig/PHP do tema;
- Domain ID, hostname, aliases, `DomainInterface`, storage, negotiator e services do Portal não atravessam a fronteira para Twig/SDC;
- access é resolvido antes da apresentação e cacheability é acumulada com `CacheableMetadata` e aplicada/mesclada ao render tree; não criar campo ad hoc `cacheability` no view-model e o tema não corrige metadata funcional perdida;
- o Portal não instancia SDC `aculta420:*`; o tema é quem mapeia dados neutros para props/slots e escolhe SDC/Bootstrap, evitando dependência inversa do módulo funcional no provider visual;
- navegação usa Menu API/MenuLinkTree sempre que possível para preservar access, cache contexts/tags e invalidação de `system.menu.*`;
- nunca passar entidade `Domain` diretamente para Twig/SDC;
- branding específico de purpose é opcional: fallback ACULTA e, depois, título textual;
- todos os purposes compartilham a mesma arquitetura de shell; variam dados, não um
  header paralelo por subdomínio;
- light/dark/auto troca tokens e assets compatíveis, não geometria ou markup do shell;
- light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura;
- color mode no ACULTA420 é uma variação de tokens, não uma variação de layout;
- componentes não conhecem o color mode; seletores dark são permitidos somente em `aculta420/css/tokens.css`;
- o gate reconhece seletores dark em pseudo-classes funcionais, operadores de atributo CSS e flags `i`/`s`, inclusive em `tokens.css`; analisa recursivamente somente predicados de ternário Twig/JavaScript, labels de `switch` PHP aninhado em sintaxe com chaves ou `endswitch`, condições de `match`, e condições `if`/labels `switch` JavaScript balanceados; resultados e comentários não devem gerar finding;
- o gate detecta escritas `dataset`, chamadas `setAttribute()` opcionais/regulares e atribuições diretas `className` a classes de modo; valida mapeamentos semânticos de cores Bootstrap e borda translúcida em light/dark, tokens RGB e superfícies dark; aceita cores numericamente equivalentes, mas exige slash-alpha depois dos três canais e rejeita mistura de separadores CSS legacy/modernos;
- o gate detecta escritas `dataset` e chamadas `setAttribute()` opcionais ou regulares em atributos de modo mesmo com valor dinâmico; valida mapeamentos semânticos de cores base Bootstrap e borda translúcida em light/dark, tokens RGB e pares, rejeita alpha inválido, aliases, ciclos, duplicatas e referências ausentes, e calcula contraste com alpha composto; representações numericamente equivalentes são aceitas, mas mudar cor e RGB juntos não contorna o contrato e a sintaxe CSS não pode misturar vírgulas legacy com alpha por barra;
- fixtures também cobrem arms de objeto em ternários JavaScript, predicados Twig externos a ternários, `getColorScheme()`, regex literals em condições, case labels PHP encerrados por `;`, `?.`/`??`, atribuições compostas de `className`, todas as classes de modo protegidas e seletores de atributo parciais; a união de tokens declarados deve resolver em ambos os modos;
- o validador inspeciona JS inline de Twig, pula regex literals ao balancear corpos `switch`, reconhece `VAR()` CSS sem distinção de caixa, compara `--aculta-surface-page-rgb` à cor de página e rejeita qualquer regra fora dos dois blocos de tokens permitidos em `tokens.css`;
- também valida style blocks Twig para seletores e cores literais, persistência color-mode em scripts inline, storage por métodos e propriedades, inicializadores explícitos, leituras de atributos protegidos e mutações de `classList`/`className` inclusive `replace`, template literal e getter dinâmico; uso de storage não relacionado ao modo permanece permitido, controles Twig `{%- ... -%}` não contornam branches, ternários preservam escapes e `;` internos a strings, regex após keywords JS permanece literal, marcadores `/*` dentro de strings CSS não iniciam comentários e paths Windows são comparados após normalização de separadores/case; statements CSS externos (ex.: `@import`) são inválidos em `tokens.css`;
- a checagem de cor CSS remove comentários e cobre hex, funções modernas de cor (incluindo `oklch()`, `lab()`, `color()` e `color-mix()`) e nomes CSS em declarações de propriedades color-bearing; o escopo é intencionalmente delimitado e testado, não um parser completo de valores CSS;
- testes do gate devem incluir regressões negativas e exemplos positivos para cada sintaxe suportada, sem tocar no Runtime; roots absolutos POSIX, Windows drive-letter e UNC são válidos, mas traversal `..` é rejeitado;
- uma variante futura de asset de logo por modo preserva espaço, dimensões e layout;
- novos componentes consomem semantic tokens quando a função já existe; palette primitives ficam centralizadas;
- purpose não define cor no tema e nunca é inferido por hostname;
- não adicionar color-mode JS, seletor ou persistência antes da fase prevista;
- não criar SDC apenas para encapsular classe Bootstrap simples sem contrato reutilizável comprovado;
- não criar segunda engine de navegação quando Bootstrap já fornece Collapse/Offcanvas;
- Bootstrap5 continua infraestrutura estrutural/comportamental;
- não reimplementar behavior Bootstrap/VVJ;
- não converter Twig em massa para SDC;
- não manter `css/style.css`, `css/responsive.css` ou outro catch-all residual no tema; CSS deve ter ownership explícito;
- mover CSS para SDC apenas quando ownership exclusivo do componente estiver comprovado;
- regra antiga só sai após paridade visual, mobile, teclado/foco, AJAX
  reattachment e fallback;
- CSS de admin/diagnóstico pode permanecer no módulo.
- CSS de autenticação pode estilizar formulários Core e classes neutras do Portal, como `.aculta-login__divider` e `.aculta-auth-provider`; não pode depender de seletores internos de Social Auth, Google ou CAPTCHA/Turnstile.

## Configuração e segredos

- Configuration Sync representa configuração aprovada, não um dump cego do Runtime;
- não executar/exportar config em massa apenas para “limpar drift” sem classificar paths;
- segredos permanecem em environment/Key;
- não versionar credenciais reais ou de teste;
- alterações de módulos/config exigem documentação correspondente.
- block placements do ACULTA420 devem usar região declarada e manter `plugin`,
  `settings.id` e a dependência de conteúdo alinhados ao UUID existente.

## Lifecycle / updates

- update hooks históricos não são reescritos só para “limpar” o arquivo;
- mudança em `.install` exige fresh-install e upgrade-path tests;
- migrações de dados devem ser idempotentes/resumíveis quando aplicável;
- não remover update de migração crítica sem provar caminho de upgrade.

## Assets

- ausência de referência por grep não prova que um asset é morto;
- coleções grandes só são removidas após inventário de Runtime/conteúdo;
- assets de conteúdo devem tender a Media/File/CDN, não payload PHP do módulo,
  quando houver migração segura.
- bibliotecas externas usadas pelo Webform devem seguir as versões e os paths
  do Webform instalado, ser reconstruíveis por `composer install` e não ser
  copiadas manualmente; ao atualizar Webform, revisar novamente seu contrato
  `composer.libraries.json`/`webform.libraries.yml` e não reintroduzir
  bibliotecas deprecated sem consumidor ativo comprovado.

## Cache, access e privacidade

- access vem antes de metadata privada;
- cacheability preserva contexts + tags + max-age necessários;
- não resolver performance de dados privados com cache compartilhado inseguro;
- testar User A/User B em Profile, Address, apoio, cursos e participação.

## Ambientes

- Homelab: Apache + PHP-FPM + MariaDB;
- produção: Hostinger Cloud Startup + LiteSpeed + PHP + MariaDB;
- Nginx não é baseline;
- custom SQL precisa preservar portabilidade entre os bancos suportados (DBTNG-2);
- produção não é ambiente de experimento/desenvolvimento.
