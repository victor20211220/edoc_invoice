<?php

namespace Drupal\esign_invoice_generator\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\esign_invoice_generator\Controller\SuppliersTableController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class IrsForm.
 *
 * @package Drupal\esign_invoice_generator\irs_form
 */
class IrsForm extends FormBase
{

  static $irsHeaderTblName = 'invoice_recurring_setup_header';

  static $irsDetailsTblName = 'invoice_recurring_setup_details';

  public function getFormId()
  {
    return 'esign_invoice_generator_irsform';
  }

  public static function getHeaderFields()
  {
    return [
      'supplier_id' => t('Supplier:'),
      'uws_ref' => t('Our Ref:'),
      'uws_sup_ref' => t('Supplier Ref:'),
      'start_date' => t('Start billing from:'),
      'end_date' => t('To:'),
      'recurring_every' => t('Every:'),
      'recurring_period' => t('Period:'),
    ];
  }


  /**
   * build form
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $db = \Drupal::database();
    $form['#attached']['library'] = [
      'esign_invoice_generator/invoice_form',
      'esign_invoice_generator/irs_create_form',
    ];
    $form['#id'] = 'invoice-form';
    $form['#class'] = 'datatable';
    $headerFields = $this->getHeaderFields();
    $detailFields = InvoiceForm::getDetailFields();
    $options = InvoiceForm::getOptions();
    if (isset($_GET['irs_id'])) {
      $invoiceId = $_GET['irs_id'];
      global $isClone;
      global $isManage;
      global $action;
      $action = $_GET['action'];
      if ($isClone = ($action === "save_and_clone") || $isManage = ($action === "manage")) {
        $query = $db->select(self::$irsHeaderTblName, 'irs');
        $query->fields('irs');
        $query->condition('[irs].[id]', $invoiceId);
        global $row;
        global $detailRows;
        $row = $query->execute()->fetchAssoc();
        $detailRows = $db->select(self::$irsDetailsTblName, NULL)
          ->fields(NULL, array_keys($detailFields))
          ->condition('header_id', $invoiceId)
          ->execute()->fetchAll();
        if (!$row) {
          \Drupal::messenger()->addMessage("Setup not found");
          return new RedirectResponse(Url::fromRoute("esign_invoice_generator.irs_form", [], ['absolute' => TRUE])
            ->toString(), 302);
        }
      }
    }

    //start add header fields
    foreach ($headerFields as $key => $field) {
      $form[$key] = [
        '#title' => $field,
        '#required' => TRUE,
        '#type' => $this->getTypeFromFormKey($key),
        '#default_value' => (isset($isClone) || isset($isManage)) ? $row[$key] : ($this->isKeyRp($key) ? "DAY" : ""),
      ];
      if (isset($isManage)) {
        if (!in_array($key, ['start_date', 'end_date'])) {
          $form[$key]['#attributes'] = ['disabled' => 'disabled'];
        }
      }
      if (in_array($key, ["supplier_id", "recurring_period"])) {
        $form[$key]['#options'] = $this->getOptionsFromFormKey($key);
      }
      if ($key === "recurring_every") {
        $form[$key]['#attributes'] = [
          ' type' => 'number',
          'min' => 1,
          'max' => 99,
        ];
      }
    }
    $form['start_date']['#prefix'] = '<div class="form-group">';
    $form['recurring_every']['#prefix'] = '<div class="form-group d-flex">';
    $form['end_date']['#suffix'] = '</div>';
    $form['supplier_id']['#prefix'] = '<div id="mainFields">';
    $form['recurring_period']['#suffix'] = '</div></div>';
    //end add header fields

    //start add detail fields
    if (isset($isClone) || isset($isManage)) {
      foreach ($detailRows as $i => $detailRow) {
        foreach ($detailFields as $key => $label) {
          $multi_key = $key . '-' . $i . '-[]';
          $form[$multi_key] = [
            '#title' => $i == 0 ? $label : '',
            '#required' => FALSE,
            '#value' => $detailRow->{$key},
            '#type' => 'select',
          ];
          if (isset($isManage)) {
            $form[$multi_key]['#attributes'] = ['disabled' => 'disabled'];
          }
          switch ($key) //generate different inputs per key
          {
            case 'dept':
            case 'type':
            case 'vat':
              $form[$multi_key]['#options'] = $options[$key];
              break;
            case 'qty':
              $form[$multi_key]['#type'] = 'number';
              $form[$multi_key]['#default_value'] = 1;
              break;
            case 'price_per':
            case 'amount':
              $form[$multi_key]['#type'] = 'number';
              $form[$multi_key]['#default_value'] = 0;
              if ($key === "amount") {
                $form[$multi_key]['#attributes'] = ['readonly' => ''];
              } else {
                $form[$multi_key]['#step'] = 0.01;
              }
              break;
            case 'description':
              $form[$multi_key]['#type'] = 'textfield';
              break;
          }
        }
        $delBtnAttrs = ['class' => ['delete-row']];
        if (isset($isManage)) {
          $delBtnAttrs['disabled'] = 'disabled';
        }
        $form['delete' . $i] = [
          '#type' => 'button',
          '#value' => ' X ',
          '#attributes' => $delBtnAttrs,
        ];
        $form['dept-' . $i . '-[]']['#prefix'] = '<div class="one-block">';
        $form['delete' . $i]['#suffix'] = '</div>';
      }
    } else {
      foreach ($detailFields as $key => $label) {
        $multi_key = $key . '[]';
        $form[$multi_key] = [
          '#title' => $label,
          '#required' => FALSE,
          '#type' => 'select',
        ];

        switch ($key) //generate different inputs per key
        {
          case 'dept':
          case 'type':
          case 'vat':
            $form[$multi_key]['#options'] = $options[$key];
            break;
          case 'qty':
            $form[$multi_key]['#type'] = 'number';
            $form[$multi_key]['#default_value'] = 1;
            break;
          case 'price_per':
          case 'amount':
            $form[$multi_key]['#type'] = 'number';
            $form[$multi_key]['#default_value'] = 0;
            if ($key === "amount") {
              $form[$multi_key]['#attributes'] = ['readonly' => ''];
            } else {
              $form[$multi_key]['#step'] = 0.01;
            }
            break;
          case 'description':
            $form[$multi_key]['#type'] = 'textfield';
            break;
        }
      }
      $form['delete'] = [
        '#type' => 'button',
        '#value' => ' X ',
        '#attributes' => ['class' => ['delete-row']],
      ];
      $form['dept[]']['#prefix'] = '<div class="one-block">';
      $form['delete']['#suffix'] = '</div>';
    }
    //end add detail fields

    //start add buttons
    if (isset($isManage)) {

      $form['manage'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
      $manageButtons = [
        'stop' => 'Stop',
        'cancel_replace' => 'Cancel and Replace',
        'clone' => 'Clone',
      ];
      if ($row['is_active'] == false) {
        unset($manageButtons['stop']);
        unset($manageButtons['cancel_replace']);
        $form['username']['#prefix'] = "<label>" . InvoiceForm::getUsernameById($row['last_changed_by_user_id']) . " changed on " . date('d/m/Y H:i:s', strtotime($row['date_last_changed'])) . "</label>";
      }
      foreach ($manageButtons as $key => $value) {
        $form[$key] = [
          '#type' => 'submit',
          '#attributes' => ['data-manage-btn' => [$key]],
          '#value' => t($value),
        ];
      }
      $form['#attached']['drupalSettings'] = [
        'manage' => 1
      ];
    } else {

      $form['save_and_clone'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
      $form['add'] = [
        '#type' => 'button',
        '#value' => t('Add new row'),
      ];
      $form['save'] = [
        '#type' => 'submit',
        '#value' => 'Save',
      ];
      $form['save_clone'] = [
        '#type' => 'submit',
        '#value' => 'Save and Clone',
      ];
      //end add buttons
    }
    return $form;
  }

  public
  function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }


  public
  function submitForm(array &$form, FormStateInterface $form_state)
  {
    $redirect = ["list_irs", []];
    if (isset($_GET['action'])) {
      if ($_GET['action'] === 'manage') {
        $manage = $_POST['manage'];
        $irsId = $_GET['irs_id'];
        if (in_array($manage, ["stop", "cancel_replace"])) {
          if ($this->stopIrs($irsId) === 1) {
            \Drupal::messenger()->addMessage("Invoice recurring setup stopped");
          }
        }
        if (in_array($manage, ["clone", "cancel_replace"])) {
          $redirect[0] = "irs_form";
          $redirect[1] = ['irs_id' => $irsId, 'action' => 'save_and_clone'];
          if ($manage == "cancel_replace") {
            $redirect[1][$manage] = 1;
          }
        }
      }
    }
    if (!isset($manage)) {
      $db = \Drupal::database();
      $formData = $_POST;
      $fields = [];
      $headerFields = array_keys($this->getHeaderFields());
      foreach ($headerFields as $key) {
        $fields[$key] = $formData[$key];
      };
      $fields['date_created'] = self::dbDateTime();
      $fields['user_id'] = self::getCurrentUserId();
      if (isset($_GET['cancel_replace'])) {
        $stoppedId = $_GET['irs_id'];
        $fields['link_id'] = $stoppedId;
      }
      $irsId = $db->insert(self::$irsHeaderTblName)
        ->fields($fields)
        ->execute();

      if (isset($_GET['cancel_replace'])) {
        \Drupal::database()->update(self::$irsHeaderTblName)
          ->fields(['link_id' => $irsId])
          ->condition('id', $stoppedId)
          ->execute();
      }

      $invoiceDetails = [];
      $detailFields = array_keys(InvoiceForm::getDetailFields());
      foreach ($detailFields as $key) {
        $invoiceDetails[$key] = $formData[$key];
      };

      $db->delete(self::$irsDetailsTblName)
        ->condition('header_id', $irsId)
        ->execute();
      $detailsCount = count($invoiceDetails[$detailFields[0]]);

      //insert all irs detail rows by iterating
      for ($i = 0; $i < $detailsCount; $i++) {
        $invoiceDetailRow = ['header_id' => $irsId];
        foreach ($detailFields as $key) {
          $val = $invoiceDetails[$key][$i];
          if (in_array($key, ["price_per", "amount", "qty"]) && $val == "") {
            $val = 0;
          }
          $invoiceDetailRow[$key] = $val;
        }
        $db->insert(self::$irsDetailsTblName)
          ->fields($invoiceDetailRow)
          ->execute();
      }
      \Drupal::messenger()->addMessage("Invoice recurring setup Created");
      if ($formData['save_and_clone']) {
        $redirect[0] = "irs_form";
        $redirect[1] = ['irs_id' => $irsId, 'action' => 'save_and_clone'];
      }
    }
    $form_state->setRedirect("esign_invoice_generator." . $redirect[0], $redirect[1]);
  }

  function isKeyRp($key)
  {
    return $key === "recurring_period";
  }

  function getTypeFromFormKey($key)
  {
    switch ($key) {
      case 'supplier_id' :
        $type = "select";
        break;
      case 'start_date' :
      case 'end_date' :
        $type = "date";
        break;
      case 'recurring_period' :
        $type = "radios";
        break;
      default:
        $type = "textfield";
        break;
    }
    return $type;
  }

  public static function getOptionsFromFormKey($key)
  {
    if ($key === "supplier_id") {
      $options = SuppliersTableController::getSuppliersOptions();
    } else {
      $options = [
        'DAY' => t('days'),
        'WEEK' => t('weeks'),
        'MONTH' => t('months'),
        'YEAR' => t('years'),
      ];
    }
    return $options;
  }

  function stopIrs($irsId)
  {
    $updateFields = [
      'is_active' => 0,
      'date_last_changed' => self::dbDateTime(),
      'last_changed_by_user_id' => self::getCurrentUserId(),
    ];
    return \Drupal::database()->update(self::$irsHeaderTblName)
      ->fields($updateFields)
      ->condition('id', $irsId)
      ->execute();
  }

  public static function dbDateTime()
  {
    return date('Y-m-d H:i:s');
  }

  public static function getCurrentUserId()
  {
    return \Drupal::currentUser()->id();
  }
}
