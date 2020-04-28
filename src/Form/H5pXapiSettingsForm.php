<?php

namespace Drupal\h5p_xapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class H5pXapiSettingsForm extends ConfigFormBase {

  const H5P_XAPI_SETTINGS_ID = 'h5p_xapi.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_xapi_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::H5P_XAPI_SETTINGS_ID];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config(self::H5P_XAPI_SETTINGS_ID);

    $form['content_id_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('H5P Content ID key'),
      '#description' => $this->t('Enter the H5P content id key.More info</a>.', [':w3ctags' => 'http://www.w3.org/International/articles/language-tags/']),
      '#default_value' => $settings->get('content_id_key'),
      '#size' => 60,
      '#required' => TRUE,
    ];
    $form['sub_content_id_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('H5P Sub-content ID key'),
      '#description' => $this->t('Enter the H5P sub-content id key. More info</a>.', [':w3ctags' => 'http://www.w3.org/International/articles/language-tags/']),
      '#default_value' => $settings->get('sub_content_id_key'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    $form['enable_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#default_value' => $settings->get('enable_debug'),
      '#description' => $this->t('When turned on, all xAPI statements will be logged in the browser console.'),
    ];

    $form['store_xapi_statements'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Store raw xAPI statements'),
      '#default_value' => $settings->get('store_xapi_statements'),
      '#description' => $this->t('Store complete xAPI statement JSON data. Beware, as the data can grow very quickly.'),
    ];

    $form['capture_all_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture xAPI statements for all H5P content types'),
      '#default_value' => $settings->get('capture_all_types'),
    ];

    $form['ct'] = [
      '#type' => 'container',
      'label' => [
        '#type' => 'item',
        '#title' => $this->t('Capture xAPI statements for only the following H5P content types'),
      ],
      '#states' => [
        'invisible' => [
          ':input[name="capture_all_types"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['ct']['capture_allowed_types'] = [
      '#type' => 'tableselect',
      '#header' => $this->getContentTypeHeader(),
      '#options' => $this->getContentTypeOptions(),
      '#empty' => $this->t('No content types available yet.'),
    ];

    $types = node_type_get_names();
    $allowed_content_types = $settings->get('allowed_content_types');
    $form['allowed_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types allowed to have H5P interactive content'),
      '#default_value' => $allowed_content_types ? $allowed_content_types : [],
      '#options' => $types,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    $this->config(self::H5P_XAPI_SETTINGS_ID)
      ->set('content_id_key', $values['content_id_key'])
      ->set('sub_content_id_key', $values['sub_content_id_key'])
      ->set('enable_debug', $values['enable_debug'])
      ->set('store_xapi_statements', $values['store_xapi_statements'])
      ->set('capture_all_types', $values['capture_all_types'])
      ->set('capture_allowed_types', array_filter($values['capture_allowed_types']))
      ->set('allowed_content_types', array_filter($values['allowed_content_types']))
      ->save();

    parent::submitForm($form, $form_state);
  }

  public function getH5pContentTypes() {
    $query = \Drupal::database()->select(H5P_CONTENT_TABLE, 'c');
    $query->join(H5P_LIBRARY_TABLE, 'l', 'c.library_id = l.library_id');
    $query->fields('c', ['id', 'title']);
    $query->addField('l', 'title', 'type');
    $types = $query->execute();

    return $types;
  }

  public function getContentTypeOptions() {
    $options = [];
    $contentTypes = $this->getH5pContentTypes();
    if ($contentTypes) {
      foreach ($contentTypes as $contentType) {
        $options[$contentType->id] = [
          'id' => $contentType->id,
          'title' => $contentType->title,
          'type' => $contentType->type,
        ];
      }
    }
    return $options;
  }

  public function getContentTypeHeader() {
    return [
      'id' => t('ID'),
      'title' => t('Type'),
      'type' => t('Title'),
    ];
  }
}
