<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Lets a course educator revoke a certificate while preserving its record. */
final class CourseCertificateRevokeForm extends FormBase {

  public function __construct(
    private readonly CurrentRouteMatch $certificateRouteMatch,
    private readonly CourseCertificateManagerInterface $certificates,
    private readonly AccountProxyInterface $account,
  ) {}

  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_certificate_manager'),
      $container->get('current_user'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function getFormId(): string {
    return 'aculta_lms_course_certificate_revoke';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $certificate = $this->certificateRouteMatch->getParameter('certificate');
    if (!$certificate instanceof CourseCertificate || $certificate->get('state')->value !== 'active') {
      $form['message'] = ['#plain_text' => $this->t('Este certificado já não está válido.')];
      return $form;
    }
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Certificado'),
      '#plain_text' => $this->t('@name — @course (emissão @version)', [
        '@name' => $certificate->get('certificate_name')->value,
        '@course' => $certificate->get('course_title')->value,
        '@version' => $certificate->get('sequence')->value,
      ]),
    ];
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo da revogação'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Revogar certificado'),
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('aculta_lms.certificate_issue', ['group' => $this->certificateRouteMatch->getParameter('group')->id()]),
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('Informe o motivo para manter o histórico da revogação.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $certificate = $this->certificateRouteMatch->getParameter('certificate');
    $group = $this->certificateRouteMatch->getParameter('group');
    if (!$certificate instanceof CourseCertificate || $group === NULL || (int) $certificate->get('course_id')->value !== (int) $group->id()) {
      $this->messenger()->addError($this->t('Não foi possível revogar este certificado. Recarregue a página e tente novamente.'));
      return;
    }
    try {
      $this->certificates->revoke($certificate, $this->account, (string) $form_state->getValue('reason'));
      $this->messenger()->addStatus($this->t('Certificado revogado; o registro e a verificação pública foram preservados.'));
      $form_state->setRedirect('aculta_lms.certificate_issue', ['group' => $group->id()]);
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('Não foi possível revogar este certificado. Verifique sua permissão e tente novamente.'));
    }
  }

}
