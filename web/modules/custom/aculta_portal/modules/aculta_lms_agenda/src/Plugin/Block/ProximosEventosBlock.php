<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Plugin\Block;

use Drupal\aculta_lms_agenda\AgendaEntriesProvider;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Learning panel with upcoming deadlines and events for the user's groups.
 *
 * Only enrolled users see entries. Visitors and users without groups see an
 * empty block.
 */
#[Block(
  id: 'aculta_lms_agenda_proximos',
  admin_label: new TranslatableMarkup('Próximos prazos e eventos das turmas'),
  category: new TranslatableMarkup('ACULTA LMS'),
)]
final class ProximosEventosBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Quantidade de itens no painel.
   */
  private const LIMITE = 5;

  /**
   * Janela à frente de agora.
   */
  private const DIAS = 60;

  /**
   * Construtor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AgendaEntriesProvider $agenda,
    private readonly AccountInterface $usuario,
    private readonly TimeInterface $tempo,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_lms_agenda.entries'),
      $container->get('current_user'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $ate = $agora->modify('+' . self::DIAS . ' days');

    $entradas = array_slice($this->agenda->forAccount($this->usuario, $agora, $ate), 0, self::LIMITE);
    $itens = [];
    foreach ($entradas as $entrada) {
      $texto = $entrada['inicio']->format('d/m/Y H:i') . ' — ' . $entrada['titulo'];
      if ($entrada['cancelada']) {
        $texto .= ' (cancelado)';
      }
      $itens[] = ['#markup' => $texto];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum prazo ou evento nos próximos @dias dias.', ['@dias' => self::DIAS]),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['aculta_lms_agenda'],
        'max-age' => 900,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
