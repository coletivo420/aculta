<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\aculta_learning_contracts\Learning\AccountCourseSummary as ContractAccountCourseSummary;

/**
 * Authorized course data for the account's learning section.
 *
 * Implementação do DTO neutro do Portal; o provedor normaliza o status e
 * mantém a descrição como texto de origem com o formato.
 */
final readonly class AccountCourseSummary extends ContractAccountCourseSummary {
}
