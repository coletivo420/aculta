<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\Validation\Constraint;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\UploadPolicy;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Aplica a política de imagem do LMS a anexos de resposta.
 *
 * Usa a mesma regra de conteúdo do aculta_file_uploader para a integração
 * `lms`: extensão, tamanho, MIME pelo conteúdo e dimensões decodificáveis.
 * Arquivos fora do catálogo de imagens seguem a validação nativa do Core.
 */
final class LmsSubmissionImageValidator extends ConstraintValidator {

  public function __construct(
    #[Autowire(service: 'aculta_file_uploader.consumer_policy')]
    private readonly ConsumerUploadPolicy $uploadPolicy,
  ) {}

  /** {@inheritdoc} */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LmsSubmissionImage) {
      throw new UnexpectedTypeException($constraint, LmsSubmissionImage::class);
    }
    if (!$value instanceof FileInterface) {
      throw new UnexpectedTypeException($value, FileInterface::class);
    }

    $extension = strtolower(pathinfo($value->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($extension, explode(' ', UploadPolicy::EXTENSIONS), TRUE)) {
      return;
    }

    if (!$this->uploadPolicy->isValidImage($value, 'lms')) {
      $this->context->addViolation($constraint->message);
    }
  }

}
