var $ = jQuery;
var dt;
$(function () {
  appendFilterTable();
  doFilter();
  initDatatable();
});

$(document).on('change', '#min, #max, #minD, #maxD, #status, [name=is_exported], [name=document_type], [name=invoice_sent]',  function () {
  dt.draw();
});

$(document).on('click', '[data-btn-flag]', function () {
  let searchedRows = dt.rows({search: 'applied'}).data().toArray();
  if (!searchedRows.length) {
    alert('No invoices to export');
  }
  else {
    var markedIds = [];
    searchedRows.map(function (row) {
      markedIds.push(row[0]);
    })
    markedIds = markedIds.join(',');
    var btnFlag = $(this).data('btn-flag');
    if(btnFlag <= 1){
      if (!window.confirm("Mark these invoices/data as " + (btnFlag ? 'A1 ' : '') + "exported ?")) {
        return false;
      }
    }
    $.ajax({
      url: "export",
      method: "POST",
      data: {marked_ids: markedIds, btn_flag: btnFlag},
      dataType: "json",
      success: function(res){
        if(res.status){
          if(btnFlag == 2){
            $('.buttons-csv').trigger('click');
          }
          if(btnFlag == 3){
            window.open('/' + res.a1_exported_file, '_blank');
          }
          location.reload();
        }
      }
    })
  }
})

function doFilter() {
  $.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
      var min = parseInt($('#min').val(), 10);
      var max = parseInt($('#max').val(), 10);
      var invoiceNum = parseFloat(data[0]) || 0;

      var minD = $('#minD').val();
      var maxD = $('#maxD').val();
      var date = swapDayMonth(data[19]).getTime();
      if (!noDateVal(minD)) {
        minD = new Date(minD);
        minD.setHours(0, 0, 0, 0);
        minD = minD.getTime();
      }
      if (!noDateVal(maxD)) {
        maxD = new Date(maxD);
        maxD.setHours(23, 59, 59, 59);
        maxD = maxD.getTime();
      }
      var statusVal = $('#status').val();
      var status = data[18];

      var invoiceSentVal = $('[name=invoice_sent]:checked').val();
      var invoiceSent = (status === "Created") ? "0" : "1";


      var documentTypeVal = $('[name=document_type]:checked').val();
      var documentType = (data[2] === "Credit Note") ? "c" : "i";

      var isExportedVal = $('[name=is_exported]:checked').val();
      var isExported = data[23];
      var isExported1 = data[24];
      var isA1Filter = isExportedVal.indexOf('1') !== -1;
      return !!(((isNaN(min) && isNaN(max)) ||
        (isNaN(min) && invoiceNum <= max) ||
        (min <= invoiceNum && isNaN(max)) ||
        (min <= invoiceNum && invoiceNum <= max)) &&
        (
          (noDateVal(minD) && noDateVal(maxD)) ||
          (noDateVal(minD) && date <= maxD) ||
          (minD <= date && noDateVal(maxD)) ||
          (minD <= date && date <= maxD)
        ) &&
        (statusVal == '' || statusVal == status) &&
        (invoiceSentVal == "all" || invoiceSentVal == invoiceSent) &&
        (documentTypeVal == 'all' || documentTypeVal == documentType) &&
        (isExportedVal == 'all' || (isA1Filter ? isExportedVal.slice(0, -1) === isExported1 : isExportedVal === isExported)));
    }
  );
}

function appendFilterTable() {
  filterForm = '<div class="search-filters">\
        <div class="filter">\
            <label>Invoice number from:</label>\
            <div class="values">\
            <input type="text" id="min" name="min"></div>\
            <label>to:</label>\
            <div class="values">\
            <input type="text" id="max" name="max"></div>\
        </div>\
        <div class="filter">\
            <label>Date from:</label>\
            <div class="values">\
            <input type="date" id="minD" name="minD"></div>\
            <label>to:</label>\
            <div class="values">\
            <input type="date" id="maxD" name="maxD"></div>\
        </div>\
        <div class="filter">\
            <label>Show Only:</label>\
            <div class="values">\
                <input type="radio" name="is_exported" value="all">All<br/>\
                <input type="radio" name="is_exported" value="Yes">Exported<br/>\
                <input type="radio" name="is_exported" value="No">Not Exported<br/>\
                <input type="radio" name="is_exported" value="Yes1">A1 Exported<br/>\
                <input type="radio" name="is_exported" value="No1" checked>A1 Not Exported\
            </div>\
            <label>Status:</label>\
            <div class="values">\
            <select id="status">\
                <option value=""></option>\
                <option value="Created">Created</option>\
                <option value="Sent">Sent</option>\
                <option value="Send Failed">Send Failed</option>\
                <option value="Signed 1">Signed 1</option>\
                <option value="Signed 2">Signed 2</option>\
                <option value="Cancelled">Cancelled</option>\
            </select></div>\
        </div>\
        <div class="filter">\
            <label>Document Type:</label>\
            <div class="values">\
                <input type="radio" name="document_type" value="all" checked>All<br/>\
                <input type="radio" name="document_type" value="i">Invoices<br/>\
                <input type="radio" name="document_type" value="c">Credit Notes<br/>\
            </div>\
            <label>Invoice Issued Status:</label>\
            <div class="values">\
                <input type="radio" name="invoice_sent" value="all" checked>All<br/>\
                <input type="radio" name="invoice_sent" value="1">Sent<br/>\
                <input type="radio" name="invoice_sent" value="0">Not Sent<br/>\
            </div>\
        </div>\
    </div>';
  $(filterForm).insertBefore('#invoices-table');
  let actionButtons = '<div id="action-buttons">' +
    // '<button class="mark-exported-true" data-btn-flag="0">Mark These as Exported</button>' +
    // '<button class="mark-a1-exported-true" data-btn-flag="1">Mark These as A1 Exported</button>' +
    '<button class="export-to-csv" data-btn-flag="2">Export to CSV</button>' +
    '<button class="export-to-excel" data-btn-flag="3">Export to Excel</button>' +
    '</div>' +
    '<link rel="stylesheet" media="all" href="/modules/esign_invoice_generator/css/datatable/dataTables.dateTime.min.css?r41slx" />';
  $(actionButtons).insertAfter('#invoices-table');
}

function initDatatable() {
  dt = $('#invoices-table').DataTable({
    "columnDefs": [
      {
        "targets": [0,7,8,11,13,14,15,16,19,20,21,25],
        "visible": false
      }
    ],
    "dom": 'Blfrtip',
    "buttons": [
      'csv'
    ],
    "pageLength": 10,
    "lengthMenu": [10, 25, 50],
    "oLanguage": {
      "sLengthMenu": "Show _MENU_ invoices",
      "sZeroRecords": "No matching invoices found",
      "sInfo": "Showing _START_ to _END_ of _TOTAL_ invoices",
      "sInfoEmpty": "Showing 0 to 0 of 0 invoices",
    },
    "order": []
  });
}

function swapDayMonth(str) {
  var parts = str.split("/");
  return new Date(parseInt(parts[2], 10),
    parseInt(parts[1], 10) - 1,
    parseInt(parts[0], 10));
}

function noDateVal(val) {
  return val === "" || typeof val === "undefined"
}
