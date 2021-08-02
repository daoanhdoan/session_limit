<?php
namespace Drupal\session_limit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SessionLimitForm extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "session_limit_form";
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $user = \Drupal::currentUser();
    $sid = \Drupal::service('session')->getId();

    $settings = $this->config('session_limit.settings');

    if ($settings->get('behaviour') == SESSION_LIMIT_DISALLOW_NEW) {
      session_destroy();
      user_logout();
      return;
    }

    $result = \Drupal::database()->query('SELECT * FROM {sessions} WHERE uid = :uid', array(':uid' => $user->id()));

    $active_sessions = array();
    $session_references = array();

    foreach ($result as $session_reference => $obj) {
      $active_sessions[$session_reference] = $obj->sid;

      $message = $sid == $obj->sid ? t('Your current session.') : '';

      $session_references[$session_reference] = t('<strong>Host:</strong> %host (idle: %time) <b>@message</b>',
        array(
          '%host' => $obj->hostname,
          '@message' => $message,
          '%time' => \Drupal::service('date.formatter')->formatInterval(time() - $obj->timestamp))
      );
    }

    $form['active_sessions'] = array(
      '#type' => 'value',
      '#value' => $active_sessions,
    );

    $form['session_reference'] = array(
      '#type' => 'radios',
      '#title' => t('Select a session to disconnect.'),
      '#options' => $session_references,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Disconnect session'),
    );

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $session_reference = $form_state->getValue('session_reference');
    $active_sessions = $form_state->getValue('active_sessions');
    $sid = $active_sessions[$session_reference];

    if (\Drupal::service('session')->getId() == $sid) {
      $this->redirect('user.logout');
    }
    else {
      session_limit_invoke_session_limit($sid, 'disconnect');
      $this->redirect('<front>');
    }
  }
}
