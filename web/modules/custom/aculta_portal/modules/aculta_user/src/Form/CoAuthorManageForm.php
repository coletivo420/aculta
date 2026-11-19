<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_user\CoAuthor\CoAuthorAuditService;
use Drupal\aculta_user\CoAuthor\CoAuthorManager;
use Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a management form for assigning co-authors to a specific node.
 */
final class CoAuthorManageForm extends FormBase {

  /**
   * The coauthor manager.
   *
   * @var \Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface
   */
  protected CoAuthorManagerInterface $coAuthorManager;

  /**
   * The audit service.
   *
   * @var \Drupal\aculta_user\CoAuthor\CoAuthorAuditService
   */
  protected CoAuthorAuditService $auditService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a CoAuthorManageForm.
   */
  public function __construct(
    CoAuthorManagerInterface $coAuthorManager,
    CoAuthorAuditService $auditService,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->coAuthorManager = $coAuthorManager;
    $this->auditService = $auditService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.coauthor_manager'),
      $container->get('aculta_user.coauthor_audit'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_coauthor_manage_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\node\NodeInterface|null $node
   *   The node entity being managed.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface) {
      return $form;
    }

    $form_state->set('node_id', (int) $node->id());
    $owner = $node->getOwner();
    $ownerName = $owner->getDisplayName();

    $form['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Conteúdo: @title', ['@title' => (string) $node->label()]),
      '#markup' => '<p>' . $this->t('<strong>Autor principal:</strong> @author (não pode ser alterado aqui).', ['@author' => $ownerName]) . '</p>',
    ];

    $coauthorIds = $this->coAuthorManager->getCoAuthorIds($node);
    $userStorage = $this->entityTypeManager->getStorage('user');
    $defaultEntities = !empty($coauthorIds) ? $userStorage->loadMultiple($coauthorIds) : [];

    $form['coauthors'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#title' => $this->t('Coautores'),
      '#description' => $this->t('Digite o nome ou apelido dos coautores a serem associados.'),
      '#default_value' => $defaultEntities,
      '#maxlength' => 1024,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Salvar coautores'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $rawNid = $form_state->get('node_id');
    $nid = is_scalar($rawNid) ? (int) $rawNid : 0;
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface) {
      $form_state->setErrorByName('coauthors', $this->t('Conteúdo não encontrado.'));
      return;
    }

    $ownerId = (int) $node->getOwnerId();
    /** @var array<int, array<string, mixed>> $entries */
    $entries = (array) $form_state->getValue('coauthors');
    $seen = [];
    $userStorage = $this->entityTypeManager->getStorage('user');

    foreach ($entries as $entry) {
      $uid = isset($entry['target_id']) && is_scalar($entry['target_id']) ? (int) $entry['target_id'] : 0;
      if ($uid <= 0) {
        continue;
      }
      if ($uid === $ownerId) {
        $form_state->setErrorByName('coauthors', $this->t('O autor principal não pode ser incluído como coautor.'));
        return;
      }
      if (isset($seen[$uid])) {
        $form_state->setErrorByName('coauthors', $this->t('Não é permitido repetir o mesmo coautor.'));
        return;
      }
      $seen[$uid] = TRUE;
      $account = $userStorage->load($uid);
      if (!$account instanceof UserInterface || !$account->isActive()) {
        $form_state->setErrorByName('coauthors', $this->t('Um dos usuários selecionados não existe ou está bloqueado.'));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rawNid = $form_state->get('node_id');
    $nid = is_scalar($rawNid) ? (int) $rawNid : 0;
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface) {
      return;
    }

    /** @var array<int, array<string, mixed>> $entries */
    $entries = (array) $form_state->getValue('coauthors');
    $uids = [];
    foreach ($entries as $entry) {
      $uid = isset($entry['target_id']) && is_scalar($entry['target_id']) ? (int) $entry['target_id'] : 0;
      if ($uid > 0) {
        $uids[] = $uid;
      }
    }

    $uids = array_values(array_unique($uids));
    $items = array_map(static fn(int $uid): array => ['target_id' => $uid], $uids);

    $node->set(CoAuthorManager::FIELD_NAME, $items);
    $node->save();

    // Grava auditoria.
    $currentUser = (int) $this->currentUser()->id();
    $this->auditService->record($nid, $currentUser, 'manage_form_save', $uids);

    $this->messenger()->addStatus($this->t('Coautores de "%title" atualizados com sucesso.', ['@title' => (string) $node->label()]));
    $form_state->setRedirect('aculta_user.coauthors.overview');
  }

}
