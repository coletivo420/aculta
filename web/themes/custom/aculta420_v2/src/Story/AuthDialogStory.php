<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_patterns_library\StoryDefault;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Story executável para o Diálogo de Autenticação (SDC).
 *
 * Renderiza o formulário oficial de login do Drupal (UserLoginForm) no slot 'content'
 * preservando atributos, tokens CSRF e marcações sem sanitização Xss destrutiva.
 */
final class AuthDialogStory extends StoryDefault implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * @param array<string, mixed> $configuration
   * @param mixed $plugin_definition
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FormBuilderInterface $formBuilder,
    protected RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('renderer'),
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function props(): array {
    $isInstitutional = str_contains($this->pluginId, 'institutional');
    return [
      'id' => $isInstitutional ? 'auth-dialog-institutional' : 'auth-dialog-default',
      'title' => 'Entrar na ACULTA',
      'trigger_label' => $isInstitutional ? 'Abrir Diálogo de Autenticação (Institucional)' : 'Abrir Diálogo de Autenticação (Neutro)',
      'subtitle' => 'Acesse sua conta e continue de onde parou.',
      'header_variant' => $isInstitutional ? 'institutional' : 'neutral',
      'show_accent_stripes' => TRUE,
      'close_label' => 'Fechar janela de acesso',
      'google_auth_url' => '/user/login/google',
      'register_url' => '/user/register',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function slots(): array {
    $form = $this->formBuilder->getForm(UserLoginForm::class);
    $attributes = $form['#attributes'] ?? [];
    $attributes = is_array($attributes) ? $attributes : [];
    $attributes['data-portal-ui'] = 'login-popup';
    $classes = $attributes['class'] ?? [];
    $classes = is_array($classes) ? $classes : [];
    $classes[] = 'aculta-login-popup';
    $attributes['class'] = $classes;
    $form['#attributes'] = $attributes;

    $actions = $form['actions'] ?? [];
    if (is_array($actions) && is_array($actions['submit'] ?? NULL)) {
      $submit = $actions['submit'];
      $submitAttributes = $submit['#attributes'] ?? [];
      $submitAttributes = is_array($submitAttributes) ? $submitAttributes : [];
      $submitAttributes['data-portal-ui'] = 'button-primary';
      $submit['#attributes'] = $submitAttributes;
      $actions['submit'] = $submit;
      $form['actions'] = $actions;
    }

    $rendered = $this->renderer->renderInIsolation($form);
    $attached = $form['#attached'] ?? [];
    return [
      'content' => [
        '#markup' => $rendered,
        '#attached' => is_array($attached) ? $attached : [],
      ],
    ];
  }

}
