<?php
/** Explicit, local cleanup of unused configuration; preserve editorial entities. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
if (\Drupal::state()->get('aculta.admin_cleanup_done')) {echo "Already cleaned.\n"; return;}
$active = \Drupal::service('config.storage');
$before = [];
foreach ($active->listAll() as $name) $before[$name] = $active->read($name);
$backup = ['config' => $before, 'translations' => []];
foreach ($active->getAllCollectionNames() as $collection) {
  $store = $active->createCollection($collection);
  foreach ($store->listAll() as $name) $backup['translations'][$collection][$name] = $store->read($name);
}
file_put_contents(dirname(__DIR__) . '/tmp/admin-cleanup-before.json', json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$removed = [];
foreach (['archive', 'content_recent', 'frontpage', 'glossary', 'taxonomy_term', 'who_s_new', 'who_s_online', 'vvjb_example'] as $id) {
  if ($view = \Drupal\views\Entity\View::load($id)) {
    $dependents = \Drupal::service('config.manager')->getConfigDependencyManager()->getDependentEntities('config', $view->getConfigDependencyName());
    if ($dependents) throw new RuntimeException('View unexpectedly referenced: ' . $id);
    $view->delete(); $removed[] = 'views.view.' . $id;
  }
}
foreach (\Drupal\block\Entity\Block::loadMultiple() as $block) {
  if ($block->getTheme() === 'bootstrap5' || in_array($block->id(), ['aculta_home_care', 'aculta_home_knowledge', 'aculta_home_research', 'aculta_powered'])) {
    $removed[] = $block->getConfigDependencyName(); $block->delete();
  }
}
$sample = \Drupal\webform\Entity\Webform::load('contact');
if ($sample) {
  $count = \Drupal::entityTypeManager()->getStorage('webform_submission')->getQuery()->accessCheck(FALSE)->condition('webform_id', 'contact')->count()->execute();
  if ($count) throw new RuntimeException('Sample form has submissions; preserve it.');
  $sample->delete(); $removed[] = 'webform.webform.contact';
}
// No fields reference this empty vocabulary. Preserve taxonomy itself (Core/profile).
$tags = \Drupal\taxonomy\Entity\Vocabulary::load('tags');
if ($tags) {
  if (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'tags')->count()->execute()) throw new RuntimeException('Tags contain content.');
  foreach (\Drupal\field\Entity\FieldConfig::loadMultiple() as $field) {
    if (isset($field->getSetting('handler_settings')['target_bundles']['tags'])) throw new RuntimeException('Tags still referenced by a field.');
  }
  $role = \Drupal\user\Entity\Role::load('content_editor');
  if ($role) {$role->revokePermission('create terms in tags')->revokePermission('edit terms in tags')->save();}
  $tags->delete(); $removed[] = 'taxonomy.vocabulary.tags';
}
// This Core module's two forms were replaced by the actual institutional Webform.
if (\Drupal::moduleHandler()->moduleExists('contact')) {
  $blockers = \Drupal::service('module_installer')->validateUninstall(['contact']);
  if ($blockers) throw new RuntimeException('Contact module has uninstall blockers.');
  \Drupal::service('module_installer')->uninstall(['contact']);
}
// These menus were placed only in the removed base-theme blocks.
foreach (['footer', 'tools'] as $id) {
  foreach (\Drupal\block\Entity\Block::loadMultiple() as $block) if ($block->getPluginId() === 'system_menu_block:' . $id) throw new RuntimeException('Menu still placed: ' . $id);
  if (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => $id])) throw new RuntimeException('Menu contains editorial links.');
  if ($menu = \Drupal\system\Entity\Menu::load($id)) {$removed[] = $menu->getConfigDependencyName(); $menu->delete();}
}
// Translate and explain administrative Views that are still actually required.
foreach ([
  'content' => ['Conteúdo', 'Administração de páginas, projetos, notícias, atividades, documentos e destaques. Permite filtrar, editar e controlar a publicação.'],
  'block_content' => ['Blocos de conteúdo', 'Administração dos textos reutilizáveis e dos dados institucionais. A colocação pública é configurada em Layout de blocos.'],
  'files' => ['Arquivos', 'Inventário dos arquivos gerenciados pelo Drupal e de seu uso. Não representa uma listagem pública de documentos.'],
  'media' => ['Mídia', 'Administração de imagens, documentos, áudio e vídeo gerenciados pela biblioteca de mídia.'],
  'media_library' => ['Biblioteca de mídia', 'Seleção e reutilização de mídia no editor. Mantém os displays e argumentos exigidos pelo Media Library.'],
  'user_admin_people' => ['Usuários', 'Administração de contas, estados e papéis de acesso. Preserve os filtros e as ações de gestão de usuários.'],
  'watchdog' => ['Registro de eventos', 'Consulta administrativa de erros, avisos e eventos registrados pelo Drupal. Usada para diagnóstico do ambiente.'],
  'redirect' => ['Redirecionamentos', 'Administração dos redirecionamentos de URLs, incluindo mudanças de aliases. Evita destinos antigos sem continuidade.'],
  'webform_submissions' => ['Submissões de formulários', 'Consulta administrativa das mensagens e demais submissões do Webform, conforme as permissões de acesso.'],
] as $id => [$label, $description]) {
  if ($view = \Drupal\views\Entity\View::load($id)) {$view->set('label', $label)->set('description', $description)->save();}
}
foreach ([
  'admin' => ['Administração', 'Navegação administrativa do Drupal: conteúdo, estrutura, configuração, usuários e relatórios.'],
  'content' => ['Gestão de conteúdo', 'Menu utilizado pelo módulo Navigation para acessar conteúdo, mídia, blocos e arquivos. É necessário mesmo sem links criados manualmente.'],
  'navigation-user-links' => ['Links do usuário', 'Menu utilizado pela navegação administrativa para conta e ações do usuário autenticado.'],
  'account' => ['Conta do usuário', 'Acesso à conta e saída de usuários autenticados; utilizado pela faixa utilitária do site.'],
] as $id => [$label, $description]) {
  if ($menu = \Drupal\system\Entity\Menu::load($id)) {$menu->set('label', $label)->set('description', $description)->save();}
}
$basic = \Drupal\block_content\Entity\BlockContentType::load('basic');
if ($basic) $basic->set('label', 'Bloco editorial')->set('description', 'Textos reutilizáveis das seções institucionais. Edite o conteúdo aqui; configure posição e visibilidade em Layout de blocos.')->save();
// Translation overrides inherited from Standard can otherwise mask the new names.
$overrides = \Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'block_content.type.basic');
$overrides->set('label', 'Bloco editorial')->set('description', $basic->get('description'))->save();
foreach (['admin','content','navigation-user-links','account'] as $id) {
  $menu = \Drupal\system\Entity\Menu::load($id);
  if ($menu) {
    $override = \Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'system.menu.' . $id);
    $override->set('label', $menu->get('label'))->set('description', $menu->getDescription())->save();
  }
}
foreach (['content','block_content','files','media','media_library','user_admin_people','watchdog','redirect','webform_submissions'] as $id) {
  $view = \Drupal\views\Entity\View::load($id);
  if ($view) {
    $override = \Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'views.view.' . $id);
    $override->set('label', $view->get('label'))->set('description', $view->get('description'))->save();
  }
}
// Export only configuration whose active data actually changed in this cleanup.
$changed = []; $deleted = [];
$directory = dirname(__DIR__) . '/config/sync/';
foreach (array_unique(array_merge(array_keys($before), $active->listAll())) as $name) {
  $after = $active->read($name);
  if ($after === ($before[$name] ?? FALSE)) continue;
  if ($after === FALSE) {if (is_file($directory . $name . '.yml')) unlink($directory . $name . '.yml'); $deleted[] = $name;}
  else {file_put_contents($directory . $name . '.yml', Yaml::dump($after, 12, 2)); $changed[] = $name;}
}
foreach (array_unique(array_merge(array_keys($backup['translations']), $active->getAllCollectionNames())) as $collection) {
  $store = $active->createCollection($collection);
  $path = $directory . str_replace('.', '/', $collection) . '/';
  foreach (array_unique(array_merge(array_keys($backup['translations'][$collection] ?? []), $store->listAll())) as $name) {
    $after = $store->read($name);
    if ($after === ($backup['translations'][$collection][$name] ?? FALSE)) continue;
    if ($after === FALSE) {if (is_file($path . $name . '.yml')) unlink($path . $name . '.yml');}
    else {if (!is_dir($path)) mkdir($path, 0777, TRUE); file_put_contents($path . $name . '.yml', Yaml::dump($after, 12, 2));}
  }
}
$report = ['changed' => $changed, 'deleted' => $deleted, 'editorial_content' => 'Preserved; no nodes, blocks of content, media or submissions deleted.'];
\Drupal::state()->set('aculta.admin_cleanup_done', $report);
file_put_contents(dirname(__DIR__) . '/tmp/admin-cleanup-result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
