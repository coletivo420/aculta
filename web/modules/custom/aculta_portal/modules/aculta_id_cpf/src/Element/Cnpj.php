<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Element;

use Drupal\Core\Render\Attribute\FormElement;

/**
 * CNPJ input accepting both numeric and ASCII alphanumeric bodies.
 */
#[FormElement('aculta_cnpj')]
final class Cnpj extends Cpf {

  public function getInfo() {
    $info = parent::getInfo();
    $info['#aculta_document_type'] = 'cnpj';
    $info['#attributes']['data-aculta-document'] = 'cnpj';
    $info['#attributes']['inputmode'] = 'text';
    return $info;
  }

}
