<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\aculta_lms_h5p\Event\aculta_H5PFinishedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Handles authenticated, course-scoped H5P progress requests. */
final class aculta_H5PAJAX extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly aculta_H5PCourseAccessManager $courseAccess,
  ) {}

  public static function create(ContainerInterface $container): static {
    $controller = new static(
      $container->get('database'),
      $container->get('event_dispatcher'),
      $container->get('aculta_lms_h5p.course_access'),
    );
    return $controller;
  }

  /** Stores a completed score after validating membership and the H5P token. */
  public function setFinished(int $group_id, Request $request): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $content_id = $request->request->getInt('contentId');
    $score = $request->request->getInt('score', -1);
    $max_score = $request->request->getInt('maxScore', -1);
    $opened = $request->request->getInt('opened');
    $token = (string) $request->query->get('token', '');

    if (!$uid || $content_id < 1 || $score < 0 || $max_score < 0 || $score > $max_score || $opened < 1 || !\H5PCore::validToken('result', $token) || !$this->courseAccess->studentAccess($group_id, $content_id, $this->currentUser())->isAllowed()) {
      return $this->privateResponse(['success' => FALSE, 'message' => $this->t('Solicitação inválida.')], 403);
    }

    $existing = $this->database->select('h5p_points', 'p')
      ->fields('p', ['started'])
      ->condition('content_id', $content_id)
      ->condition('uid', $uid)
      ->execute()->fetchField();
    $fields = [
      'content_id' => $content_id,
      'uid' => $uid,
      'finished' => time(),
      'points' => $score,
      'max_points' => $max_score,
    ];
    if ($existing === FALSE) {
      $fields['started'] = $opened;
    }
    $this->database->merge('h5p_points')->keys(['uid' => $uid, 'content_id' => $content_id])->fields($fields)->execute();
    $this->eventDispatcher->dispatch(new aculta_H5PFinishedEvent($fields), aculta_H5PFinishedEvent::FINISHED_EVENT);
    return $this->privateResponse(['success' => TRUE]);
  }

  /** Loads or saves user state for content accessible through this course. */
  public function contentUserData(int $group_id, int $content_main_id, string $data_id, int $sub_content_id, Request $request): JsonResponse {
    $user = $this->currentUser();
    if (!$user->isAuthenticated() || $content_main_id < 1 || !preg_match('/^[a-zA-Z0-9_.-]{1,128}$/', $data_id) || $sub_content_id < 0 || !$this->courseAccess->studentAccess($group_id, $content_main_id, $user)->isAllowed() || !\H5PCore::validToken('contentuserdata', (string) $request->query->get('token', ''))) {
      return $this->privateResponse(['success' => FALSE, 'message' => $this->t('Solicitação inválida.')], 403);
    }

    if ($request->request->has('data')) {
      $data = (string) $request->request->get('data');
      if (strlen($data) > 1048576) {
        return $this->privateResponse(['success' => FALSE], 413);
      }
      $delete = $data === '0';
      if ($delete) {
        $this->database->delete('h5p_content_user_data')->condition('content_main_id', $content_main_id)->condition('data_id', $data_id)->condition('user_id', $user->id())->condition('sub_content_id', $sub_content_id)->execute();
      }
      else {
        $values = [
          'timestamp' => time(),
          'data' => $data,
          'preloaded' => $request->request->get('preload') === '0' ? 0 : 1,
          'delete_on_content_change' => $request->request->get('invalidate') === '0' ? 0 : 1,
        ];
        $this->database->merge('h5p_content_user_data')
          ->keys([
            'user_id' => (int) $user->id(),
            'content_main_id' => $content_main_id,
            'sub_content_id' => $sub_content_id,
            'data_id' => $data_id,
          ])
          ->fields($values)
          ->execute();
      }
      Cache::invalidateTags(['h5p_content:' . $content_main_id]);
      return $this->privateResponse(['success' => TRUE]);
    }

    $data = $this->database->select('h5p_content_user_data', 'd')->fields('d', ['data'])->condition('user_id', $user->id())->condition('content_main_id', $content_main_id)->condition('sub_content_id', $sub_content_id)->condition('data_id', $data_id)->execute()->fetchField();
    return $this->privateResponse(['success' => TRUE, 'data' => $data === FALSE ? NULL : $data]);
  }

  /** Returns personalized progress data with browser and proxy caching disabled. */
  private function privateResponse(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
