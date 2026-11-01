<?php

/**
 * Applies the official registration supplied by the responsible person, locally.
 * One-time update; later editorial changes belong in the Drupal administration.
 */
use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1'], TRUE)) {
  throw new RuntimeException('Local site only.');
}
global $state;
$state = \Drupal::state()->get('aculta.institution_setup', []);
if (empty($state['complete'])) {
  throw new RuntimeException('Install the institutional structure first.');
}
if (!empty($state['official_applied'])) {
  echo 'Official data already applied; edit through Drupal.' . PHP_EOL;
  return;
}
$data = json_decode(file_get_contents(__DIR__ . '/institution/content.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$official = $data['official'];
$block = BlockContent::load($state['blocks']['institution_data']);
$fields = [
  'field_nonprofit_description' => ['string', 'Natureza institucional', 'Associação privada sem fins lucrativos'],
  'field_trade_name' => ['string', 'Nome fantasia registrado', $official['registered_trade_name']],
  'field_legal_code' => ['string', 'Código da natureza jurídica', $official['legal_code']],
  'field_opening_date' => ['string', 'Data de abertura', '22 de outubro de 2025'],
  'field_registration_status' => ['string', 'Situação cadastral', $official['registration_status']],
  'field_main_activity' => ['string_long', 'Atividade econômica principal', $official['main_activity']],
  'field_secondary_activity' => ['string_long', 'Atividade econômica secundária', $official['secondary_activity']],
  'field_schema_phone' => ['string', 'Telefone internacional (JSON-LD)', $official['schema_phone']],
  'field_street_address' => ['string', 'Logradouro e complemento (JSON-LD)', $official['street_address']],
  'field_locality' => ['string', 'Cidade (JSON-LD)', $official['locality']],
  'field_address_region' => ['string', 'UF (JSON-LD)', $official['region']],
  'field_postal_code' => ['string', 'CEP (JSON-LD)', $official['postal_code']],
  'field_country' => ['string', 'País ISO (JSON-LD)', $official['country']],
];
$form_display = EntityFormDisplay::load('block_content.aculta_institution.default');
foreach ($fields as $name => [$type, $label, $value]) {
  if (!FieldStorageConfig::loadByName('block_content', $name)) {
    FieldStorageConfig::create(['entity_type' => 'block_content', 'field_name' => $name, 'type' => $type])->save();
  }
  if (!FieldConfig::loadByName('block_content', 'aculta_institution', $name)) {
    FieldConfig::create(['entity_type' => 'block_content', 'bundle' => 'aculta_institution', 'field_name' => $name, 'label' => $label])->save();
  }
  $form_display->setComponent($name, ['type' => $type === 'string_long' ? 'string_textarea' : 'string_textfield', 'weight' => 20 + count($form_display->getComponents())]);
}
$form_display->save();
// Reload with the new field definitions before setting the entity values.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::entityTypeManager()->getStorage('block_content')->resetCache([$block->id()]);
$block = BlockContent::load($block->id());
foreach ($fields as $name => [$type, $label, $value]) {
  $block->set($name, $value);
}
foreach (['field_cnpj' => $official['cnpj'], 'field_legal_nature' => $official['legal_nature'], 'field_address' => $official['address'], 'field_email' => $data['email'], 'field_phone' => $official['phone']] as $name => $value) {
  $block->set($name, $value);
}
$block->setNewRevision(TRUE);
$block->setRevisionLogMessage('Dados oficiais fornecidos pelo responsável, conferidos no comprovante do CNPJ.');
$block->save();

$common = ['field_org_name', 'field_legal_nature', 'field_cnpj', 'field_opening_date', 'field_headquarters', 'field_address', 'field_email', 'field_phone', 'field_site'];
$modes = [
  'home' => ['field_org_name', 'field_nonprofit_description', 'field_cnpj', 'field_headquarters', 'field_site'],
  'institutional' => $common,
  'registration' => ['field_org_name', 'field_trade_name', 'field_cnpj', 'field_legal_nature', 'field_legal_code', 'field_opening_date', 'field_registration_status', 'field_main_activity', 'field_secondary_activity', 'field_address', 'field_email', 'field_phone', 'field_site', 'field_governance'],
  'footer' => ['field_org_name', 'field_org_description', 'field_cnpj', 'field_headquarters', 'field_site'],
  'contact' => ['field_org_name', 'field_email', 'field_phone', 'field_address', 'field_site'],
];
foreach ($modes as $mode => $names) {
  if (!EntityViewMode::load('block_content.' . $mode)) {
    EntityViewMode::create(['id' => 'block_content.' . $mode, 'label' => ['home' => 'Home compacta', 'institutional' => 'Dados institucionais', 'registration' => 'Dados cadastrais completos', 'footer' => 'Rodapé', 'contact' => 'Contato'][$mode], 'targetEntityType' => 'block_content'])->save();
  }
  $display = EntityViewDisplay::load('block_content.aculta_institution.' . $mode) ?? EntityViewDisplay::create(['targetEntityType' => 'block_content', 'bundle' => 'aculta_institution', 'mode' => $mode, 'status' => TRUE]);
  foreach (array_keys($display->getComponents()) as $name) {
    $display->removeComponent($name);
  }
  foreach ($names as $weight => $name) {
    $type = $block->getFieldDefinition($name)->getType();
    $display->setComponent($name, ['type' => ['string' => 'string', 'string_long' => 'basic_string', 'email' => 'email_mailto', 'link' => 'link', 'text_long' => 'text_default'][$type], 'label' => 'hidden', 'weight' => $weight]);
  }
  $display->setStatus(TRUE)->save();
}
$home_placement = Block::load('aculta_institution_data');
$settings = $home_placement->get('settings');
$settings['view_mode'] = 'home';
$home_placement->set('settings', $settings)->setVisibilityConfig('request_path', ['id' => 'request_path', 'negate' => FALSE, 'pages' => '<front>'])->save();
foreach (['institutional' => ['institutional', 'DADOS INSTITUCIONAIS', '/institucional'], 'registration' => ['transparency', 'DADOS CADASTRAIS', '/transparencia']] as $mode => [$node_key, $label, $path]) {
  $id = 'aculta_data_' . $mode;
  if (!Block::load($id)) {
    Block::create(['id' => $id, 'theme' => 'aculta420', 'region' => 'content', 'plugin' => 'block_content:' . $block->uuid(), 'weight' => 90, 'status' => TRUE, 'settings' => ['id' => 'block_content:' . $block->uuid(), 'label' => $label, 'label_display' => 'visible', 'view_mode' => $mode], 'visibility' => ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => $path]]])->save();
  }
}

$institutional = Node::load($state['nodes']['institutional']);
$body = $institutional->get('body')->value;
$body = str_replace('<h2>Quem somos</h2>', '<h2>Quem somos</h2><p>A Associação Cultural Antiproibicionista é uma associação privada sem fins lucrativos, com sede em Goiânia, Goiás.</p>', $body);
$body = str_replace('<li><strong>2026', '<li><strong>22 de outubro de 2025 — Formalização da Associação.</strong> Data de abertura registrada no Cadastro Nacional da Pessoa Jurídica. A trajetória cultural dos projetos antecede a constituição da pessoa jurídica.</li><li><strong>2026', $body);
$institutional->set('body', ['value' => $body, 'format' => 'full_html'])->setNewRevision(TRUE);
$institutional->save();

$privacy = Node::load($state['nodes']['privacy']);
$privacy->set('body', ['value' => '<p>Esta página descreve as funcionalidades de privacidade presentes nesta versão do site oficial da Associação Cultural Antiproibicionista.</p><h2>Formulário de contato</h2><p>O formulário solicita nome, e-mail, assunto e mensagem. Essas informações são usadas para encaminhar o contato à Associação e permitir sua resposta. O formulário não publica a mensagem.</p><h2>Navegação e autenticação</h2><p>O site utiliza mecanismos de sessão e proteção para permitir o acesso de usuários autenticados e o funcionamento dos formulários.</p><h2>Fontes externas</h2><p>O site carrega Inter e Oswald pelo Google Fonts. O navegador faz requisições ao serviço para obter esses arquivos.</p><h2>Contato sobre dados pessoais</h2><p>Entre em contato pelo e-mail <a href="mailto:4e20coletivo@gmail.com">4e20coletivo@gmail.com</a>.</p>', 'format' => 'full_html'])->setNewRevision(TRUE);
$privacy->save();

$contact = \Drupal\contact\Entity\ContactForm::load('aculta_contact');
$contact->set('label', 'Contato')->set('recipients', [$data['email']])->save();
\Drupal::configFactory()->getEditable('contact.settings')->set('default_form', 'aculta_contact')->save();
foreach (['anonymous', 'authenticated'] as $role_id) {
  \Drupal\user\Entity\Role::load($role_id)->grantPermission('access site-wide contact form')->save();
}
\Drupal::configFactory()->getEditable('aculta_portal.settings')->set('institution_data_uuid', $block->uuid())->set('institution_transparency_nid', (int) $state['nodes']['transparency'])->save();

// Create a private, editable document record. No download appears without its PDF.
if (empty($state['nodes']['cnpj_document'])) {
  $document = Node::create(['type' => 'document', 'title' => 'COMPROVANTE DE INSCRIÇÃO E SITUAÇÃO CADASTRAL - CNPJ', 'langcode' => 'pt-br', 'uid' => 1, 'status' => FALSE, 'body' => ['value' => '<p>Comprovante de inscrição da Associação Cultural Antiproibicionista no Cadastro Nacional da Pessoa Jurídica.</p>', 'format' => 'full_html'], 'field_category' => 'Documentos institucionais']);
  $document->save();
  $state['nodes']['cnpj_document'] = (int) $document->id();
}
$state['official_applied'] = TRUE;
\Drupal::state()->set('aculta.institution_setup', $state);
echo 'Official institutional registration applied locally. CNPJ PDF record is an unpublished draft.' . PHP_EOL;
