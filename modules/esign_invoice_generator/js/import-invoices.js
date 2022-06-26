var $ = jQuery;
var dt;
$(function () {
  appendUploadCsvForm();
  appendFilterTable();
  doFilter();
  initDatatable();
});

$(document).on('change', '[name=valid], [name=invoiced]', function () {
  dt.draw();
});

$(document).on('click', '[data-btn-flag]', function () {
  var btnFlag = $(this).data('btn-flag');
  let selectedRows = dt
    .rows(function (idx, data, node) {
      // Get all the checkboxes in the row
      if(btnFlag === 2){
        return data[20] === '0'
      }else{
        var cells = $(node).find('input[type="checkbox"]');
        return checkedTargets(cells).length;
      }
    })
    .data()
    .toArray();
  if (!selectedRows.length) {
    alert('No rows are checked!');
  }
  else {
    var selectedIds = [];
    selectedRows.map(function (row) {
      selectedIds.push(row[19]);
    })
    selectedIds = selectedIds.join(',');
    var confirmMsg = btnFlag !== 0 ? "Delete all " + (btnFlag === 1 ? "ticked" : "invalid") +" records?" : "Are you sure you want to generate these invoices ?";
    if (!window.confirm(confirmMsg)) {
      return false;
    }
    $.ajax({
      url: "import",
      method: "POST",
      data: {selected_ids: selectedIds, btn_flag: btnFlag},
      dataType: "json",
      success: function (res) {
        if (res.status) {
          location.reload();
        }
      }
    })
  }
})


$(document).on('click', '#checkAll', function () {
  if($(this)[0].checked){
    $('tbody input[type=checkbox]:not([disabled])').attr("checked", "checked");
  }else{
    $('tbody input[type=checkbox]:not([disabled])').removeAttr("checked");
  }
})

function doFilter() {
  $.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
      var validVal = $('[name=valid]:checked').val();
      var invoicedVal = $('[name=invoiced]:checked').val();
      var invoiced = (data[17] === "") ? "0" : "1";

      return !!((validVal === "all" || validVal === data[20] ) &&
        (invoicedVal === "all" || invoicedVal === invoiced));
    }
  );
}

function appendUploadCsvForm() {
  var uploadCsvFormHtml = '<div>\
  <form class="form-horizontal" id="uploadCsvForm" action="/upload-csv" method="post" name="upload_excel" enctype="multipart/form-data">\
     <fieldset>\
        <!-- Form Name -->\
        <legend>Import CSV</legend>\
        <!-- File Button -->\
        <div class="form-group">\
            <label class="col-md-4 control-label" for="filebutton">Select File</label>\
            <div class="col-md-4">\
                <input type="file" name="file" id="file" class="input-large">\
            </div>\
        </div>\
        <!-- Button -->\
        <div class="form-group">\
            <div class="col-md-4">\
                <button type="submit" id="submit" name="Import" class="btn btn-primary button-loading" data-loading-text="Loading...">Import</button>\
            </div>\
        </div>\
     </fieldset>\
  </form><div>';
  $(uploadCsvFormHtml).insertBefore('#pending-table');
}

function appendFilterTable() {
  filterForm = '\
        <div class="filter">\
            <label>Valid:</label>\
            <div class="values">\
                <input type="radio" name="valid" value="all">All<br/>\
                <input type="radio" name="valid" value="1" checked>Valid<br/>\
                <input type="radio" name="valid" value="0">Not Valid<br/>\
            </div>\
            <label>Invoiced Status:</label>\
            <div class="values">\
                <input type="radio" name="invoiced" value="all">All<br/>\
                <input type="radio" name="invoiced" value="1">Invoiced<br/>\
                <input type="radio" name="invoiced" value="0" checked>Not Yet Invoiced<br/>\
            </div>\
        </div>\
    </div>';
  $(filterForm).insertAfter('#uploadCsvForm');
  let actionButtons = '<div id="action-buttons">' +
    '<button class="import-checked-rows" data-btn-flag="0">Import Checked Rows</button>' +
    '<button class="import-checked-rows" data-btn-flag="1">Delete Checked Rows</button>' +
    '<button class="import-checked-rows" data-btn-flag="2">Delete Invalid Rows</button>' +
    '</div>';
  $(actionButtons).insertAfter('#pending-table');
}

function initDatatable() {
  dt = $('#pending-table').DataTable({
    'columns': [
      {
        "render": function (data, type, row, meta) {
          var checkbox = $("<input/>", {
            "type": "checkbox"
          });
          if (row[0] == "1" && row[20] == "1") {
            checkbox.attr("checked", "checked");
            checkbox.addClass("checkbox_checked");
          }
          else {
            checkbox.removeAttr("checked");
            checkbox.attr("disabled", "disabled");
            checkbox.addClass("checkbox_unchecked");
          }
          return checkbox.prop("outerHTML")
        }
      },
    ],
    "columnDefs": [{
      orderable: false,
      // className: 'select-checkbox',
      targets: 0
    }, {
      "targets": [19, 20],
      "visible": false
    }],
    "select": {
      style: 'multi',
      selector: 'td:first-child'
    },
    "pageLength": 10,
    "lengthMenu": [10, 25, 50],
    "order": []
  });

  $('#pending-table tr th:first-child').html('<input type="checkbox" id="checkAll"/>');
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

function checkedTargets(checkboxes) {
  return checkboxes.filter(function (index) {
    return $(checkboxes[index]).prop('checked');
  });
}
