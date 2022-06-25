(function ($) {
  var table = $('table').DataTable({
    "pageLength": 50,
    "lengthMenu": [10, 25, 50],
    "oLanguage": {
      "sLengthMenu": "Show _MENU_ suppliers",
      "sZeroRecords": "No matching suppliers found",
      "sInfo": "Showing _START_ to _END_ of _TOTAL_ suppliers",
      "sInfoEmpty": "Showing 0 to 0 of 0 suppliers",
    }
  });
})(jQuery);
