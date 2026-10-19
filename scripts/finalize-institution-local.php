<?php
/** One-time approved editorial revision. Local only; content remains editable in Drupal. */
use Drupal\block_content\Entity\BlockContent;
use Drupal\block\Entity\Block;
use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldConfig;

if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) throw new RuntimeException('Local only.');
if (\Drupal::state()->get('aculta.final_editorial_applied')) { echo "Already applied; edit through Drupal.\n"; return; }
$state = \Drupal::state()->get('aculta.institution_setup');
$short = json_decode(file_get_contents(__DIR__ . '/institution/home-editorial.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$short['who'][0] = 'A Associação Cultural Antiproibicionista é uma organização da sociedade civil sem fins lucrativos, com sede em Goiânia, que reúne cultura, comunicação, formação e participação social.';
$short['work'] = [
  'Realizamos e apoiamos iniciativas de música, percussão, cultura de rua, carnaval, reggae e outras expressões da cultura independente e popular.',
  'Produzimos conteúdos, entrevistas, encontros e materiais que aproximam conhecimento, experiências e participação social.',
  'Desenvolvemos ações orientadas pelo cuidado, pela autonomia, pela informação e pela promoção da saúde em atividades culturais e comunitárias.',
  'Participamos de espaços de diálogo e construção coletiva relacionados a direitos humanos, cidadania e políticas públicas.',
];
$short['projects']['podplant420']['summary'] = 'Projeto audiovisual dedicado a conversas sobre cultura, direitos, sociedade, cuidado e participação popular.';
$short['knowledge'] = ['Produzir cultura também significa produzir conhecimento. Entrevistas, publicações e projetos audiovisuais aproximam diferentes experiências e saberes.', 'Nossa comunicação cria espaços de escuta e diálogo sobre cultura, saúde, direitos e sociedade.'];
$short['care'] = ['A redução de danos integra nossa atuação social por meio de informação, autonomia e cuidado em atividades culturais e comunitárias.', 'Também participamos de debates sobre direitos humanos e políticas públicas, sempre com respeito à dignidade das pessoas.'];
$short['research'] = ['Experiências construídas em nossos projetos também dialogam com pesquisa, educação popular e produção de conhecimento.', 'Práticas culturais comunitárias podem fortalecer vínculos, redes de cuidado e participação social.'];
$saveBody = static function($entity, string $html): void {
  $entity->set('body', ['value' => $html, 'format' => 'full_html'])->setNewRevision(TRUE);
  $entity->setRevisionLogMessage('Revisão institucional final local aprovada pelo responsável.');
  $entity->save();
};
$who = BlockContent::load($state['blocks']['home_who']);
$body = $who->body->value;
$body = preg_replace('/<p>A Associação Cultural Antiproibicionista.*?<\/p>/u', '<p>' . $short['who'][0] . '</p>', $body, 1);
$body = str_replace('SAIBA MAIS SOBRE A ASSOCIAÇÃO', 'CONHEÇA NOSSA HISTÓRIA', $body);
$saveBody($who, $body);
$work = BlockContent::load($state['blocks']['home_work']);
$headings = ['CULTURA E ARTE', 'COMUNICAÇÃO E FORMAÇÃO', 'CUIDADO E REDUÇÃO DE DANOS', 'DIREITOS E PARTICIPAÇÃO SOCIAL'];
$areas = '';
foreach ($short['work'] as $i => $text) $areas .= '<section><h3>' . $headings[$i] . '</h3><p>' . $text . '</p></section>';
$saveBody($work, preg_replace('/<div class="aculta-areas">.*?<\/div>/us', '<div class="aculta-areas">' . $areas . '</div>', $work->body->value, 1));
$pod = Node::load($state['nodes']['podplant420']);
$body = $pod->body->first()->getValue();
$body['summary'] = '<p>' . $short['projects']['podplant420']['summary'] . '</p>';
$pod->set('body', $body)->setNewRevision(TRUE); $pod->save();
$highlights = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'editorial_highlight']);
$copy = ['INFORMAÇÃO TAMBÉM É CULTURA' => $short['knowledge'], 'CUIDADO, INFORMAÇÃO E DIREITOS' => $short['care'], 'CULTURA, CUIDADO E PRODUÇÃO DE CONHECIMENTO' => $short['research']];
foreach ($highlights as $node) if (isset($copy[$node->label()])) { $node->set('field_summary', $copy[$node->label()][0])->set('field_complement', $copy[$node->label()][1])->setNewRevision(TRUE); $node->save(); }
$institution = Node::load($state['nodes']['institutional']);
$body = str_replace('<li><strong>22 de outubro de 2025 — Formalização da Associação.</strong> Data de abertura registrada no Cadastro Nacional da Pessoa Jurídica. A trajetória cultural dos projetos antecede a constituição da pessoa jurídica.</li>', '<li><strong>22 de agosto de 2025 — Fundação da Associação.</strong> Realização da Assembleia Geral de Fundação.</li><li><strong>22 de outubro de 2025 — Abertura do CNPJ.</strong> Data de abertura cadastral registrada no Cadastro Nacional da Pessoa Jurídica.</li>', $institution->body->value);
$governance = '<p>A Assembleia Geral é o órgão deliberativo da Associação. O Conselho Associativo é seu órgão executivo, composto pela Coordenação Geral, Secretaria e Coordenação Financeira.</p>';
$body = str_replace('<h2>Organização institucional</h2>', '<h2>Princípios e participação social</h2><p>O antiproibicionismo integra os princípios da Associação e orienta sua participação no debate público sobre políticas relacionadas às substâncias psicoativas, direitos humanos, saúde pública e redução de danos.</p><h2>Organização institucional</h2>' . $governance, $body);
$saveBody($institution, $body);
$transparency = Node::load($state['nodes']['transparency']);
$saveBody($transparency, '<p class="aculta-page-intro">A Associação Cultural Antiproibicionista mantém este espaço para facilitar o acesso a informações sobre sua organização, documentos institucionais, estrutura associativa e atividades.</p>');
$sections = '<section class="aculta-editorial-section aculta-transparency-sections"><h2>Estatuto Social</h2><p>O Estatuto Social estabelece os objetivos, os princípios e as regras de organização da Associação.</p><h2>Atas e Assembleias</h2><p>As atas registram as deliberações dos órgãos associativos. A Assembleia Geral de Fundação ocorreu em 22 de agosto de 2025.</p><h2>Governança</h2>' . $governance . '<h2>Relatórios de atividades</h2><p>Os relatórios de atividades reúnem registros dos projetos e das ações desenvolvidas pela Associação.</p><h2>Prestação de contas</h2><p>Esta área reúne informações de prestação de contas relacionadas às atividades e aos projetos, quando aplicáveis.</p><h2>Parcerias e convênios</h2><p>Os instrumentos institucionais de parceria e convênio podem ser consultados nesta área quando disponibilizados para acesso público.</p><h2>Documentos institucionais</h2><p>Os documentos disponibilizados para consulta e download são apresentados a seguir, com identificação e contexto em HTML.</p></section>';
$newBlocks = [];
foreach ([
  'aculta_transparency_sections' => ['Organização e documentos da Transparência', $sections, '/transparencia', 20],
  'aculta_home_partnership' => ['Construir em parceria — Home', '<section class="aculta-editorial-section"><h2>CONSTRUIR EM PARCERIA</h2><div class="aculta-prose"><p>Acreditamos na colaboração entre sociedade civil, instituições, empresas, universidades, poder público e iniciativas culturais para ampliar o alcance de projetos e fortalecer ações de interesse coletivo.</p><p><a class="aculta-editorial-link" href="/contato">FALE COM A ASSOCIAÇÃO</a></p></div></section>', '<front>', 95],
] as $id => [$label, $html, $path, $weight]) {
  $entity = BlockContent::create(['type' => 'basic', 'info' => $label, 'langcode' => 'pt-br', 'body' => ['value' => $html, 'format' => 'full_html']]);
  $entity->save();
  Block::create(['id' => $id, 'langcode' => 'pt-br', 'theme' => 'aculta420', 'region' => 'content', 'weight' => $weight, 'plugin' => 'block_content:' . $entity->uuid(), 'settings' => ['id' => 'block_content:' . $entity->uuid(), 'label' => $label, 'label_display' => FALSE, 'provider' => 'block_content', 'view_mode' => 'full'], 'visibility' => ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => $path]]])->save();
  $newBlocks[$id] = ['id' => $entity->id(), 'uuid' => $entity->uuid()];
}
Block::load('aculta_data_registration')->setWeight(10)->save();
Block::load('aculta_documents')->setWeight(30)->save();
$uuid = \Drupal::config('aculta_portal.settings')->get('institution_data_uuid');
$officialBlocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['uuid' => $uuid]);
$official = reset($officialBlocks);
$official->set('field_org_description', 'Lutando por um futuro livre da proibição.')->setNewRevision(TRUE); $official->save();
FieldConfig::loadByName('block_content', 'aculta_institution', 'field_org_description')->setLabel('Slogan institucional')->setDescription('Slogan oficial exibido na identificação institucional do rodapé.')->save();
// Preserve the superseded page as an unpublished revision; /contato is the existing Webform.
if (!empty($state['nodes']['contact'])) { $oldContact = Node::load($state['nodes']['contact']); $oldContact->setUnpublished()->setNewRevision(TRUE); $oldContact->save(); }
$description = 'Associação sem fins lucrativos de Goiânia que desenvolve projetos de cultura, comunicação, cuidado, direitos e participação social.';
\Drupal::configFactory()->getEditable('metatag.metatag_defaults.front')->set('tags.description', $description)->set('tags.og_description', $description)->save();
// Node-level metatags override defaults, so keep both sources consistent.
$home = Node::load($state['nodes']['home']);
if ($home->hasField('field_meta_tags') && !$home->field_meta_tags->isEmpty()) {
  $tags = json_decode($home->field_meta_tags->value, TRUE);
  if (is_array($tags)) { $tags['description'] = $description; $tags['og_description'] = $description; $home->set('field_meta_tags', json_encode($tags, JSON_UNESCAPED_UNICODE))->setNewRevision(TRUE); $home->save(); }
}
file_put_contents(__DIR__ . '/institution/home-editorial.json', json_encode($short, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
\Drupal::state()->set('aculta.final_editorial_applied', ['blocks' => $newBlocks, 'date' => date('c')]);
echo "Final editorial content, governance, founding dates, partnership, footer and mail updated locally.\n";
