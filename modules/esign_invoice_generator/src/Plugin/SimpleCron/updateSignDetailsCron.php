<?php

namespace Drupal\esign_invoice_generator\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;
use Drupal\esign_invoice_generator\Form\InvoiceForm;

/**
 * Single cron example implementation.
 *
 * @SimpleCron(
 *   id = "update_sign_details_cron",
 *   label = @Translation("Update sign details cron", context = "Update sign details cron")
 * )
 */
class UpdateSignDetailsCron extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $this->updateSignDetailsCron();
  }

  public function updateSignDetailsCron() {
    $db = \Drupal::database();
    $rows = $db->select(InvoiceForm::$invoiceHeaderTblName, 'm')
      ->condition('doc_type', "i")
      ->condition('status', [1,5], "IN")
      ->condition('document_id', [""], "NOT IN")
      ->orderBy('esign_last_checked', "ASC")
      ->range(0, 30)
      ->fields('m', ['document_id', 'id'])
      ->execute()->fetchAll();
    if(count($rows) > 0){
      $InvoiceForm = new InvoiceForm();
      foreach ($rows as $row){
        $status = $InvoiceForm->setSignStatus($row->document_id);
        $this->setCronlog('Updated row id: '. $row->id. "\n".$status);
      }
    }
    $this->setCronlog('Update sign details cron run successfully');
  }
  function setCronlog($msg){
    $this->getLogger('cron')->info($msg);
  }

}
