<?php

namespace Drupal\session_limit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SessionLimitSettingsForm extends ConfigFormBase
{

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames()
  {
    return ['session_limit.settings'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId()
  {
    return "session_limit_settings_form";
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array|void
   */
  public function buildForm($form, FormStateInterface $form_state)
  {
    $config = $this->config('session_limit.settings');
    $form['max'] = array(
      '#type' => 'textfield',
      '#title' => t('Default maximum number of active sessions'),
      '#default_value' => $config->get('max'),
      '#size' => 2,
      '#maxlength' => 3,
      '#description' => t('The maximum number of active sessions a user can have. 0 implies unlimited sessions.'),
    );

    $form['include_root_user'] = array(
      '#type' => 'checkbox',
      '#title' => t('Apply limit to root admin user.'),
      '#description' => t('By default session limits do not apply to user #1'),
      '#default_value' => $config->get('include_root_user'),
    );

    $limit_behaviours = array(
      SESSION_LIMIT_DO_NOTHING => t('Do nothing.'),
      SESSION_LIMIT_DROP => t('Automatically drop the oldest sessions without prompting.'),
      SESSION_LIMIT_DISALLOW_NEW => t('Prevent new session.'),
    );

    $form['behaviour'] = array(
      '#type' => 'radios',
      '#title' => t('When the session limit is exceeded'),
      '#default_value' => $config->get('behaviour'),
      '#options' => $limit_behaviours,
    );

    if (\Drupal::moduleHandler()->moduleExists('masquerade')) {
      $form['masquerade_ignore'] = array(
        '#type' => 'checkbox',
        '#title' => t('Ignore masqueraded sessions.'),
        '#description' => t("When a user administrator uses the masquerade module to impersonate a different user, it won't count against the session limit counter"),
        '#default_value' => $config->get('masquerade_ignore'),
      );
    }
    $form['limit_hit_message'] = array(
      '#type' => 'textarea',
      '#title' => t('Session limit has been hit message'),
      '#default_value' => $config->get('limit_hit_message'),
      '#description' => t('The message that is displayed to a user on the current workstation if the session limit has been reached.<br />
      @number is replaced with the maximum number of simultaneous sessions.'),
    );

    $form['logged_out_message_severity'] = array(
      '#type' => 'select',
      '#title' => t('Logged out message severity'),
      '#default_value' => $config->get('logged_out_message_severity'),
      '#options' => array(
        'error' => t('Error'),
        'warning' => t('Warning'),
        'status' => t('Status'),
        '_none' => t('No Message'),
      ),
      '#description' => t('The Drupal message type.  Defaults to Error.'),
    );

    $form['logged_out_message'] = array(
      '#type' => 'textarea',
      '#title' => t('Logged out message'),
      '#default_value' => $config->get('logged_out_message'),
      '#description' => t('The message that is displayed to a user if the workstation has been logged out.<br />
      @number is replaced with the maximum number of simultaneous sessions.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * Settings validation form.
   */
  function validateForm(&$form, FormStateInterface $form_state) {
    $maxsessions = $form_state->getValue('max');
    if (!is_numeric($maxsessions)) {
      $form_state->setErrorByName('max', t('You must enter a number for the maximum number of active sessions'));
    }
    elseif ($maxsessions < 0) {
      $form_state->setErrorByName('max', t('Maximum number of active sessions must be positive'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $values = $form_state->getValues();
    $settings = $this->config('session_limit.settings');

    foreach ($values as $key => $value) {
      $settings->set($key, $value);
    }

    $settings->save();

    parent::submitForm($form, $form_state);
  }
}
