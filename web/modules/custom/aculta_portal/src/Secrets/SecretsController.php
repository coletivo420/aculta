<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

use Drupal\Core\Template\Attribute;
use Drupal\Component\Render\Markup;
use Drupal\aculta_portal\Secrets\Form\SecretClearForm;
use Drupal\aculta_portal\Secrets\Form\SecretEditForm;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Credenciais do ambiente": uma seção para o ambiente de produção e outra para o de testes.
 * A troca de ambiente continua a cargo do ACULTA Deployer; o painel só mostra as duas seções.
 *
 * Olho (popup com o valor completo, sob demanda), lápis (popup para alterar) e lixeira (popup para
 * apagar). O valor só aparece dentro do popup, nunca no HTML da página.
 */
final class SecretsController {

  use StringTranslationTrait;

  private const SECTIONS = ['production', 'test'];

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
    $contract = $this->secrets->contract();
    $status = $this->secrets->status();
    $page = [
      '#attached' => [
        // dialog.ajax: o lápis abre o popup por link use-ajax com data-dialog-type="modal".
        'library' => ['aculta_portal/requirements-report', 'core/drupal.dialog.ajax'],
      ],
      'legend' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'requirements-legend'],
        'ok' => ['#plain_text' => (string) $this->t('✔ OK: chave preenchida no ambiente.')],
        'attention' => ['#plain_text' => (string) $this->t('⚠ Atenção: variável opcional sem valor.')],
        'error' => ['#plain_text' => (string) $this->t('✖ Erro: variável obrigatória sem valor.')],
      ],
      'environment' => ['#markup' => '<p>' . $this->t('Ambiente atual declarado pelo ACULTA Deployer: @env.', ['@env' => $this->secrets->environment()]) . '</p>'],
    ];
    foreach (self::SECTIONS as $environment) {
      $page['section_' . $environment] = $this->section($environment, $contract, $status);
    }
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
   * sem cache e registrada no log com o nome e o usuário.
   *
   * @return array<string, mixed>
   */
  public function show(string $name): array {
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

  /** Popup de alteração de uma credencial (rota com link use-ajax modal). */
  public function edit(string $name): array {
    return $this->formBuilder->getForm(SecretEditForm::class, $name);
  }

  /** Popup de confirmação para apagar o valor de uma credencial. */
  public function clear(string $name): array {
    return $this->formBuilder->getForm(SecretClearForm::class, $name);
  }

  /**
   * Seção de um ambiente: as variáveis do contrato que pertencem a ele. Variáveis compartilhadas
   * aparecem nas duas seções. "Obrigatória" segue o ambiente da seção.
   *
   * @param array<string, mixed> $contract
   * @param array<string, array{key_id: string, required: bool, present: bool}> $status
   * @return array<string, mixed>
   */
  private function section(string $environment, array $contract, array $status): array {
    $label = $environment === 'production'
      ? $this->t('Ambiente de Produção')
      : $this->t('Ambiente de Testes');
    $required = SecretsFormat::requiredNames($contract, $environment);
    $rows = [];
    foreach ($contract['variables'] ?? [] as $variable) {
      $name = (string) $variable['name'];
      $environments = (array) ($variable['environments'] ?? []);
      if (!in_array($environment, $environments, TRUE)) {
        continue;
      }
      $present = $status[$name]['present'] ?? FALSE;
      $is_required = in_array($name, $required, TRUE);
      $state = $present ? 'ok' : ($is_required ? 'error' : 'attention');
      $label_state = match ($state) {
        'ok' => $this->t('✔ OK — preenchida'),
        'attention' => $this->t('⚠ Atenção — opcional sem valor'),
        default => $this->t('✖ Erro — obrigatória sem valor'),
      };
      $name_cell = ['#plain_text' => $name];
      if (count($environments) > 1) {
        $name_cell = [
          '#type' => 'container',
          'name' => ['#plain_text' => $name],
          'shared' => ['#markup' => '<br><small>' . $this->t('compartilhada entre os ambientes') . '</small>'],
        ];
      }
      $rows[] = [
        ['data' => $name_cell],
        ['data' => ['#plain_text' => (string) $variable['key_id']]],
        ['data' => ['#plain_text' => $is_required ? $this->t('Sim') : $this->t('Não')]],
        ['data' => $this->valueCell($name, $present, $environment)],
        [
          'data' => ['#plain_text' => $label_state],
          'data-portal-ui' => 'requirements-status status-' . $state,
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'secrets-section', 'id' => 'credenciais-' . $environment],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $label],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Variável'), $this->t('Key'), $this->t('Obrigatória'), $this->t('Valor salvo'), $this->t('Estado')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma variável deste ambiente no contrato.'),
        '#attributes' => ['data-portal-ui' => 'requirements'],
      ],
    ];
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
