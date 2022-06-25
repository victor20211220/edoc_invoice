<?php

namespace Drupal\esign_invoice_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\esign_invoice_generator\Form\InvoiceForm;
use Drupal\esign_invoice_generator\Form\IrsForm;
use Drupal\esign_invoice_generator\Controller\SuppliersTableController;
use Drupal\user\Entity\User;

/**
 * Class InvoicesTableController.
 *
 * @package Drupal\esign_invoice_generator\Controller
 */
class IrsTableController extends ControllerBase {

  public static function getFields() {
    return [
      'id' => t('Id'),
      'user_id' => t('Username'),
      'supplier_id' => t('Supplier A/C'),
      'uws_ref' => t('Our Ref:'),
      'uws_sup_ref' => t('Supplier Ref:'),
      'start_date' => t('Start Date'),
      'end_date' => t('End Date'),
      'recurring_period' => t('Period'),
      'recurring_every' => t('Every'),
      'date_created' => t('Date Created'),
      'is_active' => t('Status'),
    ];
  }

  /**
   * Display.
   */


  public function display() {
    $tableHeader = $this->getFields();
    $fields = array_keys($tableHeader);
    $tableHeader['manage'] = t('Manage');
    //select records from table
    $query = \Drupal::database()->select(IrsForm::$irsHeaderTblName, 'irs');
    $query->fields('irs', array_merge($fields, ['start_date', 'end_date']));
    $query->leftJoin('suppliers', 's', "[irs].[supplier_id] = [s].[id]");
    $query->fields('s', ['sup_ac']);
    $query->orderBy('id', 'DESC');
    $results = $query->execute()->fetchAll();
    $fields = array_replace($fields, [2 => 'sup_ac']);
    $rows = [];
    $nowTimestamp = time();
    $roles = \Drupal::currentUser()->getRoles();
    $isAdmin = in_array('administrator', $roles);
    $userId = \Drupal::currentUser()->id();;
    foreach ($results as $data) {
      $dataUserId = $data->user_id;
      $canManage = $isAdmin ||  $dataUserId=== $userId;
      $row = [];
      $isActive = $data->is_active;
      if ($isActive == 0) {
        $class = 'blue';
      }
      else {
        $startTimestamp = strtotime($data->start_date);
        $endTimestamp = strtotime($data->end_date);
        if ($endTimestamp < $nowTimestamp) {
          $class = 'red';
        }
        if ($startTimestamp < $nowTimestamp && $nowTimestamp < $endTimestamp) {
          $class = 'green';
        }
        if ($nowTimestamp < $startTimestamp) {
          $class = 'yellow';
        }
      }
      foreach ($fields as $field) {
        $val = $data->{$field};
        switch ($field) {
          case "date_created":
            $val = date('d/m/Y H:i:s', strtotime($val));
            break;
          case "start_date":
          case "end_date":
            $val = date('d/m/Y', strtotime($val));
            break;
          case "is_active" :
            $val = $val ? "Active" : "Stopped";
            break;
          case "user_id":
            $val = InvoiceForm::getUsernameById($val);
            break;
        }
        $row[$field] = $val;
      }
      $val = Url::fromUserInput('/add-irs?irs_id=' . $data->id . '&action=manage');
      $row['manage'] = $canManage ? \Drupal\Core\Link::fromTextAndUrl('Manage', $val) : "";
      $rows[] = [
        'data' => $row,
        'class' => "color-" . $class,
      ];
    }
    //display data in site
    $form['#attached']['library'] = [
      'esign_invoice_generator/datatable',
      'esign_invoice_generator/list_irs',
    ];
    $form['table'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['row-border', 'stripe'],
        'id' => 'invoices-table',
      ],
      '#header' => $tableHeader,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => t('No invoice recurring setups found'),
    ];
    $form['#attached']['drupalSettings'] = [
      'suppliers' => json_encode(SuppliersTableController::getSuppliersOptions()),
      'trading_usernames' => $this->getTradingUsernames()
    ];
    return $form;
  }



  public function getTradingUsernames() {
    $ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', ['administrator', 'trading'], 'IN')
      ->execute();
    $users = User::loadMultiple($ids);
    $usernames = [];
    foreach ($users as $user) {
      $usernames[] = $user->get('name')->getString();
    }
    return $usernames;
  }

}
