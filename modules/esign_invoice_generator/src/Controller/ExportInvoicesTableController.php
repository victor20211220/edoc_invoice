<?php

namespace Drupal\esign_invoice_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\esign_invoice_generator\Form\InvoiceForm;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class InvoicesTableController.
 *
 *
 * @package Drupal\esign_invoice_generator\Controller
 */
class ExportInvoicesTableController extends ControllerBase {

  public static function getFields() {
    return [
      'id' => t('Id'),
      'doc_number' => t('Invoice Number'),
      'doc_type' => t('Document Type'),
      'user_id' => t('Creator'),
      'supplier_id' => t('Supplier A/C'),
      'uws_ref' => t('Our Ref'),
      'uws_sup_ref' => t('Supplier Ref'),
      'uws_period_from' => t('Period From'),
      'uws_period_to' => t('Period To'),
      'dept' => t('Dept'),
      'type' => t('Type'),
      'qty' => t('Qty'),
      'description' => t('Description'),
      'price_per' => t('Price Per'),
      'vat' => t('Vat'),
      'vat_rate' => t('Vat Rate'),
      'amount' => t('Amount'),
      'document_id' => t('Nominal Code'),
      'doc_link' => t('Doc Link'),
      'source' => t('Source'),
      'status' => t('Status'),
      'date_created' => t('Date Created'),
      'esign_status' => t('eSign Status'),
      'esign_last_checked' => t('eSign Last Checked'),
      'esign_signers' => t('eSign Signers'),
      'is_exported' => t('Exported'),
      'is_a1_exported' => t('A1 Exported'),
      'reason' => t('Reason'),
    ];
  }

  public function getNominalCodes() {
    $optionTables = ['invoice_type', 'invoice_dept'];
    $result = [];
    $conn = Database::getConnection();
    foreach ($optionTables as $optionTable) {
      $query = $conn->select($optionTable, 'm')
        ->fields('m');
      $options = $query->execute()->fetchAll();
      $optionKey = str_replace('invoice_', '', $optionTable);
      $descKey = $optionKey . "_desc";
      $pairs = [];
      $descPairs = [];
      if (count($options) > 0) {
        foreach ($options as $option) {
          $optionId = $option->id;
          $pairs[$optionId] = $option->nominal_code;
          $descPairs[$optionId] = $option->{$optionKey};
        }
      }
      $result[$optionKey] = $pairs;
      $result[$descKey] = $descPairs;
    }
    return $result;
  }

  /**
   * Display.
   *
   */


  public function display() {
    $conn = Database::getConnection();
    if (isset($_POST['marked_ids'])) {
      $response = ['status' => 0, 'a1_exported_file' => ""];
      $markedIds = array_unique(explode(',', $_POST['marked_ids']));
      $btnFlag = $_POST['btn_flag'];
      $this->markInvoicesExported($btnFlag, $markedIds);
      $response['status'] = 1;
      if ($btnFlag == 3) {
        $query = $conn->select(InvoiceForm::$invoiceHeaderTblName, 'inv')
          ->fields('inv')
          ->condition('[inv].[id]', $markedIds, 'IN');
        $query->leftJoin('suppliers', 'm', "[inv].[supplier_id] = [m].[id]");
        $query->fields('m', ['sup_ac']);
        $query->leftJoin(InvoiceForm::$invoiceDetailsTblName, 'det', "[inv].[id] = [det].[invoice_id]");
        $query->fields('det', ['dept', 'type', "qty", 'description', "price_per", 'vat', 'vat_rate', 'amount']);
        $query->orderBy('[inv].[id]', 'DESC');
        $results = $query->execute()->fetchAll();

        //output excel data
        $loggedUsername = \Drupal::currentUser()->getDisplayName();
        $spreadsheet = new Spreadsheet();
        $a1InvoSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'A1INVO');
        $a1ContSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'A1Cont');
        $plContSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'PLCont');
        $spreadsheet->addSheet($a1InvoSheet, 0);
        $spreadsheet->addSheet($a1ContSheet, 1);
        $spreadsheet->addSheet($plContSheet, 2);
        $a1InvoSheet = $spreadsheet->getSheet(0);
        $a1ContSheet = $spreadsheet->getSheet(1);
        $plContSheet = $spreadsheet->getSheet(2);
        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->removeSheetByIndex(3);
        $alphas = [
          'A',
          'B',
          'C',
          'D',
          'E',
          'F',
          'G',
          'H',
          'I',
          'J',
          'K',
          'L',
          'M',
          'N',
          'O',
          'P',
          'Q',
          'R',
          'S',
          'T',
          'U',
          'V',
          'W',
        ];

        $lastInvoiceId = "";
        $ZTotal = $ATotal = $AVat = $CTotal = $CVat = 0;
        $headerRow = $details = [];
        $invoiceCnt = count($results);
        $nominalCodes = $this->getNominalCodes();
        $rInd = $contInd = 1;
        foreach ($results as $key => $invoice) {
          $uwsPeriodFrom = $invoice->uws_period_from;
          $invoicePeriod = $uwsPeriodFrom === "" ? "" : date("d/m/Y", strtotime($uwsPeriodFrom)) . ' to ' . date("d/m/Y", strtotime($invoice->uws_period_to));
          $invoiceId = $invoice->id;
          $invoiceTimeStamp = strtotime($invoice->date_created);
          $invoiceDate = date('d/m/Y', $invoiceTimeStamp);
          $invoiceY = date('Y', $invoiceTimeStamp);
          $invoiceM = date('m', $invoiceTimeStamp);
          $username = InvoiceForm::getUsernameById($invoice->user_id);
          if ($lastInvoiceId === "" || $lastInvoiceId != $invoiceId) {
            if ($lastInvoiceId !== "") {
              $headerRow[5] = $this->formatPrice($ZTotal + $ATotal + $AVat + $CTotal + $CVat);
              $headerRow[13] = $this->formatPrice($ZTotal);
              $headerRow[16] = $this->formatPrice($ATotal);
              $headerRow[17] = $this->formatPrice($AVat);
              $headerRow[18] = $this->formatPrice($CTotal);
              $headerRow[20] = $this->formatPrice($CVat);
              foreach ($alphas as $rKey => $alpha) {
                $a1InvoSheet->setCellValue($alpha . ($rInd), $headerRow[$rKey]);
                if ($rKey < 10) {
                  $dKey = 0;
                  switch ($rKey) {
                    case 0:
                      $dKey = 3;
                      break;
                    case 1:
                      $dKey = 3;
                      break;
                    case 2:
                    case 4:
                      $dKey = 5;
                      break;
                    case 3:
                      $dKey = 6;
                      break;
                    case 5:
                      $dKey = 10;
                      break;
                    case 6:
                      $dKey = 10;
                      break;
                    case 7:
                      $dKey = 10;
                      break;
                    case 8:
                      $dKey = 7;
                      break;
                    case 9:
                      $dKey = 8;
                      break;
                  }
                  $a1ContVal = $plContVal = $headerRow[$dKey];
                  if (in_array($rKey, [2, 4])) {
                    //                    if ($rKey == 2) {
                    //                      $a1ContVal = $plContVal = $headerRow[$dKey] + $headerRow[20];
                    //                    }
                    $a1ContVal *= -1;
                    $plContVal *= -1;
                  }
                  if ($rKey == 5) {
                    $plContVal = 101;
                  }
                  if ($rKey == 6) {
                    $a1ContVal = $headerRow[7];
                  }
                  if ($rKey == 7) {
                    $a1ContVal = $headerRow[8];
                  }
                  if ($rKey < 8) {
                    $a1ContSheet->setCellValue($alpha . ($contInd), $a1ContVal);
                  }
                  $plContSheet->setCellValue($alpha . ($contInd), $plContVal);
                }
              }
              $rInd++;
              $contInd++;
              foreach ($details as $row) {
                foreach ($alphas as $rKey => $alpha) {
                  $a1InvoSheet->setCellValue($alpha . ($rInd), $row[$rKey]);
                }
                $rInd++;
              }
              $ZTotal = $ATotal = $AVat = $CTotal = $CVat = 0;
              $headerRow = $details = [];
            }
            $lastInvoiceId = $invoiceId;
            $headerRow = [
              "H",
              "01",
              $invoice->doc_type == "c" ? "CRED" : "INVO",
              $invoice->doc_number,
              $invoice->uws_ref . "-" . $invoicePeriod,
              'total_vat',
              $invoice->sup_ac,
              $invoiceY,
              $invoiceM,
              $invoice->sup_ac,
              $invoiceDate,
              "",
              "Z",
              0,
              0,
              "A",
              "aTotal",
              "aVat",
              "C",
              "cTotal",
              "cVat",
              $username,
              $username,
            ];
          }
          $invoiceType = $invoice->type;
          $net = $invoice->amount;
          if ($invoice->doc_type == "c") {
            $net *= -1;
          }
          $vat = $invoice->vat;
          switch ($vat) {
            case "Z":
              $ZTotal += $net;
              break;
            default:
              ${$vat . "Total"} += $net;
              ${$vat . "Vat"} += $invoice->vat_rate * $net / 100;
              break;
          }
          $row = [
            "N",
            "01",
            "7-01",
            "04-" . $nominalCodes['type'][$invoiceType] . "-" . $nominalCodes['dept'][$invoice->dept] . "",
            "{$nominalCodes['type_desc'][$invoiceType]} {$invoicePeriod} {$invoice->qty}x ". InvoiceForm::doubleFormat($invoice->price_per). " {$invoice->description}",
            $net,
            $invoice->vat,
          ];
          for ($i = 0; $i < 15; $i++) {
            array_push($row, "");
          }
          array_push($details, $row);
          if ($key + 1 == $invoiceCnt) {
            $headerRow[5] = $this->formatPrice($ZTotal + $ATotal + $AVat + $CTotal + $CVat);
            $headerRow[13] = $this->formatPrice($ZTotal);
            $headerRow[15] = $this->formatPrice($ATotal);
            $headerRow[17] = $this->formatPrice($AVat);
            $headerRow[18] = $this->formatPrice($CTotal);
            $headerRow[20] = $this->formatPrice($CVat);
            foreach ($alphas as $rKey => $alpha) {
              $a1InvoSheet->setCellValue($alpha . ($rInd), $headerRow[$rKey]);
              if ($rKey < 10) {
                $dKey = 0;
                switch ($rKey) {
                  case 0:
                    $dKey = 3;
                    break;
                  case 1:
                    $dKey = 3;
                    break;
                  case 2:
                  case 4:
                    $dKey = 5;
                    break;
                  case 3:
                    $dKey = 6;
                    break;
                  case 5:
                    $dKey = 10;
                    break;
                  case 6:
                    $dKey = 10;
                    break;
                  case 7:
                    $dKey = 10;
                    break;
                  case 8:
                    $dKey = 7;
                    break;
                  case 9:
                    $dKey = 8;
                    break;
                }
                $a1ContVal = $plContVal = $headerRow[$dKey];
                if (in_array($rKey, [2, 4])) {
                  $a1ContVal *= -1;
                  $plContVal *= -1;
                }
                if ($rKey == 5) {
                  $plContVal = 101;
                }
                if ($rKey == 6) {
                  $a1ContVal = $headerRow[7];
                }
                if ($rKey == 7) {
                  $a1ContVal = $headerRow[8];
                }
                if ($rKey < 8) {
                  $a1ContSheet->setCellValue($alpha . ($contInd), $a1ContVal);
                }
                $plContSheet->setCellValue($alpha . ($contInd), $plContVal);
              }
            }
            $rInd++;
            foreach ($details as $row) {
              foreach ($alphas as $rKey => $alpha) {
                $a1InvoSheet->setCellValue($alpha . ($rInd), $row[$rKey]);
              }
              $rInd++;
            }
          }
        }
        $filename = "a1_export_" . $loggedUsername . "_" . date('_d_m_Y_H_i_s') . ".xls";
        $writer = new Xls($spreadsheet);
        $writer->save($filename);
        $response['a1_exported_file'] = $filename;
      }
      \Drupal::messenger()
        ->addMessage("Marked as " . ($this->isA1Flag($btnFlag) ? 'A1 ' : '') . "exported");
      echo json_encode($response);
      exit();
    }
    else {
      //create table header
      $header_table = $this->getFields();
      $fields = array_keys($this->getFields());
      array_splice($fields, 9, 8);

      //select records from table
      $query = $conn->select(InvoiceForm::$invoiceHeaderTblName, 'inv');
      $query->fields('inv', $fields);
      $query->leftJoin('suppliers', 'm', "[inv].[supplier_id] = [m].[id]");
      $query->fields('m', ['sup_ac']);
      $query->leftJoin(InvoiceForm::$invoiceDetailsTblName, 'det', "[inv].[id] = [det].[invoice_id]");
      $query->fields('det', ['dept', 'type', 'qty', 'description', "price_per", 'vat', 'vat_rate', 'amount']);
      $query->orderBy('id', 'DESC');
      $results = $query->execute()->fetchAll();
      $fields = array_keys($this->getFields());
      $fields = array_replace($fields, [4 => 'sup_ac']);
      $detailOptionValues = InvoiceForm::getOptions();
      $rows = [];
      foreach ($results as $data) {
        $row = [];
        foreach ($fields as $field) {
          $val = $data->{$field};
          switch ($field) {
            case 'doc_type':
              $val = $val == 'c' ? "Credit Note" : "Invoice";
              break;
            case 'status':
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
              break;
            case 'date_created':
              $val = date('d/m/Y H:i:s', strtotime($val));
              break;
            case 'uws_period_from':
            case 'uws_period_to':
              $val = date('d/m/Y', strtotime($val));
              break;
            case "source" :
              $val = $val == 'r' ? "Recurring" : "User";
              break;
            case 'is_exported':
              $val = $val ? "Yes" : "No";
              break;
            case 'is_a1_exported':
              $val = $val ? "Yes" : "No";
              break;
            case 'document_id':
              $val = '"01-' . $data->dept . '-' . $data->type . '"';
              break;
            case 'dept':
            case 'type':
              $detailOptions = $detailOptionValues[$field];
              $val = isset($detailOptions[$val]) ? $detailOptions[$val] : "";
              break;
            case 'vat_rate':
              $val .= "%";
              break;
            case "user_id":
              $val = InvoiceForm::getUsernameById($val);
              break;
          }
          $row[$field] = $val;
        }
        $rows[] = [
          'data' => $row,
          'class' => [$data->doc_type === "c" ? "credit-note-row" : "invoice-row"],
        ];
      }

      //display data in site
      $form['#attached']['library'] = [
        'esign_invoice_generator/datatable',
        'esign_invoice_generator/export_invoices',
      ];
      $form['table'] = [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['row-border', 'stripe'],
          'id' => 'invoices-table',
        ],
        '#header' => $header_table,
        '#rows' => $rows,
        '#sticky' => TRUE,
        '#empty' => t('No invoices found'),
      ];
      return $form;
    }
  }

  function formatPrice($val) {
    return number_format($val, 2, '.', '');
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

}
