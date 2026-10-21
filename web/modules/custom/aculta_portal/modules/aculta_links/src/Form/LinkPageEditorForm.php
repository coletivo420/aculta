<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Entity\LinkPage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides an accessible editor for page item order and status.
 */
final class LinkPageEditorForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurpose,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_links_page_editor_form';
  }

  /**
   * {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, ?LinkPage $aculta_link_page = NULL): array {
    if (!$aculta_link_page) {
      throw new NotFoundHttpException();
    }
    if (!$aculta_link_page->access('update', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('aculta_link_page_id', $aculta_link_page->id());

    $storage = $this->entityTypeManager->getStorage('aculta_link_item');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('page', $aculta_link_page->id())
      ->sort('weight')
      ->sort('id');
    $items = $storage->loadMultiple($query->execute());

    $form['intro'] = [
      '#type' => 'item',
      '#title' => new TranslatableMarkup('Organize os itens desta micropágina'),
      '#description' => new TranslatableMarkup('Ajuste a posição e a publicação de cada item. A ordem também pode ser alterada pelo campo numérico sem JavaScript.'),
    ];
    $slug = (string) $aculta_link_page->get('slug')->value;
    $preview_url = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
      ? $this->domainPurpose->pathUrl('links', '/pagina/' . $slug . '/previa')
      : NULL;
    $links_domain = $this->domainPurpose->getDomain('links');
    if ($preview_url !== NULL && $links_domain !== NULL) {
      $form['preview'] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Pré-visualizar página'),
        '#url' => $preview_url,
        '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
      ];
      (new CacheableMetadata())
        ->addCacheContexts(['url.site'])
        ->addCacheableDependency($links_domain)
        ->applyTo($form['preview']);
    }
    $form['items'] = [
      '#type' => 'table',
      '#header' => [
        new TranslatableMarkup('Texto'),
        new TranslatableMarkup('Posição'),
        new TranslatableMarkup('Ativo'),
      ],
      '#empty' => new TranslatableMarkup('Esta micropágina ainda não tem itens.'),
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'role-weight',
      ],
      ],
    ];
    $form['add_item'] = [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Adicionar botão'),
      '#url' => Url::fromRoute('aculta_links.page_item_add', ['aculta_link_page' => $aculta_link_page->id()]),
      '#weight' => -5,
    ];
    foreach ($items as $item) {
      $id = (string) $item->id();
      $form['items'][$id] = [
        '#attributes' => ['class' => ['draggable']],
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $item->label(),
            '#url' => Url::fromRoute('entity.aculta_link_item.edit_form', ['aculta_link_item' => $id]),
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => new TranslatableMarkup('Posição'),
          '#title_display' => 'invisible',
          '#default_value' => (int) $item->get('weight')->value,
          '#delta' => max(10, count($items)),
          '#attributes' => ['class' => ['role-weight']],
        ],
        'status' => [
          '#type' => 'checkbox',
          '#title' => new TranslatableMarkup('Ativo'),
          '#title_display' => 'invisible',
          '#default_value' => (bool) $item->isPublished(),
        ],
      ];
    }
    $form['#attached']['library'][] = 'core/drupal.tabledrag';
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $page_id = $form_state->get('aculta_link_page_id');
    $page = $this->entityTypeManager->getStorage('aculta_link_page')->load($page_id);
    if (!$page instanceof LinkPage || !$page->access('update', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entityTypeManager->getStorage('aculta_link_item');
    foreach ($form_state->getValue('items', []) as $item_id => $values) {
      $item = $storage->load($item_id);
      if (!$item || !$item->access('update', $this->currentUser)
        || (string) $item->get('page')->target_id !== (string) $page->id()) {
        throw new AccessDeniedHttpException();
      }
      $item->set('weight', (int) $values['weight']);
      $item->set('status', (bool) $values['status']);
      $item->setNewRevision(TRUE);
      $item->setRevisionLogMessage((string) new TranslatableMarkup('Atualiza ordem e publicação pelo editor da micropágina.'));
      $item->save();
    }
    $this->messenger()->addStatus(new TranslatableMarkup('A composição da micropágina foi salva.'));
  }

}
