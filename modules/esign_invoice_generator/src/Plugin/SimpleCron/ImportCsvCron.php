<?php

namespace Drupal\esign_invoice_generator\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\esign_invoice_generator\Controller\ImportInvoicesTableController;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;
use Drupal\esign_invoice_generator\Form\IrsForm;
use Drupal\esign_invoice_generator\Form\InvoiceForm;

/**
 * Single cron example implementation.
 *
 * @SimpleCron(
 *   id = "import_csv_cron",
 *   label = @Translation("Import csv cron", context = "Import csv cron")
 * )
 */
class ImportCsvCron extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $this->importCsvCron();
  }

  public function importCsvCron() {
    $db = \Drupal::database();
    $query = 'DELETE FROM '.ImportInvoicesTableController::$pendingTable.' WHERE DATEDIFF(imported_on, CURDATE()) <= -14';
    $db->query($query);
    $this->setCronlog('Old pending rows deleted');
    $importDir = ImportInvoicesTableController::$importDir;
    $importedDir = ImportInvoicesTableController::$importedDir;
    if (!dir($importDir)) {
      mkdir($importDir, 0777, TRUE);
    }else{
      if ($handle = opendir($importDir)) {
        $ImportInvoicesTableController = new ImportInvoicesTableController();
        while (FALSE !== ($file = readdir($handle))) {
          if ('.' === $file) {
            continue;
          }
          if ('..' === $file) {
            continue;
          }
          if(strpos($file, 'inv_') === 0 && strpos($file, '.csv') === strlen($file) - 4){
            $importFile = $importDir . '/' . $file;
            if($ImportInvoicesTableController->importCsv($file, 0)){
              $this->setCronlog("Csv data is imported from ". $file);
              rename($importFile, $importedDir.'/'.$file);
            }
          }
        }
        closedir($handle);
      }
    }
    $this->setCronlog('Import csv cron run successfully');
  }
  function setCronlog($msg){
    $this->getLogger('cron')->info($msg);
  }

}
