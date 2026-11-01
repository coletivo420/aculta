<?php
/** Local cleanup of unused media structures and descriptions for retained ones. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$active = \Drupal::service('config.storage');
$before = [];
foreach ($active->listAll() as $name) $before[$name] = $active->read($name);
$localeBefore = [];
$localized = $active->createCollection('language.pt-br');
foreach ($localized->listAll() as $name) $localeBefore[$name] = $localized->read($name);
$allowed = \Drupal::config('filter.format.full_html')->get('filters.media_embed.settings.allowed_media_types') ?? [];
foreach (['audio', 'video'] as $id) {
  if (in_array($id, $allowed, TRUE)) throw new RuntimeException('Media type used by editor: ' . $id);
  if (\Drupal::entityTypeManager()->getStorage('media')->getQuery()->accessCheck(FALSE)->condition('bundle', $id)->count()->execute()) throw new RuntimeException('Media type contains content.');
  if ($type = \Drupal\media\Entity\MediaType::load($id)) $type->delete();
}
foreach ([
  'image' => ['Imagem', 'Imagens para projetos, notícias, atividades e demais conteúdos institucionais. Informe texto alternativo e utilize arquivos autorizados.'],
  'document' => ['Documento', 'Arquivos institucionais reutilizáveis na biblioteca de mídia. Os documentos de Transparência também possuem tipo de conteúdo próprio para contexto em HTML.'],
  'remote_video' => ['Vídeo remoto', 'Vídeos incorporados por URL de provedor suportado, como os registros audiovisuais dos projetos. Use somente destinos reais e autorizados.'],
] as $id => [$label, $description]) {
  if ($type = \Drupal\media\Entity\MediaType::load($id)) {
    $type->set('label', $label)->set('description', $description)->save();
    \Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'media.type.' . $id)->set('label', $label)->set('description', $description)->save();
  }
}
$displayNames = ['default' => 'Padrão', 'page' => 'Página', 'block' => 'Bloco', 'attachment' => 'Anexo', 'feed' => 'Feed', 'embed' => 'Incorporado'];
foreach (['content','block_content','files','media','media_library','user_admin_people','watchdog','redirect','webform_submissions'] as $id) {
  $view = \Drupal\views\Entity\View::load($id);
  if (!$view) continue;
  $displays = $view->get('display');
  $override = \Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'views.view.' . $id);
  foreach ($displays as $key => &$display) {
    $special = ['widget' => 'Seletor de mídia', 'widget_table' => 'Seletor de mídia em tabela', 'embed_administer' => 'Incorporado: administração', 'embed_default' => 'Incorporado: padrão', 'embed_manage' => 'Incorporado: gestão', 'embed_review' => 'Incorporado: revisão'];
    $title = $special[$key] ?? ($key === 'default' ? 'Padrão' : ($displayNames[$display['display_plugin']] ?? 'Exibição'));
    $display['display_title'] = $title;
    $override->set('display.' . $key . '.display_title', $title);
    if (isset($display['display_options']['title'])) {
      $display['display_options']['title'] = $view->get('label');
      $override->set('display.' . $key . '.display_options.title', $view->get('label'));
    }
  }
  unset($display);
  $view->set('display', $displays)->save(); $override->save();
}
$directory = dirname(__DIR__) . '/config/sync/';
$report = ['changed' => [], 'deleted' => []];
foreach (array_unique(array_merge(array_keys($before), $active->listAll())) as $name) {
  $after = $active->read($name);
  if ($after === ($before[$name] ?? FALSE)) continue;
  if ($after === FALSE) {if (is_file($directory . $name . '.yml')) unlink($directory . $name . '.yml'); $report['deleted'][] = $name;}
  else {file_put_contents($directory . $name . '.yml', Yaml::dump($after, 12, 2)); $report['changed'][] = $name;}
}
foreach (array_unique(array_merge(array_keys($localeBefore), $localized->listAll())) as $name) {
  $after = $localized->read($name); $path = $directory . 'language/pt-br/' . $name . '.yml';
  if ($after === ($localeBefore[$name] ?? FALSE)) continue;
  if ($after === FALSE) {if (is_file($path)) unlink($path);}
  else file_put_contents($path, Yaml::dump($after, 12, 2));
}
file_put_contents(dirname(__DIR__) . '/tmp/admin-cleanup-refinement.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
