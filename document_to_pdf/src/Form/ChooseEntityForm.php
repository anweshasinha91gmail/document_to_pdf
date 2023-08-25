<?php

namespace Drupal\document_to_pdf\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManager;
use \Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

class ChooseEntityForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'document_to_pdf_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['document_to_pdf.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Get a list of content types.
    $content_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();

    $options = [];

    // Load the field storage for the file field.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_document_to_pdf'); // Adjust 'node' if needed for a different entity type.

    foreach ($content_types as $content_type) {
      // Load the field configuration for this content type.
      $field_config = FieldConfig::loadByName('node', $content_type->id(), 'field_document_to_pdf'); // Adjust 'node' if needed for a different entity type.
      if (!$field_config) {
        $options[$content_type->id()] = $content_type->label();
      }
    }
    if(count($options) > 0) {
      $form['content_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select Content Types'),
        '#options' => $options,
        '#description' => $this->t('Select the content types where you want to attach document to pdf field.'),
      ];
    } else {
      $form['help'] = [
      '#type' => 'markup',
      '#markup' => 'There are no content type where document to pdf field can be attached.',
      '#weight' => '3',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the selected content types from the form submission.
    $selected_content_types = array_filter($form_state->getValue('content_types'));

    // Iterate through the selected content types and add a file field to each one.
    foreach ($selected_content_types as $content_type) {
      $this->addFileFieldToContentType($content_type);
    }

    // Save the configuration.
    $this->config('document_to_pdf.settings')
      ->set('content_types', $selected_content_types)
      ->save();

  parent::submitForm($form, $form_state);
  }

    /**
   * Add a file field to a content type.
   *
   * @param string $content_type
   *   The machine name of the content type.
   */
  public function addFileFieldToContentType($content_type) {
   //Define field settings.
    $field_settings = [
      // Customize field settings as needed.
      'field_name' => 'field_document_to_pdf',
      'entity_type' => 'node',
      'bundle' => $content_type,
      'type' => 'file',
      'settings' => [
       'file_extensions' => 'docx', // Specify the allowed file extension.
      ],
    ];
    // Load the field storage for the file field.
    $field_storage = FieldStorageConfig::loadByName('node', 'field_document_to_pdf');
    //Define field storage settings.Field defination fordocument_to_pdf will be created only if it doesn't exist.
    if(!isset($field_storage)) {
      $field_storage_settings = [
        'field_name' => 'field_document_to_pdf',
        'entity_type' => 'node',
        'bundle' => $content_type,
        'type' => 'file',
        'settings' => [
          'file_extensions' => 'docx',
        ],
      ];
      // Create the field storage.
      \Drupal\field\Entity\FieldStorageConfig::create($field_storage_settings)->save();
    }
   // Create the field.
    \Drupal\field\Entity\FieldConfig::create($field_settings)->save();

    // Get the current route name.
    $current_route_name = \Drupal::routeMatch()->getRouteName();
    // Add a message after form submission.
    \Drupal::messenger()->addMessage($this->t('Document To PDF field as been attached to the checked content types. Any document uploaded in this field will be converted to pdf.'));
  }

}
