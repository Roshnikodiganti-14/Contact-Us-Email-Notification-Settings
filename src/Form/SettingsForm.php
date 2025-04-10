<?php

namespace Drupal\contactus_email_setting\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configuration form for contact us email settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs the settings form.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['contactus_email_setting.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'settings_form_contact_us';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('contactus_email_setting.settings');
    $read_only = !$this->currentUser->hasPermission('edit_contactus_email_setting_permission');

    // Field definitions.
    $fields = [
      'email_address' => [
        'type' => 'textfield',
        'title' => $this->t('Internal Email Address'),
        'description' => $this->t('Enter multiple email addresses separated by commas.'),
        'attributes' => ['title' => $this->t('Upon Contact Us submission, a notification will be sent to this email address.')],
      ],
      'email_subject' => [
        'type' => 'textfield',
        'title' => $this->t('Internal Subject'),
        'description' => $this->t('Subject for internal email notifications.'),
        'attributes' => ['title' => $this->t('Subject line for internal notifications.')],
      ],
      'email_message' => [
        'type' => 'textarea',
        'title' => $this->t('Internal Message for Authenticated User'),
        'description' => $this->t('Message body for authenticated users.'),
        'attributes' => ['title' => $this->t('Body of the internal message for authenticated users.')],
      ],
      'email_message_Anonymous' => [
        'type' => 'textarea',
        'title' => $this->t('Internal Message for Anonymous User'),
        'description' => $this->t('Message body for anonymous users.'),
        'attributes' => ['title' => $this->t('Body of the internal message for anonymous users.')],
      ],
      'email_subject_enduser' => [
        'type' => 'textfield',
        'title' => $this->t('Partner Subject'),
        'description' => $this->t('Subject for end-user email notifications.'),
        'attributes' => ['title' => $this->t('Subject line for end-user notifications.')],
      ],
      'email_message_enduser' => [
        'type' => 'textarea',
        'title' => $this->t('Partner Message'),
        'description' => $this->t('Message body for end-user notifications.'),
        'attributes' => ['title' => $this->t('Body of the message for end users.')],
      ],
    ];

    foreach ($fields as $name => $details) {
      $form[$name] = [
        '#type' => $details['type'],
        '#title' => $details['title'],
        '#default_value' => $config->get($name),
        '#required' => TRUE,
        '#description' => $details['description'],
        '#attributes' => $details['attributes'],
        '#disabled' => $read_only,
      ];

      $form["{$name}_original"] = [
        '#type' => 'value',
        '#value' => $config->get($name),
      ];
    }

    // Token support.
    $form['email']['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user'],
      '#title' => $this->t('Available User tokens'),
      '#show_restricted' => TRUE,
      '#global_types' => FALSE,
      '#weight' => 90,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $emails = explode(',', $form_state->getValue('email_address'));

    foreach ($emails as $email) {
      $email = trim($email);
      $is_valid = \Drupal::service('email.validator')->isValid($email);
      if (!$is_valid || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName('email_address', $this->t('Please enter valid email address(es), separated by commas.'));
        break;
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $config = $this->config('contactus_email_setting.settings');
    $changed_fields = [];

    foreach ($form_state->getValues() as $key => $value) {
      if (str_ends_with($key, '_original')) {
        continue;
      }

      $original_key = "{$key}_original";
      $original_value = $form_state->getValue($original_key);

      $config->set($key, $value)->set($original_key, $original_value);

      if ($value !== $original_value) {
        $changed_fields[$key] = [
          'old' => $original_value,
          'new' => $value,
        ];
      }
    }

    $config->save();

    if (!empty($changed_fields)) {
      $message = $this->t('Contact Us settings updated by %user at %time from IP: %ip', [
        '%user' => $this->currentUser->getEmail(),
        '%time' => date('Y-m-d H:i:s'),
        '%ip' => Drupal::request()->getClientIp(),
      ]);

      $log_details = [];
      foreach ($changed_fields as $field => $values) {
        $log_details[] = "<b>$field</b><br><b>Existing:</b> {$values['old']} <b>→ Modified:</b> {$values['new']}";
      }

      \Drupal::logger('contactus_email_setting')
        ->info($message . '<pre><code>' . implode("\n", $log_details) . '</code></pre>');
    }
  }

}
