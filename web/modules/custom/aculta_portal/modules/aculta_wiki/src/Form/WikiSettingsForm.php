<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\aculta_wiki\Notifier\WikiReviewNotifier;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tela administrativa da Wiki: avisos de revisão e caixas editoriais.
 *
 * Grava `aculta_wiki.settings` pela Configuration API. Não há arquivo em
 * `config/sync` nem em `config/install`: os padrões (aviso desligado, nenhum
 * destinatário) vivem no código até alguém decidir o contrário nesta tela.
 */
final class WikiSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_wiki_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [WikiReviewNotifier::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(WikiReviewNotifier::CONFIG);

    if (!$this->moduleHandler->moduleExists('aculta_mailer')) {
      $form['mailer_missing'] = [
        '#type' => 'item',
        '#markup' => $this->t('O <code>aculta_mailer</code> não está habilitado. Os avisos podem ser configurados, mas não serão enviados enquanto o canal estiver desligado.'),
      ];
    }

    $form['review'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Avisos de revisão'),
      '#description' => $this->t('Quando um verbete passa a "Em revisão", um e-mail avisa as caixas editoriais. O verbete não cria tópico no Fórum, e o aviso não leva o nome de quem editou.'),
    ];
    $form['review'][WikiReviewNotifier::ENABLED_KEY] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Avisar as caixas editoriais quando um verbete entrar em revisão'),
      '#default_value' => (bool) $config->get(WikiReviewNotifier::ENABLED_KEY),
    ];
    $form['review'][WikiReviewNotifier::RECIPIENTS_KEY] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endereços que recebem os avisos'),
      '#default_value' => (string) $config->get(WikiReviewNotifier::RECIPIENTS_KEY),
      '#maxlength' => 512,
      '#description' => $this->t('Um ou mais endereços, separados por vírgula, ponto e vírgula ou espaço. Use caixas da curadoria, não o endereço de uma pessoa sem que ela saiba: o <code>aculta_mailer</code> guarda HMAC do destinatário por 90 dias.'),
    ];
    $form['review']['lgpd'] = [
      '#type' => 'item',
      '#markup' => $this->t('O aviso leva o título do verbete, o estado e o link de edição. Não leva autoria nem conteúdo novo. A busca do site indexa só verbetes publicados, e os termos buscados não são gravados. Ver <code>docs/compliance/LGPD-INVENTARIO.md</code>.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $raw = (string) $form_state->getValue(WikiReviewNotifier::RECIPIENTS_KEY);
    $valid = WikiReviewNotifier::parseRecipients($raw);
    $invalid = $this->invalidTokens($raw);
    if ($invalid !== []) {
      $form_state->setErrorByName(
        WikiReviewNotifier::RECIPIENTS_KEY,
        $this->t('Endereço inválido: @list.', ['@list' => implode(', ', $invalid)]),
      );
    }
    if ($form_state->getValue(WikiReviewNotifier::ENABLED_KEY) && $valid === []) {
      $form_state->setErrorByName(
        WikiReviewNotifier::RECIPIENTS_KEY,
        $this->t('Informe ao menos um endereço válido para ligar os avisos.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(WikiReviewNotifier::CONFIG)
      ->set(WikiReviewNotifier::ENABLED_KEY, (bool) $form_state->getValue(WikiReviewNotifier::ENABLED_KEY))
      ->set(WikiReviewNotifier::RECIPIENTS_KEY, implode(', ', WikiReviewNotifier::parseRecipients((string) $form_state->getValue(WikiReviewNotifier::RECIPIENTS_KEY))))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Tokens digitados que não são endereço válido, para a mensagem de erro.
   *
   * @return list<string>
   *   Tokens inválidos, sem repetição.
   */
  private function invalidTokens(string $raw): array {
    $invalid = [];
    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $candidate) {
      if (filter_var(strtolower(trim($candidate)), FILTER_VALIDATE_EMAIL) === FALSE) {
        $invalid[trim($candidate)] = trim($candidate);
      }
    }
    return array_values($invalid);
  }

}
