<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_links\Entity\LinkPage;
use Drupal\aculta_links\Entity\LinkItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds an owned reusable destination to one microsite.
 */
final class LinkPageItemAddForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_links_page_item_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?LinkPage $aculta_link_page = NULL): array {
    if (!$aculta_link_page) {
      throw new NotFoundHttpException();
    }
    if (!$aculta_link_page->access('update', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('aculta_link_page_id', $aculta_link_page->id());

    $link_storage = $this->entityTypeManager->getStorage('aculta_link');
    $links = $link_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $aculta_link_page->getOwnerId())
      ->sort('title')
      ->execute();
    $options = [];
    foreach ($link_storage->loadMultiple($links) as $link) {
      if ((string) $link->getOwnerId() === (string) $aculta_link_page->getOwnerId()
        && $link->access('update', $this->currentUser)) {
        $options[(string) $link->id()] = $link->label();
      }
    }

    $form['intro'] = [
      '#type' => 'item',
      '#title' => new TranslatableMarkup('Adicionar um botão à micropágina'),
      '#description' => new TranslatableMarkup('Escolha um destino reutilizável desta mesma conta. A página, o botão e o destino permanecem vinculados ao mesmo proprietário.'),
    ];
    if ($options === []) {
      $form['empty'] = [
        '#type' => 'item',
        '#markup' => $this->t('Esta conta ainda não tem destinos reutilizáveis disponíveis.'),
      ];
      return $form;
    }

    $form['link'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Destino'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Texto do botão'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Descrição opcional'),
      '#maxlength' => 255,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Adicionar botão'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $page = $this->entityTypeManager->getStorage('aculta_link_page')
      ->load($form_state->get('aculta_link_page_id'));
    if (!$page instanceof LinkPage || !$page->access('update', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }

    $link = $this->entityTypeManager->getStorage('aculta_link')->load($form_state->getValue('link'));
    if (!$link
      || (string) $link->getOwnerId() !== (string) $page->getOwnerId()
      || !$link->access('update', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }

    $item = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $page->getOwnerId(),
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'weight' => 0,
      'status' => TRUE,
    ]);
    $violations = $item->validate();
    if ($violations->count() > 0) {
      foreach ($violations as $violation) {
        $form_state->setErrorByName('link', $violation->getMessage());
      }
      return;
    }

    $item->save();
    $this->messenger()->addStatus(new TranslatableMarkup('O botão foi adicionado à micropágina.'));
    $form_state->setRedirect('aculta_links.page_editor', ['aculta_link_page' => $page->id()]);
  }

}
