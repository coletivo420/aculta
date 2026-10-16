<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Cria e edita uma conquista usando critérios administráveis e fechados. */
final class AchievementDefinitionForm extends FormBase {

  public function __construct(
    private readonly AchievementDefinitions $definitions,
    private readonly EntityTypeManagerInterface $entities,
    private readonly ModuleExtensionList $modules,
    private readonly LoggerInterface $audit,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.achievement_definitions'),
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->get('logger.factory')->get('aculta_game'),
    );
  }

  public function getFormId(): string {
    return 'aculta_game_achievement_definition';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $conquista = NULL): array {
    $all = $this->definitions->all();
    if ($conquista !== NULL && !isset($all[$conquista])) {
      throw new NotFoundHttpException();
    }
    $definition = $all[$conquista] ?? [];
    $form_state->set('achievement_id', $conquista);
    $form['identity'] = ['#type' => 'details', '#title' => $this->t('Identificação'), '#open' => TRUE, '#tree' => TRUE];
    $form['identity']['label'] = ['#type' => 'textfield', '#title' => $this->t('Nome da conquista'), '#required' => TRUE, '#maxlength' => 150, '#default_value' => $definition['label'] ?? ''];
    $form['identity']['id'] = ['#type' => 'textfield', '#title' => $this->t('Identificador interno'), '#description' => $this->t('Uso técnico permanente. Comece com uma letra minúscula e use apenas letras minúsculas, números ou sublinhado.'), '#required' => TRUE, '#maxlength' => 64, '#disabled' => $conquista !== NULL, '#default_value' => $conquista ?? ''];
    $form['identity']['description'] = ['#type' => 'textarea', '#title' => $this->t('O que esta conquista reconhece'), '#description' => $this->t('Este texto pode aparecer na página de conquistas da pessoa.'), '#default_value' => $definition['description'] ?? '', '#maxlength' => 2000];
    $form['identity']['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Disponível para novas concessões'), '#description' => $this->t('Desativar mantém o histórico e não retira a conquista de quem já a recebeu.'), '#default_value' => $definition['enabled'] ?? TRUE];

    $form['image_section'] = ['#type' => 'details', '#title' => $this->t('Imagem'), '#open' => TRUE, '#tree' => TRUE];
    $current = $this->currentImage($definition);
    if ($current !== []) {
      $form['image_section']['current'] = ['#type' => 'container', 'preview' => $current, 'caption' => ['#plain_text' => $this->t('Imagem atual')]];
      if ($conquista !== NULL) {
        $form['image_section']['remove_link'] = [
          '#type' => 'link',
          '#title' => $this->t('Remover imagem atual'),
          '#url' => Url::fromRoute('aculta_game.achievement_image_remove_confirm', ['conquista' => $conquista]),
        ];
      }
    }
    $file = $this->fileForDefinition($definition);
    $form['image_section']['image_fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Enviar imagem da conquista'),
      '#description' => $this->t('Recomendado: 512 × 512 px, em formato quadrado. PNG, JPG ou WebP, até 2 MB e no máximo 4096 × 4096 px. A nova imagem substitui a atual ao salvar.'),
      '#upload_location' => 'public://conquistas',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png jpg jpeg webp'],
        'FileIsImage' => [],
        'FileSizeLimit' => ['fileLimit' => 2 * 1024 * 1024],
        'FileImageDimensions' => ['maxDimensions' => '4096x4096'],
      ],
      '#default_value' => $file ? [(int) $file->id()] : [],
      '#multiple' => FALSE,
    ];

    $form['criteria'] = ['#type' => 'details', '#title' => $this->t('Como a pessoa conquista'), '#open' => TRUE, '#tree' => TRUE];
    $form['criteria']['summary'] = ['#type' => 'container', 'text' => ['#plain_text' => $this->t('Marque todas as formas que podem conceder esta conquista. Basta cumprir uma das formas marcadas; elas funcionam como alternativas.')]];
    $triggers = $definition['triggers'] ?? ['manual'];
    $form['criteria']['community'] = ['#type' => 'fieldset', '#title' => $this->t('Participação na comunidade')];
    foreach (['forum_comment', 'wiki_revision', 'profile_basic'] as $id) {
      $form['criteria']['community'][$id] = $this->triggerElement($id, $triggers);
    }
    $form['criteria']['learning'] = ['#type' => 'fieldset', '#title' => $this->t('Formação')];
    $form['criteria']['learning']['course_passed'] = $this->triggerElement('course_passed', $triggers);
    $form['criteria']['support'] = ['#type' => 'fieldset', '#title' => $this->t('Apoio à associação')];
    foreach (['order_completed', 'donation_completed'] as $id) {
      $form['criteria']['support'][$id] = $this->triggerElement($id, $triggers);
    }
    $form['criteria']['manual'] = ['#type' => 'fieldset', '#title' => $this->t('Reconhecimento pela equipe')];
    $form['criteria']['manual']['manual'] = $this->triggerElement('manual', $triggers);
    $form['criteria']['version'] = ['#type' => 'container', 'text' => ['#plain_text' => $this->t('Versão atual das condições: @version. Alterar as condições registra uma nova versão e vale para fatos futuros.', ['@version' => $definition['criterion_version'] ?? 1])]];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar conquista')];
    return $form;
  }

  private function triggerElement(string $id, array $selected): array {
    $labels = AchievementDefinitions::TRIGGERS;
    $descriptions = [
      'manual' => $this->t('Uma pessoa administradora concede e registra o motivo.'),
      'order_completed' => $this->t('Concedida quando um pedido da loja é concluído.'),
      'donation_completed' => $this->t('Concedida quando um apoio é concluído pelo sistema de doações.'),
      'forum_comment' => $this->t('Concedida após publicar uma resposta no Growroom420.'),
      'wiki_revision' => $this->t('Concedida após uma revisão da Wiki420 ser publicada.'),
      'course_passed' => $this->t('Concedida após concluir um curso com aprovação.'),
      'profile_basic' => $this->t('Concedida quando os dados básicos do perfil ficam completos.'),
    ];
    return [
      '#type' => 'checkbox',
      '#title' => $this->t($labels[$id]),
      '#description' => $descriptions[$id],
      '#default_value' => in_array($id, $selected, TRUE),
    ];
  }

  private function fileForDefinition(array $definition): ?FileInterface {
    if (empty($definition['image_uuid'])) {
      return NULL;
    }
    $files = $this->entities->getStorage('file')->loadByProperties(['uuid' => $definition['image_uuid']]);
    $file = $files ? reset($files) : NULL;
    return $file instanceof FileInterface && str_starts_with($file->getFileUri(), 'public://') ? $file : NULL;
  }

  private function currentImage(array $definition): array {
    if ($file = $this->fileForDefinition($definition)) {
      return ['#theme' => 'image', '#uri' => $file->getFileUri(), '#alt' => (string) ($definition['label'] ?? ''), '#width' => 120];
    }
    $legacy = [
      'fundadore' => 'fundadore.png',
      'associado' => 'associado-antiproibicionista.png',
      'apoiador' => 'apoiadore.png',
    ][$definition['image'] ?? ''] ?? NULL;
    return $legacy ? ['#theme' => 'image', '#uri' => $this->modules->getPath('aculta_game') . '/assets/conquistas/' . $legacy, '#alt' => (string) ($definition['label'] ?? ''), '#width' => 120] : [];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->get('achievement_id');
    $identity = (array) $form_state->getValue('identity', []);
    $id = $existing ?? trim((string) ($identity['id'] ?? ''));
    if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/D', $id)) {
      $form_state->setErrorByName('id', $this->t('Informe um identificador válido.'));
    }
    if ($existing === NULL && isset($this->definitions->all()[$id])) {
      $form_state->setErrorByName('id', $this->t('Esse identificador já existe.'));
    }
    $selected = array_filter($form_state->getValue(['criteria']) ?? [], static fn ($value): bool => $value === '1' || $value === 1 || $value === TRUE);
    if ($selected === []) {
      $form_state->setErrorByName('criteria', $this->t('Marque pelo menos uma forma de conquistar esta conquista.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $identity = (array) $form_state->getValue('identity', []);
    $id = $form_state->get('achievement_id') ?? trim((string) ($identity['id'] ?? ''));
    $criteria = $form_state->getValue('criteria', []);
    $selected = [];
    foreach (AchievementDefinitions::TRIGGERS as $trigger => $label) {
      if (!empty($criteria['community'][$trigger]) || !empty($criteria['learning'][$trigger]) || !empty($criteria['support'][$trigger]) || !empty($criteria['manual'][$trigger])) {
        $selected[] = $trigger;
      }
    }
    $imageValues = $form_state->getValue('image_section', []);
    $fids = array_values(array_filter((array) ($imageValues['image_fid'] ?? [])));
    $this->definitions->save($id, [
      'label' => $identity['label'] ?? '',
      'description' => $identity['description'] ?? '',
      'enabled' => !empty($identity['enabled']),
      'triggers' => $selected,
      'image_fid' => $fids[0] ?? NULL,
      'remove_image' => FALSE,
    ]);
    $this->audit->notice('Definição da conquista @id salva pela conta @actor.', ['@id' => $id, '@actor' => $this->currentUser()->id()]);
    $this->messenger()->addStatus($this->t('Conquista salva.'));
    $form_state->setRedirect('aculta_game.achievements');
  }

}
