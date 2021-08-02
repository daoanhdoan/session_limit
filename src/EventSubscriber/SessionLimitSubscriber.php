<?php
namespace Drupal\session_limit\EventSubscriber;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
/**
 * Redirect .html pages to corresponding Node page.
 */
class SessionLimitSubscriber implements EventSubscriberInterface {
  /**
   * Redirect pattern based url
   * @param RequestEvent $event
   */
  public function limit(RequestEvent $event) {
    $user = \Drupal::currentUser();
    $config = \Drupal::config('session_limit.settings');
    $user_match = $config->get('include_root_user') ? 0 : 1;
    if ($user->id() > $user_match && !isset($_SESSION['session_limit'])) {
      if (_session_limit_bypass()) {
        // Bypass the session limitation on this page callback.
        return;
      }

      $query = \Drupal::database()->select('sessions', 's')
        // Use distict so that HTTP and HTTPS sessions
        // are considered a single session.
        ->distinct()
        ->fields('s', array('sid'))
        ->condition('s.uid', $user->id());

      if (\Drupal::moduleHandler()->moduleExists('masquerade') && $config->get('masquerade_ignore')) {
        $query->leftJoin('masquerade', 'm', 's.uid = m.uid_as AND s.sid = m.sid');
        $query->isNull('m.sid');
      }

      $active_sessions = $query->countQuery()->execute()->fetchField();
      $max_sessions = session_limit_user_max_sessions();

      if (!empty($max_sessions) && $active_sessions > $max_sessions) {
        session_limit_invoke_session_limit(session_id(), 'collision');
      }
      else {
        // force checking this twice as there's a race condition around session creation.
        // see issue #1176412
        if (!isset($_SESSION['session_limit_checkonce'])) {
          $_SESSION['session_limit_checkonce'] = TRUE;
        }
        else {
          // mark session as verified to bypass this in future.
          $_SESSION['session_limit'] = TRUE;
        }
      }
    }
  }

  /**
   * Listen to kernel.request events and call customRedirection.
   * {@inheritdoc}
   * @return array Event names to listen to (key) and methods to call (value)
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('limit');
    return $events;
  }
}
