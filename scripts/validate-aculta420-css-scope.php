<?php

/**
 * @file
 * Gate ADR-054 P7: escopo de carregamento de CSS.
 *
 * Verifica que CSS de aplicação não voltou ao `global-styling` sem decisão e
 * que as bibliotecas contextuais declaram o arquivo certo. Neste pacote cobre
 * `statistics.css`, carregado apenas nas rotas `aculta_statistics.*`.
 *
 * Uso: php scripts/validate-aculta420-css-scope.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$theme = $root . '/web/themes/custom/aculta420_v2';
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
  $checks++;
  if (!$condition) {
    $failures[] = $message;
  }
};

$libraries = Yaml::parseFile($theme . '/aculta420_v2.libraries.yml');
$global = array_keys($libraries['global-styling']['css']['theme'] ?? []);
$portalExtrasPath = $theme . '/src/styles/portal-ui-extras.css';
$portalExtrasCss = is_file($portalExtrasPath) ? (string) file_get_contents($portalExtrasPath) : '';
$addressFieldsCss = (string) file_get_contents($theme . '/src/styles/address-fields.css');
$portalUiMapCss = (string) file_get_contents($theme . '/src/styles/portal-ui-map.css');
$portalRequirementsCss = (string) file_get_contents($theme . '/components/portal-requirements/portal-requirements.css');
$portalRequirementsController = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php');
$addressFields = array_keys($libraries['address-fields']['css']['theme'] ?? []);
$accountAddressForm = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Account/AccountAddressForm.php');
$assert(!in_array('src/styles/portal-ui-extras.css', $global, TRUE), 'portal-ui-extras.css voltou ao global-styling.');
$assert(!is_file($portalExtrasPath), 'portal-ui-extras.css permaneceu depois de migrar seus consumidores.');
$assert(in_array('src/styles/address-fields.css', $addressFields, TRUE), 'A biblioteca address-fields não declara sua folha.');
$assert(str_contains($addressFieldsCss, '.portal-street-number input.form-text'), 'address-fields.css perdeu o estilo do campo de número.');
$assert(str_contains($accountAddressForm, "'aculta420_v2/address-fields'"), 'AccountAddressForm não anexa o CSS contextual ao widget com Número/S/N.');
$loginPage = array_keys($libraries['login-page']['css']['theme'] ?? []);
$loginPageCss = (string) file_get_contents($theme . '/src/styles/login-page.css');
$supportPageCss = (string) file_get_contents($theme . '/components/support-form/support-form.css');
$supportComponent = (string) file_get_contents($theme . '/components/support-form/support-form.component.yml');
$searchPage = array_keys($libraries['search-page']['css']['theme'] ?? []);
$searchPageCss = (string) file_get_contents($theme . '/src/styles/search.css');
$supportForm = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php');
$presentationHooks = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$assert(!in_array('src/styles/login-page.css', $global, TRUE), 'login-page.css voltou ao global-styling.');
$assert(in_array('src/styles/login-page.css', $loginPage, TRUE), 'A biblioteca login-page não declara sua folha.');
$assert(str_contains($presentationHooks, "=== 'user.login'"), 'A biblioteca login-page não está restrita à rota user.login.');
$assert(str_contains($loginPageCss, 'body.path-entrar .aculta-login__reset-link'), 'login-page.css perdeu os estilos de acesso.');
$assert(!isset($libraries['support-page']), 'A biblioteca antiga support-page permaneceu após a migração para SDC.');
$assert(str_contains($presentationHooks, "=== 'aculta_portal.support_form'"), 'O SDC support-form não está restrito à rota do formulário de apoio.');
$assert(str_contains($presentationHooks, "'support-form', 'support-form'"), 'A rota de apoio não envolve o Form API no SDC support-form.');
$assert(str_contains($supportComponent, 'support-form.css'), 'O SDC support-form não declara seu asset local.');
$assert(str_contains($supportPageCss, '.aculta-support fieldset'), 'O CSS local do SDC support-form perdeu os estilos do formulário.');
$assert(!str_contains($portalExtrasCss, '[data-portal-ui="support-form"]'), 'O seletor de apoio permaneceu no CSS global.');
$assert(!str_contains($supportForm, "'aculta_portal/support'"), 'SupportForm mantém referência à biblioteca inexistente aculta_portal/support.');
$assert(!in_array('src/styles/search.css', $global, TRUE), 'search.css voltou ao global-styling.');
$assert(in_array('src/styles/search.css', $searchPage, TRUE), 'A biblioteca search-page não declara search.css.');
$assert(($libraries['search-page']['dependencies'] ?? []) === ['aculta420_v2/global-styling'], 'A biblioteca search-page deve depender de global-styling.');
$assert(str_contains($presentationHooks, "=== 'aculta_portal.search'"), 'A biblioteca search-page não está restrita à rota de busca.');
$assert(str_contains($searchPageCss, '.aculta-search__results'), 'search.css perdeu estilos da página central de busca.');
$assert(!str_contains($portalUiMapCss, '.aculta-portal-requirements'), 'Layout do relatório de requisitos permaneceu no mapa global.');
$assert(str_contains($portalRequirementsCss, '.portal-requirements .aculta-portal-requirements__cep'), 'O SDC portal-requirements perdeu estilos de seção.');
$assert(str_contains($portalRequirementsController, "'aculta420_v2:portal-requirements'"), 'O relatório de requisitos não compõe seu SDC.');
$statistics = array_keys($libraries['statistics']['css']['theme'] ?? []);

$assert(!in_array('src/styles/statistics.css', $global, TRUE), 'statistics.css voltou ao global-styling.');
$assert(in_array('src/styles/statistics.css', $statistics, TRUE), 'A biblioteca statistics não declara src/styles/statistics.css.');
$assert(is_file($theme . '/src/styles/statistics.css'), 'O arquivo statistics.css não existe.');
$assert(($libraries['statistics']['dependencies'] ?? []) === ['aculta420_v2/global-styling'], 'A biblioteca statistics deve depender de global-styling.');

$assert(!isset($libraries['studio']), 'A biblioteca contextual studio deve ser substituída pelo SDC studio-shell.');
$assert(!is_file($theme . '/src/styles/studio.css'), 'studio.css permaneceu após migrar o escopo para o SDC.');
$assert(!in_array('src/styles/studio-identity.css', $global, TRUE), 'studio-identity.css voltou ao global-styling.');
$assert(!is_file($theme . '/src/styles/studio-identity.css'), 'studio-identity.css reapareceu sem consumidor que aplique html.studio-identity.');
$studioShellMetadataPath = $theme . '/components/studio-shell/studio-shell.component.yml';
$studioShellMetadata = is_file($studioShellMetadataPath) ? Yaml::parseFile($studioShellMetadataPath) : [];
$assert(isset($studioShellMetadata['assets']['css']['component']['studio-shell.css']), 'O SDC studio-shell não declara seu CSS local.');
$assert(is_file($theme . '/components/studio-shell/studio-shell.css'), 'O CSS do SDC studio-shell não existe.');
$studioFormMetadataPath = $theme . '/components/studio-form/studio-form.component.yml';
$studioFormMetadata = is_file($studioFormMetadataPath) ? Yaml::parseFile($studioFormMetadataPath) : [];
$assert(isset($studioFormMetadata['assets']['css']['component']['studio-form.css']), 'O SDC studio-form não declara seu CSS local.');
$assert(is_file($theme . '/components/studio-form/studio-form.css'), 'O CSS do SDC studio-form não existe.');
$studioShellCss = (string) file_get_contents($theme . '/components/studio-shell/studio-shell.css');
$assert(!str_contains($studioShellCss, '.studio__main form'), 'A skin de formulários ainda está no CSS genérico studio-shell.');
$studioSectionHeaderMetadataPath = $theme . '/components/studio-section-header/studio-section-header.component.yml';
$studioSectionHeaderMetadata = is_file($studioSectionHeaderMetadataPath) ? Yaml::parseFile($studioSectionHeaderMetadataPath) : [];
$assert(isset($studioSectionHeaderMetadata['assets']['css']['component']['studio-section-header.css']), 'O SDC studio-section-header não declara seu CSS local.');
$assert(is_file($theme . '/components/studio-section-header/studio-section-header.css'), 'O CSS do SDC studio-section-header não existe.');

$statisticsCss = (string) file_get_contents($theme . '/src/styles/statistics.css');
$portalMapCss = (string) file_get_contents($theme . '/src/styles/portal-ui-map.css');
$assert(str_contains($statisticsCss, '.aculta-statistics__controls'), 'statistics.css perdeu os seletores movidos do mapa.');
$assert(!str_contains($portalMapCss, 'aculta-statistics'), 'portal-ui-map.css ainda contém seletores de estatísticas; o CSS continua global.');

$hook = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$assert(str_contains($hook, 'aculta420_v2/statistics'), 'O hook não anexa a biblioteca statistics.');
$assert(str_contains($hook, "'aculta_statistics.dashboard'") && str_contains($hook, "'aculta_statistics.area'"), 'O hook não restringe o CSS às rotas do painel de estatísticas.');
$assert(str_contains($hook, "'aculta420_v2:studio-shell'"), 'O hook não envolve as rotas do Studio no SDC studio-shell.');
$assert(str_contains($hook, "str_starts_with(\$routeName, 'aculta_studio.')"), 'O SDC studio-shell não está restrito às rotas aculta_studio.*.');
$assert(str_contains($hook, "'aculta420_v2:studio-form'"), 'O hook não envolve formulários Studio no SDC studio-form.');
$assert(str_contains($hook, "\$routeName === 'aculta_lms.course_create'"), 'O SDC studio-form não contempla a criação de curso LMS.');
$assert(str_contains($hook, "'studio-section-header',\n          'studio-section-header'"), 'O hook não envolve os cabeçalhos de seção Marley no SDC proprietário.');

$publicProfileCss = (string) file_get_contents($theme . '/components/public-profile-page/public-profile-page.css');
$publicProfileComponent = (string) file_get_contents($theme . '/components/public-profile-page/public-profile-page.component.yml');
$articleAuthorsCss = (string) file_get_contents($theme . '/components/article-authors/article-authors.css');
$articleAuthorsComponent = (string) file_get_contents($theme . '/components/article-authors/article-authors.component.yml');
$assert(!isset($libraries['public-profile']), 'A biblioteca antiga public-profile permaneceu após a migração para SDC.');
$assert(str_contains($publicProfileComponent, 'public-profile-page.css'), 'O SDC public-profile-page não declara sua folha local.');
$assert(!in_array('src/styles/article-authors.css', $global, TRUE), 'article-authors.css voltou ao global-styling.');
$assert(!is_file($theme . '/src/styles/article-authors.css'), 'A folha antiga de autoria permaneceu fora do SDC.');
$assert(!str_contains($publicProfileCss, '.portal-article-author'), 'public-profile.css ainda contém regras de autoria de artigo.');
$assert(str_contains($articleAuthorsCss, '.portal-article-author'), 'O SDC article-authors perdeu as regras de autoria de artigo.');
$assert(str_contains($articleAuthorsComponent, 'article-authors.css'), 'O SDC article-authors não declara seu CSS local.');
$assert(str_contains($hook, "'aculta_user.perfil_publico'"), 'O SDC public-profile-page não está restrito à rota do perfil público.');
$assert(str_contains($hook, "'public-profile', 'public-profile-page'"), 'A rota do perfil público não envolve o render array no SDC proprietário.');

$accountTermsComponent = (string) file_get_contents($theme . '/components/account-terms-modal/account-terms-modal.component.yml');
$accountTermsCss = (string) file_get_contents($theme . '/components/account-terms-modal/account-terms-modal.css');
$accountTermsTwig = (string) file_get_contents($theme . '/components/account-terms-modal/account-terms-modal.twig');
$accountTermsTemplate = (string) file_get_contents($theme . '/templates/aculta-portal-account-terms-modal.html.twig');
$accountCss = (string) file_get_contents($theme . '/src/styles/portal-account.css');
$assert(str_contains($accountTermsComponent, 'account-terms-modal.css'), 'O SDC account-terms-modal não declara seu CSS local.');
$assert(str_contains($accountTermsCss, '.account-terms-modal .portal-terms-modal__documents'), 'O SDC account-terms-modal perdeu o estilo da lista de documentos.');
$assert(!str_contains($accountCss, '.portal-terms-modal__documents'), 'O estilo do modal de termos permaneceu na folha geral da Conta.');
$assert(str_contains($accountTermsTemplate, "include('aculta420_v2:account-terms-modal'"), 'O template do Portal não compõe o SDC account-terms-modal.');
$assert(str_contains($accountTermsTwig, "setAttribute('id', 'aculta-account-terms')") && str_contains($accountTermsTwig, "setAttribute('data-dialog-title', title)") && str_contains($accountTermsTwig, "setAttribute('hidden', 'hidden')"), 'O SDC account-terms-modal perdeu atributos consumidos pelo diálogo.');

$accountDashboardComponent = Yaml::parseFile($theme . '/components/account-experience-dashboard/account-experience-dashboard.component.yml');
$studioShellCss = (string) file_get_contents($theme . '/components/studio-shell/studio-shell.css');
$accountDashboardCss = (string) file_get_contents($theme . '/components/account-experience-dashboard/account-experience-dashboard.css');
$studioSectionHeaderCss = (string) file_get_contents($theme . '/components/studio-section-header/studio-section-header.css');
$portalUiHooks = (string) file_get_contents($theme . '/src/Hook/PortalUiHooks.php');
$learnerDashboardComposer = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_studio/src/Learning/LearnerDashboardComposer.php');
$learnerDashboardSdc = Yaml::parseFile($theme . '/components/lms-learning-dashboard/lms-learning-dashboard.component.yml');
$learnerDashboardTwig = (string) file_get_contents($theme . '/components/lms-learning-dashboard/lms-learning-dashboard.twig');
$assert(str_contains($learnerDashboardComposer, "'#component' => 'aculta420_v2:lms-learning-dashboard'"), 'O composer do estudante deixou de emitir a nova página SDC.');
$assert(!str_contains($learnerDashboardComposer, 'aculta_portal_component') && !str_contains($learnerDashboardComposer, 'aculta-learning-home'), 'O composer do estudante reteve wrappers ou marcadores da página legada.');
$assert(isset($learnerDashboardSdc['slots']['courses'], $learnerDashboardSdc['slots']['resume'], $learnerDashboardSdc['slots']['certificates']), 'O SDC novo do estudante perdeu os slots de cursos/jornada/certificados.');
$assert(str_contains($learnerDashboardTwig, "include('aculta420_v2:heading'") && str_contains($learnerDashboardTwig, "include('aculta420_v2:empty-state'"), 'A página nova deixou de compor heading/empty-state por SDC.');
$assert(!str_contains($portalUiHooks, "'studio-fidelity-scope'"), 'O token de escopo legado do dashboard LMS permaneceu no mapa.');
$assert(!str_contains($studioShellCss, '[data-portal-ui="studio-heading"]'), 'O estilo de studio-heading permaneceu no CSS geral do shell.');
$assert(str_contains($studioSectionHeaderCss, '[data-portal-ui="studio-heading"]'), 'O SDC studio-section-header perdeu o estilo do título Marley.');
$assert(!str_contains($studioShellCss, '.studio__panel'), 'O seletor studio__panel permaneceu no CSS geral do shell.');
$assert(!str_contains($portalUiHooks, "'studio-panel' => ['studio__panel'"), 'O mapa voltou a emitir classe de painel genérica para studio-panel.');
$assert(!str_contains($studioShellCss, '--aculta-studio-fidelity-row-gap'), 'O alias de espaçamento da Conta permaneceu no shell do Studio.');
$assert(str_contains($accountDashboardCss, 'gap: var(--spacing-lg)'), 'O espaçamento de identidade da Conta não foi internalizado no SDC.');
$lmsBaseCss = (string) file_get_contents($theme . '/src/styles/base.css');
$lmsDashboardCss = (string) file_get_contents($theme . '/components/lms-learning-dashboard/lms-learning-dashboard.css');
$lmsJourneyCss = (string) file_get_contents($theme . '/components/lms-learning-journey/lms-learning-journey.css');
$lmsRoomCss = (string) file_get_contents($theme . '/components/lms-learning-room/lms-learning-room.css');
$assert(str_contains($lmsBaseCss, 'font-family: var(--font-family-base)') && str_contains($lmsBaseCss, 'overflow-wrap: anywhere'), 'base.css deixou de fornecer tipografia base às páginas LMS.');
$assert(!str_contains($lmsDashboardCss, '[data-aculta-learning-home] {'), 'Tipografia herdada da home LMS permaneceu duplicada no SDC.');
$assert(!str_contains($lmsJourneyCss, 'font-family: var(--font-family-base)'), 'Tipografia base permaneceu duplicada na jornada LMS.');
$assert(!str_contains($lmsRoomCss, 'font-family: var(--font-family-base)'), 'Tipografia base permaneceu duplicada na sala LMS.');
$accountDashboardTemplate = (string) file_get_contents($theme . '/templates/aculta-studio-account.html.twig');
$assert(isset($accountDashboardComponent['libraryOverrides']['js']['../../js/account-carousel.js']), 'O SDC account-experience-dashboard não declara o JS local do carrossel.');
$assert(($accountDashboardComponent['libraryOverrides']['dependencies'] ?? []) === ['core/drupal', 'core/once'], 'O carrossel do dashboard não declara dependências de behavior.');
$assert(!str_contains($accountDashboardTemplate, "attach_library('aculta420_v2/account-carousel')"), 'O template legado ainda anexa a biblioteca account-carousel inexistente.');
$connectionsBlockTwig = (string) file_get_contents($theme . '/components/studio-connections-block/studio-connections-block.twig');
$connectionsPageTwig = (string) file_get_contents($theme . '/components/studio-connections-page/studio-connections-page.twig');
$assert(isset($libraries['studio-connections-block']) === FALSE && !str_contains($connectionsBlockTwig, "attach_library('aculta420_v2/studio-connections-block')"), 'studio-connections-block ainda anexa biblioteca inexistente.');
$assert(isset($libraries['studio-connections-page']) === FALSE && !str_contains($connectionsPageTwig, "attach_library('aculta420_v2/studio-connections-page')"), 'studio-connections-page ainda anexa biblioteca inexistente.');

$lessonPageCss = (string) file_get_contents($theme . '/components/lms-lesson-page/lms-lesson-page.css');
$baseCss = (string) file_get_contents($theme . '/src/styles/base.css');
$assert(str_contains($baseCss, ':focus-visible'), 'base.css perdeu o indicador global de foco visível.');
$assert(!str_contains($lessonPageCss, ':focus-visible'), 'lms-lesson-page.css duplicou o indicador global de foco.');
$curriculumComponent = (string) file_get_contents($theme . '/components/lms-course-curriculum/lms-course-curriculum.component.yml');
$curriculumTwig = (string) file_get_contents($theme . '/components/lms-course-curriculum/lms-course-curriculum.twig');
$curriculumCss = (string) file_get_contents($theme . '/components/lms-course-curriculum/lms-course-curriculum.css');
$resourceHook = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseResourcesHooks.php');
$presentationHook = (string) file_get_contents($theme . '/src/Hook/PagePresentationHooks.php');
$assert(str_contains($curriculumComponent, 'resources:'), 'O SDC lms-course-curriculum não declara o slot de recursos.');
$assert(str_contains($curriculumTwig, '{{ resources }}'), 'O SDC lms-course-curriculum não imprime o slot de recursos.');
$assert(str_contains($resourceHook, "'aculta_lms_course_builder_resources'"), 'O hook do LMS não expõe o render array de recursos.');
$assert(str_contains($presentationHook, "'#slots']['resources']"), 'O hook do tema não move os recursos para o slot do currículo.');
$eventLayoutCss = (string) file_get_contents($theme . '/components/event-page-layout/event-page-layout.css');
$assert(str_contains($eventLayoutCss, '[data-portal-ui~="event-supporters"]') && str_contains($eventLayoutCss, '.event-supporters'), 'O SDC event-page-layout perdeu o estilo contextual de apoiadores.');
$assert(str_contains($eventLayoutCss, '[data-portal-ui~="event-press-quotes"]') && str_contains($eventLayoutCss, '.event-press-quotes'), 'O SDC event-page-layout perdeu o estilo contextual de citações.');
$assert(!str_contains((string) file_get_contents($theme . '/src/styles/events.css'), '.event-supporters'), 'Estilos de apoiadores permaneceram na folha geral de Events.');

$messengerComponent = (string) file_get_contents($theme . '/components/messenger-trigger/messenger-trigger.component.yml');
$messengerCss = (string) file_get_contents($theme . '/components/messenger-trigger/messenger-trigger.css');
$messengerBar = (string) file_get_contents($theme . '/components/multidomain-bar/multidomain-bar.twig');
$assert(!in_array('src/styles/messenger-drawer.css', $global, TRUE), 'messenger-drawer.css voltou ao global-styling.');
$assert(!is_file($theme . '/src/styles/messenger-drawer.css'), 'A folha antiga da gaveta permaneceu fora do SDC.');
$assert(str_contains($messengerComponent, 'messenger-trigger.css'), 'O SDC messenger-trigger não declara o CSS da gaveta.');
$assert(str_contains($messengerBar, "{% if messages_url %}"), 'A barra institucional inclui mensagens sem URL/integração disponível.');
$assert(str_contains($messengerBar, "include('aculta420_v2:messenger-trigger'"), 'A barra não compõe a gaveta pelo SDC messenger-trigger.');
$assert(str_contains($messengerCss, '.aculta-messenger-notice-origin'), 'O SDC messenger-trigger perdeu os estilos dos grupos de avisos.');
$assert(!str_contains($portalExtrasCss, '.aculta-messenger-notice-origin'), 'Estilos de avisos do Messenger voltaram ao portal-ui-extras global.');
$authDialogCss = (string) file_get_contents($theme . '/components/auth-dialog/auth-dialog.css');
$multidomainBarCss = (string) file_get_contents($theme . '/components/multidomain-bar/multidomain-bar.css');
$assert(!str_contains($portalExtrasCss, '.aculta-login-popup__links'), 'Estilos duplicados do popup de autenticação voltaram ao CSS global.');
$assert(!str_contains($portalExtrasCss, 'navbar-nav'), 'Seletor de navegação legado voltou ao CSS global.');
$assert(str_contains($authDialogCss, '[data-portal-ui="login-popup-links"]'), 'O SDC auth-dialog perdeu os estilos dos links de autenticação.');
$assert(str_contains($multidomainBarCss, '[data-portal-ui="login-popup-links"]'), 'O SDC multidomain-bar perdeu os estilos dos links de autenticação.');
$assert(str_contains($multidomainBarCss, '.aculta-institution-bar--account .aculta-account-menu a[data-portal-ui="account-home"]'), 'O SDC multidomain-bar perdeu o destaque da conta no domínio account.');

$salesCss = (string) file_get_contents($theme . '/components/sales-panel/sales-panel.css');
$salesComponent = (string) file_get_contents($theme . '/components/sales-panel/sales-panel.component.yml');
$salesController = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/SalesReportController.php');
$donationController = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/src/Controller/DonationReportController.php');
$assert(!str_contains($portalExtrasCss, '.aculta-sales-panel'), 'Estilos dos relatórios voltaram ao portal-ui-extras global.');
$assert(str_contains($salesCss, '.aculta-sales-panel__table'), 'O SDC sales-panel perdeu os estilos locais das tabelas.');
$assert(str_contains($salesComponent, 'sales-panel.css'), 'O SDC sales-panel não declara seu CSS local.');
$assert(str_contains($salesController, "'aculta420_v2:sales-panel'"), 'O relatório de vendas não compõe a casca pelo SDC.');
$assert(str_contains($donationController, "'aculta420_v2:sales-panel'"), 'O relatório de doações não compõe a casca pelo SDC.');

$relatedContentCss = (string) file_get_contents($theme . '/components/related-content/related-content.css');
$relatedContentComponent = (string) file_get_contents($theme . '/components/related-content/related-content.component.yml');
$contentCss = (string) file_get_contents($theme . '/src/styles/content.css');
$relatedContentBlock = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php');
$assert(!str_contains($contentCss, '.aculta-related-content'), 'Os seletores do bloco relacionado voltaram ao content.css global.');
$assert(str_contains($relatedContentCss, '.aculta-related-content__item'), 'O SDC related-content perdeu o CSS dos itens.');
$assert(str_contains($relatedContentComponent, 'related-content.css'), 'O SDC related-content não declara sua folha local.');
$assert(str_contains($relatedContentBlock, 'aculta420_v2:related-content'), 'O bloco RelatedContent não compõe o SDC de apresentação.');

$imageAuditCss = (string) file_get_contents($theme . '/components/image-audit-report/image-audit-report.css');
$imageAuditComponent = (string) file_get_contents($theme . '/components/image-audit-report/image-audit-report.component.yml');
$imageAuditController = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_image/src/Controller/ImageAuditController.php');
$assert(!str_contains($portalExtrasCss, '.aculta-image-audit'), 'Estilos do relatório de imagens voltaram ao portal-ui-extras global.');
$assert(str_contains($imageAuditCss, '.aculta-image-audit'), 'O SDC image-audit-report perdeu o estilo contextual.');
$assert(str_contains($imageAuditComponent, 'image-audit-report.css'), 'O SDC image-audit-report não declara seu CSS local.');
$assert(str_contains($imageAuditController, "'aculta420_v2:image-audit-report'"), 'O relatório administrativo de imagens não compõe o SDC.');

$consentComponent = (string) file_get_contents($theme . '/components/consent-banner/consent-banner.component.yml');
$consentCss = (string) file_get_contents($theme . '/components/consent-banner/consent-banner.css');
$consentHook = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_statistics/src/Hook/ConsentBannerHooks.php');
$consentJs = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_statistics/js/consent.js');
$assert(!str_contains($portalExtrasCss, '.aculta-consent'), 'Estilos de consentimento voltaram ao portal-ui-extras global.');
$assert(str_contains($consentComponent, 'consent-banner.css'), 'O SDC consent-banner não declara seu CSS local.');
$assert(str_contains($consentCss, '.aculta-consent'), 'O SDC consent-banner perdeu os estilos.');
$assert(str_contains($consentHook, "'aculta420_v2:consent-banner'"), 'O hook de consentimento não compõe o SDC.');
$assert(str_contains($consentJs, '[data-consent-banner]'), 'O JavaScript do consentimento não encontra o marcador do SDC.');

$secretCss = (string) file_get_contents($theme . '/components/secret-reveal/secret-reveal.css');
$secretComponent = (string) file_get_contents($theme . '/components/secret-reveal/secret-reveal.component.yml');
$secretController = (string) file_get_contents($root . '/web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php');
$secretAdmin = array_keys($libraries['secrets-admin']['css']['theme'] ?? []);
$secretAdminCss = (string) file_get_contents($theme . '/src/styles/secrets-admin.css');
$assert(str_contains($presentationHooks, "'aculta_portal.secrets_'"), 'A biblioteca secrets-admin não está limitada às rotas do módulo de segredos.');
$assert(!str_contains($portalExtrasCss, '.aculta-secret-popup__value'), 'Estilos da revelação de segredos voltaram ao CSS global.');
$assert(!str_contains($portalExtrasCss, '[data-portal-ui="secret-dialog"]'), 'O dimensionamento do modal de segredos voltou ao CSS global.');
$assert(str_contains($secretComponent, 'secret-reveal.css'), 'O SDC secret-reveal não declara seu CSS local.');
$assert(str_contains($secretCss, '.aculta-secret-popup__value'), 'O SDC secret-reveal perdeu o estilo do valor.');
$assert(str_contains($secretController, "'aculta420_v2:secret-reveal'"), 'O controller de segredos não compõe o SDC.');
$assert(in_array('src/styles/secrets-admin.css', $secretAdmin, TRUE), 'A biblioteca secrets-admin não declara sua folha.');
$assert(str_contains($secretAdminCss, '.ui-dialog:has(.aculta-secret-dialog'), 'A folha contextual não dimensiona os formulários do modal.');

$accountDialogsCss = (string) file_get_contents($theme . '/src/styles/account-dialogs.css');
$accountDialogs = array_keys($libraries['account-dialogs']['css']['theme'] ?? []);
$surfaceHooks = (string) file_get_contents($theme . '/src/Hook/SurfaceSettingsHooks.php');
$assert(!str_contains($portalExtrasCss, '.ui-dialog:has'), 'Seletores globais de modais Drupal voltaram a portal-ui-extras.');
$assert(in_array('src/styles/account-dialogs.css', $accountDialogs, TRUE), 'A biblioteca account-dialogs não declara sua folha.');
$assert(str_contains($accountDialogsCss, '.ui-dialog:has(.aculta-photo-dialog'), 'A folha account-dialogs perdeu os estilos de foto/biografia.');
$assert(str_contains($accountDialogsCss, '.ui-dialog:has(.aculta-social-link-dialog'), 'A folha account-dialogs perdeu os estilos de links sociais.');
$assert(str_contains($surfaceHooks, "'aculta420_v2/account-dialogs'"), 'O domínio account não anexa account-dialogs.');
$assert(str_contains($presentationHooks, "'aculta420_v2/account-dialogs'"), 'As rotas do Studio não anexam account-dialogs.');

$revistaCss = (string) file_get_contents($theme . '/components/revista-publication/revista-publication.css');
$revistaComponent = (string) file_get_contents($theme . '/components/revista-publication/revista-publication.component.yml');
$revistaTemplate = (string) file_get_contents($theme . '/templates/content/node--revista--full.html.twig');
$assert(str_contains($revistaCss, '.portal-post__meta'), 'O SDC revista-publication perdeu os estilos de metadados.');
$assert(!str_contains($portalExtrasCss, '.portal-post__meta'), 'Metadados editoriais voltaram ao portal-ui-extras global.');
$assert(str_contains($revistaComponent, 'revista-publication.css'), 'O SDC revista-publication não declara seu CSS.');
$assert(!str_contains($revistaTemplate, "attach_library('aculta420_v2/revista-publication')"), 'O template ainda anexa uma biblioteca inexistente em vez do asset SDC.');

$portalAccount = array_keys($libraries['portal-account']['css']['theme'] ?? []);
$portalAccountCss = (string) file_get_contents($theme . '/src/styles/portal-account.css');
$accountShellCss = (string) file_get_contents($theme . '/components/account-shell/account-shell.css');
$assert(!in_array('src/styles/portal-account.css', $global, TRUE), 'portal-account.css voltou ao global-styling.');
$assert(in_array('src/styles/portal-account.css', $portalAccount, TRUE), 'A biblioteca portal-account não declara portal-account.css.');
$assert(str_contains($accountShellCss, '.aculta-security-pending'), 'account-shell.css perdeu os avisos de segurança contextual.');
$assert(!str_contains($portalAccountCss, '.portal-terms-modal__documents'), 'portal-account.css ainda contém o layout dos documentos de termos.');
$assert(!str_contains($portalExtrasCss, '.aculta-security-pending'), 'Estilos de segurança de Conta voltaram ao portal-ui-extras.');
$assert(!str_contains($portalExtrasCss, '.portal-terms-modal__documents'), 'Estilos do modal de termos voltaram ao portal-ui-extras.');
$surfaceHook = (string) file_get_contents($theme . '/src/Hook/SurfaceSettingsHooks.php');
$assert(str_contains($surfaceHook, 'aculta420_v2/portal-account'), 'O preprocess_html não anexa a biblioteca portal-account.');
$assert(str_contains($surfaceHook, "\$siteKey === 'account'"), 'O preprocess_html não restringe o CSS ao site_key account.');

$eventOrganization = array_keys($libraries['event-organization']['css']['theme'] ?? []);
$assert(!in_array('src/styles/event-organization.css', $global, TRUE), 'event-organization.css voltou ao global-styling.');
$assert(in_array('src/styles/event-organization.css', $eventOrganization, TRUE), 'A biblioteca event-organization não declara o arquivo.');
$assert(str_contains($hook, 'aculta420_v2/event-organization'), 'O hook não anexa a biblioteca event-organization.');
$assert(str_contains($hook, "'aculta_events.'"), 'O hook não restringe o CSS às rotas aculta_events.*.');

$wiki = array_keys($libraries['wiki']['css']['theme'] ?? []);
$assert(!in_array('src/styles/wiki.css', $global, TRUE), 'wiki.css voltou ao global-styling.');
$assert(in_array('src/styles/wiki.css', $wiki, TRUE), 'A biblioteca wiki não declara wiki.css.');
$assert(str_contains($surfaceHook, 'aculta420_v2/wiki'), 'O preprocess_html não anexa a biblioteca wiki.');
$assert(str_contains($surfaceHook, "\$siteKey === 'wiki'"), 'O preprocess_html não restringe o CSS ao site_key wiki.');

$assert(!isset($libraries['forum']), 'A biblioteca global/contextual forum foi reintroduzida.');
$assert(!is_file($theme . '/src/styles/forum.css'), 'A folha legada forum.css permaneceu após migrar seus consumidores.');
$assert(!str_contains($hook, 'aculta420_v2/forum'), 'PagePresentationHooks ainda anexa a biblioteca removida forum.');
foreach (['forum-list-page', 'forum-category-page', 'forum-search-page'] as $forumComponent) {
  $metadataPath = $theme . '/components/' . $forumComponent . '/' . $forumComponent . '.component.yml';
  $metadata = is_file($metadataPath) ? Yaml::parseFile($metadataPath) : [];
  $componentCss = $metadata['assets']['css']['component'] ?? [];
  $assert(isset($componentCss[$forumComponent . '.css']), "O SDC {$forumComponent} não declara seu CSS local.");
  $assert(is_file($theme . '/components/' . $forumComponent . '/' . $forumComponent . '.css'), "O asset CSS do SDC {$forumComponent} não existe.");
}
$assert(is_file($theme . '/templates/views/views-view--aculta-forum-search--page-search.html.twig'), 'A busca do Fórum não tem template que compõe o SDC local.');
$assert(str_contains($hook, "'view.aculta_forum"), 'O hook deixou de reconhecer rotas View do Fórum para composição de cabeçalho.');

$events = array_keys($libraries['events']['css']['theme'] ?? []);
$assert(!in_array('src/styles/events.css', $global, TRUE), 'events.css voltou ao global-styling.');
$assert(in_array('src/styles/events.css', $events, TRUE), 'A biblioteca events não declara events.css.');
$assert(str_contains($hook, 'aculta420_v2/events'), 'O hook não anexa a biblioteca events.');

$layoutCss = (string) file_get_contents($theme . '/src/styles/layout.css');
$componentLibraryCss = (string) file_get_contents($theme . '/src/styles/component-library-mode.css');
$uiLibraryAdminCss = (string) file_get_contents($theme . '/src/styles/ui-library-admin.css');
$uiLibraryCardRule = preg_match('/\.ui_patterns_component\s*\{([^}]*)\}/s', $uiLibraryAdminCss, $uiLibraryCardMatch) === 1 ? $uiLibraryCardMatch[1] : '';
$assert(!str_contains($layoutCss, '.aculta-studio'), 'layout.css ainda contém regras do Studio; elas pertencem ao CSS contextual.');
$assert(str_contains($componentLibraryCss, '.aculta-studio-topbar'), 'Regras do Studio na biblioteca de componentes não foram movidas para component-library-mode.css.');
// A UI Patterns Library não pode voltar a estilos globais em layout.css.
$assert(!str_contains($layoutCss, '.aculta-component-library-page'), 'layout.css voltou a conter regras exclusivas da UI Patterns Library.');
$assert(!str_contains($layoutCss, '.ui_patterns_component'), 'layout.css voltou a conter regras da UI Patterns Library.');
$assert(str_contains($componentLibraryCss, '.aculta-component-library-page'), 'component-library-mode.css perdeu o escopo da UI Patterns Library.');
$assert(str_contains($componentLibraryCss, '.ui_patterns_component'), 'component-library-mode.css perdeu as regras de cartão da UI Patterns Library.');
$assert(str_contains($componentLibraryCss, '.aculta-component-library-page .aculta-component-library__details > summary'), 'O resumo de detalhes deixou de estar escopado à biblioteca de componentes.');
$assert(!preg_match('/(?<!\.aculta-component-library-page )\.aculta-component-library__details(?:\s|\{)/', $componentLibraryCss), 'A regra genérica de detalhes duplicada voltou ao CSS de modo da biblioteca.');
$assert(str_contains($uiLibraryCardRule, 'break-inside: avoid;'), 'A biblioteca UI Patterns perdeu a regra de evitar quebras dos cartões.');
$assert(!str_contains($uiLibraryCardRule, 'margin-block-end:'), 'A margem do cartão ficou duplicada entre as folhas da UI Patterns.');

// O container do site_key account pertence à biblioteca contextual da conta.
$portalAccountCss = (string) file_get_contents($theme . '/src/styles/portal-account.css');
$assert(!str_contains($layoutCss, 'data-site-key="account"'), 'layout.css voltou a conter o container do site_key account.');
$assert(str_contains($portalAccountCss, 'html[data-site-key="account"]'), 'portal-account.css perdeu o container do site_key account.');

// Evidência: bytes do global que deixaram de ser baixados em toda página.
$globalBytes = 0;
foreach ($global as $asset) {
  if (str_starts_with($asset, 'src/') || str_starts_with($asset, 'components/')) {
    $path = $theme . '/' . $asset;
    if (is_file($path)) {
      $globalBytes += filesize($path);
    }
  }
}
$statisticsBytes = is_file($theme . '/src/styles/statistics.css') ? filesize($theme . '/src/styles/statistics.css') : 0;

if ($failures !== []) {
  fwrite(STDERR, "FAIL — validate-aculta420-css-scope\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  - $failure\n");
  }
  exit(1);
}

echo "PASS — validate-aculta420-css-scope ($checks checks)\n";
echo 'global-styling: ' . count($global) . " entradas, $globalBytes bytes de CSS local\n";
echo "statistics: $statisticsBytes bytes fora do carregamento global\n";
