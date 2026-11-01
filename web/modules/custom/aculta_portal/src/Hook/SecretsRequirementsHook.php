<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Relatório de status: avisa quando faltam credenciais obrigatórias e aponta para o cadastro.
 */
final class SecretsRequirementsHook {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_portal.secrets_manager')]
    private readonly SecretsManager $secrets,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /** @return array<string, array<string, mixed>> */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $missing = $this->secrets->missingRequired();
    $title = $this->t('Credenciais do ambiente (ACULTA)');
    if ($missing === []) {
      return ['aculta_secrets' => [
        'title' => $title,
        'value' => $this->t('Todas as credenciais obrigatórias estão configuradas (@env).', ['@env' => $this->secrets->environment()]),
        'severity' => REQUIREMENT_OK,
      ]];
    }
    return ['aculta_secrets' => [
      'title' => $title,
      'value' => $this->t('Faltam: @names. Preencha em /painel-administrativo/aculta/segredos.', ['@names' => implode(', ', $missing)]),
      'severity' => REQUIREMENT_WARNING,
    ]];
  }

}
