<?php

namespace Drupal\esign_invoice_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\esign_invoice_generator\Form\InvoiceForm;

/**
 * Class InvoicesTableController.
 *
 * @package Drupal\esign_invoice_generator\Controller
 */
class InvoicesTableController extends ControllerBase {

  public static function getFields() {
    return [
      'id' => t('Id'),
      'doc_number' => t('Invoice Number'),
      'supplier_id' => t('Supplier A/C'),
      'uws_ref' => t('Our Ref'),
      'uws_sup_ref' => t('Supplier Ref'),
      'user_id' => t('Creator'),
      'source' => t('Source'),
      'status' => t('Status'),
      'doc_type' => t('Credited After (days)'),
      'date_created' => t('Date Created'),
      'invoice_file' => t('PDF'),
      'reason' => t('Reason'),
    ];
  }

  /**
   * Display.
   */


  public function display() {
    $roles = \Drupal::currentUser()->getRoles();
    $is_admin = in_array('administrator', $roles);
    $userId = \Drupal::currentUser()->id();;
    $isManager = $is_admin ? 1 : in_array('document_manager', $roles);
    //create table header
    $header_table = $this->getFields();
    $header_table['oper'] = t('Operation');

    $header_table['document'] = t('Signed PDF');
    $header_table['sign_status'] = t('Sign Status');
    $header_table['sign_details'] = t('Sign Details');


    $fields = array_keys($this->getFields());
    $fields = array_merge($fields, [
      'document_id',
      'doc_link',
      'esign_status',
      'esign_signers',
      'esign_last_checked',
    ]);
    //select records from table
    $query = \Drupal::database()
      ->select(InvoiceForm::$invoiceHeaderTblName, 'inv');
    $query->fields('inv', $fields);
    $query->leftJoin(InvoiceForm::$invoiceHeaderTblName, 'inv_i', "[inv].[doc_link] = [inv_i].[doc_number]");
    $query->fields('inv_i', ['date_created']);
    $query->leftJoin('suppliers', 'm', "[inv].[supplier_id] = [m].[id]");
    $query->fields('m', ['sup_ac']);
    if (InvoiceForm::isSupplier()) {
      $query->condition('[m].[sup_ac]', \Drupal::currentUser()
        ->getDisplayName());
    }
    $query->orderBy('id', 'DESC');

    $results = $query->execute()->fetchAll();
    $fields = array_keys($this->getFields());
    $fields = array_replace($fields, [2 => 'sup_ac']);
    $fields[] = 'oper';
    if (InvoiceForm::isSupplier()) {
      $noSupplierFields = ['user_id', 'oper', 'sign_status', 'sign_details'];
      foreach ($noSupplierFields as $noSupplierField) {
        unset($header_table[$noSupplierField]);
        if (($key = array_search($noSupplierField, $fields)) !== FALSE) {
          unset($fields[$key]);
        }
      }
    }
    $rows = [];
    foreach ($results as $data) {
      $docType = $data->doc_type;
      if (InvoiceForm::isSupplier() && $data->status == 0) {
        continue;
      }
      $dataUserId = $data->user_id;
      $status = $data->status;
      $row = [];
      $canManage = $isManager || $dataUserId === $userId;
      foreach ($fields as $field) {
        global $class;
        $class = '';
        if (in_array($field, ['document_id', 'doc_link', 'esign_status', 'esign_signers', 'esign_last_checked'])) {
          continue;
        }
        if ($field !== "oper") {
          $val = $data->{$field};
        }
        switch ($field) {
          case "oper":
            if ($canManage && $data->doc_type === "i") {
              switch ($status = $data->status) {
                case 0:
                  if ($data->invoice_file == '') {
                    $val = Url::fromUserInput('/send-invoice?invoice_eid=' . $data->id);
                    $val = \Drupal\Core\Link::fromTextAndUrl('Edit', $val);
                  }
                  else {
                    $val = Url::fromUserInput('/send-invoice?invoice_id=' . $data->id);
                    $val = \Drupal\Core\Link::fromTextAndUrl('Send', $val);
                  }
                  break;
                case 2:
                  $val = Url::fromUserInput('/send-invoice?invoice_id=' . $data->id);
                  $val = \Drupal\Core\Link::fromTextAndUrl('Retry', $val);
                  break;
                case 5:
                  $val = '';
                  break;
                default:
                  $documentId = $data->document_id;
                  if ($documentId != '' && !is_null($documentId) && in_array($data->status, [1,3,4]) && (is_null($data->doc_link) || $data->doc_link === "")) {
                    $val = Url::fromUserInput('/cancel-sign-invite?document_id=' . $documentId . '&invoice_cid=' . $data->id);
                    $val = \Drupal\Core\Link::fromTextAndUrl('Cancel', $val);
                  }
                  else {
                    $val = '';
                  }
                  break;
              }
            }
            else {
              $val = '';
            }
            $row[$field] = $val;
            break;
          case "invoice_file":
            if ($val == '') {
              $val = 'Not created';
            }
            else {
              if ($canManage || InvoiceForm::isSupplier()) {
                $val = Url::fromUserInput('/modules/esign_invoice_generator/invoices/' . $val, ['attributes' => ['target' => '_blank']]);
                $val = \Drupal\Core\Link::fromTextAndUrl('Show', $val);
              }
              else {
                $val = '';
              }
            }
            break;
          case "reason":
            if ($val != '' && $canManage) {
              $val = Url::fromUserInput('/get-cn-reason?reason_id=' . $data->id, ['attributes' => ['target' => '_blank']]);
              $val = \Drupal\Core\Link::fromTextAndUrl('Show', $val);
            }else{
              $val = '';
            }
            break;
          case "doc_type":
            if ($val === 'c' && $data->doc_link != NULL) {
              $creditDate = $data->date_created;
              $invoiceDate = $data->inv_i_date_created;
              $diffDays = $this->getDiffDays($invoiceDate, $creditDate);
              $val = $diffDays;
              $class = "color-" . ($diffDays < 30 ? 'green' : ($diffDays < 90 ? 'amber' : 'red')) . " tooltip";
            }
            else {
              $val = '';
            }
            break;
          case "date_created":
            $val = date('d/m/Y H:i:s', strtotime($val));
            break;
          case "status":
            $docLink = $data->doc_link;
            if ($docLink == '') {
              switch ($val) {
                case 1:
                  $val = 'Sent';
                  break;
                case 2:
                  $val = 'Send Failed';
                  break;
                case 3:
                  $val = 'Signed 1';
                  break;
                case 4:
                  $val = 'Signed 2';
                  break;
                case 5:
                  $val = 'Cancelled';
                  break;
                default:
                  $val = 'Created';
                  break;
              }
            }
            else {
              $val = $docLink;
              $class = "color-red";
            }
            break;
          case "source" :
            $val = $val == 'r' ? "Recurring" : "User";
            break;
          case "user_id":
            $val = InvoiceForm::getUsernameById($val);
            break;
        }
        $row[$field] = [
          'data' => $val,
          'class' => $class,
        ];
      }
      if ($canManage || InvoiceForm::isSupplier()) {
        $val = $val1 = $val2 = '';
        $documentId = $data->document_id;
        if (in_array($status, [
            1,
            5,
          ]) && $documentId != '' && !is_null($documentId)) {
          $val = Url::fromUserInput('/send-invoice?download_document_id=' . $documentId);
          $val1 = Url::fromUserInput('/get-invoice-sign-status?sign_document_id=' . $documentId);
          $val = \Drupal\Core\Link::fromTextAndUrl('Download', $val);
          $val1 = \Drupal\Core\Link::fromTextAndUrl('Update', $val1);
        }
        $row['document'] = $val;
        if (!InvoiceForm::isSupplier()) {
          $row['sign-status'] = $val1;
          $eSignLastChecked =  $data->esign_last_checked;
          $eSignStatus =  is_null($data->esign_status) ? "empty" : $data->esign_status;
          if(is_null($eSignLastChecked)){
            $row['sign-details'] =  $docType === "c" ? "" : "Not checked";
          }else{
            $row['sign-details'] = date('d/m/Y H:i:s', strtotime($eSignLastChecked)). "\n(".$data->esign_signers.")".$eSignStatus;
          }
        }
      }
      else {
        $row['document'] = $row['sign-status'] = $row['sign-details'] = "";
      }
     $rows[] = [
        'data' => $row,
        'class' => [$data->doc_type === "c" ? "credit-note-row" : "invoice-row"],
      ];
    }
    //display data in site
    $form['#attached']['library'] = [
      'esign_invoice_generator/datatable',
      'esign_invoice_generator/invoices',
    ];
    $form['table'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['row-border', 'stripe']],
      '#header' => $header_table,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => t('No invoices found'),
    ];
    return $form;
  }

  function getDiffDays($date1, $date2) {
    $startStamp = strtotime($date1); // or your date as well
    $endStamp = strtotime($date2);
    $datediff = $endStamp - $startStamp;

    return round($datediff / (60 * 60 * 24));
  }

}
