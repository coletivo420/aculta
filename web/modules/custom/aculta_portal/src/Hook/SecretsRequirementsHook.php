<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Component\Utility\DeprecationHelper;
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

  /**
   * Informa o estado das credenciais obrigatórias do ambiente.
   *
   * @return array<string, array<string, mixed>>
   *   Requisitos de runtime indexados pelo identificador.
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $missing = $this->secrets->missingRequired();
    $title = $this->t('Credenciais do ambiente (ACULTA)');
    if ($missing === []) {
      return ['aculta_secrets' => [
        'title' => $title,
        'value' => $this->t('Todas as credenciais obrigatórias estão configuradas (@env).', ['@env' => $this->secrets->environment()]),
        'severity' => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => RequirementSeverity::OK, fn() => REQUIREMENT_OK),
      ]];
    }
    return ['aculta_secrets' => [
      'title' => $title,
      'value' => $this->t('Faltam: @names. Preencha em /painel-administrativo/aculta/segredos.', ['@names' => implode(', ', $missing)]),
      'severity' => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => RequirementSeverity::Warning, fn() => REQUIREMENT_WARNING),
    ]];
  }

}
