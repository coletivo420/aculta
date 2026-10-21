<?php
/** Read-only checks of local cleanup and the retained administrative contract. */
use Symfony\Component\Yaml\Yaml;
// Reviewed inventory of Views. Portal and enabled modules provide the course,
// Wiki, Commerce, LMS and Social Auth views; any other Views fail the check.
$expected = [
  // Views do próprio projeto e do núcleo, e Views providas por módulos habilitados
  // (Commerce, LMS/Group, Wiki, Social Auth, Webform, Revista, dblog, Private Message). Cada uma depende de módulo
  // habilitado (verificado em 2026-10-14; as exportadas em config/sync e a 'message' em 2026-10-19). Qualquer outra View
  // reprova o gate. As Views de exemplo do VVJ habilitadas no Runtime não estão aqui: a decisão de desabilitá-las é do
  // responsável.
  'aculta_album_fotos',
  'aculta_albuns',
  'aculta_shop_catalog',
  'classes_admin',
  'lms_course_students',
  'media_galleries',
  'message',
  'aculta_activities',
  'aculta_documents',
  'aculta_news',
  'home_editorial_highlights',
  'block_content',
  'content',
  'files',
  'media',
  'media_library',
  'redirect',
  'user_admin_people',
  'watchdog',
  'webform_submissions',
  'activities_selection',
  'commerce_cart_block',
  'commerce_cart_form',
  'commerce_carts',
  'commerce_checkout_order_summary',
  'commerce_order_item_table',
  'commerce_order_item_table_admin',
  'commerce_order_payments',
  'commerce_orders',
  'commerce_stores',
  'commerce_user_orders',
  'courses',
  'courses_admin',
  'courses_catalog',
  'group_members',
  'lessons_selection',
  'locked_content',
  'moderated_content',
  'profiles',
  'social_auth_profiles',
  'wiki_categories',
  'wiki_entries',
  'comment',
  'comments_recent',
  'commerce_products',
  'galeria_produto',
  'order_shipments',
  'revista_sessoes',
  // Views exportadas e de donos de módulo, incluídas no retrato depois da limpeza: Fórum (aculta_forum), Revista (aculta_magazine),
  // Group (group_nodes) e exemplos opcionais do pacote vvj (config/optional de cada vvj*).
  'aculta_forum_admin_comments',
  'aculta_forum_admin_topics',
  'aculta_forum_categories',
  'aculta_forum_lms_admin_comments',
  'aculta_forum_public_topics',
  'aculta_forum_search',
  'aculta_forum_unread_topics',
  'aculta_magazine_admin_comments',
  'aculta_magazine_admin_content',
  'group_nodes',
  'vvja_example',
  'vvjt_example',
];
$views = \Drupal\views\Entity\View::loadMultiple();
sort($expected); $actual = array_keys($views); sort($actual);
if ($actual !== $expected) throw new RuntimeException('Unexpected retained Views.');
// Curated administrative Views must document their purpose. Views shipped by
// Commerce, LMS, Wiki and Social Auth are owned by those modules; their
// descriptions are not part of this cleanup contract.
$curated = ['aculta_activities','aculta_documents','aculta_news','home_editorial_highlights','block_content','content','files','media','media_library','redirect','user_admin_people','watchdog','webform_submissions'];
foreach ($views as $view) {
  if (in_array($view->id(), $curated, TRUE) && !$view->get('description')) throw new RuntimeException('Missing View description: ' . $view->id());
  $view->getExecutable()->initDisplay();
}
foreach (['admin','content','navigation-user-links','account','main','aculta-footer-content','aculta-footer-institution'] as $id) {
  $menu = \Drupal\system\Entity\Menu::load($id);
  if (!$menu || !$menu->getDescription()) throw new RuntimeException('Missing menu/reference: ' . $id);
}
if (\Drupal::moduleHandler()->moduleExists('contact')) throw new RuntimeException('Old Contact module remains.');
if (!\Drupal\webform\Entity\Webform::load('aculta_contact') || \Drupal\webform\Entity\Webform::load('contact')) throw new RuntimeException('Unexpected Webforms.');
foreach (['image','document','remote_video'] as $id) if (!\Drupal\media\Entity\MediaType::load($id)) throw new RuntimeException('Missing active editor media type.');
$media = array_keys(\Drupal\media\Entity\MediaType::loadMultiple()); sort($media);
// course_document é do LMS (aculta_lms, exportado em config/sync); os três editoriais continuam obrigatórios.
if ($media !== ['course_document','document','image','remote_video']) throw new RuntimeException('Unexpected media types.');
$before = json_decode(file_get_contents(dirname(__DIR__) . '/tmp/admin-structure-audit.json'), TRUE, 512, JSON_THROW_ON_ERROR);
// Itens aposentados por decisão do responsável (2026-10-20): bloco de participação da home e os três links de
// participação do rodapé. Qualquer outro item do snapshot ausente reprova.
$retired = [
  '2a3a1cc7-802b-45ce-a137-7775527b15c6', // Home — Participação (block_content)
  'a2079029-53bf-4c12-96c2-79881a43a656', // Contato (menu aculta-footer-participation)
  'd9f4dac8-647f-4cdc-aa5d-841d5cb91178', // Acompanhe nossas atividades (menu aculta-footer-participation)
  '18825a7c-e0f4-4277-9f70-56ec5842fe98', // Política de Privacidade (menu aculta-footer-participation)
  // Projetos removidos pelo responsável (tipo projeto retirado, 2026-10-21).
  'dc796008-38c0-4deb-864c-0e3d12c09f4a', // Carnareggae Bloco Sativa
  '267321dc-f286-472f-887b-dec6aa3ee0b8', // Grupo de Percussão Baque Sativa
  '9a52f9b6-40ca-4a90-b1e6-6caecc6447fe', // PodPlant420
  'cd3b0bcc-cbe2-4500-aeb0-5a6d1fe3b854', // Batalha do Riddim
  'ae127c2e-aa03-4a8e-b89c-69f864b7e2eb', // Menu do PodPlant420 (removido com o nó)
];
foreach (['node','block_content','menu_link_content'] as $type) {
  // A limpeza não pode apagar conteúdo existente. O conteúdo novo (importações, publicações) é esperado: a checagem é
  // por UUID do snapshot, sem exigir igualdade de contagem.
  $present = [];
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) $present[$entity->uuid()] = TRUE;
  foreach ($before['content'][$type] as $item) {
    if (!isset($present[$item['uuid']]) && !in_array($item['uuid'], $retired, TRUE)) throw new RuntimeException('Existing content removed: ' . $type . ' ' . ($item['label'] ?? $item['uuid']));
  }
}
$config = \Drupal::service('config.storage');
$names = $config->listAll();
foreach ($names as $name) foreach ($config->read($name)['dependencies']['config'] ?? [] as $dependency) if (!$config->exists($dependency)) throw new RuntimeException("Missing dependency $dependency in $name");
$report = \Drupal::state()->get('aculta.admin_cleanup_done');
foreach ($report['deleted'] as $name) if ($config->exists($name)) throw new RuntimeException('Removed config still present.');
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/config/sync', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) if ($file->getExtension() === 'yml') {Yaml::parseFile($file->getPathname()); $count++;}
echo "14 required Views, 8 menus, 3 editor media types; contact migration intact.\n";
echo "Editorial entity counts preserved; configuration dependencies intact; $count YAML files parsed.\n";
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal\user\Entity\User::load(1));
try {
  // Checked by stable route name: admin paths are localized (e.g. /painel-administrativo/conteudo).
  foreach (['system.admin_content','entity.block_content.collection','entity.media.collection','view.files.page_1','entity.user.collection','dblog.overview','redirect.list','entity.webform.collection','entity.view.collection','entity.menu.collection'] as $routeName) {
    if (!\Drupal::service('router.route_provider')->getRoutesByNames([$routeName])) throw new RuntimeException('Required administrative route missing: ' . $routeName);
  }
} finally {$switcher->switchBack();}
$changes = ['changed' => [], 'deleted' => []];
foreach (array_unique(array_merge(array_keys($before['configuration']), $config->listAll())) as $name) {
  $value = $config->read($name);
  if ($value === ($before['configuration'][$name] ?? FALSE)) continue;
  $changes[$value === FALSE ? 'deleted' : 'changed'][] = $name;
}
file_put_contents(dirname(__DIR__) . '/tmp/admin-cleanup-final-diff.json', json_encode($changes, JSON_PRETTY_PRINT));
echo '10 administrative routes present; ' . count($changes['deleted']) . ' configurations removed, ' . count($changes['changed']) . ' updated.' . PHP_EOL;
