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
