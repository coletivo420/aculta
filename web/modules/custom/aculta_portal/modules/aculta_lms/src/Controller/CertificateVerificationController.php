<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Certificate\CourseCertificateManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Displays public certificate verification and its PDF representation. */
final class CertificateVerificationController {

  use StringTranslationTrait;

  public function __construct(
    private readonly CourseCertificateManagerInterface $certificates,
    private readonly EntityPrintPluginManagerInterface $printEngines,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  public function verify(string $codigo): array {
    $certificate = $this->certificates->loadByCode($codigo);
    if ($certificate === NULL) {
      throw new NotFoundHttpException();
    }
    $valid = $certificate->get('state')->value === 'active';
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-certificate-verification']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Verificação de certificado'),
      ],
      'status' => ['#plain_text' => $valid ? $this->t('Certificado válido') : $this->t('Certificado revogado')],
      'name' => ['#plain_text' => (string) $certificate->get('certificate_name')->value],
      'course' => ['#plain_text' => (string) $certificate->get('course_title')->value],
      'hours' => ['#plain_text' => $this->t('@hours horas', ['@hours' => (int) $certificate->get('course_hours')->value])],
      '#cache' => ['max-age' => 0],
    ];
    if ($valid) {
      $build['pdf'] = [
        '#type' => 'link',
        '#title' => $this->t('Baixar certificado em PDF'),
        '#url' => Url::fromRoute('aculta_lms.certificate_pdf', ['codigo' => $codigo]),
      ];
    }
    CacheableMetadata::createFromObject($certificate)->applyTo($build);
    return $build;
  }

  public function pdf(string $codigo): Response {
    $certificate = $this->certificates->loadByCode($codigo);
    if ($certificate === NULL || $certificate->get('state')->value !== 'active') {
      throw new NotFoundHttpException();
    }

    // Certificate templates must not fetch attacker-controlled or remote
    // resources. Dompdf defaults remote fetching on; override it per request.
    $engine = $this->printEngines->createInstance('dompdf', [
      'enable_remote' => FALSE,
      'embedded_php' => FALSE,
      'disable_log' => TRUE,
    ]);
    // Render this snapshot directly. The entity intentionally denies generic
    // view access; Entity Print's generic renderer would therefore either
    // suppress the document or require weakening that privacy boundary.
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $name = $escape((string) $certificate->get('certificate_name')->value);
    $course = $escape((string) $certificate->get('course_title')->value);
    $hours = (int) $certificate->get('course_hours')->value;
    $code = $escape($codigo);
    $completed = $escape((string) $this->dateFormatter->format((int) $certificate->get('completed')->value, 'long'));
    $issued = $escape((string) $this->dateFormatter->format((int) $certificate->get('issued')->value, 'long'));
    $html = '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><style>'
      . '@page{size:A4 landscape;margin:18mm}body{font-family:DejaVu Sans,sans-serif;text-align:center;color:#173b32}'
      . 'main{border:3px solid #527a61;padding:24mm 16mm}h1{font-size:30pt}h2{font-size:22pt}p{font-size:15pt}'
      . '.code{font-size:9pt;color:#445}</style><main><h1>Certificado de conclusão</h1>'
      . '<p>Certificamos que</p><h2>' . $name . '</h2><p>concluiu com aprovação o curso</p>'
      . '<h2>' . $course . '</h2><p>Carga horária: ' . $hours . ' horas</p>'
      . '<p>Concluído em ' . $completed . '</p><p>Emitido em ' . $issued . '</p>'
      . '<p class="code">Código de verificação: ' . $code . '</p></main></html>';
    $engine->addPage($html);
    $response = new Response($engine->getBlob(), Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="certificado-' . $codigo . '.pdf"',
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Content-Type-Options' => 'nosniff',
    ]);
    return $response;
  }

}
