<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Envio de teste de um modelo: usa o caminho real de envio, com valores de exemplo do tipo.
 *
 * Ao administrador aparece só o resultado genérico; o texto completo da falha fica no relatório de falhas.
 */
final class MailTemplateTestForm extends FormBase {

  /**
   * Modelo do teste, definido pela rota ou pelo chamador.
   */
  private ?MailTemplate $template = NULL;

  public function __construct(
    private readonly AcultaMailerInterface $mailer,
    private readonly MailTypeRegistry $types,
    private readonly UuidInterface $uuid,
    private readonly RouteMatchInterface $currentRoute,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_mailer.mailer'),
      $container->get(MailTypeRegistry::class),
      $container->get('uuid'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_template_test_form';
  }

  /**
   * Define o modelo do teste (usado pela rota e pelos testes).
   */
  public function setTemplate(MailTemplate $template): void {
    $this->template = $template;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $template = $this->template();
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Envia o modelo <strong>@label</strong> para o endereço informado, com valores de exemplo.', ['@label' => $template->label()]) . '</p>',
    ];
    $form['recipient'] = [
      '#type' => 'email',
      '#title' => $this->t('Destinatário do teste'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar teste'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!filter_var((string) $form_state->getValue('recipient'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('recipient', $this->t('Informe um endereço de e-mail válido.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $template = $this->template();
    $samples = [];
    foreach ($this->types->get($template->id())->variables() as $name => $variable) {
      $samples[$name] = $variable['sample'];
    }
    $message = new MailMessage($template->id(), (string) $form_state->getValue('recipient'), 'pt-br', $samples, $this->uuid->generate());
    $result = $this->mailer->deliver($message);
    if ($result->sent) {
      $this->messenger()->addStatus($this->t('Teste aceito pelo sistema de e-mail para @to.', ['@to' => $message->to]));
    }
    else {
      $this->messenger()->addError($this->t('Não foi possível enviar o teste. O motivo completo está no relatório de falhas (correlação @id).', ['@id' => $message->correlationId]));
    }
  }

  /**
   * Modelo do teste: o definido por setTemplate() ou o parâmetro da rota.
   */
  private function template(): MailTemplate {
    if ($this->template === NULL) {
      $param = $this->currentRoute->getParameter(MailTemplate::ENTITY_TYPE);
      if (!$param instanceof MailTemplate) {
        throw new \LogicException('Modelo do teste não informado.');
      }
      $this->template = $param;
    }
    return $this->template;
  }

}
