<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Token hook implementations with explicit cacheability and DI.
 */
final class TokenHooks {

  public function __construct(
    #[Autowire('%app.root%')]
    private readonly string $appRoot,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'domain.negotiator')]
    private readonly DomainNegotiatorInterface $domainNegotiator,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'file_url_generator')]
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /** Declares institutional and editorial tokens. */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $tokens = [];
    foreach ([
      'editorial-summary',
      'canonical',
      'image',
      'published',
      'modified',
      'author-type',
      'author-name',
      'start',
      'end',
      'event-status',
      'attendance',
      'organizer',
      'free',
      'session',
    ] as $key) {
      $tokens[$key] = [
        'name' => $this->translation->translate('Editorial: @field', ['@field' => $key]),
        'description' => $this->translation->translate(
          'Derived from structured editorial fields; never from the Drupal account name.',
        ),
      ];
    }

    $institution = [];
    foreach ([
      'name',
      'alternate',
      'tax-id',
      'email',
      'phone',
      'url',
      'description',
      'street',
      'city',
      'region',
      'postal',
      'country',
    ] as $key) {
      $institution[$key] = [
        'name' => $this->translation->translate('Institutional: @field', ['@field' => $key]),
        'description' => $this->translation->translate('Verified public institutional data.'),
      ];
    }

    return [
      'types' => [
        'institution' => [
          'name' => $this->translation->translate('Institutional data'),
          'description' => $this->translation->translate(
            'Public official data from the institutional block.',
          ),
        ],
      ],
      'tokens' => [
        'node' => $tokens,
        'institution' => $institution,
      ],
    ];
  }

  /**
   * Link de confirmação de e-mail (email_confirmer) sempre no host da conta.
   *
   * O módulo monta o link com o host da requisição que criou o pedido; fora da conta, a rota responde 404
   * pela política de purposes. Aqui o link é refeito com o domínio ACCOUNT do ambiente.
   */
  #[Hook('tokens_alter')]
  public function alterEmailConfirmationUrl(array &$replacements, array $context, BubbleableMetadata $metadata): void {
    if (($context['type'] ?? NULL) !== 'aculta-mail-confirmer' || !isset($context['data']['aculta_mail_confirmer'])) {
      return;
    }
    $confirmation = $context['data']['aculta_mail_confirmer'];
    $url = $this->domainPurposeManager->routeUrl('account', 'entity.aculta_mail_confirmer.response_form', [
      'aculta_mail_confirmer' => $confirmation->uuid(),
      'hash' => $confirmation->getHash(),
    ]);
    foreach ($context['tokens'] ?? [] as $name => $original) {
      if ($name === 'confirmation-url' && $url !== NULL && isset($replacements[$original])) {
        $replacements[$original] = $url->toString();
      }
    }
    $metadata->setCacheMaxAge(0);
  }

  /** Replaces institutional/editorial tokens while preserving cacheability. */
  #[Hook('tokens')]
  public function tokens(
    string $type,
    array $tokens,
    array $data,
    array $options,
    BubbleableMetadata $metadata,
  ): array {
    $result = [];

    if ($type === 'institution') {
      $settings = $this->configFactory->get('aculta_portal.settings');
      $metadata->addCacheableDependency($settings);

      $uuid = trim((string) $settings->get('institution_data_uuid'));
      if ($uuid === '') {
        return [];
      }

      $blocks = $this->entityTypeManager
        ->getStorage('block_content')
        ->loadByProperties(['uuid' => $uuid]);
      $block = $blocks ? reset($blocks) : NULL;
      if ($block === NULL) {
        return [];
      }

      $metadata->addCacheableDependency($block);
      $fields = [
        'name' => 'field_org_name',
        'alternate' => 'field_trade_name',
        'tax-id' => 'field_cnpj',
        'email' => 'field_email',
        'phone' => 'field_schema_phone',
        'description' => 'field_org_description',
        'street' => 'field_street_address',
        'city' => 'field_locality',
        'region' => 'field_address_region',
        'postal' => 'field_postal_code',
        'country' => 'field_country',
      ];

      foreach ($tokens as $name => $original) {
        if ($name === 'url') {
          // Endereço principal do ambiente (mapeamento de endereços por ambiente); o campo do bloco guarda o endereço de produção.
          $result[$original] = DeployerEnvironment::siteUrl(dirname($this->appRoot)) ?? (string) ($block->get('field_site')->uri ?? '');
        }
        elseif (isset($fields[$name]) && $block->hasField($fields[$name])) {
          $result[$original] = (string) ($block->get($fields[$name])->value ?? '');
        }
      }
    }

    if ($type !== 'node' || !($data['node'] ?? NULL) instanceof NodeInterface) {
      return $result;
    }

    $node = $data['node'];
    $metadata->addCacheableDependency($node);

    $domainPurpose = 'main';
    if ($node->hasField('field_domain_source') && !$node->get('field_domain_source')->isEmpty()) {
      $domainPurpose = $this->domainPurposeManager->getPurposeForDomainId(
        (string) $node->get('field_domain_source')->target_id,
      ) ?? 'main';
    }

    $get = static fn(string $field): string => $node->hasField($field)
      ? (string) ($node->get($field)->value ?? '')
      : '';
    $date = static fn(string $field): string => $get($field) !== ''
      ? (new \DateTimeImmutable($get($field), new \DateTimeZone('UTC')))->format(DATE_ATOM)
      : '';

    foreach ($tokens as $name => $original) {
      $value = NULL;

      switch ($name) {
        case 'editorial-summary':
          $value = $get('field_summary') ?: (string) ($node->get('body')->summary ?? '');
          break;

        case 'canonical':
          $canonicalUrl = $node->toUrl('canonical');
          $sourceDomain = $node->hasField('field_domain_source')
            ? $node->get('field_domain_source')->entity
            : NULL;
          if ($sourceDomain instanceof DomainInterface) {
            $canonicalUrl->setOption('domain', $sourceDomain);
            $metadata->addCacheableDependency($sourceDomain);
          }

          $activeDomain = $this->domainNegotiator->getActiveDomain();
          $request = $this->requestStack->getCurrentRequest();
          if ($request !== NULL
            && $activeDomain !== NULL
            && isset($activeDomain->alias)
            && $activeDomain->alias->getEnvironment() === 'local') {
            $canonicalUrl->setOption('https', $request->isSecure());
          }

          // The absolute URL reflects the request scheme and host (url.site)
          // and the active alias environment (domain) when no source Domain
          // pins it. Declare both so a page cache entry cannot cross them.
          $metadata->addCacheContexts(['url.site', 'domain']);
          $generated = $canonicalUrl->setAbsolute()->toString(TRUE);
          $metadata->addCacheableDependency($generated);
          $value = $generated->getGeneratedUrl();
          break;

        case 'published':
          $value = $date('field_published_at');
          break;

        case 'modified':
          $value = gmdate(DATE_ATOM, $node->getChangedTime());
          break;

        case 'start':
          $value = $date('field_event_start');
          break;

        case 'end':
          $value = $date('field_event_end');
          break;

        case 'event-status':
          $value = [
            'agendado' => 'https://schema.org/EventScheduled',
            'reagendado' => 'https://schema.org/EventRescheduled',
            'adiado' => 'https://schema.org/EventPostponed',
            'cancelado' => 'https://schema.org/EventCancelled',
            'concluido' => 'https://schema.org/EventScheduled',
          ][$get('field_event_status')] ?? '';
          break;

        case 'attendance':
          $value = [
            'presencial' => 'https://schema.org/OfflineEventAttendanceMode',
            'online' => 'https://schema.org/OnlineEventAttendanceMode',
            'hibrido' => 'https://schema.org/MixedEventAttendanceMode',
          ][$get('field_modality')] ?? '';
          break;

        case 'organizer':
          $value = $get('field_organizer');
          break;

        case 'session':
          // Sessão da Revista = termo de field_editorial_category. Sem termo, cai na Notícias.
          $term = $node->hasField('field_editorial_category')
            ? $node->get('field_editorial_category')->entity
            : NULL;
          if ($term !== NULL) {
            $metadata->addCacheableDependency($term);
          }
          $value = $term?->label() ?: (string) $this->translation->translate('Notícias');
          break;

        case 'free':
          $value = $node->hasField('field_free')
            ? ($get('field_free') === '1' ? 'True' : 'False')
            : '';
          break;

        case 'author-type':
        case 'author-name':
          $author = $node->hasField('field_editorial_author')
            ? $node->get('field_editorial_author')->entity
            : NULL;
          if ($author !== NULL) {
            $metadata->addCacheableDependency($author);
            $value = $name === 'author-name'
              ? $author->label()
              : ($author->get('field_author_kind')->value === 'person' ? 'Person' : 'Organization');
          }
          else {
            $value = '';
          }
          break;

        case 'image':
          $media = $node->hasField('field_media_image')
            ? $node->get('field_media_image')->entity
            : NULL;
          $file = $media && $media->hasField('field_media_image')
            ? $media->get('field_media_image')->entity
            : NULL;
          $value = '';
          if ($file !== NULL && $media->isPublished()) {
            $metadata
              ->addCacheableDependency($media)
              ->addCacheableDependency($file);
            $relative = $this->fileUrlGenerator->generateString($file->getFileUri());
            $imageUrl = str_starts_with($relative, '/')
              ? $this->domainPurposeManager->pathUrl($domainPurpose, $relative)
              : NULL;
            // pathUrl() is built from the purpose Domain entity and the active
            // alias environment, and it returns a plain Url without metadata.
            $purposeDomain = $this->domainPurposeManager->getDomain($domainPurpose);
            if ($purposeDomain !== NULL) {
              $metadata->addCacheableDependency($purposeDomain);
            }
            $metadata->addCacheContexts(['url.site', 'domain']);
            $value = $imageUrl?->toString() ?? $relative;
          }
          break;
      }

      if ($value !== NULL) {
        $result[$original] = $value;
      }
    }

    return $result;
  }

}
