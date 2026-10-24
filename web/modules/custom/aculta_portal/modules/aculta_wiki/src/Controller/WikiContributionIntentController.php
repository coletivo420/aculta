<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Service\WikiProposalSubmission;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Presents only contribution actions backed by real Wiki workflows.
 */
final class WikiContributionIntentController extends ControllerBase {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposes,
    private readonly WikiProposalSubmission $proposalSubmission,
    private readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_wiki.proposal_submission'),
      $container->get('current_user'),
    );
  }

  /**
   * Builds the contextual intention selector for one published Wiki entry.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Target entry from the route.
   *
   * @return array<string, mixed>
   *   Cacheable WE SATIVA intent page.
   */
  public function page(NodeInterface $node): array {
    $result = $this->domainPurposes->runInPurpose('wiki', function () use ($node): array {
      $wikiDomain = $this->domainPurposes->getDomain('wiki');
      $node = $this->entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
      if (!$node instanceof NodeInterface
        || $node->bundle() !== 'wiki_entry'
        || !$node->isDefaultRevision()
        || !$node->isPublished()
        || !$wikiDomain
        || !$node->hasField('field_domain_source')
        || $node->get('field_domain_source')->target_id !== $wikiDomain->id()) {
        throw new AccessDeniedHttpException();
      }

      $viewAccess = $node->access('view', $this->account, TRUE);
      $updateAccess = $node->access('update', $this->account, TRUE);
      if (!$viewAccess->isAllowed()) {
        throw new AccessDeniedHttpException();
      }

      $canEdit = $updateAccess->isAllowed();
      if (!$canEdit) {
        $this->proposalSubmission->assertCanSubmit();
      }

      return [
        'node' => $node,
        'can_edit' => $canEdit,
        'cacheability' => (new CacheableMetadata())
          ->addCacheContexts(['user', 'user.permissions', 'domain'])
          ->addCacheableDependency($node)
          ->addCacheableDependency($wikiDomain)
          ->addCacheableDependency($viewAccess)
          ->addCacheableDependency($updateAccess),
      ];
    });
    $target = is_array($result) ? ($result['node'] ?? NULL) : NULL;
    if (!$target instanceof NodeInterface) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\Core\Cache\CacheableMetadata $cacheability */
    $cacheability = $result['cacheability'];
    $backUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $target->id()]);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'we-sativa-intent'],
      'header' => [
        '#type' => 'component',
        '#component' => 'aculta420:context-header',
        '#props' => [
          'eyebrow' => 'WE SATIVA · Wiki420',
          'title' => (string) $this->t('Como você quer contribuir?'),
          'subtitle' => (string) $target->label(),
        ],
      ],
      'notice' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $result['can_edit']
          ? $this->t('A publicação continua sujeita ao fluxo editorial da Wiki420.')
          : $this->t('Sua sugestão será analisada antes de aparecer publicamente.'),
      ],
    ];
    if ($backUrl !== NULL) {
      $build['header']['#slots']['back_link'] = Link::fromTextAndUrl($this->t('Voltar ao verbete'), $backUrl)->toRenderable();
    }

    $cards = [];
    if ($result['can_edit']) {
      $editUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.edit_form', ['node' => $target->id()]);
      if ($editUrl !== NULL) {
        $cards[] = $this->intentCard(
          $this->t('Editar verbete completo'),
          $this->t('Revise o conteúdo inteiro pela edição oficial do verbete.'),
          Link::fromTextAndUrl($this->t('Editar este verbete'), $editUrl)->toRenderable(),
        );
      }
    }
    else {
      $descriptions = [
        'correction' => $this->t('Aponte uma informação que precisa ser corrigida.'),
        'reference' => $this->t('Sugira uma fonte para apoiar o conteúdo.'),
        'improve' => $this->t('Proponha uma melhoria no texto ou na organização.'),
        'image' => $this->t('Sugira uma imagem já disponível e publicada.'),
      ];
      foreach (array_keys(WikiProposal::INTENTS) as $intent) {
        $url = $this->domainPurposes->routeUrl('wiki', 'aculta_wiki.proposal_submit', ['node' => $target->id()]);
        if ($url === NULL) {
          continue;
        }
        $url->setOption('query', ['intent' => $intent]);
        $intentLabel = match ($intent) {
          'correction' => $this->t('Corrigir informação'),
          'reference' => $this->t('Acrescentar fonte'),
          'improve' => $this->t('Melhorar conteúdo'),
          default => $this->t('Sugerir imagem'),
        };
        $cards[] = $this->intentCard(
          $intentLabel,
          $descriptions[$intent],
          Link::fromTextAndUrl($this->t('Escolher @intent', ['@intent' => $intentLabel]), $url)->toRenderable(),
        );
      }
    }

    $cardItems = $cards === []
      ? ['#plain_text' => $this->t('Nenhuma forma de contribuição está disponível para este verbete.')]
      : $cards;
    $build['choices'] = [
      '#type' => 'component',
      '#component' => 'aculta420:content-section',
      '#props' => ['title' => (string) $this->t('Escolha uma forma de contribuir')],
      '#slots' => [
        'body' => [
          '#type' => 'component',
          '#component' => 'aculta420:content-grid',
          '#props' => ['columns' => 2],
          '#slots' => ['items' => $cardItems],
        ],
      ],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds one intention card with its real, authorized destination.
   */
  private function intentCard(\Stringable|string $title, \Stringable|string $description, array $link): array {
    return [
      '#type' => 'component',
      '#component' => 'aculta420:card',
      '#slots' => [
        'header' => ['#plain_text' => $title],
        'content' => ['#plain_text' => $description],
        'footer' => $link,
      ],
    ];
  }

}
