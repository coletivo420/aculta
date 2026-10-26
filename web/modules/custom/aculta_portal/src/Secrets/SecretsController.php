<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Secrets\Form\SecretClearForm;
use Drupal\aculta_portal\Secrets\Form\SecretEditForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Credenciais do ambiente": duas abas independentes, Produção e Testes.
 *
 * A aba é só uma preferência de visualização. O ambiente de execução vem do ACULTA Deployer e nunca
 * muda com a aba. A aba do ambiente ativo mostra estado e ações; a do outro ambiente mostra só os
 * metadados públicos do contrato, sem consultar valores nem a existência real da credencial.
 *
 * Olho (popup com o valor completo, sob demanda), lápis (popup para alterar) e lixeira (popup para
 * apagar). O valor só aparece dentro do popup, nunca no HTML da página.
 */
final class SecretsController {

  use StringTranslationTrait;

  public function __construct(
    private readonly SecretsManager $secrets,
    private readonly FormBuilderInterface $formBuilder,
    private readonly AccountInterface $account,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /** @return array<string, mixed> */
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
        // dialog.ajax: o lápis abre o popup por link use-ajax com data-dialog-type="modal".
        'library' => ['aculta_portal/requirements-report', 'aculta_portal/secrets-tabs', 'core/drupal.dialog.ajax'],
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
        'text' => ['#markup' => '<p>' . $this->t('Ambiente operacional deste servidor, declarado pelo ACULTA Deployer: @env. As abas só organizam a visualização e não alteram esse ambiente.', ['@env' => $active]) . '</p>'],
      ],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'secrets-tabs', 'class' => ['secrets-tabs']],
        'list' => [
          '#type' => 'container',
          '#attributes' => ['role' => 'tablist', 'aria-label' => (string) $this->t('Ambientes das credenciais'), 'class' => ['secrets-tabs__list']],
        ] + $buttons,
      ] + $panels,
    ];
    if (!$this->secrets->canSaveHere()) {
      $page['storage_notice'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'text' => ['#plain_text' => (string) $this->t('⚠ Atenção: neste ambiente as credenciais são armazenadas no banco de dados, criptografadas. O provisionamento é feito pelo ACULTA Deployer após o deploy; o formulário não grava aqui.')],
      ];
    }
    return $page;
  }

  /**
   * Popup com o valor completo de uma credencial. Resposta do próprio popup (não fica no HTML da página),
   * sem cache e registrada no log com o nome e o usuário. Negada para credenciais de outro ambiente.
   *
   * @return array<string, mixed>
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
      'value' => ['#type' => 'container', 'label' => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#value' => $this->t('Valor de @name:', ['@name' => $name])], 'text' => ['#theme' => 'aculta_portal_element', '#tag' => 'pre', '#element_attributes' => new Attribute(['data-portal-ui' => 'secret-popup-value']), '#value' => Markup::create(htmlspecialchars($value, ENT_QUOTES))]],
      'help' => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#element_attributes' => new Attribute(['data-portal-ui' => 'secret-popup-hint']), '#value' => $this->t('Feche este popup quando terminar.')],
    ];
  }

  /** Popup de alteração de uma credencial do ambiente ativo (rota com link use-ajax modal). */
  public function edit(string $name): array {
    $this->assertInActiveScope($name);
    return $this->formBuilder->getForm(SecretEditForm::class, $name);
  }

  /** Popup de confirmação para apagar o valor de uma credencial do ambiente ativo. */
  public function clear(string $name): array {
    $this->assertInActiveScope($name);
    return $this->formBuilder->getForm(SecretClearForm::class, $name);
  }

  /** Rótulo de cada aba. */
  private function tabLabel(string $environment): string {
    return (string) ($environment === 'production'
      ? $this->t('Ambiente de Produção')
      : $this->t('Ambiente de Testes'));
  }

  /** Recusa, no backend, qualquer ação sobre credencial que não pertence ao ambiente ativo. */
  private function assertInActiveScope(string $name): void {
    if (!$this->secrets->inActiveScope($name)) {
      throw new AccessDeniedHttpException('Credencial de outro ambiente.');
    }
  }

  /**
   * Painel de um ambiente. Só o ambiente ativo recebe estado real e ações; os demais recebem apenas
   * os metadados do contrato, com a indicação de que o estado é gerenciado no ambiente correspondente.
   *
   * @return array<string, mixed>
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
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $this->tabLabel($environment)],
      'table_wrapper' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['secrets-table-scroll']],
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Variável'), $this->t('Key'), $this->t('Obrigatória'), $this->t('Valor salvo'), $this->t('Estado')],
          '#rows' => $rows,
          '#empty' => $this->t('Nenhuma variável deste ambiente no contrato.'),
          '#attributes' => ['data-portal-ui' => 'requirements'],
        ],
      ],
    ];
  }

  /**
   * Nomes declarados em mais de um ambiente (rótulo "compartilhada"). Lido do contrato, sem valores.
   *
   * @return array<string, true>
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
   * Célula do valor: máscara, botão de olho e lápis. O valor completo não entra no HTML; o olho pede
   * ao servidor (rota com token) só quando acionado. O lápis aparece só onde a gravação é possível.
   *
   * @return array<string, mixed>
   */
  private function valueCell(string $name, bool $present, string $environment): array {
    $cell = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'secret-value'],
    ];
    if ($present) {
      $value = $this->secrets->value($name);
      $masked = $value === NULL ? '—' : SecretsManager::mask($value);
      // A rota tem _csrf_token: o gerador de URL já acrescenta ?token=. Não concatenar outro.
      $cell['masked'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['data-portal-ui' => 'secret-value-text']), '#value' => Markup::create(htmlspecialchars($masked, ENT_QUOTES))];
      $cell['eye'] = [
        '#type' => 'link',
        '#title' => '👁',
        '#url' => Url::fromRoute('aculta_portal.secrets_show', ['name' => $name]),
        '#attributes' => [
          'class' => ['use-ajax'], 'data-portal-ui' => 'secret-value-eye',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t('Mostrar valor de @name (@environment)', ['@name' => $name, '@environment' => $environment]),
          'title' => (string) $this->t('Mostrar valor'),
        ],
      ];
    }
    else {
      $cell['masked'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['data-portal-ui' => 'secret-value-text']), '#value' => '—'];
    }
    if ($this->secrets->canSaveHere()) {
      $cell['edit'] = [
        '#type' => 'link',
        '#title' => '✎',
        '#url' => Url::fromRoute('aculta_portal.secrets_edit', ['name' => $name]),
        '#attributes' => [
          'class' => ['use-ajax'], 'data-portal-ui' => 'secret-value-edit',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t('Alterar valor de @name (@environment)', ['@name' => $name, '@environment' => $environment]),
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
          'class' => ['use-ajax'], 'data-portal-ui' => 'secret-value-clear',
          'data-dialog-type' => 'modal',
          'aria-label' => (string) $this->t('Apagar valor de @name (@environment)', ['@name' => $name, '@environment' => $environment]),
          'title' => (string) $this->t('Apagar valor'),
        ],
      ];
    }
    return $cell;
  }

}
