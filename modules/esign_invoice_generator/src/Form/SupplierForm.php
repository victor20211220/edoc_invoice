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
class SupplierForm extends FormBase
{

  public function getAllFields()
  {
    return [
      'sup_ac' => [t('Supplier A/C'), 40],
      'company_name' => [t('Company Name'), 40],
      'address_1' => [t('Address 1'), 40],
      'address_2' => [t('Address 2'), 40],
      'county' => [t('County/Town'), 40],
      'city' => [t('City'), 40],
      'postcode' => [t('Postcode'), 10],
      'supplier_name' => [t('Contact Name'), 40],
      'supplier_role' => [t('Contact Role'), 40],
      'supplier_number' => [t('Contact Number'), 15],
      'email' => [t('Contact Email'), 60],
      'email_cc1' => [t('Email CC1'), 60],
      'when_cc1' => t('When CC1'),
      'email_cc2' => [t('Email CC2'), 60],
      'when_cc2' => t('When CC2'),
      'email_cc3' => [t('Email CC3'), 60],
      'when_cc3' => t('When CC3'),
      'email_cc4' => [t('Email CC4'), 60],
      'when_cc4' => t('When CC4'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'esign_invoice_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
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
    $form['#attached']['library'] = [
      'esign_invoice_generator/supplier_form',
    ];
    foreach ($fields as $key => $field) {
      if (in_array($key, ["when_cc1", "when_cc2", "when_cc3", "when_cc4"])) {
        $form[$key] = [
          '#type' => 'radios',
          '#default_value' => (isset($record[$key]) && $_GET['num']) ? !is_null($record[$key]) ? $record[$key] : 1 : 1,
          '#options' => array(
            0 => t('Immediate'),
            1 => t('After 1 signed'),
            2 => t('After 2 signed'),
          ),
        ];
        $form[$key]['#suffix'] = '</div>';
      } else {
        $form[$key] = [
          '#type' => in_array($key, ["email", "email_cc1", "email_cc2", "email_cc3", "email_cc4"]) ? 'email' : 'textfield',
          '#required' => !(self::isCcEmailFields($key) || in_array($key, ["address_2", "county"])),
          '#default_value' => (isset($record[$key]) && $_GET['num']) ? $record[$key] : "",
        ];
      }
      $form[$key] = array_merge($form[$key], ['#title' => is_array($field) ? $field[0] : $field]);
      if (is_array($field)) {
        $form[$key]['#attributes']['maxlength'] = $field[1];
        $form[$key]['#attributes']['size'] = $field[1];
      }
      if (self::isCcEmailFields($key)) {
        $form[$key]['#prefix'] = '<div class="form-group">';
      }

    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'save',
    ];
    return $form;
  }

  static function isCcEmailFields($key)
  {
    return in_array($key, ["email_cc1", "email_cc2", "email_cc3", "email_cc4"]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
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
    } else {
      $query = \Drupal::database();
      $query->insert('suppliers')
        ->fields($fields)
        ->execute();
      \Drupal::messenger()->addMessage("succesfully saved");
    }
    $form_state->setRedirect("esign_invoice_generator.suppliers_controller_display");
  }

}
