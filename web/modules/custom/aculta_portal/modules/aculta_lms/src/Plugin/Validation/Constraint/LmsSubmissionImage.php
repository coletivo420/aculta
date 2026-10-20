<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/** Validates raster images attached to LMS answer uploads. */
#[Constraint(
  id: 'AcultaLmsSubmissionImage',
  label: new TranslatableMarkup('ACULTA LMS submission image', [], ['context' => 'Validation']),
  type: 'file',
)]
final class LmsSubmissionImage extends SymfonyConstraint {

  /** Message shown when an image attachment violates ACULTA image policy. */
  public string $message = 'This image does not meet the ACULTA image upload policy.';

  /** {@inheritdoc} */
  public function validatedBy(): string {
    return 'aculta_lms.submission_image_validator';
  }

}
