<?php

namespace Drupal\https_www_redirects\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to configure site-wide
 * redirects for HTTPS and WWW.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['https_www_redirects.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'https_www_redirects_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('https_www_redirects.settings');
    $form = parent::buildForm($form, $form_state);

    $form['chkHttps'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force the site to use HTTPS'),
      '#default_value' => $config->get('force_https') ?? 0,
    ];

    $bypassedHttps = $config->get('bypass_https_hosts');
    if (!empty($bypassedHttps)) {
      $bypassedHttps = join(',', $bypassedHttps);
    }

    $form['txtHttpsBypass'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Hosts to bypass forced HTTPS'),
      '#default_value' => $bypassedHttps,
      '#description' => $this->t('These settings may be overridden in the settings.php file.'),
      '#states' => [
        'visible' => [
          ':input[name="chkHttps"]' => array('checked' => TRUE),
        ],
      ]
    ];

    $form['chkWWW'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force the site to use WWW'),
      '#default_value' => $config->get('force_www') ?? 0,
    ];

    $bypassedWww = $config->get('bypass_www_hosts');
    if (!empty($bypassedWww)) {
      $bypassedWww = join(',', $bypassedWww);
    }

    $form['txtWWWBypass'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Hosts to bypass forced WWW'),
      '#default_value' => $bypassedWww,
      '#description' => $this->t('These settings may be overridden in the settings.php file.'),
      '#states' => [
        'visible' => [
          ':input[name="chkWWW"]' => array('checked' => TRUE),
        ],
      ]
    ];

    $form['#cache']['tags'][] = 'config:https_www_redirects.settings';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $https = $form_state->getValue('chkHttps');
    $www = $form_state->getValue('chkWWW');

    $httpsBypassed = $form_state->getValue('txtHttpsBypass');
    if (!empty($httpsBypassed)) {
      $httpsBypassed = explode(',', $httpsBypassed);
    }

    $wwwBypassed = $form_state->getValue('txtWWWBypass');
    if (!empty($wwwBypassed)) {
      $wwwBypassed = explode(',', $wwwBypassed);
    }

    $this->config('https_www_redirects.settings')
      ->set('force_https', $https)
      ->set('force_www', $www)
      ->set('bypass_https_hosts', $httpsBypassed)
      ->set('bypass_www_hosts', $wwwBypassed)
      ->save(TRUE);

    // Since these settings affecting routing, flush the cache after save.
    drupal_flush_all_caches();
  }
}
