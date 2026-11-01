<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\link\LinkItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates local or HTTPS-only ACULTA Links destinations. */
final class LinkDestinationConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc} */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof LinkItemInterface) {
      throw new UnexpectedValueException($value, LinkItemInterface::class);
    }
    if ($value->isEmpty()) {
      return;
    }

    $uri = (string) $value->uri;
    if ($uri === '' || preg_match('/[\x00-\x20\\\\]/', $uri)) {
      $this->addViolation($constraint);
      return;
    }

    $parts = parse_url($uri);
    $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
    $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
    $is_safe_internal = $scheme === 'internal'
      && str_starts_with($uri, 'internal:/')
      && !str_starts_with($path, '//')
      && !isset($parts['host'], $parts['user'], $parts['pass']);
    $is_safe_entity = $scheme === 'entity'
      && preg_match('/^entity:[a-z][a-z0-9_]*\/[A-Za-z0-9._~-]+$/D', $uri) === 1
      && !isset($parts['host'], $parts['user'], $parts['pass']);
    if ($is_safe_internal || $is_safe_entity) {
      return;
    }

    $is_safe_https = $scheme === 'https'
      && filter_var($uri, FILTER_VALIDATE_URL) !== FALSE
      && is_array($parts)
      && !empty($parts['host'])
      && !isset($parts['user'])
      && !isset($parts['pass'])
      && (!isset($parts['port']) || $parts['port'] === 443);
    if (!$is_safe_https) {
      $this->addViolation($constraint);
    }
  }

  /**
   * Adds the constraint's generic violation. */
  private function addViolation(Constraint $constraint): void {
    $this->context->buildViolation($constraint->message)->atPath('uri')->addViolation();
  }

}
