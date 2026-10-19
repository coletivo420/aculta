<?php
/** Administrative reference text; no changes to public editorial copy. */
use Symfony\Component\Yaml\Yaml;
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
$changed = [];
$map = [
  'node_type' => [
    'page' => ['Página institucional', 'Páginas permanentes da associação, como Institucional, Transparência e Política de Privacidade. A Home utiliza composição de conteúdo e blocos.'],
    'project' => ['Projeto', 'Projetos culturais e sociais. O resumo aparece na Home; a descrição completa, o histórico e os registros aprofundam a página do projeto.'],
    'activity' => ['Atividade', 'Registros de atividades reais, com data e relação opcional com um projeto. Aparecem na listagem de Atividades quando publicados.'],
    'article' => ['Notícia', 'Notícias, artigos e atualizações institucionais. Podem ser relacionados a um projeto e aparecem na listagem de Notícias quando publicados.'],
    'document' => ['Documento institucional', 'Documentos oficiais com contexto em HTML e arquivo PDF quando disponível. Publicar somente documentos reais e autorizados.'],
    'editorial_highlight' => ['Destaque editorial', 'Itens do carrossel Cultura, informação e cuidado da Home. Edite categoria, título, resumo, complemento, CTA e ordem. Somente itens publicados aparecem; menor ordem vem primeiro.'],
  ],
  'block_content_type' => [
    'basic' => ['Bloco editorial', 'Textos e seções reutilizáveis. A publicação na página depende também da colocação e visibilidade do bloco em Layout de blocos.'],
    'aculta_institution' => ['Dados institucionais', 'Fonte estruturada dos dados oficiais da associação, compartilhada entre páginas, footer e JSON-LD. Alterar somente com confirmação documental.'],
  ],
  'menu' => [
    'main' => ['Navegação principal', 'Sete destinos institucionais do header. Preserve rótulos completos e links para páginas existentes.'],
    'account' => ['Conta do usuário', 'Links de conta e saída para usuários autenticados, apresentados discretamente na faixa utilitária.'],
    'footer' => ['Rodapé', 'Menu padrão de rodapé do Drupal. O footer institucional utiliza também os menus específicos de instituição, conteúdo e participação.'],
    'aculta-footer-institution' => ['Institucional', 'Navegação institucional do footer: quem somos, projetos e transparência.'],
    'aculta-footer-content' => ['Conteúdo', 'Navegação de notícias, atividades e comunicação no footer.'],
  ],
];
foreach ($map as $entity_type => $items) {
  $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
  foreach ($items as $id => [$label, $description]) {
    if ($entity = $storage->load($id)) {
      $entity->set($entity_type === 'menu' ? 'label' : 'name', $label)->set('description', $description)->save();
      $changed[] = $entity->getConfigDependencyName();
    }
  }
}
foreach (\Drupal::entityTypeManager()->getStorage('menu')->loadMultiple() as $menu) {
  if (str_starts_with($menu->id(), 'aculta') && !in_array($menu->getConfigDependencyName(), $changed)) {
    $menu->set('description', 'Navegação do footer institucional. Organize links para páginas reais e mantenha os rótulos públicos aprovados.')->save();
    $changed[] = $menu->getConfigDependencyName();
  }
}
foreach (['aculta_projects' => ['Projetos da associação', 'Lista projetos publicados, ordenados pelo campo de ordem. Utilizada na Home e na página de Projetos.'], 'aculta_activities' => ['Atividades da associação', 'Lista atividades reais publicadas, com seus dados e relações editoriais.'], 'aculta_news' => ['Notícias da associação', 'Lista notícias publicadas para acompanhamento da atuação institucional.'], 'aculta_documents' => ['Documentos da associação', 'Lista documentos oficiais publicados para a área de Transparência. Não substitui o contexto institucional em HTML.'], 'home_editorial_highlights' => ['Destaques editoriais da Home', 'Carrossel VVJB de destaques publicados. Ordenação crescente por Ordem e, em caso de empate, identificador do conteúdo. Não depende de três IDs fixos.']] as $id => [$label, $description]) {
  if ($view = \Drupal\views\Entity\View::load($id)) {
    $view->set('label', $label)->set('description', $description)->save(); $changed[] = $view->getConfigDependencyName();
  }
}
foreach (['field_category' => 'Categoria curta do destaque. Exemplo: COMUNICAÇÃO • CULTURA.', 'field_summary' => 'Resumo curto e legível; não utilizar HTML ou cortar informação com reticências.', 'field_complement' => 'Parágrafo complementar opcional que aprofunda o resumo.', 'field_link' => 'Escolha um destino existente e informe o texto do CTA. Não inserir URL fictícia.', 'field_weight' => 'Número inteiro para ordenar os destaques: os menores números aparecem primeiro.'] as $name => $description) {
  $field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'editorial_highlight', $name);
  $field->setDescription($description)->save(); $changed[] = $field->getConfigDependencyName();
}
$directory = dirname(__DIR__) . '/config/sync/';
foreach ($changed as $name) file_put_contents($directory . $name . '.yml', Yaml::dump(\Drupal::service('config.storage')->read($name), 12, 2));
\Drupal::state()->set('aculta.admin_reference_config', $changed);
echo json_encode($changed, JSON_PRETTY_PRINT) . PHP_EOL;
