<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Service\PrivateMessageServiceInterface;
use Drupal\aculta_messenger\Traits\PrivateMessageSettingsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message inbox block.
 */
/**
 * @phpstan-consistent-constructor
 */
#[Block(
  id: 'private_message_inbox_block',
  admin_label: new TranslatableMarkup('Private Message Inbox'),
  category: new TranslatableMarkup('Private Message'),
 )]
class PrivateMessageInboxBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  use PrivateMessageSettingsTrait;

  /**
   * Tipagem do porte Private Message.
   *
   * @param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AccountProxyInterface $currentUser,
    protected PrivateMessageServiceInterface $privateMessageService,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CsrfTokenGenerator $csrfToken,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('aculta_messenger.pm.service'),
      $container->get('entity_type.manager'),
      $container->get('csrf_token'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(
      $account->isAuthenticated()
      && $account->hasPermission('use private messaging system')
    )->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function build(): array {
    /** @var array{thread_count: int, ajax_load_count: int, ajax_refresh_rate: int} $config */
    $config = $this->getConfiguration();
    $thread_info = $this->privateMessageService->getThreadsForUser((int) $config['thread_count']);
    $total_thread = $this->privateMessageService->getCountThreadsForUser();
    $block = [];
    if (count($thread_info['threads'])) {
      $view_builder = $this->entityTypeManager->getViewBuilder('private_message_thread');
      $threads = $thread_info['threads'];

      foreach ($threads as $thread) {
        /** @var array{'#attached'?: array{library?: list<string>, drupalSettings?: array{privateMessageInboxBlock?: array<string, mixed>, ...<string, mixed>}, ...<string, mixed>}, '#attributes'?: array{class?: list<string>, ...<string, mixed>}, ...<int|string, mixed>} $block */
        $block[$thread->id()] = $view_builder->view($thread, 'inbox');
      }

      $block['#attached']['library'][] = 'private_message/inbox_block_script';
      $style_disabled = $this->getPrivateMessageSettings()->get('remove_css');
      if (!$style_disabled) {
        $block['#attached']['library'][] = 'private_message/inbox_block_style';
      }
      if ($thread_info['next_exists']) {
        $prev_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_old_inbox_threads']);
        $prev_token = $this->csrfToken->get($prev_url->getInternalPath());
        $prev_url->setOptions(['query' => ['token' => $prev_token]]);

        $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
        $new_token = $this->csrfToken->get($new_url->getInternalPath());
        $new_url->setOptions(['query' => ['token' => $new_token]]);

        $last_thread = array_pop($threads);
        $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
          'oldestTimestamp' => $last_thread->get('updated')->value,
          'loadPrevUrl' => $prev_url->toString(),
          'loadNewUrl' => $new_url->toString(),
          'threadCount' => $config['ajax_load_count'],
        ];
      }
      else {
        $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
          'oldestTimestamp' => FALSE,
        ];
      }
    }
    else {
      $block['no_threads'] = [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('You do not have any private messages'),
      ];
    }

    $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
    $new_token = $this->csrfToken->get($new_url->getInternalPath());
    $new_url->setOptions(['query' => ['token' => $new_token]]);

    $block['#attached']['drupalSettings']['privateMessageInboxBlock']['loadNewUrl'] = $new_url->toString();

    $config = $this->getConfiguration();
    $block['#attached']['drupalSettings']['privateMessageInboxBlock']['ajaxRefreshRate'] = $config['ajax_refresh_rate'];
    $block['#attached']['drupalSettings']['privateMessageInboxBlock']['totalThreads'] = $total_thread;
    $block['#attached']['drupalSettings']['privateMessageInboxBlock']['itemsToShow'] = $config['thread_count'];
    // Add the default classes, as these are not added when the block output
    // is overridden with a template.
    $block['#attributes']['class'][] = 'block';
    $block['#attributes']['class'][] = 'block-private-message';
    $block['#attributes']['class'][] = 'block-private-message-inbox-block';

    // Wrapper using in js to place Load Previous button for multiple threads.
    $block['#prefix'] = '<div class="private-message-thread--full-container">';
    $block['#suffix'] = '</div>';

    /** @var array<string, mixed> $block */
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['private_message_inbox_block:uid:' . $this->currentUser->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Vary caching of this block per user.
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'thread_count' => 5,
      'ajax_load_count' => 5,
      'ajax_refresh_rate' => 15,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['thread_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of threads to show'),
      '#description' => $this->t('The number of threads to be shown in the block'),
      '#default_value' => $config['thread_count'],
      '#min' => 1,
    ];

    $form['ajax_load_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of threads to load with ajax'),
      '#description' => $this->t('The number of threads to be loaded when the load previous link is clicked'),
      '#default_value' => $config['ajax_load_count'],
      '#min' => 1,
    ];

    $form['ajax_refresh_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Ajax refresh rate'),
      '#default_value' => $config['ajax_refresh_rate'],
      '#min' => 0,
      '#description' => $this->t('The number of seconds after which the inbox should refresh itself. Setting this to a low number will result in more requests to the server, adding overhead and bandwidth. Setting this number to zero will disable ajax refresh, and the inbox will only updated if/when the page is refreshed.'),
    ];

    /** @var array<string, mixed> $form */
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['thread_count'] = $form_state->getValue('thread_count');
    $this->configuration['ajax_load_count'] = $form_state->getValue('ajax_load_count');
    $this->configuration['ajax_refresh_rate'] = $form_state->getValue('ajax_refresh_rate');
  }

}
