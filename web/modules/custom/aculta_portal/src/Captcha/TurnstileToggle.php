<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Captcha;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Liga e desliga o Turnstile do site (política de CAPTCHA do Portal).
 *
 * A fonte da verdade é a configuração do CAPTCHA e os pontos com o desafio
 * Turnstile. O serviço não mantém configuração própria. Ao mudar, aplica a
 * decisão ao CAPTCHA global e aos pontos de cadastro, login, recuperação de
 * senha e contato. O segredo não passa por aqui: só verificamos se há chave.
 */
final class TurnstileToggle {

  public const CHALLENGE = 'turnstile/Turnstile';

  public const KEY_ID = 'turnstile';

  /**
   * Lists purchase forms that never receive CAPTCHA.
   *
   * Abuse protection uses flood control and business rules.
   */
  public const COMMERCE_FORM_IDS = [
    'commerce_order_item_add_to_cart_form',
    'views_form_commerce_cart_form_default',
    'commerce_checkout_flow',
    'commerce_checkout_flow_default',
    'commerce_checkout_flow_fluxo_doacao',
  ];

  /**
   * Lists forms that always bypass Turnstile, including commerce flows.
   *
   * See docs/portal/TURNSTILE.md for the complete list and rationale.
   */
  public const EXCEPTION_FORM_IDS = [
    'commerce_order_item_add_to_cart_form',
    'views_form_commerce_cart_form_default',
    'commerce_checkout_flow',
    'commerce_checkout_flow_default',
    'commerce_checkout_flow_fluxo_doacao',
    'aculta_support_form',
    'aculta_login_popup_form',
    'aculta_register_popup_form',
    'aculta_messenger_new_conversation',
    'private_message_add_form',
    'aculta_messenger_conversation_relationship',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly KeyRepositoryInterface $keys,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Checks whether the global CAPTCHA challenge is enabled.
   */
  public function isEnabled(): bool {
    return (bool) $this->configFactory->get('captcha.settings')->get('enable_globally');
  }

  /**
   * Checks whether the key exists without revealing its value.
   */
  public function keyConfigured(): bool {
    $value = $this->keys->getKey(self::KEY_ID)?->getKeyValue();
    return is_string($value) && $value !== '';
  }

  /**
   * Applies the decision and logs the account that changed it.
   */
  public function setEnabled(bool $enabled, string $account): void {
    $this->configFactory->getEditable('captcha.settings')->set('enable_globally', $enabled)->save();
    foreach ($this->entityTypeManager->getStorage('captcha_point')->loadByProperties(['captchaType' => self::CHALLENGE]) as $point) {
      if (in_array($point->id(), self::EXCEPTION_FORM_IDS, TRUE) || self::isCommercePurchaseForm($point->id())) {
        if ($point->status()) {
          $point->setStatus(FALSE)->save();
        }
        continue;
      }
      if ((bool) $point->status() !== $enabled) {
        $point->setStatus($enabled)->save();
      }
    }
    $this->loggerFactory->get('aculta_portal')->notice('Turnstile @state por @account.', [
      '@state' => $enabled ? 'ativado' : 'desativado',
      '@account' => $account,
    ]);
  }

  /**
   * Determines whether a form ID belongs to any Commerce purchase step.
   */
  public static function isCommercePurchaseForm(string $form_id): bool {
    return in_array($form_id, self::COMMERCE_FORM_IDS, TRUE)
      || str_starts_with($form_id, 'commerce_order_item_add_to_cart_form_')
      || str_starts_with($form_id, 'commerce_checkout_flow_')
      || str_starts_with($form_id, 'views_form_commerce_cart_form');
  }

}
