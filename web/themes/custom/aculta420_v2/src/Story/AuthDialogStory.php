<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Story;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
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

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
    private readonly RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
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
   */
  public function slots(): array {
    $form = $this->formBuilder->getForm(UserLoginForm::class);
    // Aplica atributo de design portal-ui no formulário
    $form['#attributes']['data-portal-ui'] = 'login-popup';
    $form['#attributes']['class'][] = 'aculta-login-popup';

    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#attributes']['data-portal-ui'] = 'button-primary';
    }

    $renderedHtml = (string) $this->renderer->renderPlain($form);

    return [
      'content' => [
        '#markup' => Markup::create($renderedHtml),
        '#attached' => $form['#attached'] ?? [],
      ],
    ];
  }

}
