<?php

namespace Drupal\esign_invoice_generator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Database\Database;

/**
 * Class SupplierForm.
 *
 * @package Drupal\esign_invoice_generator\Form
 */
class SupplierForm extends FormBase {

  public function getAllFields() {
    return [
      'sup_ac' => t('Supplier A/C'),
      'company_name' => t('Company Name'),
      'address_1' => t('Address 1'),
      'address_2' => t('Address 2'),
      'county' => t('County/Town'),
      'city' => t('City'),
      'postcode' => t('Postcode'),
      'supplier_name' => t('Contact Name'),
      'supplier_number' => t('Contact Number'),
      'supplier_role' => t('Contact Role'),
      'email' => t('Contact Email'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'esign_invoice_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $conn = Database::getConnection();
    $record = [];
    if (isset($_GET['num'])) {
      $query = $conn->select('suppliers', 'm')
        ->condition('id', $_GET['num'])
        ->fields('m');
      $record = $query->execute()->fetchAssoc();
    }
    $fields = $this->getAllFields();
    $form = [];
    foreach ($fields as $key => $field) {
      $form[$key] = [
        '#type' => $key == 'email' ? 'email' : 'textfield',
        '#title' => $field,
//        '#required' => in_array($key, ['sup_ac', 'email'])  ? true:false,
        '#required' => true,
        '#default_value' => (isset($record[$key]) && $_GET['num']) ? $record[$key] : '',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'save',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_data = $form_state->getValues();
    $fields = $this->getAllFields();
    foreach ($fields as $key => $col) {
      $fields[$key] = $form_data[$key];
    };
    $fields['user_id'] = \Drupal::currentUser()->id();
    if (isset($_GET['num'])) {
      $query = \Drupal::database();
      $query->update('suppliers')
        ->fields($fields)
        ->condition('id', $_GET['num'])
        ->execute();
      \Drupal::messenger()->addMessage("succesfully updated");
    }
    else {
      $query = \Drupal::database();
      $query->insert('suppliers')
        ->fields($fields)
        ->execute();
      \Drupal::messenger()->addMessage("succesfully saved");
    }
    $form_state->setRedirect("esign_invoice_generator.suppliers_controller_display");
  }

}
