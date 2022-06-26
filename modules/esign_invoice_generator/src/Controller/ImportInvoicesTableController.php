<?php

namespace Drupal\esign_invoice_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Url;
use Drupal\esign_invoice_generator\Form\InvoiceForm;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class InvoicesTableController.
 *
 *
 * @package Drupal\esign_invoice_generator\Controller
 */
class ImportInvoicesTableController extends ControllerBase {

  public static $pendingTable = "invoice_import_pending";

  public static $importDir = __DIR__ . '/../../import';

  public static $importedDir = __DIR__ . '/../../imported';

  public function csvFields() {
    $fields = [
      'user_id',
      'supplier_id',
      'uws_ref',
      'uws_sup_ref',
      'uws_period_from',
      'uws_period_to',
      'doc_type',
      'dept',
      'type',
      'qty',
      'description',
      'price_per',
      'vat',
      'amount',
    ];
    return $fields;
  }

  public function orderFields() {
    return [
      'user_id',
      'supplier_id',
      'uws_ref',
      'uws_sup_ref',
      'uws_period_from',
      'uws_period_to',
      'doc_type',
    ];
  }

  public function concatFields() {
    return [
      'id',
      'dept',
      'type',
      'qty',
      'description',
      'price_per',
      'vat',
      'amount',
    ];
  }

  public static function getFields() {
    return [
      'to_import' => '',
      'line_number' => t("Line\nNum"),
      'user_id' => t('User'),
      'supplier_id' => t('Supplier A/C'),
      'uws_ref' => t('Our Ref'),
      'uws_sup_ref' => t('Supplier Ref'),
      'uws_period_from' => t('Period From'),
      'uws_period_to' => t('Period To'),
      'doc_type' => t('Type'),
      'dept' => t('Dept'),
      'type' => t('Type'),
      'qty' => t('Qty'),
      'description' => t('Description'),
      'price_per' => t('Price Per'),
      'vat' => t('Vat'),
      'amount' => t('Amount'),
      'imported_on' => t('Created at'),
      'invoiced_on' => t('Invoiced at'),
      'file_name' => t('Filename'),
      'id' => '',
      'valid' => '',
    ];
  }

  public function display() {
    $vats = InvoiceForm::getVats();
    $db = \Drupal::database();
    if (isset($_POST['selected_ids'])) {
      $response = ['status' => 0];
      $selectedIds = array_unique(explode(',', $_POST['selected_ids']));
      $btnFlag = $_POST['btn_flag'];
      if ($btnFlag == 0) {
        $orderFields = $this->orderFields();
        $concatFields = $this->concatFields();
        $query = "SELECT doc_type,";
        $orderFieldStr = implode(',', $orderFields);
        $query .= $orderFieldStr;
        foreach ($concatFields as $concatField) {
          $query .= "," . $this->groupConcatValues($concatField);
        }
        $query .= " FROM " . self::$pendingTable;
        $query .= " WHERE id IN (" . implode(',', $selectedIds) . ")";
        $query .= " GROUP BY " . $orderFieldStr;
        $results = $db->query($query)->fetchAll();
        if (count($results) > 0) {
          foreach ($results as $result) {
            $invoiceRow = (array) $result;
            $newDate = date('Y-m-d H:i:s');
            $invoiceRow['date_created'] = $newDate;
            $invoiceRow['is_exported'] = $invoiceRow['is_a1_exported'] = $invoiceRow['status'] = 0;
            $invoiceRow['source'] = 'u';
            $gInvId = InvoiceForm::getTblLastId();
            $docNumber = InvoiceForm::invoice_num($gInvId, 7, "");
            $invoiceRow['doc_number'] = $docNumber;
            $ids = explode('@@@', $invoiceRow['ids']);
            $newDtRows = [];
            foreach ($concatFields as $concatField) {
              $newDtRows[$concatField] = explode("@@@", $invoiceRow[$concatField . 's']);
              unset($invoiceRow[$concatField . 's']);
            }
            foreach ($ids as $key => $id) {
              $newDtRow = [];
              foreach ($concatFields as $concatField) {
                if ($concatField != "id") {
                  $newDtRow[$concatField] = $newDtRows[$concatField][$key];
                }
              }
              $newDtRow['invoice_id'] = $gInvId;
              $newDtRow['vat_rate'] = $vats[$newDtRow['vat']];
              $db->insert(InvoiceForm::$invoiceDetailsTblName)
                ->fields($newDtRow)
                ->execute();
            }
            $invoiceForm = new InvoiceForm();
            $invoiceRow = $invoiceForm->saveAndSend($invoiceRow, $newDtRows);
            $db->update(self::$pendingTable)
              ->fields(['invoiced_on' => $newDate, 'to_import' => 0])
              ->condition('id', $ids, 'IN')
              ->execute();
            $insertedId = $db->insert(InvoiceForm::$invoiceHeaderTblName)
              ->fields($invoiceRow)
              ->execute();
            $this->getLogger('system')
              ->info('Invoice created from pending data:' . $insertedId);
          }
          $response['msg'] = 'Invoices generated';
        }
      }
      else {
        $db->delete(self::$pendingTable)
          ->condition('id', $selectedIds, 'IN')
          ->execute();
        $response['msg'] = 'Deleted '.($btnFlag === 1 ? "ticked" : "invalid").' records';
      }
      $response['status'] = 1;
      \Drupal::messenger()
        ->addMessage($response['msg']);
      echo json_encode($response);
      exit();
    }
    else {
      //create table header
      $header_table = $this->getFields();

      $fields = array_keys($this->getFields());
      //select records from table
      $query = $db->select(self::$pendingTable, 'pend');
      $query->fields('pend');
      $query->leftJoin('suppliers', 'sup', "[pend].[supplier_id] = [sup].[id]");
      $query->fields('sup', ['sup_ac']);
      $query->orderBy('pend.imported_on', 'DESC');
      $query->orderBy('pend.line_number', 'ASC');
      $results = $query->execute()->fetchAll();
      $fields = array_replace($fields, [3 => 'sup_ac']);
      $vats = InvoiceForm::getVats();
      $rows = [];
      foreach ($results as $data) {
        $row = [];
        foreach ($fields as $field) {
          $val = $data->{$field};
          $cellClass = "";
          if ($val == -1 || ($field === "sup_ac" && $val == "")) {
            $val = "Error";
            $cellClass = "color-red";
          }
          else {
            switch ($field) {
              case 'doc_type':
                $val = $val == 'c' ? "Credit Note" : "Invoice";
                break;
              case 'imported_on':
              case 'invoiced_on':
                if ($val != "") {
                  $val = date('d/m/Y H:i:s', strtotime($val));
                }
                break;
              case 'uws_period_from':
              case 'uws_period_to':
                if ($val != "") {
                  $val = date('d/m/Y', strtotime($val));
                }
                break;
              case 'vat':
                $val .= "-" . $vats[$val] . "%";
                break;
              case "user_id":
                $val = InvoiceForm::getUsernameById($val);
                break;
            }
          }
          $row[$field] = [
            'data' => $val,
            'class' => $cellClass,
          ];
        }
        $rows[] = [
          'data' => $row,
          'class' => [$data->doc_type === "c" ? "credit-note-row" : "invoice-row"],
        ];
      }

      //display data in site
      $form['#attached']['library'] = [
        'esign_invoice_generator/datatable',
        'esign_invoice_generator/import_invoices',
      ];
      $form['table'] = [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['row-border', 'stripe'],
          'id' => 'pending-table',
        ],
        '#header' => $header_table,
        '#rows' => $rows,
        '#sticky' => TRUE,
        '#empty' => t('No pending found'),
      ];
      return $form;
    }
  }

  function groupConcatValues($field) {
    $separator = '@@@';
    return "GROUP_CONCAT(" . $field . " SEPARATOR '" . $separator . "') as " . $field . "s";
  }

  function isA1Flag($flag) {
    return in_array($flag, [1, 3]);
  }

  function markInvoicesExported($btnFlag, $markedIds) {
    $field = $this->isA1Flag($btnFlag) ? 'is_a1_exported' : 'is_exported';
    Database::getConnection()->update(InvoiceForm::$invoiceHeaderTblName)
      ->fields([$field => 1])
      ->condition('id', $markedIds, 'IN')
      ->execute();
  }

  function importCsv($filename, $flag = 1) {
    global $db;
    $db = \Drupal::database();
    $file = fopen(($flag ? self::$importedDir : self::$importDir) . '/' . $filename, "r");
    $first = TRUE;
    $lineNumber = 0;
    $importedOn = date('Y-m-d H:i:s');
    while (($getData = fgetcsv($file, 10000, ",")) !== FALSE) {
      if ($first == TRUE) {
        $first = FALSE;
        continue;
      }
      $lineNumber++;
      $csvFields = $this->csvFields();
      $row = [
        'imported_on' => $importedOn,
        'to_import' => 1,
      ];
      foreach ($csvFields as $index => $csvField) {
        $value = $getData[$index];
        $result = $this->checkFieldValid($csvField, $value);
        $row[$csvField] = $result[0];
        if ($result[1] === FALSE) {
          $row['valid'] = 0;
        }
      }
      if (!isset($row['valid'])) {
        $row['valid'] = 1;
      }
      $row['line_number'] = $lineNumber;
      $row['file_name'] = $filename;
      $db->insert(self::$pendingTable)
        ->fields($row)
        ->execute();
    }
    fclose($file);
    return TRUE;
  }

  public function checkFieldValid($field, $value) {
    global $db;
    if (in_array($field, ['user_id', 'supplier_id', 'dept', 'type'])) {
      if (!is_numeric($value)) {
        $value = -1;
      }
    }
    switch ($field) {
      case 'user_id':
        $user = User::load($value);
        $check = !is_null($user);
        break;
      case 'supplier_id':
        $supplier = $db->select('suppliers', 'm')
          ->condition('id', $value)
          ->fields('m', ['id'])->execute()->fetchAssoc();
        $check = $supplier !== FALSE;
        break;
      case 'uws_ref':
      case 'uws_sup_ref':
      case 'uws_period_from':
      case 'uws_period_to':
      case 'description':
        $length = (strpos($field, 'period') === FALSE) ? ($field === 'description' ? 30 : 25) : 10;
        $check = strlen($value) <= $length;
        if (in_array($field, ['uws_period_from', 'uws_period_to'])) {
          list($dd, $mm, $yyyy) = explode('/', $value);
          $check = checkdate($mm, $dd, $yyyy);
          $value = $check === TRUE ? $yyyy . '-' . $mm . '-' . $dd : -1;
        }
        break;
      case 'doc_type':
        $check = in_array($value, ['c', 'i']);
        break;
      case 'dept':
      case 'type':
      case 'vat':
        $option = $db->select('invoice_' . $field, 'm')
          ->condition('id', $value)
          ->fields('m', ['id'])->execute()->fetchAssoc();
        $check = $option !== FALSE;
        break;
      case 'qty':
      case 'price_per':
      case 'amount':
        $check = is_numeric($value);
        break;
    }
    if ($check === FALSE) {
      $value = $field == "doc_type" ? "n" : -1;
    }
    return [$value, $check];
  }

  function uploadCsv() {
    if ($_FILES["file"]["size"] > 0) {
      $importedDir = self::$importedDir;
      if (!dir($importedDir)) {
        mkdir($importedDir, 0777, TRUE);
      }
      else {
        $fileName = $_FILES["file"]["name"];
        $filePath = $importedDir . '/' . $fileName;
        if (file_exists($filePath)) {
          unlink($filePath);
        }
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $filePath)) {
          if ($this->importCsv($fileName)) {
            \Drupal::messenger()
              ->addMessage("Csv data is imported");
          }
          else {
            \Drupal::messenger()
              ->addMessage("File uploaded but rows in file are incorrect");
          }
        }
      }
    }
    else {
      \Drupal::messenger()
        ->addMessage("File is invalid. please upload correct file");
    }

    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute("esign_invoice_generator.import_controller_display", [], $options)
      ->toString(), 302);
  }

}
