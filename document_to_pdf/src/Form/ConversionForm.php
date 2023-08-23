<?php

namespace Drupal\document_to_pdf\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Mpdf\Mpdf;

/**
 * Provides a Document to pdf form.
 */
class ConversionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'document_to_pdf_conversion';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => t('Upload a file'),
      '#required'=>TRUE,
      '#description' => t('Upload a file, allowed extensions: docx'),
      '#upload_location' => 'public://', // You can specify the upload destination.
      '#upload_validators' => [
        'file_validate_extensions' => ['docx'], // Specify allowed file extensions.
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values =$form_state->getValues();
    $file_upload_id = $values['file_upload'][0];
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_upload_id);
    if($file) {
      $file_extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      $uri = $file->getFileUri();
      $fname = $file->getFilename();
      $file_name_without_ext = basename($fname, $file_extension);
      $file_name_without_ext = str_replace('.','',$file_name_without_ext);
      $file_path = $file->createFileUrl(TRUE);
      $file_path = trim($file_path, '/');
      switch($file_extension) {
        case 'docx':
          //Extract the file data
          $data = \Drupal::service('document_to_pdf.conversionservices')->fetch_docx_data($file_path, $file_name_without_ext);
          // Create new PDF document.
          $mpdf = new Mpdf();
          $mpdf->WriteHTML($data);
          // Save the file put which location you need folder/filname.
          $mpdf->Output();
          $files = glob('sites/default/files/doc_imgs/*');
          // Iterate image files and delete it.
          foreach ($files as $file1) {
            if (is_file($file1)) {
              // Delete local saved image files.
              unlink($file1);
            }
          }
        break;
      }
    }
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

}
