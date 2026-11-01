<?php
/** Ensure the inherited Portuguese block override does not mask its description. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$description = 'Textos reutilizáveis das seções institucionais. Edite o conteúdo aqui; configure posição e visibilidade em Layout de blocos.';
\Drupal::configFactory()->getEditable('block_content.type.basic')->set('label', 'Bloco editorial')->set('description', $description)->save();
\Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'block_content.type.basic')->clear('name')->set('label', 'Bloco editorial')->set('description', $description)->save();
$contentDescription = 'Administração de páginas, projetos, notícias, atividades, documentos e destaques. Permite filtrar, editar e controlar a publicação.';
\Drupal::configFactory()->getEditable('views.view.content')->set('label', 'Conteúdo')->set('description', $contentDescription)->save();
\Drupal::languageManager()->getLanguageConfigOverride('pt-br', 'views.view.content')->set('label', 'Conteúdo')->set('description', $contentDescription)->save();
$store = \Drupal::service('config.storage')->createCollection('language.pt-br');
foreach (['block_content.type.basic', 'views.view.content'] as $name) {
  file_put_contents(dirname(__DIR__) . '/config/sync/' . $name . '.yml', Yaml::dump(\Drupal::service('config.storage')->read($name), 12, 2));
  file_put_contents(dirname(__DIR__) . '/config/sync/language/pt-br/' . $name . '.yml', Yaml::dump($store->read($name), 12, 2));
}
echo "Administrative block reference synchronized.\n";
