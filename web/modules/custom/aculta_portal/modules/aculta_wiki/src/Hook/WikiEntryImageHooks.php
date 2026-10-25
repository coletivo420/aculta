<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Service\WikiContributorWorkspace;
use Drupal\aculta_wiki\Form\WikiEntryFormActions;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Customizes native Wiki Node forms for the lead image and safe revision saves.
 *
 * The verbete form keeps its Core routes and fields. The image tab reuses the
 * central upload policy (consumer "wiki"), while edits capture and lock their
 * base revision before saving.
 */
final class WikiEntryImageHooks {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $stringTranslation,
    private readonly UserUploadPolicy $uploadPolicy,
    private readonly UserUploadDialogBuilder $dialogBuilder,
    private readonly WikiLeadImageUploader $uploader,
    private readonly WikiContributorWorkspace $workspace,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteProviderInterface $routeProvider,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Adds the "Imagem de destaque" tab to the wiki_entry node form.
   */
  #[Hook('form_node_wiki_entry_form_alter')]
  #[Hook('form_node_wiki_entry_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $node = $form_object instanceof ContentEntityFormInterface ? $form_object->getEntity() : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'wiki_entry') {
      return;
    }

    $this->addWorkspaceReturnLink($form);

    if (!$node->isNew()) {
      $form[WikiLeadImageUploader::EXPECTED_REVISION] = [
        '#type' => 'hidden',
        '#value' => (int) $node->getRevisionId(),
      ];
      $form['#validate'][] = 'aculta_wiki.wiki_lead_image_uploader:validateExpectedRevision';
    }

    // Serialize updates to the current Wiki revision and preserve Core's
    // EntityForm save behavior even when image upload controls are unavailable.
    $submitHandlers = $form['actions']['submit']['#submit'] ?? [];
    $saveHandler = array_search('::save', $submitHandlers, TRUE);
    if ($saveHandler !== FALSE) {
      $submitHandlers[$saveHandler] = 'aculta_wiki.wiki_lead_image_uploader:saveNode';
      $form['actions']['submit']['#submit'] = $submitHandlers;
    }

    $this->prepareAuthoringForm($form, $node);

    $field = WikiLeadImageUploader::FIELD;
    if (!isset($form[$field])) {
      $this->addReviewAction($form, $node);
      return;
    }
    $access = $form[$field]['#access'] ?? TRUE;
    if ($access === FALSE || ($access instanceof AccessResultInterface && !$access->isAllowed())) {
      $this->addReviewAction($form, $node);
      return;
    }

    // Without an enabled image extension the policy has nothing to offer; the
    // core autocomplete stays visible instead of locking the field.
    if ($this->uploadPolicy->imageExtensions(WikiLeadImageUploader::CONSUMER) === '') {
      $this->addReviewAction($form, $node);
      return;
    }

    $submitHandlers = $form['actions']['submit']['#submit'] ?? [];
    if (!in_array('::save', $submitHandlers, TRUE)
      && !in_array('aculta_wiki.wiki_lead_image_uploader:saveNode', $submitHandlers, TRUE)) {
      $this->addReviewAction($form, $node);
      return;
    }

    $form_object = $form_state->getFormObject();
    $node = $form_object instanceof ContentEntityFormInterface ? $form_object->getEntity() : NULL;
    $current = $node instanceof NodeInterface ? $this->currentImage($node) : NULL;

    $form[$field]['#access'] = FALSE;
    if (!isset($form['advanced'])) {
      $form['advanced'] = [
        '#type' => 'vertical_tabs',
        '#weight' => 99,
      ];
    }

    $tab = WikiLeadImageUploader::TAB;
    $form[$tab] = [
      '#type' => 'details',
      '#title' => $this->t('Imagem de destaque'),
      '#group' => 'advanced',
      '#weight' => 20,
      // Keeps the submitted values under the tab key, as the uploader reads.
      '#tree' => TRUE,
    ];
    $form[$tab]['introduction'] = [
      '#type' => 'item',
      '#markup' => $this->t('A imagem de destaque aparece no card da listagem e no topo do verbete. Tamanho e formatos seguem a política de upload da Wiki.'),
    ];
    if ($current !== NULL) {
      $form[$tab]['current'] = [
        '#type' => 'item',
        '#title' => $this->t('Imagem atual'),
        '#markup' => $current['alt'] === ''
          ? $this->t('@name (sem descrição)', ['@name' => $current['name']])
          : $this->t('@name — descrição: @alt', ['@name' => $current['name'], '@alt' => $current['alt']]),
      ];
    }

    $this->dialogBuilder->addFileControls($form[$tab], WikiLeadImageUploader::CONSUMER, WikiLeadImageUploader::DIRECTORY, WikiLeadImageUploader::FILE);
    $form[$tab][WikiLeadImageUploader::FILE]['#title'] = $this->t('Nova imagem de destaque');
    $form[$tab][WikiLeadImageUploader::ALT] = [
      '#type' => 'textfield',
      '#title' => $this->t('Descrição da imagem'),
      '#description' => $this->t('Obrigatória ao enviar uma nova imagem. Descreva o que ela mostra.'),
      '#maxlength' => WikiLeadImageUploader::ALT_MAX_LENGTH,
      '#default_value' => $current['alt'] ?? '',
    ];
    if ($current !== NULL) {
      $form[$tab][WikiLeadImageUploader::REMOVE] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Remover a imagem de destaque atual'),
      ];
    }

    $form['#validate'][] = 'aculta_wiki.wiki_lead_image_uploader:validateUpload';
    // The save button defines its own #submit list, which replaces the
    // form-level list. Keep Core's submitForm, align Media publication after
    // the submitted revision is built, and wrap Node::save in a transaction.
    if (isset($form['actions']['submit'])) {
      $submitHandlers = array_merge([
        'aculta_wiki.wiki_lead_image_uploader:applyUpload',
      ], $submitHandlers);
      $saveHandler = array_search('::save', $submitHandlers, TRUE);
      if ($saveHandler !== FALSE) {
        $submitHandlers[$saveHandler] = 'aculta_wiki.wiki_lead_image_uploader:saveNode';
      }
      $saveHandler = array_search('aculta_wiki.wiki_lead_image_uploader:saveNode', $submitHandlers, TRUE);
      if ($saveHandler !== FALSE) {
        array_splice($submitHandlers, $saveHandler, 0, [
          'aculta_wiki.wiki_lead_image_uploader:prepareNodeSave',
        ]);
      }
      $form['actions']['submit']['#submit'] = $submitHandlers;
    }
    $this->addReviewAction($form, $node);
  }

  /**
   * Orders core fields and adds concise guidance for a new Wiki entry.
   */
  private function prepareAuthoringForm(array &$form, NodeInterface $node): void {
    $weights = [
      'title' => 0,
      'field_wiki_summary' => 1,
      'body' => 2,
      'field_wiki_references' => 3,
      'field_wiki_categories' => 4,
      'field_wiki_related' => 5,
      'moderation_state' => 7,
    ];
    foreach ($weights as $fieldName => $weight) {
      if (isset($form[$fieldName])) {
        $form[$fieldName]['#weight'] = $weight;
      }
    }
    if (!$node->isNew()) {
      return;
    }
    if (isset($form['title'])) {
      $form['title']['#description'] = $this->t('Use um título simples e específico para o verbete.');
    }
    if (isset($form['field_wiki_summary'])) {
      $form['field_wiki_summary']['#description'] = $this->t('Resuma o tema em poucas frases. Essa síntese ajuda a pessoa leitora a decidir se abre o verbete.');
    }
    if (isset($form['body'])) {
      $form['body']['#description'] = $this->t('Explique o tema com linguagem clara. Inclua fontes verificáveis no campo Referências.');
    }
  }

  /**
   * Adds a separate review action only when Core exposes that transition.
   */
  private function addReviewAction(array &$form, NodeInterface $node): void {
    if (!isset($form['actions']['submit'])) {
      return;
    }
    $widget = $form['moderation_state']['widget'][0]['state'] ?? NULL;
    $options = is_array($widget) ? ($widget['#options'] ?? []) : [];
    if ($node->isNew() && is_array($options) && isset($options['draft'])) {
      $form['actions']['submit']['#value'] = $this->t('Salvar rascunho');
    }
    if (!is_array($options) || !isset($options['review'])) {
      return;
    }
    $review = $form['actions']['submit'];
    $review['#value'] = $this->t('Enviar para revisão');
    $submitHandlers = $review['#submit'] ?? [];
    $entitySubmitHandler = array_search('::submitForm', $submitHandlers, TRUE);
    if ($entitySubmitHandler === FALSE) {
      array_unshift($submitHandlers, WikiEntryFormActions::class . '::submitForReview');
    }
    else {
      array_splice($submitHandlers, $entitySubmitHandler + 1, 0, [WikiEntryFormActions::class . '::submitForReview']);
    }
    $review['#submit'] = $submitHandlers;
    $form['actions']['submit_review'] = $review;
  }

  /**
   * Synchronizes lead image visibility after a Wiki Node is inserted.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->uploader->syncPublishedMedia($entity);
    }
  }

  /**
   * Synchronizes lead image visibility after a Wiki Node is updated.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->uploader->syncPublishedMedia($entity);
      $original = $entity->getOriginal();
      if ($original instanceof NodeInterface
        && $original->bundle() === 'wiki_entry'
        && $this->leadImageId($original) !== NULL
        && $this->leadImageId($original) !== $this->leadImageId($entity)) {
        // Reconcile the image removed from the current revision. Older
        // revisions still reference it, but they no longer make it public.
        $this->uploader->syncPublishedMedia($original, TRUE);
      }
    }
  }

  /**
   * Reconciles image publication after a Wiki Node is deleted.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->uploader->syncPublishedMedia($entity, TRUE);
    }
  }

  /**
   * Returns the label and alt text of the image currently referenced.
   */
  private function currentImage(NodeInterface $node): ?array {
    if (!$node->hasField(WikiLeadImageUploader::FIELD) || $node->get(WikiLeadImageUploader::FIELD)->isEmpty()) {
      return NULL;
    }
    $item = $node->get(WikiLeadImageUploader::FIELD)->first();
    $media = $item?->get('entity')->getValue();
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $alt = '';
    if ($media->hasField('field_media_image')) {
      $alt = (string) ($media->get('field_media_image')->first()?->get('alt')->getValue() ?? '');
    }
    return ['name' => (string) $media->label(), 'alt' => $alt];
  }

  /**
   * Returns the current field's Media ID, if available.
   */
  private function leadImageId(NodeInterface $node): ?int {
    if (!$node->hasField(WikiLeadImageUploader::FIELD)
      || $node->get(WikiLeadImageUploader::FIELD)->isEmpty()) {
      return NULL;
    }
    $targetId = $node->get(WikiLeadImageUploader::FIELD)->target_id;
    return $targetId === NULL || $targetId === '' ? NULL : (int) $targetId;
  }

  /**
   * Adds a return link when the optional Studio destination is available.
   */
  private function addWorkspaceReturnLink(array &$form): void {
    try {
      $this->routeProvider->getRouteByName('aculta_studio.we_sativa');
    }
    catch (RouteNotFoundException) {
      return;
    }

    $access = $this->workspace->access($this->currentUser);
    $url = $this->domainPurposes->routeUrl('account', 'aculta_studio.we_sativa');
    if ($url === NULL) {
      return;
    }

    $form['wiki_workspace_return'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar para Meu Trabalho WE SATIVA'),
      '#url' => $url,
      '#weight' => -100,
      '#access' => $access,
      '#attributes' => ['data-portal-ui' => 'wiki-workspace-return'],
    ];
    $form['#cache']['contexts'] = Cache::mergeContexts($form['#cache']['contexts'] ?? [], $access->getCacheContexts());
    $form['#cache']['tags'] = Cache::mergeTags($form['#cache']['tags'] ?? [], $access->getCacheTags());
  }

}
