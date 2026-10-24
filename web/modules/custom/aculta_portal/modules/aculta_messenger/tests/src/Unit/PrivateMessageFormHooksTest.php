<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\PrivateMessageFormHooks;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks the Messenger hooks wired into the native Private Message form.
 */
#[CoversClass(PrivateMessageFormHooks::class)]
#[Group('aculta_messenger')]
final class PrivateMessageFormHooksTest extends UnitTestCase {

  /**
   * Registers the flood counter after native persistence.
   */
  public function testRegistersFloodCounterAfterNativeSubmit(): void {
    $hooks = new PrivateMessageFormHooks();
    $form = [
      'message' => [
        'widget' => [
          0 => [
            '#type' => 'text_format',
            'format' => ['format' => []],
          ],
        ],
      ],
      'actions' => ['submit' => []],
    ];

    $hooks->formAlter($form, new FormState(), 'private_message_add_form');

    self::assertSame(
      'aculta_messenger.private_message_form_validator:registerRateLimit',
      end($form['#submit']),
    );
    self::assertSame([
      'aculta_messenger.private_message_form_validator:validateBlockedRecipients',
      'aculta_messenger.private_message_form_validator:validateNewConversationPrivacy',
      'aculta_messenger.private_message_form_validator:validateRateLimit',
    ], array_slice($form['#validate'], -3));
  }

}
