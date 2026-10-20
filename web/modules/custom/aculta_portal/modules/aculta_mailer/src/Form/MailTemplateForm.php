<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Editor de modelo: assunto e corpo com as variáveis declaradas pelo tipo, e prévia com valores de exemplo.
 */
final class MailTemplateForm extends EntityForm {

  public function __construct(
    private readonly MailTypeRegistry $types,
    private readonly MailTemplateRenderer $renderer,
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MailTypeRegistry::class),
      $container->get(MailTemplateRenderer::class),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\aculta_mailer\Entity\MailTemplate $template */
    $template = $this->entity;
    $type = $this->types->has($template->id()) ? $this->types->get($template->id()) : NULL;

    $options = [];
    foreach ($this->types->ids() as $id) {
      $options[$id] = $id;
    }
    $form['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de e-mail'),
      '#options' => $options,
      '#default_value' => $template->id(),
      '#required' => TRUE,
      '#disabled' => !$template->isNew(),
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome'),
      '#default_value' => $template->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Usar este modelo (desmarcado = texto padrão do tipo)'),
      '#default_value' => $template->status(),
    ];
    $layouts = ['' => $this->t('- Sem layout -')];
    foreach ($this->entityTypes->getStorage(MailLayout::ENTITY_TYPE)->loadMultiple() as $layout) {
      $layouts[$layout->id()] = $layout->label();
    }
    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => $layouts,
      '#default_value' => $template->getLayout(),
    ];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Assunto'),
      '#default_value' => $template->getSubject() !== '' ? $template->getSubject() : ($type?->defaultSubject() ?? ''),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Corpo (texto simples)'),
      '#default_value' => $template->getBody() !== '' ? $template->getBody() : ($type?->defaultBody() ?? ''),
      '#required' => TRUE,
      '#rows' => 14,
    ];

    $form['variables'] = [
      '#type' => 'details',
      '#title' => $this->t('Variáveis disponíveis'),
      '#description' => $this->t('Escreva {{ nome }} no lugar do valor. Só estas variáveis são substituídas.'),
      '#open' => FALSE,
    ];
    $rows = [];
    foreach ($type?->variables() ?? [] as $name => $variable) {
      $rows[] = ['{{ ' . $name . ' }}', $variable['label']];
    }
    $form['variables']['list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Placeholder'), $this->t('Significado')],
      '#rows' => $rows,
      '#empty' => $this->t('Este tipo não tem variáveis.'),
    ];

    if ($type !== NULL) {
      $preview = $this->renderer->preview($type, (string) ($template->getSubject() ?: $type->defaultSubject()), (string) ($template->getBody() ?: $type->defaultBody()));
      $form['preview'] = [
        '#type' => 'details',
        '#title' => $this->t('Prévia (valores de exemplo)'),
        '#open' => TRUE,
        'subject' => ['#markup' => '<p><strong>' . $this->t('Assunto:') . '</strong> ' . htmlspecialchars($preview['subject'], ENT_QUOTES) . '</p>'],
        'body' => ['#type' => 'html_tag', '#tag' => 'pre', '#value' => $preview['body']],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $type_id = (string) $form_state->getValue('id');
    if (!$this->types->has($type_id)) {
      $form_state->setErrorByName('id', $this->t('Tipo de e-mail não registrado.'));
      return;
    }
    $allowed = array_keys($this->types->get($type_id)->variables());
    foreach (['subject', 'body'] as $field) {
      $unknown = MailTemplateRenderer::unknownPlaceholders((string) $form_state->getValue($field), $allowed);
      if ($unknown !== []) {
        $form_state->setErrorByName($field, $this->t('Variável desconhecida: @names. Use apenas as variáveis listadas.', [
          '@names' => implode(', ', array_map(static fn(string $name): string => '{{ ' . $name . ' }}', $unknown)),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_mailer\Entity\MailTemplate $template */
    $template = $this->entity;
    $template->set('label', (string) $form_state->getValue('label'));
    $template->set('status', (bool) $form_state->getValue('status'));
    $template->setSubject((string) $form_state->getValue('subject'));
    $template->setBody((string) $form_state->getValue('body'));
    $template->setLayout((string) $form_state->getValue('layout'));
    $status = $template->save();
    $this->messenger()->addStatus($this->t('Modelo salvo.'));
    $form_state->setRedirect('entity.' . MailTemplate::ENTITY_TYPE . '.collection');
    return $status;
  }

}
