$ = jQuery;
$(function () {
  var table = $('table').DataTable({
    "pageLength": 25,
    "lengthMenu": [10, 25, 50],
    "oLanguage": {
      "sLengthMenu": "Show _MENU_ invoices",
      "sZeroRecords": "No matching invoices found",
      "sInfo": "Showing _START_ to _END_ of _TOTAL_ invoices",
      "sInfoEmpty": "Showing 0 to 0 of 0 invoices",
    },
    "order": []
  });
  $(document).on('click', '.dataTable tbody td a[href*="cancel-sign-invite"]', function (e) {
    link = $(this).attr('href');
    e.preventDefault();
    let inputReason = prompt("What's the reason?");
    if(inputReason !== null){
      if (inputReason !== "") {
        $(this).addClass('disabled');
        location.href = link + "&reason=" + inputReason;
      }
    }
  });

  $(document).on('click', '.dataTable tbody td a[href*="sign_document_id"]', function (e) {
    e.preventDefault();
    _this = $(this);
    _this.text('Updating status...');
    var href = _this.attr('href');
    documentId = href.substr(href.indexOf('=') + 1);
    if (documentId) {
      $.ajax(href)
        .done(function (res) {
          _this.text(res);
        })
        .fail(function (res) {
          alert('Error ocured!');
          _this.text('Update');
        })
        .always(function () {
          // alert("complete");\edocinvoicing\vendor\guzzlehttp\guzzle\src\functions.
        });

    }
  });

  $(document).on('click', '.dataTable tbody td a[href*="gisd"]', function (e) {
    e.preventDefault();
    _this = $(this);
    _this.text('Getting ..');
    var href = _this.attr('href');
    invoiceId = href.substr(href.indexOf('=') + 1);
    if (invoiceId) {
      $.getJSON(href)
        .done(function (res) {
          console.log(res);
          let detailsInfo = "Not checked yet";
          let eSignStatus = res['esign_status'];
          if (res['esign_status'] != null) {
            detailsInfo = res['esign_signers'] + " : " + eSignStatus + "\n" + "Last checked: " + res['esign_last_checked'];
          }
          alert(detailsInfo);
          _this.text('Show');
        })
        .fail(function (res) {
          alert('Error ocured!');
          _this.text('Show');
        })
        .always(function () {
          // alert("complete");\edocinvoicing\vendor\guzzlehttp\guzzle\src\functions.
        });

    }
  })


  $(document).on('click', '.dataTable tbody td a[href*="get-cn-reason"]', function (e) {
    e.preventDefault();
    _this = $(this);
    _this.attr('disabled', 'disabled');
    var href = _this.attr('href');
    rowId = href.substr(href.indexOf('=') + 1);
    if (rowId) {
      $.ajax(href)
        .done(function (res) {
          alert(res);
        })
        .fail(function (res) {
          alert('Error ocured!');
          _this.text('Show');
        })
        .always(function () {
          // alert("complete");\edocinvoicing\vendor\guzzlehttp\guzzle\src\functions.
        });

    }
  })
})
$('.tooltip').hover(function () {
  let _class = $(this).attr('class');
  if (_class.indexOf('green') !== -1) {
    $(this).attr('title', 'the credit note was created within 30 days of invoice');
  }
  if (_class.indexOf('amber') !== -1) {
    $(this).attr('title', 'the credit note was created within 30 and 90 days of invoice');
  }
  if (_class.indexOf('red') !== -1) {
    $(this).attr('title', 'the  credit note was created 90 days after the invoice');
  }
})
