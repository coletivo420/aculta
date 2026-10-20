# Fronteira módulo x tema (aculta_portal x ACULTA420)

> **Código no Aculta Portal, Design no ACULTA420.**

Regra permanente (AGENTS.md, seção "Código no Aculta Portal, Design no ACULTA420"). Gate:
`php scripts/validate-module-theme-boundary.php`.

## Regra

- **Aculta Portal** (`web/modules/custom/aculta_portal/**`) = funções: rotas, controllers, forms, `#ajax`, validação
  (Número/S/N, CEP), endpoints, serviços, access, dados, cache, checkout panes, Commerce/Shipping, JS de
  comportamento e traduções. O JS usa `data-portal-*` e `data-aculta-*`, nunca classes de design.
- **ACULTA420** (`web/themes/custom/aculta420/**`) = design: templates Twig de apresentação, CSS, layout, cores,
  larguras, ícones, animações, estilos de mensagens e caixas, preprocess de apresentação.
- O módulo **não** carrega CSS, **não** emite classes de design e **não** referencia o tema. Entrega dados
  (listas, links, estado, textos). Os templates do módulo são **fallback neutro**, sem classes, para o site continuar
  funcionando sem o ACULTA420.
- O tema **não** tem rota, `form_alter`, serviço, `Drupal::`, nem classe ou biblioteca do módulo.
- Quem implementa uma função no Aculta Portal pode alterar o ACULTA420 na mesma entrega (autorização do responsável),
  cada parte no seu dono.
- Contrato entre os dois: `data-portal-account-*`, `data-portal-cart-status*`, `data-aculta-*` e nomes de campo do
  Drupal. O tema sobrescreve templates pelo nome; o módulo não sabe qual tema está ativo.

## Por que

Se o módulo carrega aparência, trocar o tema deixa a conta sem layout ou exige editar código do módulo. Se o tema tem
rota ou validação, a regra de negócio some junto com o visual. A separação permite testar o módulo com outro tema e
manter o design em um só lugar.

## De → para (revisão de 2026-10-12)

| Antes (módulo) | Depois | Dono |
| --- | --- | --- |
| `templates/aculta-portal-shell.html.twig` com classes `portal-account*` | Design em `templates/aculta-portal-shell.html.twig` (tema); fallback neutro no módulo | Tema (design), módulo (fallback) |
| `AccountShellBuilder`: HTML de menu, sanfona e Sair montado em strings PHP com classes | Dados (`menu_items`, `accordion_items`, `logout`); markup no tema | Módulo (dados), tema (markup) |
| `js/account-navigation.js`: seletores `.portal-account__menu`, `__item`, `__summary`, `is-active` | `[data-portal-account-menu]`, `[data-portal-account-item]`, `[data-portal-account-summary]`; estado ativo só por `aria-current` | Módulo (comportamento) |
| `js/account-navigation.js`: `is-active` alternado no JS | removido; `aria-current="page"` (o CSS do tema já reconhece) | Módulo (comportamento), tema (estilo) |
| `js/search-preview.js`, `js/account-terms-modal.js`: sem classes de design | sem mudança | Módulo |
| `templates/aculta-portal-account-terms-modal.html.twig` com classes | Design no tema; fallback sem classes no módulo | Tema / módulo |
| `templates/aculta-portal-photo-editor.html.twig` com classes e ícone SVG | Design e ícone no tema; fallback sem classes e sem SVG no módulo | Tema / módulo |
| `templates/portal-cart-status.html.twig` (caixa "Item adicionado com sucesso.") com classes | Design no tema; fallback sem classes no módulo | Tema / módulo |
| `js/photo-editor`: classe `is-enhanced` | atributo `data-portal-enhanced`; CSS do tema atualizado | Módulo / tema |
| `css/account-portal.css`, `css/cep-address.css`, `css/account-terms-modal.css`, `css/requirements-report.css`, `css/support.css` (no módulo) | `css/components/portal-*.css` no tema (carregados pelo `global`) | Tema |
| `js/cep-address.js`: ordem do CEP, colunas `row`/`col-auto` do Número/S/N/Complemento | `js/address-layout.js` (tema); comportamento (busca, validação, S/N) segue no módulo | Tema (layout), módulo (comportamento) |
| `src/Cart/AddToCartAjax.php`: HTML da caixa e cópia do badge do carrinho | Template do módulo sem classes + design no tema; o badge do tema recebe `data-aculta-cart-count`/`-trigger` | Módulo (dados), tema (design) |
| `components/patterns/cart-offcanvas` (tema): badge só com contagem > 0 | badge sempre no DOM com `data-count`; CSS do tema esconde em zero | Tema |
| `src/Deployer/DeployerStatusController.php`: `#attached` de biblioteca só com CSS | removido; CSS vem pelo tema | Módulo / tema |

## Verificação

- **Marcação da Minha conta (HTTP):** oito páginas (Minha conta, Dados, Endereço, Conexões, Segurança, Meus cursos,
  Configurações, Meu apoio) comparadas antes e depois da migração, após normalizar o que mudou de propósito
  (`data-portal-account-*` acrescentados; `is-active` trocado por `aria-current`; `is-enhanced` trocado por atributo)
  e os valores de runtime (tokens de logout, valor do carrinho, hashes de CSS/JS, JSON de settings, BigPipe).
  **Resultado: 8 de 8 idênticas.**
- **Fluxos com outro tema:** Olivero como tema padrão no servidor de testes. Ver `docs/portal/TESTES-NAVEGADOR.md`, seção L.
- **Navegador com captura de tela:** não executado nesta rodada (sem ferramenta de navegador disponível no ambiente).

## Mecanismo (como o módulo entrega design sem classes)

- O módulo marca o elemento com `data-portal-ui="token [token2]"`. O token é um nome semântico (ex.: `account-avatar`,
  `button-primary`, `requirements-status status-ok`), nunca uma classe de design.
- O tema traduz os tokens em classes em `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` (`TOKENS`). Os hooks
  de pré-processamento (`container`, `html_tag`, `item_list`, `input`, `form_element`, `image` e os templates
  `aculta_portal_*`) leem os atributos do elemento (inclusive os que o núcleo ainda não mesclou) e trocam os tokens.
- Elementos de texto ou link do módulo usam o template neutro `aculta_portal_element` (`#tag`, `#value`,
  `#element_attributes`). Links com token passam por `Drupal\aculta_portal\Render\PortalLinkElement::build()`, que
  preserva o `href` e a cacheabilidade de `Url::toString(TRUE)`. O gerador de links do núcleo não tem template de tema,
  por isso o link não recebe o token diretamente.
- `hook_link_alter` não serve ao tema: hooks de alteração não rodam para temas.
- O gate confere que todo token usado pelo módulo existe em `TOKENS`.

## Resultado da migração desta rodada

- **Classes de design no módulo: 0** (era 91 atributos de classe em PHP e 28 tokens de classe em templates/JS).
- Exceções de propósito (não são design): `use-ajax` (o Drupal usa essa classe para abrir diálogos e formulários por
  AJAX), `is-active` (estado da aba atual, que o tema também estiliza) e marcação semântica sem classe (`<p>`,
  `<strong>`, `<small>`, `<br>`).
- Migrados nesta rodada (módulo → tema): `PortalController` (avatar, identidade, cartões de pedidos/pagamentos, segurança,
  navegação de Meus dados, botões), `AccountCoursesController` (cursos, botões), `WikiController`, `CoursesController`,
  `SearchUtility`/`SearchPreviewController`/`SearchPageController`/`SearchPopupForm` (busca, prévia, popup, dicas),
  `SecretsController` e `SecretsForm*` (tela de segredos), `PortalRequirementsController` e `DeployerStatusController`
  (relatórios), `SupportForm`, `RegistrationController`, `PortalHooks` e `FormHooks` (login, aviso de e-mail),
  `StreetNumberField` (Número e S/N), `AccountTermsAcceptForm`, `DomainPresentationBuilder` (imagem da marca),
  breadcrumb do AJAX (`AccountAjaxMainContentRenderer`, agora `item_list` com tokens) e o gatilho da busca (template
  `portal-search-trigger` com ícone no tema).
- JS: o breadcrumb da conta é selecionado por `[data-portal-breadcrumb]` (antes `ol.breadcrumb`, classe do tema).

## Verificação

- **Marcação da Minha conta (HTTP, antes × depois):** 9 páginas da conta idênticas e 3 páginas de checkout/loja idênticas
  depois de normalizar: ordem de atributos e de classes, valores de runtime (tokens, carrinho, hashes de CSS/JS,
  settings, BigPipe, ids do carrossel) e as alterações intencionais (`is-active` como estado, `aria-current`).
- **Roteiro de aceitação:** 10/10 com ACULTA420 e 10/10 com Olivero como tema padrão (o módulo funciona sem o tema).
- **Navegador com captura de tela:** não executado (sem ferramenta de navegador no ambiente).

## Limites conhecidos

- `src/Controller/PortalRequirementsController.php` (verificação de instalação do tema) ainda referencia o tema,
  listado em `$allowedThemeReferences` do gate. É diagnóstico de ambiente, não dependência visual.

## Marca de domínio (contrato neutro)

- O Portal decide a marca do domínio e envia só a chave neutra em `domain_presentation.regions.brand_media`:
  `['#brand_key' => 'wiki420', '#alt' => título]`. Nenhum caminho de tema no módulo.
- O tema transforma a chave em imagem em `ThemeHooks::brandMediaRender()` (asset em `assets/branding/wiki420/web/`).
- Sem tema que trate a chave, o cabeçalho mostra só o título (fallback neutro).
- Não há alter de módulo para tema: temas não recebem `hook_*_alter` de módulos; o dado vai pelo `domain_presentation`.

## Teste de compatibilidade de tema (2026-10-13)

- Tema padrão trocado temporariamente para Claro (Core) no servidor de testes, e restaurado para aculta420 ao fim.
- Com Claro: painel 200 com gatilho e avatar; popup de foto como modal centralizado (centro em 640×400 de uma tela 1280×800),
  com prévia, texto, campo de envio e botões. Upload → recorte → aplicar: uma única requisição de recorte, navegação ao
  painel, sem loop e sem erro de JavaScript. "Remover foto" → 303 e avatar do acervo.
- Com aculta420 (padrão restaurado): logo do wiki voltou pelo tema (`brandMediaRender`).
- Templates do módulo com fallback neutro: `aculta-portal-shell`, `aculta-portal-account-terms-modal`,
  `aculta-portal-photo-editor`, `portal-cart-status`, `portal-search-trigger` e `portal-element` (estrutural).

## Perfil público e ações da Minha conta (regra de separação, 2026-10-13)

Política: toda ferramenta do `aculta_user` funciona com outro tema. O módulo entrega estrutura, dados e URLs; o ACULTA420 entrega a aparência.

| Parte | Dono | Exemplo |
| --- | --- | --- |
| Dados, privacidade, URL do perfil (`main`), ordem e textos | `aculta_user` | `PublicProfileController`, `SocialLinksCard`, `AccountDashboardHooks` (ação "Ver Perfil") |
| Marcação neutra com `data-portal-ui` (tokens semânticos) | `aculta_user` | `account-view-profile`, `public-profile-link-instagram` |
| Classes, bordas, cores, tamanhos e ícones | ACULTA420 | `PortalUiHooks::TOKENS`, `css/components/public-profile.css`, `portal-account.css` |

Cartão de autoria usa contrato neutro próprio: `aculta_user` registra o hook
`aculta_user_author_card` com campos preparados e cacheáveis; o template do
ACULTA420 compõe o SDC `aculta420:author-card`. O módulo não conhece o SDC nem
emite classes de design. Ações chegam como render arrays montados por um
consumidor que já avaliou acesso; imagem e visibilidade seguem `aculta_image` e
`PublicProfilePolicy`.

Sem o ACULTA420, os elementos continuam funcionando: links com `href` válido, textos e foto. Só a aparência volta ao padrão do tema ativo. Não há classes de design (`btn`, `bi-*`, `portal-*`) no módulo; o gate `validate-module-theme-boundary.php` confere.

## ACULTA Messenger (auditoria de ownership, 2026-10-14)

| Camada | Responsabilidade | Arquivos atuais |
| --- | --- | --- |
| Core/contrib | Fonte de verdade de conversas, threads, bloqueios, mensagens/notificações, assinaturas, Site Alerts, Flags e workflow | Private Message, Message, Message Notify, Message Subscribe, Site Alert, Flag e entidades Core |
| `aculta_portal` (módulo principal) | Extensão genérica do shell Minha Conta: alters para seções e rotas-filhas, sem vocabulário ou dependência do Messenger | `src/AccountShellBuilder.php` |
| `aculta_messenger` (submódulo) | Regras, rotas, permissões, Domain Purpose, access/cache, contadores, preferências, leitura individual, filas e integração com APIs upstream; entrega render arrays neutros | `modules/aculta_messenger/src/**`, `*.routing.yml`, `*.services.yml`, `config/install/**` |
| ACULTA420 | Mapeamento de dados neutros para SDCs, templates Twig, layout da barra, ícones Bootstrap, CSS e tokens visuais; não decide access nem consulta storage | `src/Hook/ThemeHooks.php` (apenas apresentação), `src/Hook/PortalUiHooks.php`, `templates/page.html.twig`, `templates/private-message-thread.html.twig`, `templates/site-alert.html.twig`, `components/**`, `css/components/dialog.css`, `css/components/portal-account.css` |

O submódulo fornece dados da barra e estados (`MessengerBarHooks`); o tema converte esses valores em SDCs. O hook genérico `aculta_portal_account_sections_alter` pertence ao Portal, e a seção Mensagens é fornecida pelo submódulo. O tema envolve render arrays autorizados em SDCs, preservando Form API e saída upstream. O submódulo não referencia `aculta420:*`, classes de design, CSS ou templates do tema.

Na navegação da barra, `aculta_messenger` entrega links com fallback canônico,
endpoints de fragmento same-origin e comandos Drupal AJAX com os render arrays
autorizados. ACULTA420 fornece a gaveta, os estilos e o comportamento de
apresentação; o clique na conversa substitui o corpo da gaveta e a seta solicita
a lista novamente. Nenhum modal de conversa ou transporte cross-domain é usado.

Não foi necessário mover lógica de negócio: a inspeção confirmou os donos acima. Foi corrigida uma referência de documentação de tema que havia entrado no registry PHP do Portal; metadados de módulo agora apontam para a documentação neutra em `docs/portal/IMPLEMENTATIONS.md`.

Verificação específica: `php scripts/validate-module-theme-boundary.php` PASS (973 verificações); `php scripts/validate-aculta420-sdc-schemas.php` PASS (24 componentes, 365 verificações); `php scripts/validate-aculta-portal-drupal11.php` PASS (768 verificações).

## Documentos e gates complementares (2026-10-14)

- Correspondência de cada módulo e submódulo customizado, e de cada módulo contrib habilitado, com os templates, CSS, componentes e tokens do tema: `docs/portal/MAPA-MODULO-TEMA.md`. Gate: `php scripts/validate-module-theme-map.php`.

- Tokens `data-portal-ui` e atributos `data-portal-*`, `data-aculta-*`, `data-dialog-*`: `docs/portal/TOKENS-E-ATRIBUTOS.md`. Gate: `php scripts/validate-portal-ui-docs.php` (todo token e atributo usado precisa estar documentado).
- Popup padrão (modal do Drupal) e exceções explícitas: `docs/portal/POPUP-PADRAO.md`. Gate: `php scripts/validate-portal-popup-rule.php` (sem `<dialog>` nativo, sem tamanho de diálogo no módulo, `data-dialog-type` só `modal`).

## Varredura de barreiras (2026-10-14)

Escopo: os 27 arquivos alterados nesta branch (`feat/revista-visual-importacao-bloco-sativa`, contra `origin/main`),
agrupados por camada. Esta seção registra a delimitação e o resultado; não altera a regra acima.

### Camadas e dono

| Camada | Arquivos desta branch | Dono | Regra verificada |
| --- | --- | --- | --- |
| Tema `aculta420` | `templates/node--article--full`, `templates/node--blog--full`, `components/patterns/revista-publication`, `templates/comment--aculta-discussion-post`, `css/components/revista-post.css`, `css/components/discussion.css` | ACULTA420 | Só apresentação. O slot `discussion` recebe `content.field_aculta_discussion` preparado pelo Drupal; sem `Drupal::`, sem domínio, host ou decisão de access no tema. |
| Submódulo `aculta_magazine` | Atual: Comment direto na publicação, `Forum/PublicationTypeConverter`, `Forum/LegacyPublicationTopicLookup`, `Hook/ForumTopicHooks` (link legado); alvo: remover leitores dos vínculos Forum no corte | Portal (função) | Estado e destino em `aculta_forum/docs/MIGRATION-REMOVAL.md`; access/cache ficam no Portal. |
| Submódulo `aculta_forum` | Discussion Context API, Node/Comment/Taxonomy/Views, Search API público restrito a `aculta_forum_topic`, ferramentas de moderação/movimento e regras de access/cache | Portal (função) | Runtime é dono de contexto, índice, query, acesso e cache; ACULTA420 apresenta Views e comentários pelos componentes existentes; nunca possui cartão de autoria nem SDC do tema; LMS entra por adapter opcional. |
| Submódulo `aculta_user` | Atual: `DiscussionCommentAuthorHooks`, Author Card API, `PublicProfilePolicy`, `PublicProfileLink`, AvatarPool, LegacyAuthor; hooks de `forum`/`comment_forum` permanecem apenas durante a retirada | Portal (identidade/acesso/dados) | `aculta_forum` e os Comments da Revista consomem cartões, sem builder nem consulta privada própria; ACULTA420 apresenta. Ver `aculta_user/docs/AUTHOR-CARDS.md`. |
| Scripts de conteúdo | `scripts/content/editorial/*` (importação e JSON das notícias) | Projeto (ferramenta operacional) | Fora de `src/`. Usa `\Drupal::` no mesmo padrão dos scripts de conteúdo existentes (`link-project-art.php`). |
| Documentação | `docs/portal/ACULTA-USER-PLAN.md` (E3.1), `aculta_magazine/docs/README.md`, esta seção | Projeto | Descreve o comportamento implementado e marca as regras propostas. |

### Resultado da varredura nesta branch

- `validate-module-theme-boundary`: PASS (848 verificações; 0 tokens de apresentação e 0 classes de design no módulo).
- Linhas acrescentadas em `src/` do módulo com `\Drupal::`: 0.
- Linhas acrescentadas no tema com classe de módulo, referência a `aculta_portal`, hex/rgb ou host fixo: 0.
- `validate-aculta420-design-foundations`: PASS (1736 verificações). `validate-aculta420-shell-contract`: PASS (11). `validate-aculta420-sdc-schemas`: PASS (14 componentes).
- `validate-aculta-portal-drupal11`: PASS (696). `validate-portal-ui-docs`: PASS (167 tokens). `validate-portal-popup-rule`: PASS.
- `validate-public-slugs`: PASS. `validate-environment-addresses`: PASS. `validate-admin-domain-policy`: PASS (2781). `validate-payment-domain-policy`: PASS (49).

### Falhas que já existiam (não causadas por esta branch)

Os arquivos abaixo não foram alterados nesta branch. Duas delas (`validate-root-policy` e `validate-shop-captcha`)
foram confirmadas no `origin/main` limpo.

- `validate-root-policy`: raiz de MAIN ou SUPPORT redirecionada. FAIL também no `origin/main`.
- `validate-shop-captcha`: `TurnstileToggle::setEnabled()` não pula os formulários da loja. FAIL também no `origin/main`.
- `validate-aculta420-foundation`: allowlist de overrides Twig, por `templates/login-with.html.twig` (já no main), e DT-T19.
- `validate-admin-cleanup`: "Unexpected retained Views". Lê `config/sync`, que esta branch não alterou.
- `validate-aculta-portal-conta-root`: espera o diretório `aculta_portal/css`, que não existe.
- `validate-institution`: depende de `Symfony\Component\Yaml`, indisponível no PHP puro.
- `validate-secrets-contract`: a chave `aculta_document_encryption_key` consta como aprovada no contrato.
- `validate-cross-domain-request-policy`: `js/account-navigation.js` não contém `credentials: 'same-origin'`. Arquivo não alterado nesta branch.
- `validate-domain-presentation-contract`: objeto cruzando a fronteira Portal → tema no purpose `wiki`. `DomainPresentationBuilder` não alterado nesta branch.

Gates que exigem execução local com contexto próprio (saem com "Local only"): `validate-final-contact`,
`validate-final-drupal`, `validate-final-sitemap`, `validate-home-carousel`, `validate-portal-commerce-security`.

### Pendências desta sessão

- Comando Drush `aculta:revista:convert-type` não registrado. A troca de tipo funciona pelo serviço.
- Apelido do perfil do uid 1 é `pessoa-1` (provisório). Aparece na caixa de autoria do Bloco Sativa. Decisão do responsável.
- Tópico 89 (despublicado), de uma importação já apagada, ocupa o endereço do tópico do Bloco (saiu com `-0`).
- Scripts de importação usam `\Drupal::`. Fora de `src/`, no padrão dos scripts de conteúdo, mas registrado aqui.

### Atualização (2026-10-14, correções)

Depois da varredura acima, foram corrigidos os itens abaixo. Os que não estão nesta lista continuam pendentes.

- `root-policy`, `shop-captcha`, `conta-root`, `admin-cleanup`, `aculta420-foundation`: gates desatualizados em relação
  ao comportamento documentado (ver o histórico dos commits `e55c15e` e `99d0ed5`).
- `domain-presentation`: o `Attribute` da dica da Wiki sai da presentation; é criado no preprocess de
  `aculta_portal_element` (`aculta_portal` 0.4.0-dev.15). Gate: PASS (127).
- `secrets-contract`: o contrato JSON listava `aculta_document_encryption_key` duas vezes (corrigido) e o gate não
  conhecia `aculta_document_encryption_key` nem `aculta_id_cpf_fingerprint`, ambas documentadas em `SECRETS.md`.
  Gate: PASS. O provisionamento em produção segue pendente, como documentado.
- `institution`: gate de Drupal. Passa pelo Drush (`drush scr`); o script agora avisa a forma de execução quando rodado
  pelo PHP puro, com código de saída 2.
- `cross-domain-request-policy`: corrigido. A asserção procurava a forma negativa da checagem de origem duas vezes;
  agora verifica a regra onde ela existe: `isInternalLink()` compara a origem (forma positiva) e o handler de clique
  sai antes de `preventDefault()` quando o link é de outra origem. Gate: PASS (18). A exigência de same-origin no AJAX
  continua verificada em `search-preview.js`.
- Gates "Local only" continuam dependendo do ambiente local.
