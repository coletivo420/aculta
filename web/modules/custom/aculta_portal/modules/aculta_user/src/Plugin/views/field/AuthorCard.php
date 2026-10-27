<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Plugin\views\field;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\UserInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mostra o cartão de autor público de cada conta da listagem.
 *
 * Só renderiza contas com perfil público explícito. O cartão em si segue a
 * PublicProfilePolicy (identidade, avatar e campos liberados); este campo não
 * concede acesso nem consulta campos privados.
 */
#[ViewsField('aculta_user_author_card')]
final class AuthorCard extends FieldPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AuthorCardBuilder $cardBuilder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_user.author_card_builder'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Campo virtual: não adiciona colunas à consulta; usa a entidade da linha.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['variant'] = ['default' => 'profile_compact'];
    $options['size'] = ['default' => 'sm'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $variants = array_combine(AuthorIdentityResolver::USER_VARIANTS, AuthorIdentityResolver::USER_VARIANTS);
    $form['variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Variante do cartão'),
      '#options' => $variants,
      '#default_value' => $this->options['variant'],
    ];
    $form['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Tamanho'),
      '#options' => ['sm' => $this->t('Compacto'), 'md' => $this->t('Padrão'), 'lg' => $this->t('Destaque')],
      '#default_value' => $this->options['size'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $account = $this->getEntity($values);
    if (!$account instanceof UserInterface) {
      return [];
    }
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    if ($profile === NULL || !$profile->hasField('field_perfil_publico') || $profile->get('field_perfil_publico')->isEmpty() || !(bool) $profile->get('field_perfil_publico')->value) {
      return [];
    }
    return $this->cardBuilder->build($account, (string) $this->options['variant'], [], NULL, (string) $this->options['size']) ?? [];
  }

}
