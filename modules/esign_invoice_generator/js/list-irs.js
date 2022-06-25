$ = jQuery;
var dt;
$(function () {
  appendFilterTable();
  initDatatable();
  doFilter();
});
$(document).on('change', '[name=suppliers_select], [name=tradings_select]', function () {
  dt.draw();
});

function doFilter() {
  $.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
      var selectedTradingUser = $('[name=tradings_select]').val();
      var username = data[1];
      var selectedSupplier = $('[name=suppliers_select]').val();
      var supplier = data[2];
      return !!(
        (selectedTradingUser === '' || username === selectedTradingUser) &&
        (selectedSupplier === '' || supplier === selectedSupplier));
    }
  );
}

function appendFilterTable() {
  filterForm = '<div class="search-filters">\
        <div class="filter">\
            <label>Trading Users:</label>\
            <div class="values">\
            <select name="tradings_select">\
              <option value=""></ooption>\
            </select></div>\
            <label>Status:</label>\
            <div class="values">\
            <select name="suppliers_select">\
              <option value=""></ooption>\
            </select></div>\
        </div>\
    </div>';
  var suppliers = JSON.parse(drupalSettings.suppliers);
  var suppliersHtml = tradingsHtml = "";
  for (const key in suppliers) {
    value = suppliers[key];
    suppliersHtml += '<option value="' + value + '">' + value + '</ooption>';
  }
  drupalSettings.trading_usernames.map(function(value){
    tradingsHtml += '<option value="' + value + '">' + value + '</ooption>';
  })
  $(filterForm).insertBefore('#invoices-table');
  $('[name=suppliers_select]').append(suppliersHtml);
  $('[name=tradings_select]').append(tradingsHtml);
}

function initDatatable() {
  dt = $('#invoices-table').DataTable({
    "pageLength": 10,
    "lengthMenu": [10, 25, 50],
    "order": []
  });
}
