<?php

namespace Drupal\esign_invoice_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class SuppliersTableController.
 *
 * @package Drupal\esign_invoice_generator\Controller
 */
class SuppliersTableController extends ControllerBase {

  public static $tblName = 'suppliers';

  public static function getFields() {
    return [
      'sup_ac' => t('Supplier A/C'),
      'company_name' => t('Company Name'),
      'city' => t('City'),
      'supplier_name' => t('Contact Name'),
      'supplier_role' => t('Contact Role'),
      'email' => t('Contact Email'),
    ];
  }

  /**
   * Display.
   *
   * @return array element
   */


  public function display() {
    $is_admin = in_array('administrator', \Drupal::currentUser()->getRoles());
    //create table header
    $thead = $this->getFields();
    $operCols = [
      'opte' => t('Edit'),
      'optg' => t('Generate e-Invoice'),
    ];
    if ($is_admin)  $operCols['opt'] = t('Delete');
    $thead = array_merge($thead, $operCols);
    $fields = array_keys($this->getFields());
    array_push($fields, 'id');
    //select records from table=
    $query = \Drupal::database()->select(self::$tblName, 'm')
      ->fields('m', $fields);
    $query->orderBy('sup_ac', 'asc');
    $results = $query->execute()->fetchAll();
    $rows = [];
    foreach ($results as $data) {
      $row = [];
      foreach ($fields as $field) {
        if ($field != 'id') {
//          $row[$field] = $data->{$field};
          array_push($row, $data->{$field});
        }
      }
      $delete = Url::fromUserInput('/delete-supplier/' . $data->id);
      $edit = Url::fromUserInput('/edit-supplier?num=' . $data->id);
      $generate = Url::fromUserInput('/suppliers/form/invoice_form?num=' . $data->id);
      array_push($row, \Drupal\Core\Link::fromTextAndUrl('Edit', $edit));
      array_push($row, \Drupal\Core\Link::fromTextAndUrl('Generate e-Invoice', $generate));
      if ($is_admin) {
        array_push($row, \Drupal\Core\Link::fromTextAndUrl('Delete', $delete));
      }
      array_push($rows, $row);
    }
    //display data in site
    $form['#attached']['library'] = [
      'esign_invoice_generator/datatable',
      'esign_invoice_generator/suppliers'
    ];
    $form['suppliers'] = [
      '#type' => 'table',
      '#attributes' => ['class'=>['row-border','stripe']],
      '#header' => $thead,
      '#rows' => $rows,
      '#sticky' => true,
      '#empty' => t('No suppliers found'),
    ];
    return $form;
  }

  public static function getSuppliersOptions(){
     $query = \Drupal::database()->select(self::$tblName, 'm')
      ->fields('m', ['id', 'sup_ac'])
      ->orderBy('sup_ac', 'asc');
      $suppliers =  $query->execute()->fetchAllKeyed(0, 1);
      return $suppliers;
  }
}
