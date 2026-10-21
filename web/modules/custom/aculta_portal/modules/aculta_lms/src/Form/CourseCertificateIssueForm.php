<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\lms\Entity\CourseStatusInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Lets authorized course educators issue a certificate from approved status. */
final class CourseCertificateIssueForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CourseCertificateManagerInterface $certificates,
    private readonly CurrentRouteMatch $certificateRouteMatch,
    private readonly AccountProxyInterface $account,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_lms.course_certificate_manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('date.formatter'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function getFormId(): string {
    return 'aculta_lms_course_certificate_issue';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $course = $this->certificateRouteMatch->getParameter('group');
    $options = [];
    if ($course !== NULL && ($this->account->hasPermission('administer lms') || $course->hasPermission('grade students', $this->account))) {
      $statusStorage = $this->entityTypeManager->getStorage('lms_course_status');
      $statusIds = $statusStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition(CourseStatusInterface::COURSE_FIELD, $course->id())
        ->condition('status', CourseStatusInterface::STATUS_PASSED)
        ->condition('finished', 0, '>')
        ->execute();
      $statuses = $statusStorage->loadMultiple($statusIds);
      foreach ($statuses as $status) {
        if (!$status instanceof CourseStatusInterface || !$status->isFinished()) {
          continue;
        }
        $student = $status->getUser();
        $options[$status->id()] = $this->t('@student — conclusão em @date', [
          '@student' => $student->getDisplayName(),
          '@date' => $this->dateFormatter->format($status->getFinished(), 'long'),
        ]);
      }
    }
    if ($course !== NULL && ($this->account->hasPermission('administer lms') || $course->hasPermission('grade students', $this->account))) {
      $certificates = $this->entityTypeManager->getStorage('aculta_lms_certificate')
        ->loadByProperties(['course_id' => (int) $course->id()]);
    }
    else {
      $certificates = [];
    }
    if ($certificates !== [] && $course !== NULL) {
      $rows = [];
      foreach ($certificates as $certificate) {
        $rows[] = [
          'student' => ['data' => ['#plain_text' => (string) $certificate->get('certificate_name')->value]],
          'course' => ['data' => ['#plain_text' => (string) $certificate->get('course_title')->value]],
          'version' => ['data' => ['#plain_text' => (string) $certificate->get('sequence')->value]],
          'status' => ['data' => ['#plain_text' => $certificate->get('state')->value === 'active' ? $this->t('Válido') : $this->t('Revogado')]],
          'verification' => [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Verificar'),
              '#url' => Url::fromRoute('aculta_lms.certificate_verify', ['codigo' => $certificate->get('verification_code')->value]),
            ],
          ],
          'revoke' => [
            'data' => $certificate->get('state')->value === 'active' ? [
              '#type' => 'link',
              '#title' => $this->t('Revogar'),
              '#url' => Url::fromRoute('aculta_lms.certificate_revoke', [
                'group' => $course->id(),
                'certificate' => $certificate->id(),
              ]),
            ] : ['#plain_text' => '—'],
          ],
        ];
      }
      $form['issued_certificates'] = [
        '#type' => 'table',
        '#caption' => $this->t('Certificados já emitidos neste curso'),
        '#header' => [$this->t('Estudante'), $this->t('Curso'), $this->t('Emissão'), $this->t('Estado'), $this->t('Verificação'), $this->t('Ação')],
        '#rows' => $rows,
        '#weight' => 10,
        '#cache' => ['max-age' => 0],
      ];
    }
    $form['course_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Conclusão aprovada'),
      '#options' => $options,
      '#empty_option' => $this->t('- Selecione uma pessoa -'),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Emitir certificado'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->certificateRouteMatch->getParameter('group');
    $status = $this->entityTypeManager->getStorage('lms_course_status')->load($form_state->getValue('course_status'));
    if (!$status instanceof CourseStatusInterface || $course === NULL || $status->getCourseId() !== (string) $course->id()) {
      $this->messenger()->addError($this->t('Não foi possível emitir este certificado. Recarregue a página e tente novamente.'));
      return;
    }
    try {
      $certificate = $this->certificates->issue($status, $this->account);
      $url = Url::fromRoute('aculta_lms.certificate_verify', ['codigo' => $certificate->get('verification_code')->value]);
      $this->messenger()->addStatus($this->t('Certificado emitido. Código de verificação: @code', [
        '@code' => $certificate->get('verification_code')->value,
      ]));
      $form_state->setRedirectUrl($url);
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('Não foi possível emitir este certificado. Verifique a conclusão aprovada, matrícula e carga horária do curso.'));
    }
  }

}
