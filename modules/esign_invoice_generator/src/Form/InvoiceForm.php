<?php

namespace Drupal\esign_invoice_generator\Form;

require_once __DIR__ . '/../../vendor/autoload.php';

// reference the Dompdf namespace
use Dompdf\Dompdf;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

//require signnow
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\Entity\User;
use SignNow\Api\Action\OAuth as SignNowOAuth;
use SignNow\Api\Entity\Document\Upload as DocumentUpload;
use SignNow\Api\Entity\Document\Document;
use SignNow\Api\Entity\Document\Field\SignatureField;
use SignNow\Api\Entity\Document\Field\TextField;
use SignNow\Rest\Http\Request;
use SignNow\Api\Entity\Invite\Recipient;
use SignNow\Api\Entity\Invite\Invite;
use SignNow\Api\Entity\Document\DownloadLink;
use  SignNow\Api\Entity\Auth\Token;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class InvoiceForm.
 *
 * @package Drupal\esign_invoice_generator\invoice_form
 */
class InvoiceForm extends FormBase
{

  public $apiUrl;

  public $token;

  public $documentId;

  public $entityManager;

  static $invoiceHeaderTblName = 'invoice_header';

  static $invoiceDetailsTblName = 'invoice_details';

  public $siteInfo = [];

  public $detailRows = [];

  public function __construct()
  {
    $query = \Drupal::database()->select('invoice_sites', 'm')
      ->condition('id', 1)
      ->fields('m');
    $this->siteInfo = $query->execute()->fetchAssoc();
  }

  public function setApiUrl($apiUrl)
  {
    $this->apiUrl = $apiUrl;
  }

  public function setToken($token)
  {
    $this->token = $token;
  }

  public function setDocumentId($documentId)
  {
    $this->documentId = $documentId;
  }

  public function setEntityManager($entityManager)
  {
    $this->entityManager = $entityManager;
  }

  public static function getDetailFields()
  {
    return [
      'dept' => t('Dept'),
      'type' => t('Type'),
      'qty' => t('Qty'),
      'description' => [t('Description'), 25],
      'price_per' => t('Price per'),
      'vat' => t('Vat%'),
      'amount' => t('Amount'),
    ];
  }

  public static function getMainFields()
  {
    return [
      'uws_ref' => [t('Our Ref:'), 40],
      'uws_sup_ref' => [t('Supplier Ref:'), 40],
      'uws_period_from' => t('Period Range From:'),
      'uws_period_to' => t('To:'),
      'doc_type' => t('Document type:'),
    ];
  }

  public static function getOptions()
  {
    $optionTables = ['invoice_dept', 'invoice_type', 'invoice_vat'];
    $result = [];
    $db = Database::getConnection();
    foreach ($optionTables as $optionTable) {
      $query = $db->select($optionTable, 'm')
        ->fields('m');
      if ($optionTable === "invoice_dept") {
        $query->addExpression('id + 0', 'order_field');
        $query->orderBy('order_field', "ASC");
      }
      $options = $query->execute()->fetchAll();
      $optionKey = str_replace('invoice_', '', $optionTable);
      $pairs = [];
      if (count($options) > 0) {
        foreach ($options as $option) {
          $pairs[$option->id] = ($optionKey == 'dept' ? $option->id . " - " : "") . ($option->{$optionKey == 'vat' ? "id" : $optionKey});
        }
      }
      $result[$optionKey] = $pairs;
    }
    return $result;
  }

  public static function getVats()
  {
    $db = Database::getConnection();
    $options = $db->select('invoice_vat', "iv")
      ->fields("iv")
      ->execute()->fetchAll();
    $result = [];
    foreach ($options as $option) {
      $result[$option->id] = $option->vat;
    }
    return $result;
  }

  public function getFormId()
  {
    return 'esign_invoice_generator_invoiceform';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $db = \Drupal::database();
    if (isset($_GET['document_id'])) { //cancel invite
      $documentId = $_GET['document_id'];
      $cancelStatus = $this->cancelInvite($documentId);
      if ($cancelStatus == 'success') {
        $invoiceCid = $_GET['invoice_cid'];
        $query = $db->select(self::$invoiceHeaderTblName, 'inv')
          ->fields('inv')
          ->condition('id', $invoiceCid);
        $invoiceRow = $query->execute()->fetchAssoc();
        $signersNum = $invoiceRow['esign_signers'];
        $unsetFields = [
          'id',
          'invoice_file',
          'document_id',
          'esign_status',
          'esign_last_checked',
          'esign_signers',
          'reason',
        ];
        foreach ($unsetFields as $unsetField) {
          unset($invoiceRow[$unsetField]);
        }
        $invoiceRow['date_created'] = date('Y-m-d H:i:s');
        $invoiceRow['is_exported'] = $invoiceRow['is_a1_exported'] = $invoiceRow['status'] = 0;
        $invoiceRow['doc_type'] = 'c';
        $invoiceRow['reason'] = $_GET['reason'];
        $invoiceRow['doc_link'] = $invoiceRow['doc_number'];
        $gInvId = $this->getTblLastId();
        $docNumber = $this->invoice_num($gInvId, 7, "");
        $invoiceRow['doc_number'] = $docNumber;

        $invDtRows = $db->select(self::$invoiceDetailsTblName, 'inv_dt')
          ->fields('inv_dt')
          ->condition('invoice_id', $invoiceCid)
          ->execute()->fetchAll();
        $newDtRows = [];
        foreach ($invDtRows as $invDtRow) {
          $newDtRow = (array)$invDtRow;
          unset($newDtRow['id']);
          foreach (array_keys($newDtRow) as $detailKey) {
            $newDtRows[$detailKey][] = $newDtRow[$detailKey];
          }
          $newDtRow['invoice_id'] = $gInvId;
          $db->insert(self::$invoiceDetailsTblName)
            ->fields($newDtRow)
            ->execute();
        }
        $db->update(self::$invoiceHeaderTblName)
          ->fields([
            'status' => 5,
            'doc_link' => $docNumber,
          ])
          ->condition('id', $invoiceCid)
          ->execute();
        $invoiceRow = $this->saveAndSend($invoiceRow, $newDtRows);
        $db->insert(self::$invoiceHeaderTblName)
          ->fields($invoiceRow)
          ->execute();
        if ($signersNum == 0) {
          $emailResult = $this->emailOnCreditCreation($invoiceCid, $invoiceRow['invoice_file']);
          \Drupal::messenger()->addMessage($emailResult);
        }


        \Drupal::messenger()->addMessage("eSign cancelled");
      } else {
        \Drupal::messenger()->addMessage("eSign cancellation failed");
      }
      $options['absolute'] = TRUE;
      return new RedirectResponse(Url::fromRoute("esign_invoice_generator.invoices_controller_display", [], $options)
        ->toString(), 302);
    } else {
      if (isset($_GET['download_document_id'])) {
        return new TrustedRedirectResponse($this->getDocumentStatusLink($_GET['download_document_id']));
      } else {
        if (isset($_GET['sign_document_id'])) {
          $this->setSignStatus($_GET['sign_document_id']);
          echo "Updated";
          exit();
        } else {
          if (isset($_GET['gisd'])) {
            echo json_encode($this->getSignDetails($_GET['gisd']));
            exit();
          } else {
            if (isset($_GET['oiv'])) {
              echo json_encode($this->checkOIV($_GET['oiv']));
              exit();
            } else {
              if (isset($_GET['reason_id'])) {
                echo $this->checkOIV($_GET['reason_id'])['reason'];
                exit();
              } else {
                $form['#attached']['library'] = [
                  'esign_invoice_generator/invoice_form',
                  'esign_invoice_generator/user_invoice_create_form',
                ];
                $form['#id'] = 'invoice-form';
                $form['#class'] = 'datatable';
                $uwsFields = $this->getMainFields();
                $detailFields = $this->getDetailFields();
                $options = $this->getOptions();
                if (isset($_GET['invoice_eid'])) {
                  $invoiceId = $_GET['invoice_eid'];
                  $query = $db->select(self::$invoiceHeaderTblName, 'inv');
                  $query->fields('inv');
                  $query->leftJoin('suppliers', 'm', "[inv].[supplier_id] = [m].[id]");
                  $query->fields('m', ['email', 'user_id']);
                  $query->condition('[inv].[id]', $invoiceId);
                  global $row;
                  global $detailRows;
                  $row = $query->execute()->fetchAssoc();
                  $detailRows = $db->select(self::$invoiceDetailsTblName)
                    ->fields(NULL, array_keys($detailFields))
                    ->condition('invoice_id', $invoiceId)
                    ->execute()->fetchAll();
                  if (!$row) {
                    \Drupal::messenger()
                      ->addMessage("Invoice entries not found");
                    return new RedirectResponse(Url::fromRoute("esign_invoice_generator.invoices_controller_display", [], ['absolute' => TRUE])
                      ->toString(), 302);
                  }
                }
                global $row;
                global $detailRows;
                $isEdit = isset($row);
                foreach ($uwsFields as $key => $field) {
                  $form[$key] = [
                    '#title' => is_array($field) ? $field[0] : $field,
                    '#required' => TRUE,
                    '#type' => $this->isUwsPeriodField($key) === FALSE ?
                      ($this->isDocTypeField($key) ? "select" : "textfield") : "date",
                    '#default_value' => $isEdit ? $row[$key] : ($this->isDocTypeField($key) ? "i" : ""),
                  ];
                  if (is_array($field)) {
                    $form[$key]['#attributes']['maxlength'] = $field[1];
                    $form[$key]['#attributes']['size'] = $field[1];
                  }
                  if ($this->isDocTypeField($key)) {
                    $form[$key]['#options'] = [
                      'i' => $this->t('Invoice'),
                      'c' => $this->t('Credit Note'),
                    ];
                  }
                }

                $form['uws_period_from']['#prefix'] = '<div class="form-group">';
                $form['uws_period_to']['#suffix'] = '</div>';
                $form['uws_ref']['#prefix'] = '<div id="mainFields">';
                $form['doc_type']['#suffix'] = '</div>';
                if ($isEdit) {
                  foreach ($detailRows as $i => $detailRow) {
                    foreach ($detailFields as $key => $label) {
                      $multi_key = $key . '-' . $i . '-[]';
                      $form[$multi_key] = [
                        '#title' => $i == 0 ? is_array($label) ? $label[0] : $label : '',
                        '#required' => FALSE,
                        '#value' => $detailRow->{$key},
                      ];
                      if (is_array($label)) {
                        $form[$multi_key]['#attributes']['size'] = $label[1];
                        $form[$multi_key]['#attributes']['maxlength'] = $label[1];
                      }
                      if ($key == 'amount') {
                        $form[$multi_key]['#default_value'] = 0;
                        $form[$multi_key]['#step'] = 0.01;
                      }
                      if (!in_array($key, ['description', 'amount'])) {
                        $form[$multi_key]['#type'] = 'select';
                        $form[$multi_key]['#options'] = $options[$key];
                      } else {
                        $form[$multi_key]['#type'] = $key == 'amount' ? 'number' : 'textfield';
                      }
                      if (in_array($key, ['qty', 'price_per'])) {
                        $form[$multi_key]['#attributes']['min'] = 0;
                        $form[$multi_key]['#attributes']['max'] = 1000000;
                      }
                    }
                    $form['delete' . $i] = [
                      '#type' => 'button',
                      '#value' => ' X ',
                      '#attributes' => ['class' => ['delete-row']],
                    ];
                    $form['dept-' . $i . '-[]']['#prefix'] = '<div class="one-block">';
                    $form['delete' . $i]['#suffix'] = '</div>';
                  }
                } else {
                  foreach ($detailFields as $key => $label) {
                    $multi_key = $key . '[]';
                    $form[$multi_key] = [
                      '#title' => is_array($label) ? $label[0] : $label,
                      '#required' => FALSE,
                      '#type' => 'select',
                    ];
                    if (is_array($label)) {
                      $form[$multi_key]['#attributes']['size'] = $label[1];
                      $form[$multi_key]['#attributes']['maxlength'] = $label[1];
                    }
                    if (in_array($key, ['qty', 'price_per'])) {
                      $form[$multi_key]['#attributes']['min'] = 0;
                      $form[$multi_key]['#attributes']['max'] = 1000000;
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
                          $form[$multi_key]['#default_value'] = 0.00;
                          $form[$multi_key]['#attributes']['readonly'] = "";
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
                $form['send_to_signnow'] = [
                  '#type' => 'hidden',
                  '#value' => 0,
                ];
                $form['add'] = [
                  '#type' => 'button',
                  '#value' => t('Add new row'),
                ];
                $form['save'] = [
                  '#type' => 'hidden',
                  '#value' => 'Save',
                ];
                $form['save_send'] = [
                  '#type' => 'submit',
                  '#value' => 'Generate Document',
                ];
                return $form;
              }
            }
          }
        }
      }
    }

    //    }
  }

  /**
   * {@inheritdoc}
   */
  public
  function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public
  function submitForm(array &$form, FormStateInterface $form_state)
  {
    $db = Database::getConnection();
    $form_data = $_POST;
    $fields = ['doc_link' => '', 'source' => 'u'];
    $uwsFields = $this->getMainFields();
    foreach ($uwsFields as $key => $col) {
      $fields[$key] = $form_data[$key];
    };
    if (isset($_GET['invoice_eid'])) {
      $invoiceEid = $_GET['invoice_eid'];
      $last_id = $invoiceEid * 1;
      $query = $db->select(self::$invoiceHeaderTblName, NULL)
        ->condition('id', $invoiceEid)
        ->fields(NULL, ['supplier_id', 'doc_link']);
      $invoice = $query->execute()->fetchAssoc();
      $fields['supplier_id'] = $invoice['supplier_id'];
      $fields['doc_link'] = $invoice['doc_link'];
    } else {
      $last_id = $this->getTblLastId();
    }
    $docNumber = $this->invoice_num($last_id, 7, "");
    $fields['doc_number'] = $docNumber;
    if (!isset($invoiceEid)) {
      $fields['supplier_id'] = $_GET['num'];
      $fields['date_created'] = date('Y-m-d H:i:s');
      $fields['status'] = 0;
      $fields['is_exported'] = 0;
      $fields['is_a1_exported'] = 0;
      $fields['user_id'] = \Drupal::currentUser()->id();

      /*when create credit note*/
      if ($fields['doc_type'] === "c") {
        $oivNum = $_POST['oiv-num'];
        $fields['reason'] = $_POST['reason'];
        $fields['doc_link'] = ($this->checkOIV($oivNum))['doc_number'];
        $db->update(self::$invoiceHeaderTblName)
          ->fields(['doc_link' => $docNumber])
          ->condition('id', $oivNum)
          ->execute();
      }
    }
    $invoiceDetails = [];
    $detailFields = $this->getDetailFields();
    foreach ($detailFields as $key => $col) {
      $invoiceDetails[$key] = $form_data[$key];
    };
    if ($form_data['send_to_signnow']) {
      $fields = $this->saveAndSend($fields, $invoiceDetails);
    }
    \Drupal::messenger()->addMessage("Invoice generated");

    $db->delete(self::$invoiceDetailsTblName)
      ->condition('invoice_id', $last_id)
      ->execute();
    $detail_keys = array_keys($detailFields);
    $details_count = count($invoiceDetails[$detail_keys[0]]);
    $vats = self::getVats();
    for ($i = 0; $i < $details_count; $i++) {
      $invoiceDetailRow = ['invoice_id' => $last_id];
      foreach ($detail_keys as $detail_key) {
        $val = $invoiceDetails[$detail_key][$i];
        if (in_array($detail_key, ["price_per", "amount", "qty"]) && $val == "") {
          $val = 0;
        }
        $invoiceDetailRow[$detail_key] = $val;
      }
      $invoiceDetailRow['vat_rate'] = $vats[$invoiceDetailRow['vat']];
      //insert invoice details row
      $db->insert(self::$invoiceDetailsTblName)
        ->fields($invoiceDetailRow)
        ->execute();
    }

    $query = \Drupal::database();
    if (isset($invoiceEid)) {
      $query->update(self::$invoiceHeaderTblName)
        ->fields($fields)
        ->condition('id', $invoiceEid)
        ->execute();
    } else {
      $query->insert(self::$invoiceHeaderTblName)
        ->fields($fields)
        ->execute();
    }
    $form_state->setRedirect("esign_invoice_generator.invoices_controller_display");
  }


  public static function invoice_num($input, $pad_len = 7, $prefix = NULL)
  {
    if (is_string($prefix)) {
      return sprintf("%s%s", $prefix, str_pad($input, $pad_len, "0", STR_PAD_LEFT));
    }
    return str_pad($input, $pad_len, "0", STR_PAD_LEFT);
  }

  public
  function getInvoiceHtml($fields, $invoiceDetails)
  {
    $logo_path = file_url_transform_relative(file_create_url(theme_get_setting('logo.url')));
    $options = $this->getOptions();
    $site_info = $this->siteInfo;
    $db = Database::getConnection();
    $query = $db->select('suppliers', 'm')
      ->condition('id', $fields['supplier_id'])
      ->fields('m');
    $supplier_details = $query->execute()->fetchAssoc();
    //    $user_name = \Drupal::currentUser()->getDisplayName();
    //    $user_mail = \Drupal::currentUser()->getEmail();
    $userId = $fields['user_id'];
    $user_name = self::getUsernameById($userId);
    $user_mail = self::getUserMailById($userId);
    $details = [];
    $detail_keys = array_keys($this->getDetailFields());
    $details_count = count($invoiceDetails[$detail_keys[0]]);
    $total_vat = 0;
    $total_net = 0;
    $testCount = 19;
    $vats = self::getVats();
    for ($i = 0; $i < $details_count; $i++) {
      $row = '<tr>';
      foreach ($detail_keys as $detail_key) {
        $val = $invoiceDetails[$detail_key][$i];
        if($detail_key === "type") continue;
        if (!in_array($detail_key, ["qty", "description", "price_per", "amount"])) {
          if ($detail_key == 'vat') {
            $vatVal = $vats[$val];
            $net = (float)$invoiceDetails['amount'][$i] * 1;
            $total_vat += $net * $vatVal / 100;
            $total_net += $net;
            $val = $val . "-" . $vatVal . "%";
          } else {
            $val = $options[$detail_key][$val];
            if ($detail_key == "dept") {
              $val = explode(" - ", $val)[1] ."<br/><span>".$options['type'][$invoiceDetails['type'][$i]]."</span>";
            }
          }
        }
        if (in_array($detail_key, ['price_per', 'amount'])) {
          $val = number_format((float)$val, 2, '.', '');
        }
        $row .= '<td>' . $val . '</td>';
      }
      $row .= '</tr>';
      $details[] = $row;
      /*
      for ($j = 0; $j < $testCount; $j++) {
        $details[] = $row;
      }
      */
    }
    $total_due = number_format($total_net + $total_vat, 2, '.', '');
    $total_vat = number_format($total_vat, 2, '.', '');
    $total_net = number_format($total_net, 2, '.', '');
    $sign = $sign_date = "";
    if ($fields['doc_type'] === "c") {
      $sign = "Not Required";
      $sign_date = "As Above";
    }
    $keys = [
      'site_name',
      'site_address',
      'site_tel',
      'site_vat_reg',
      'uws_ref',
      'uws_period_from',
      'uws_period_to',
      'uws_sup_ref',
      'doc_number',
      'sup_ac',
      'company_name',
      'address_1',
      'address_2',
      'county',
      'city',
      'postcode',
      'total_net',
      'total_vat',
      'total_due',
      'user_name',
      'user_mail',
      'supplier_name',
      'email',
      'supplier_role',
      'doc_type_title',
      'show_doc_link',
      'doc_link',
      'sign',
      'sign_date',
    ];
    $data = [
      'invoice_date' => date('d/m/Y'),
      'site_logo' => $this->getBase64Image(__DIR__ . '/../../../..' . $logo_path),
    ];
    foreach ($keys as $key) {
      $site_info_check = str_contains($key, 'site');
      $replace = '';
      global $replace;
      if ($site_info_check) {
        $replace = $site_info[substr($key, 5)];
      } elseif ($this->isUwsPeriodField($key) !== FALSE) {
        $val = $fields[$key];
        $replace = $val === "" ? "" : date('d/m/Y', strtotime($val));
      } elseif ($key === "doc_type_title") {
        $replace = $fields['doc_type'] === "i" ? "Invoice" : "Credit Note";
      } elseif ($key === "show_doc_link") {
        $replace = $fields['doc_type'] === "i" ? "none" : "table-row";
      } elseif ($key === "doc_link") {
        $replace = $fields['doc_type'] === "i" ? "" : $fields[$key];
      } elseif (isset($fields[$key])) {
        $replace = $fields[$key];
      } elseif (isset($supplier_details[$key])) {
        $replace = $supplier_details[$key];
      } else {
        $replace = ${$key};
      }
      $data[$key] = $replace;
    }
    //\Drupal::service('renderer')->renderPlain($data),
    return [
      $data,
      $data['user_mail'],
      $details,
      $supplier_details,
    ];

  }

  public
  function getBase64Image($path)
  {
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    $type = $type == 'svg' ? 'svg+xml' : $type;
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
  }

  public
  function makeToken()
  {
    // configuring entity manager with the basic token
    $siteInfo = $this->siteInfo;
    $apiUrl = $siteInfo['signow_live'] ? 'https://api.signnow.com' : 'https://api-eval.signnow.com';
    $this->setApiUrl($apiUrl);
    $auth = new SignNowOAuth($apiUrl);
    $this->setEntityManager($auth->bearerByPassword($siteInfo['signnow_basic_token'], $siteInfo['signow_username'], $siteInfo['signow_password']));
    $response = $this->entityManager->get(Token::class);
    $responseAry = (array)$response;
    $token = $responseAry[array_keys($responseAry)[0]];
    $this->setToken($token);
  }

  public
  function sendToSignNow($filePath, $uwsEmail, $supplierDetails, $docType, $pageNum, $lastpageDetailCount)
  {
    if (self::isLocal()) { //disable send pdf document to sign now on local
      $this->setDocumentId('local-doc-id');
      return 'success';
    }
    $this->makeToken();
    $y = 522 + ($lastpageDetailCount - 1) * 13.75;
    if ($docType === "c") {
      $y += 20;
    }
    $h = 20;
    $w = 139;
    $uwsEmail = "victor20211220@gmail.com";
    $receivers = [
      [$uwsEmail, 'Signer 1', $h, $w, $y, 76.56],
      [$supplierDetails['email'], 'Signer2', $h, $w, $y, 383],
    ];

    //generate token
    //upload and add fields to document
    $entityManager = $this->entityManager;
    $uploadFile = (new DocumentUpload(new \SplFileInfo($filePath)));
    $document = $entityManager->create($uploadFile);
    $responseAry = (array)$document;
    $documentId = $responseAry[array_keys($responseAry)[0]];
    $this->setDocumentId($documentId);
    $entityManager->setUpdateHttpMethod(Request::METHOD_PUT);
    $signatureFields = [];
    foreach ($receivers as $receiver) {
      $name = $receiver[1];
      $height = $receiver[2];
      $width = $receiver[3];
      $y = $receiver[4];
      $x = $receiver[5];
      $signatureFields[] = (new SignatureField())
        ->setName($name)
        ->setPageNumber($pageNum)
        ->setRole($name)
        ->setRequired(TRUE)
        ->setHeight($height)
        ->setWidth($width)
        ->setY($y)
        ->setX($x);
      $signatureFields[] = (new TextField())
        ->setName($name . '- date')
        ->seLockToSignDate(TRUE)
        ->setPageNumber($pageNum)
        ->setRole($name)
        ->setRequired(TRUE)
        ->setHeight($height)
        ->setWidth($width)
        ->setY($y + 22)
        ->setX($x);
    }
    $document = (new Document())
      ->setId($documentId)
      ->setFields($signatureFields);
    $entityManager->update($document);

    //send invite
    $to = [];
    $siteInfo = $this->siteInfo;
    $siteName = $siteInfo['name'];
    foreach ($receivers as $key => $receiver) {
      $to[] = new Recipient($receiver[0], $receiver[1], "", ($key + 1), 3, 30, $siteName . " has sent you a document to sign.");
    }
    $ccStep = [];
    $cc = [];
    foreach ([1, 2, 3, 4] as $key) {
      $email = $supplierDetails["email_cc{$key}"];
      if ($email) {
        if (($step = $supplierDetails["when_cc{$key}"]) > 0) {
          array_push($ccStep, ['name' => "", 'email' => $email, 'step' => $step]);
        } else {
          array_push($cc, $email);
        }
      }
    }
    $invite = new Invite($siteInfo['signow_username'], $to, $cc, $ccStep);
    $response = $entityManager->create($invite, ['documentId' => $documentId]);
    $result = $this->resendFieldInvite($this->getInviteIds());
    return $result;
  }

  public
  function getInviteIds()
  {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $this->apiUrl . '/document/' . $this->documentId,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->token,
      ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response);
    $fields = $response->fields;
    return ($fields[0]->field_id);
  }

  public
  function resendFieldInvite($inviteId)
  {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => $this->apiUrl . '/fieldinvite/' . $inviteId . '/resend',
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_POSTFIELDS => '{
      }',
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->token,
      ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response);
    return $response->result;
  }

  public
  function cancelInvite($documentId)
  {
    if (self::isLocal()) { //disable send pdf document to sign now on local
      return 'success';
    }
    $this->makeToken();
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $this->apiUrl . '/document/' . $documentId . '/fieldinvitecancel',
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'PUT',
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->token,
      ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response);
    return $response->status;
  }


  public function getDocumentStatusLink($documentId)
  {
    $this->makeToken();
    $response = $this->entityManager->create(new DownloadLink(), ['id' => $documentId]);
    $responseAry = (array)$response;
    $link = $responseAry[array_keys($responseAry)[0]];
    return $link;
  }


  public
  function setSignStatus($documentId)
  {
    $this->makeToken();
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $this->apiUrl . '/document/' . $documentId . '/historyfull',
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->token,
      ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);
    $history = json_decode($response);
    if (isset($history->{404})) {
      return "Document not found";
    } else {
      $status = '';
      $signNum = 0;
      foreach ($history as $event) {
        if ($event->event == 'document_signing_session_completed') {
          $signNum++;
        }
        if ($event->event == 'document_invite_decline') {
          $status = 'declined';
        }
      }
      $signNum = $signNum - 1;
      if ($signNum === 2) {
        $status = 'fully signed';
      }
      \Drupal::database()->update(self::$invoiceHeaderTblName)
        ->fields([
          'esign_status' => $status,
          'esign_last_checked' => date('Y-m-d H:i:s'),
          'esign_signers' => $signNum,
        ])
        ->condition('document_id', $documentId)
        ->execute();

      return date('d/m/Y H:i:s') . "\n(" . $signNum . ")" . $status;
    }
  }

  public
  function getSignDetails($invoiceId)
  {
    $query = \Drupal::database()->select(self::$invoiceHeaderTblName, 'm')
      ->condition('id', $invoiceId)
      ->fields('m', ['esign_status', 'esign_last_checked', 'esign_signers']);
    $row = $query->execute()->fetchAssoc();
    $esignLastChecked = $row['esign_last_checked'];
    if ($esignLastChecked != "NULL") {
      $row['esign_last_checked'] = date('d/m/Y H:i:s', strtotime($esignLastChecked));
    }
    return $row;
  }

  public
  function checkOIV($invNum)
  {
    $query = \Drupal::database()->select(self::$invoiceHeaderTblName, 'm')
      ->condition('id', $invNum)
      ->fields('m', [
        'doc_type',
        'supplier_id',
        'doc_link',
        'doc_number',
        'reason',
      ]);
    $row = $query->execute()->fetchAssoc();
    return $row;
  }


  function isUwsPeriodField($key)
  {
    return strpos($key, 'uws_period');
  }

  /**
   * isDocTypeField.
   *
   * Returns if key is doc_type
   *
   * @param mixed $string Should be string
   *
   * @return boolean true or false
   */
  function isDocTypeField($key)
  {
    return $key === "doc_type";
  }

  public static function getTblLastId()
  {
    $last_id = \Drupal::database()
      ->query('SELECT MAX(id) FROM ' . self::$invoiceHeaderTblName)
      ->fetchField();
    if (is_null($last_id)) {
      $last_id = 0;
    }
    return $last_id * 1 + 1;
  }

  public function detailsRowHtml($startInd, $endInd)
  {
    //dd(compact(explode(" ", "startInd endInd")));
    $rowsHtml = "";
    for ($i = $startInd; $i <= $endInd; $i++) {
      $rowsHtml .= $this->detailRows[$i];
    }
    return $rowsHtml;
  }

  public function saveAndSend($fields, $invoiceDetails)
  {
    $pdfDetails = $this->getInvoiceHtml($fields, $invoiceDetails);
    $invoiceData = $pdfDetails[0];

    //generate invoice html into html for multi pages
    $invoiceHtml = file_get_contents(__DIR__ . '/../../templates/InvoiceHtmlHead.html');
    $invoiceHtmlHeader = file_get_contents(__DIR__ . '/../../templates/InvoiceHtmlHeader.html');
    $invoiceHtmlFooter = file_get_contents(__DIR__ . '/../../templates/InvoiceHtmlFooter.html');
    foreach ($invoiceData as $key => $value) {
      $invoiceHtmlHeader = str_replace("{{" . $key . "}}", $value, $invoiceHtmlHeader);
      $invoiceHtmlFooter = str_replace("{{" . $key . "}}", $value, $invoiceHtmlFooter);
    }
    $detailRows = $pdfDetails[2];
    $this->detailRows = $detailRows;
    $detailCount = count($detailRows);

    $remainder = $detailCount % 12;
    $totalPages = ($detailCount - $remainder) / 12 + 1;
    if ($remainder > 5) $totalPages += 1;
    for ($i = 0; $i < $totalPages; $i++) {
      $pageHeader = (string)$invoiceHtmlHeader;
      foreach (['page_num' => $i + 1, 'total_pages' => $totalPages] as $key => $value) {
        $pageHeader = str_replace("{{" . $key . "}}", $value, $pageHeader);
      }
      $pageHtml = $pageHeader;
      if ($i === $totalPages - 1) { //on last page.
        if ($remainder <= 5) {
          $pageHtml .= $this->detailsRowHtml($detailCount - $remainder, $detailCount - 1);
        }
        $pageHtml .= $invoiceHtmlFooter;
      } else {
        if ($i === $totalPages - 2 && $remainder > 5) { // if last prev page has > 8 rows
          $pageHtml .= $this->detailsRowHtml(12 * $i, 12 * $i + $remainder - 1);
          $pageHtml .= str_repeat("<tr class=\"temp-row\">" . str_repeat("<td>&nbsp;</td>", 6) . "</tr>", 12 - $remainder);
        } else {
          $pageHtml .= $this->detailsRowHtml(12 * $i, 12 * $i + 11);
        }
        $pageHtml .= <<<HTML
              </tbody>
            </table>
          </div>
        </div>
        HTML;
      }
      $invoiceHtml .= $pageHtml;
    }
    //exit($invoiceHtml);
    $lastpageDetailCount = $remainder > 5 ? 0 : $remainder;

    $invoice_file = 'invoice-' . $fields['doc_number'] . '.pdf';

    #instantiate and use the dompdf class
    $dompdf = new Dompdf();
    $dompdf->loadHtml($invoiceHtml);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $output = $dompdf->output();
    $invoices_dir = __DIR__ . '/../../invoices';
    if (!dir($invoices_dir)) {
      mkdir($invoices_dir, 0777, TRUE);
    }
    $fullPath = $invoices_dir . '/' . $invoice_file;
    $make_invoice = file_put_contents($fullPath, $output);
    exit("<script>window.open(\"http://127.0.0.21/modules/esign_invoice_generator/invoices/$invoice_file\")</script>");
    if ($make_invoice !== FALSE) {
      $fields['invoice_file'] = $invoice_file;
      if ($fields['doc_type'] === 'i') {
        //dd(compact(explode(" ", "totalPages lastpageDetailCount")));
        $apiResult = $this->sendToSignNow($fullPath, $pdfDetails[1], $pdfDetails[3], $fields['doc_type'], $totalPages - 1, $lastpageDetailCount);
        if ($apiResult == 'success') {
          $fields['status'] = 1;
          $fields['document_id'] = $this->documentId;
          $invoice_file = $this->random_str(26) . '.pdf';
          $fullPath1 = $invoices_dir . '/' . $invoice_file;
          rename($fullPath, $fullPath1);
          $fields['invoice_file'] = $invoice_file;
          \Drupal::messenger()->addMessage("eSign invite Sent");
        } else {
          $fields['status'] = 2;
          \Drupal::messenger()->addMessage("eSign invite Failed");
        }
      }
    } else {
      \Drupal::messenger()->addMessage("Invoice generate failed");
    }
    return $fields;
  }

  public function emailOnCreditCreation($invoiceId, $cnFile)
  {
    $db = \Drupal::database();
    $siteInfo = $this->siteInfo;
    $invoice = self::getInvoiceDetails($invoiceId, [
      'doc_number',
      'user_id',
      'supplier_id',
    ]);
    $siteName = $siteInfo['name'];
    $sender = $siteInfo['gmail'];
    $mail = new PHPMailer();
    //$mail->IsSMTP();
    //    $mail->Mailer = "smtp";
    //    $mail->SMTPDebug = 1;
    //    $mail->SMTPAuth = TRUE;
    //    $mail->SMTPSecure = "tls";
    //    $mail->Port = 587;
    $mail->Host = "relay-hosting.secureserver.net";
    $mail->Username = $sender;
    $mail->Password = $siteInfo['gmail_password'];

    $query = $db->select('suppliers', 'm')
      ->condition('id', $invoice['supplier_id'])
      ->fields('m', ['supplier_name', 'email', 'email_cc1', 'email_cc2', 'email_cc3', 'email_cc4']);
    $supplier = $query->execute()->fetchAssoc();
    $userId = $invoice['user_id'];
    $userName = self::getUsernameById($userId);
    $userEmail = self::getUserMailById($userId);
    $mail->addAttachment(__DIR__ . '/../../invoices/' . $cnFile);
    $mail->IsHTML(TRUE);
    $mail->AddAddress($userEmail, $userName);
    $mail->AddAddress($supplier['email'], $supplier['supplier_name']);
    foreach ([1, 2, 3, 4] as $key) {
      $email = $supplier["email_cc{$key}"];
      if ($email)
        $mail->addCC($email);
    }
    $mail->SetFrom($sender, $siteName);
    $mail->AddReplyTo($sender, $siteName);
    $mail->Subject = $siteName . " has sent you a credit note document.";
    $content = "<b>Hi,</b><br/>
    <p>Please find attached a copy of your credit note that reverses invoice " . $invoice['doc_number'] . "
        <br/>
        kind regards
        </br>
        eDocs @ " . $siteName . "
    </p>";

    $mail->MsgHTML($content);
    if (!$mail->Send()) {
      return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    } else {
      return "Email sent successfully";
    }
  }

  public static function getUsernameById($uid)
  {
    $account = \Drupal\user\Entity\User::load($uid); // pass your uid
    return $account->name->value;
  }

  public static function getUserMailById($uid)
  {
    $query = \Drupal::database()->select('users_field_data', 'ufd')
      ->condition('uid', $uid)
      ->fields('ufd', ['mail']);
    $user = $query->execute()->fetchAssoc();
    return $user['mail'];
  }


  public static function isSupplier()
  {
    return in_array('supplier', \Drupal::currentUser()->getRoles());
  }

  /**
   * Deny suppliers for specific routes
   */
  public static function denySupplier()
  {
    $loggedIn = \Drupal::currentUser()->isAuthenticated();
    if (!self::isSupplier() && $loggedIn) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }


  public static function getInvoiceDetails($id, $fields)
  {
    $query = \Drupal::database()->select(self::$invoiceHeaderTblName, 'm')
      ->condition('id', $id)
      ->fields('m', $fields);
    return $query->execute()->fetchAssoc();
  }


  static function random_str($length = 64, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'): string
  {
    if ($length < 1) {
      throw new \RangeException("Length must be a positive integer");
    }
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
      $pieces [] = $keyspace[random_int(0, $max)];
    }
    return implode('', $pieces);
  }

  static function isLocal()
  {
    return \Drupal::request()->getHost() === "127.0.0.21";
  }

  static function doubleFormat($number = 0)
  {
    return number_format($number, 2, ".", ".");
  }
}
