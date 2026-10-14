<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Support\Form;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;

/** Institutional support page, integrated with Commerce Donation Flow. */
final class SupportForm extends FormBase {

  public function __construct(
    private readonly ConfigFactoryInterface $configs,
    private readonly EntityTypeManagerInterface $entities,
    private readonly BlockManagerInterface $blockManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
    );
  }

  public function getFormId(): string {
    return 'aculta_support_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['data-portal-ui'] = 'support-form';
    $form['#attached']['library'][] = 'aculta_portal/support';
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => Html::escape((string) ($this->configs->get('aculta_portal.support')->get('intro') ?: $this->t('Sua contribuição ajuda a manter iniciativas culturais, comunicação, formação e ações de interesse coletivo.'))),
      '#attributes' => ['data-portal-ui' => 'support-intro'],
    ];
    $gateway = $this->entities->getStorage('commerce_payment_gateway')->load('mercado_pago');
    $gateway_configuration = $gateway ? $gateway->getPluginConfiguration() : [];
    $gateway_ready = $gateway && $gateway->status()
      && !empty($gateway_configuration['public_key_test'])
      && !empty($gateway_configuration['access_token_test']);
    if ($gateway_ready) {
      // Donation Flow owns the route and order creation; the portal only
      // presents its native link when the reviewed gateway is enabled.
      $donation_link = $this->blockManager->createInstance(
        'commerce_donation_flow_link',
        ['return_path' => FALSE]
      )->build();
      if (isset($donation_link['#links']['default']['title'])) {
        $donation_link['#links']['default']['title'] = $this->t('Continuar para pagamento');
      }
      $form['donation_flow'] = $donation_link;
    }
    else {
      $form['status'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'message' => [
          '#plain_text' => $this->t('O apoio financeiro está temporariamente indisponível. Nenhuma cobrança será iniciada por esta página.'),
        ],
      ];
    }
    $form['other_support'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'support-other'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $this->t('Outras formas de apoio')],
      'text' => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#value' => $this->t('Parcerias institucionais, apoio cultural e patrocínio de projetos também fortalecem nossas iniciativas.')],
      'contact' => PortalLinkElement::build($this->t('Fale com a Associação'), \Drupal\Core\Url::fromUri('internal:/contato'), ['data-portal-ui' => 'aculta-button-outline']),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // There is intentionally no payment submission until the Commerce flow is
    // implemented and a restricted Commerce gateway is configured.
  }

}
