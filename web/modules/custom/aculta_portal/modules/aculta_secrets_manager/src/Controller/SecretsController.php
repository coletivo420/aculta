<?php

declare(strict_types=1);

namespace Drupal\aculta_secrets_manager\Controller;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_secrets_manager\Form\SecretClearForm;
use Drupal\aculta_secrets_manager\Form\SecretEditForm;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Two independent tabs for production and test environment credentials.
 *
 * The tab is a display preference only. The runtime environment comes from
 * ACULTA Deployer and never changes with the selected tab. The active
 * environment shows status and actions; the other shows public contract
 * metadata without querying credential values or their presence.
 *
 * The value is shown only on explicit reveal and never in the page HTML.
 */
final class SecretsController {

  use StringTranslationTrait;

  /**
   * Constructs the panel controller.
   */
  public function __construct(
    private readonly SecretsManager $secrets,
    private readonly FormBuilderInterface $formBuilder,
    private readonly AccountInterface $account,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Builds the credentials panel render array.
   *
   * @return array<string, mixed>
   *   The render array for both environment tabs.
   */
  public function page(): array {
    $active = $this->secrets->environment();
    $buttons = [];
    $panels = [];
    foreach (SecretsManager::ENVIRONMENTS as $environment) {
      $is_active = $environment === $active;
      $tab_id = 'credenciais-tab-' . $environment;
      $buttons[$environment] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->tabLabel($environment),
        '#attributes' => [
          'type' => 'button',
          'role' => 'tab',
          'id' => $tab_id,
          'aria-controls' => 'credenciais-' . $environment,
          'aria-selected' => $is_active ? 'true' : 'false',
          'tabindex' => $is_active ? '0' : '-1',
          'class' => ['secrets-tabs__tab'],
          'data-portal-ui' => 'secrets-tab',
        ],
      ];
      $panels['section_' . $environment] = $this->section($environment, $is_active, $tab_id);
    }
    $page = [
      '#attached' => [
        // Dialog AJAX handles the use-ajax modal links.
        'library' => [
          'aculta_portal/requirements-report',
          'aculta_secrets_manager/secrets-tabs',
          'core/drupal.dialog.ajax',
        ],
      ],
      'legend' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'requirements-legend'],
        'ok' => ['#plain_text' => (string) $this->t('✔ OK: chave preenchida no ambiente.')],
        'attention' => ['#plain_text' => (string) $this->t('⚠ Atenção: variável opcional sem valor.')],
        'error' => ['#plain_text' => (string) $this->t('✖ Erro: variável obrigatória sem valor.')],
      ],
      'environment' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'secrets-environment'],
        'text' => [
          '#markup' => '<p>' . $this->t(
            'Ambiente operacional deste servidor, declarado pelo ACULTA Deployer: @env. As abas só organizam a visualização e não alteram esse ambiente.',
            ['@env' => $active],
          ) . '</p>',
        ],
      ],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'secrets-tabs', 'class' => ['secrets-tabs']],
        'list' => [
          '#type' => 'container',
          '#attributes' => [
            'role' => 'tablist',
            'aria-label' => (string) $this->t('Ambientes das credenciais'),
            'class' => ['secrets-tabs__list'],
          ],
        ] + $buttons,
      ] + $panels,
    ];
    if (!$this->secrets->canSaveHere()) {
      $page['storage_notice'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'text' => [
          '#plain_text' => (string) $this->t(
            '⚠ Atenção: neste ambiente as credenciais são armazenadas no banco de dados, criptografadas. O provisionamento é feito pelo ACULTA Deployer após o deploy; o formulário não grava aqui.',
          ),
        ],
      ];
    }
    return $page;
  }

  /**
   * Shows a credential value in a non-cacheable popup.
   *
   * The value is logged only by credential name and user. Requests for a
   * credential outside the active environment are denied.
   *
   * @return array<string, mixed>
   *   A render array containing the escaped credential value.
   */
  public function show(string $name): array {
    $this->assertInActiveScope($name);
    $value = $this->secrets->value($name);
    if ($value === NULL) {
      throw new NotFoundHttpException();
    }
    $this->secrets->logReveal($name, (string) $this->account->getAccountName());
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'secret-dialog'],
      '#cache' => ['max-age' => 0, 'contexts' => ['user']],
      'value' => [
        '#type' => 'container',
        'label' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'p',
          '#value' => $this->t('Valor de @name:', ['@name' => $name]),
        ],
        'text' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'pre',
          '#element_attributes' => new Attribute([
            'data-portal-ui' => 'secret-popup-value',
          ]),
          '#value' => Markup::create(htmlspecialchars($value, ENT_QUOTES)),
        ],
      ],
      'help' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'p',
        '#element_attributes' => new Attribute([
          'data-portal-ui' => 'secret-popup-hint',
        ]),
        '#value' => $this->t('Feche este popup quando terminar.'),
      ],
    ];
  }

  /**
   * Builds the edit form for an active-environment credential.
   */
  public function edit(string $name): array {
    $this->assertInActiveScope($name);
    return $this->formBuilder->getForm(SecretEditForm::class, $name);
  }

  /**
   * Builds a confirmation form to clear an active-environment credential.
   */
  public function clear(string $name): array {
    $this->assertInActiveScope($name);
    return $this->formBuilder->getForm(SecretClearForm::class, $name);
  }

  /**
   * Returns the display label for an environment tab.
   */
  private function tabLabel(string $environment): string {
    return (string) ($environment === 'production'
      ? $this->t('Ambiente de Produção')
      : $this->t('Ambiente de Testes'));
  }

  /**
   * Denies actions for credentials outside the active environment.
   */
  private function assertInActiveScope(string $name): void {
    if (!$this->secrets->inActiveScope($name)) {
      throw new AccessDeniedHttpException('Credencial de outro ambiente.');
    }
  }

  /**
   * Builds a panel for one credential environment.
   *
   * Only the active environment receives live status and actions. Other
   * environments show contract metadata only.
   *
   * @return array<string, mixed>
   *   The environment panel render array.
   */
  private function section(string $environment, bool $is_active, string $tab_id): array {
    $shared = $this->sharedNames();
    $rows = [];
    foreach ($this->secrets->statusForEnvironment($environment) as $name => $row) {
      $name_cell = ['#plain_text' => $name];
      if (isset($shared[$name])) {
        $name_cell = [
          '#type' => 'container',
          'name' => ['#plain_text' => $name],
          'shared' => ['#markup' => '<br><small>' . $this->t('compartilhada entre os ambientes') . '</small>'],
        ];
      }
      if ($is_active) {
        $state = $row['state'];
        $state_class = $state === 'configured' ? 'ok' : ($state === 'missing_optional' ? 'attention' : 'error');
        $label_state = match ($state) {
          'configured' => $this->t('✔ OK — preenchida'),
          'missing_optional' => $this->t('⚠ Atenção — opcional sem valor'),
          default => $this->t('✖ Erro — obrigatória sem valor'),
        };
        $value_cell = $this->valueCell($name, (bool) $row['present'], $environment);
      }
      else {
        $state_class = 'elsewhere';
        $label_state = $this->t('Gerenciado no ambiente correspondente.');
        $value_cell = ['#plain_text' => $this->t('Não consultado neste servidor')];
      }
      $rows[] = [
        ['data' => $name_cell],
        ['data' => ['#plain_text' => $row['key_id']]],
        ['data' => ['#plain_text' => $row['required'] ? $this->t('Sim') : $this->t('Não')]],
        ['data' => $value_cell],
        [
          'data' => ['#plain_text' => $label_state],
          'data-portal-ui' => 'requirements-status status-' . $state_class,
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'secrets-section',
        'id' => 'credenciais-' . $environment,
        'role' => 'tabpanel',
        'aria-labelledby' => $tab_id,
        'class' => ['secrets-tabs__panel'],
      ],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h2',
        '#value' => $this->tabLabel($environment),
      ],
      'table_wrapper' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['secrets-table-scroll']],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Variável'),
            $this->t('Key'),
            $this->t('Obrigatória'),
            $this->t('Valor salvo'),
            $this->t('Estado'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('Nenhuma variável deste ambiente no contrato.'),
          '#attributes' => ['data-portal-ui' => 'requirements'],
        ],
      ],
    ];
  }

  /**
   * Gets credential names shared across environments.
   *
   * The public contract metadata is read without resolving any values.
   *
   * @return array<string, true>
   *   A set of credential names present in multiple environments.
   */
  private function sharedNames(): array {
    $shared = [];
    foreach ($this->secrets->contract()['variables'] ?? [] as $variable) {
      if (count((array) ($variable['environments'] ?? [])) > 1) {
        $shared[(string) $variable['name']] = TRUE;
      }
    }
    return $shared;
  }

  /**
   * Builds the masked value and credential action render elements.
   *
   * The full value is loaded only for the active environment and is not placed
   * in the initial HTML. Edit actions require write access.
   *
   * @return array<string, mixed>
   *   Render elements for the value and allowed actions.
   */
  private function valueCell(string $name, bool $present, string $environment): array {
    $cell = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'secret-value'],
    ];
    if ($present) {
      $value = $this->secrets->value($name);
      $masked = $value === NULL ? '—' : SecretsManager::mask($value);
      // The route adds its CSRF token to the generated URL.
      $cell['masked'] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'span',
        '#element_attributes' => new Attribute([
          'data-portal-ui' => 'secret-value-text',
        ]),
        '#value' => Markup::create(htmlspecialchars($masked, ENT_QUOTES)),
      ];
      $cell['eye'] = [
        '#type' => 'link',
        '#title' => '👁',
        '#url' => Url::fromRoute('aculta_portal.secrets_show', ['name' => $name]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-portal-ui' => 'secret-value-eye',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t(
            'Mostrar valor de @name (@environment)',
            ['@name' => $name, '@environment' => $environment],
          ),
          'title' => (string) $this->t('Mostrar valor'),
        ],
      ];
    }
    else {
      $cell['masked'] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'span',
        '#element_attributes' => new Attribute([
          'data-portal-ui' => 'secret-value-text',
        ]),
        '#value' => '—',
      ];
    }
    if ($this->secrets->canSaveHere()) {
      $cell['edit'] = [
        '#type' => 'link',
        '#title' => '✎',
        '#url' => Url::fromRoute('aculta_portal.secrets_edit', ['name' => $name]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-portal-ui' => 'secret-value-edit',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t(
            'Alterar valor de @name (@environment)',
            ['@name' => $name, '@environment' => $environment],
          ),
          'title' => (string) $this->t('Alterar valor'),
        ],
      ];
    }
    if ($this->secrets->canSaveHere() && $present) {
      $cell['clear'] = [
        '#type' => 'link',
        '#title' => '🗑',
        '#url' => Url::fromRoute('aculta_portal.secrets_clear', ['name' => $name]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-portal-ui' => 'secret-value-clear',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t(
            'Apagar valor de @name (@environment)',
            ['@name' => $name, '@environment' => $environment],
          ),
          'title' => (string) $this->t('Apagar valor'),
        ],
      ];
    }
    return $cell;
  }

}
