<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKeyRegistry;
use Drupal\aculta_wiki\Adapter\WikiContributionAdapter;
use Drupal\aculta_wiki\Service\WikiEditorialEventQueue;
use Drupal\aculta_wiki\Service\WikiProposalDecision;
use Drupal\aculta_wiki\Service\WikiProposalSubmission;
use Drupal\aculta_wiki\Service\WikiContributionMigrationGate;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Tests Wiki-specific source and application context validation.
 */
final class WikiContributionAdapterTest extends UnitTestCase {

  /**
   * List choices remain explicitly tied to selected, allowlisted fields. */
  public function testValidatesListApplicationModes(): void {
    $adapter = $this->adapter();

    self::assertSame(
      ['list_modes' => ['field_wiki_references' => 'append']],
      $adapter->validateDecisionData(
        'update',
        ['field_wiki_references'],
        ['list_modes' => ['field_wiki_references' => 'append']],
      ),
    );
    $this->expectException(UnprocessableEntityHttpException::class);
    $adapter->validateDecisionData(
      'update',
      ['title'],
      ['list_modes' => ['field_wiki_references' => 'append']],
    );
  }

  /**
   * Every selected list field must carry an explicit merge mode. */
  public function testRequiresModeForSelectedListField(): void {
    $this->expectException(UnprocessableEntityHttpException::class);
    $this->adapter()->validateDecisionData('update', ['field_wiki_categories'], []);
  }

  /**
   * Wiki source context normalizes text and deduplicates safe source URLs. */
  public function testValidatesAndNormalizesWikiProposalContext(): void {
    $context = $this->adapter()->validateSourceData('update', [
      'wiki_intent' => 'reference',
      'proposed_text' => '  Inclua uma fonte recente.  ',
      'source_uris' => ['https://example.test/source', 'https://example.test/source'],
    ]);

    self::assertSame([
      'wiki_intent' => 'reference',
      'proposed_text' => 'Inclua uma fonte recente.',
      'source_uris' => ['https://example.test/source'],
    ], $context);
    $this->expectException(UnprocessableEntityHttpException::class);
    $this->adapter()->validateSourceData('update', [
      'wiki_intent' => 'reference',
      'proposed_text' => 'Texto.',
      'source_uris' => ['javascript:alert(1)'],
    ]);
  }

  /**
   * Creates the adapter with isolated service dependencies. */
  private function adapter(): WikiContributionAdapter {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $domain = new DomainSiteManager(
      $entities,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
      new SiteKeyRegistry(),
    );
    $emailPolicy = new EmailConfirmationPolicy(
      $this->createMock(UserDataInterface::class),
      $entities,
      $this->createMock(ModuleHandlerInterface::class),
    );
    $account = $this->createMock(AccountInterface::class);
    $submission = new WikiProposalSubmission(
      $entities,
      $account,
      $domain,
      $emailPolicy,
      $this->createMock(FloodInterface::class),
      $this->createMock(LockBackendInterface::class),
      $this->createMock(KeyValueExpirableFactoryInterface::class),
      $this->createMock(Connection::class),
    );
    $decision = new WikiProposalDecision(
      $entities,
      $account,
      $this->createMock(Connection::class),
      new WikiEditorialEventQueue($this->createMock(QueueFactory::class), $this->createMock(Connection::class)),
      $domain,
      $this->createMock(StateTransitionValidationInterface::class),
      $this->createMock(ModerationInformationInterface::class),
    );

    $migrationGate = new WikiContributionMigrationGate(
      $this->createMock(Connection::class),
      $entities,
    );
    return new WikiContributionAdapter($entities, $domain, $emailPolicy, $submission, $decision, $migrationGate);
  }

}
