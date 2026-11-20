<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Plugin\views\field;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\Component\Render\MarkupInterface;
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

  /**
   * Creates the Views field plugin.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID supplied by Views.
   * @param mixed $plugin_definition
   *   Plugin definition supplied by Views.
   * @param \Drupal\aculta_user\Identity\AuthorCardBuilder $cardBuilder
   *   Builder for account author cards.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AuthorCardBuilder $cardBuilder,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID supplied by Views.
   * @param mixed $plugin_definition
   *   Plugin definition supplied by Views.
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
   *
   * @return array<string, mixed>
   *   Field options with the supported author-card defaults.
   */
  protected function defineOptions(): array {
    $options = [];
    foreach (parent::defineOptions() as $key => $value) {
      if (is_string($key)) {
        $options[$key] = $value;
      }
    }
    $options['variant'] = ['default' => 'profile_compact'];
    $options['size'] = ['default' => 'sm'];
    return $options;
  }

  /**
   * Builds the configuration form for this author-card field.
   *
   * @param array<mixed> &$form
   *   Options form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $formElements = $form;
    parent::buildOptionsForm($formElements, $form_state);
    if (!is_array($formElements)) {
      return;
    }
    $variants = array_combine(AuthorIdentityResolver::USER_VARIANTS, AuthorIdentityResolver::USER_VARIANTS);
    $formElements['variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Variante do cartão'),
      '#options' => $variants,
      '#default_value' => $this->option('variant', AuthorIdentityResolver::USER_VARIANTS, 'profile_compact'),
    ];
    $formElements['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Tamanho'),
      '#options' => ['sm' => $this->t('Compacto'), 'md' => $this->t('Padrão'), 'lg' => $this->t('Destaque')],
      '#default_value' => $this->option('size', ['sm', 'md', 'lg'], 'sm'),
    ];
    $form = $formElements;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    $account = $this->getEntity($values);
    if (!$account instanceof UserInterface) {
      // Views cacheia o resultado da consulta sem as entidades carregadas; a
      // linha mantém o uid da tabela base e permite recompor a conta.
      $uid = $values->uid ?? NULL;
      if (!is_int($uid) && !(is_string($uid) && ctype_digit($uid))) {
        return '';
      }
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$account instanceof UserInterface) {
        return '';
      }
    }
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    if ($profile === NULL || !$profile->hasField('field_perfil_publico') || $profile->get('field_perfil_publico')->isEmpty() || !(bool) $profile->get('field_perfil_publico')->value) {
      return '';
    }
    $variant = $this->option('variant', AuthorIdentityResolver::USER_VARIANTS, 'profile_compact');
    $size = $this->option('size', ['sm', 'md', 'lg'], 'sm');
    $build = $this->cardBuilder->build($account, $variant, [], NULL, $size);
    return $build === NULL || $build === [] ? '' : $this->renderer->render($build);
  }

  /**
   * Returns a configured option only when it is one of the supported values.
   *
   * @param string $name
   *   Option name.
   * @param list<string> $allowed
   *   Allowed values for the option.
   * @param string $default
   *   Fallback option value.
   */
  private function option(string $name, array $allowed, string $default): string {
    $value = $this->options[$name] ?? NULL;
    return is_string($value) && in_array($value, $allowed, TRUE) ? $value : $default;
  }

}
