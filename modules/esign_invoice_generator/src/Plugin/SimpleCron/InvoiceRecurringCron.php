<?php

namespace Drupal\esign_invoice_generator\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;
use Drupal\esign_invoice_generator\Form\IrsForm;
use Drupal\esign_invoice_generator\Form\InvoiceForm;
/**
 * Single cron example implementation.
 *
 * @SimpleCron(
 *   id = "invoice_recurring_cron",
 *   label = @Translation("Invoice recurring cron", context = "Invoice recurring cron")
 * )
 */
class InvoiceRecurringCron extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $this->irsCron();
  }

  public function saveAndSendIrs($invoiceRow) {
    $vats = InvoiceForm::getVats();
    $db = \Drupal::database();
    $irsId = $invoiceRow['id'];
    $newDate = $invoiceRow['new_date'];
    $invoiceRow['uws_period_from'] = $invoiceRow['base_date'];
    $invoiceRow['uws_period_to'] = $newDate;
    $unsetFields = [
      'id',
      'is_active',
      'agreement_id',
      'start_date',
      'end_date',
      'link_id',
      'date_last_changed',
      'last_invoiced',
      'last_changed_by_user_id',
      'recurring_period',
      'recurring_every',
      'base_date',
      'new_date',
    ];
    foreach ($unsetFields as $unsetField) {
      unset($invoiceRow[$unsetField]);
    }
    $invoiceRow['date_created'] = date('Y-m-d H:i:s');
    $invoiceRow['is_exported'] = $invoiceRow['is_a1_exported'] = $invoiceRow['status'] = 0;
    $invoiceRow['doc_type'] = 'i';
    $invoiceRow['source'] = 'r';
    $gInvId = InvoiceForm::getTblLastId();
    $docNumber = InvoiceForm::invoice_num($gInvId, 7, "");
    $invoiceRow['doc_number'] = $docNumber;

    $invDtRows = $db->select(IrsForm::$irsDetailsTblName, 'irs_dt')
      ->fields('irs_dt')
      ->condition('header_id', $irsId)
      ->execute()->fetchAll();
    $newDtRows = [];
    foreach ($invDtRows as $key => $invDtRow) {
      $newDtRow = (array) $invDtRow;
      unset($newDtRow['id']);
      unset($newDtRow['header_id']);
      foreach (array_keys($newDtRow) as $detailKey) {
        $newDtRows[$detailKey][] = $newDtRow[$detailKey];
      }
      $newDtRow['vat_rate'] = $vats[$newDtRow['vat']];
      $newDtRow['invoice_id'] = $gInvId;
      $db->insert(InvoiceForm::$invoiceDetailsTblName)
        ->fields($newDtRow)
        ->execute();
    }
    $invoiceForm = new InvoiceForm();
    $invoiceRow = $invoiceForm->saveAndSend($invoiceRow, $newDtRows);
    $db->update(IrsForm::$irsHeaderTblName)
      ->fields(['last_invoiced' => $newDate])
      ->condition('id', $irsId)
      ->execute();
    $insertedId = $db->insert(InvoiceForm::$invoiceHeaderTblName)
      ->fields($invoiceRow)
      ->execute();
    $this->getLogger('cron')->info('Invoice recurring created ID:'.$insertedId);
    return $insertedId;
  }

  public function irsCron() {
    $db = \Drupal::database();
//    $baseDate = 'IFNULL(last_invoiced, start_date)';
//    $periodKeys = array_keys($this->getOptionsFromFormKey('recurring_period'));
//    $newDate = 'CASE recurring_period ';
//    foreach ($periodKeys as $periodKey) {
//      $newDate .= 'WHEN "' . $periodKey . '" THEN date_add(' . $baseDate . ', INTERVAL recurring_every ' . $periodKey . ') ';
//    }
//    $newDate .= 'END';
//    $query = 'SELECT
//        *,
//       DATE(' . $newDate . ') as new_date,
//        ' . $baseDate . ' as base_date
//    FROM
//      ' . self::$irsHeaderTblName . '
//    WHERE (is_active = TRUE AND ' . $baseDate . ' < CURDATE()) && (' . $newDate . ' <= CURDATE() && ' . $newDate . ' <= end_date)';
//    exit($query);
    $baseDate = 'DATE(IFNULL(last_invoiced, start_date))';
    $periodKeys = array_keys(IrsForm::getOptionsFromFormKey('recurring_period'));
    $query = 'SELECT *, ' . $baseDate . ' as base_date FROM ' . IrsForm::$irsHeaderTblName . ' WHERE (is_active = TRUE AND ' . $baseDate . ' < CURDATE())';
    $pass1 = $db->query($query)->fetchAll();
    $now = date('Y-m-d');
    $irIds = [];
    if(count($pass1) > 0){
      foreach($pass1 as $row1){
        $newDateStr = $row1->base_date." +".$row1->recurring_every." ".$row1->recurring_period;
        $newDate = date('Y-m-d', strtotime($newDateStr));
        if($newDate <= $now && $newDate <= $row1->end_date){
          $row1->new_date = $newDate;
          $irIds[] = $this->saveAndSendIrs((array)$row1);
        }
      }
//      print_r($irIds);
      if(count($irIds) > 0){
        $this->irsCron();
      }else{
        $this->getLogger('cron')->info('Invoice recurring cron run successfully');
      }
    }else{
      $this->getLogger('cron')->info('Invoice recurring cron run successfully');
      exit();
    }
  }

}
