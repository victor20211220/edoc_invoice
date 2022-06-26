$ = jQuery;
var clonedHtml, firstVatVal, firstDeptVal, firstTypeVal, rowHtml, newRowHtml;


var qs = (function(a) {
    if (a == "") return {};
    var b = {};
    for (var i = 0; i < a.length; ++i)
    {
        var p=a[i].split('=', 2);
        if (p.length === 1)
            b[p[0]] = "";
        else
            b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
    }
    return b;
})(window.location.search.substr(1).split('&'));

$(function () {
  ["dept","type", "qty", "description", "price_per", "vat", "amount", ].forEach( (name) => {
    $('[name^=' + name + ']').attr('name', name + '[]');
  })
  clonedHtml = $('.one-block:nth-child(2)').clone(true);
  firstVatVal = $('[name="vat[]"] option:first-child').attr('value');
  firstDeptVal = $('[name="dept[]"] option:first-child').attr('value');
  firstTypeVal = $('[name="type[]"] option:first-child').attr('value');
  rowHtml = clonedHtml.prop('outerHTML');
  clonedHtml.find('label').remove();
  newRowHtml = clonedHtml.prop('outerHTML');
});
$('#edit-add').click(function (e) { //when add new detail row
  e.preventDefault();
  var rowLen = $("#invoice-form").find(".one-block").length;
  if (rowLen === 0) {
    $(rowHtml).insertAfter('#mainFields');
    $('.one-block [name="dept[]"]').val(firstDeptVal);
    $('.one-block [name="vat[]"]').val(firstVatVal);
    $('.one-block [name="type[]"]').val(firstTypeVal);
    $('.one-block input[type!=submit]').val('');
    $('.one-block input[type=number]').val(0);
    $(`input[name="qty[]"]`).val(1);
  }
  else {
    $(newRowHtml).insertAfter('.one-block:nth-child(' + (rowLen + 1) + ')');
    var newOneBlockSelector = '.one-block:nth-child(' + (rowLen + 2) + ')';
    $(newOneBlockSelector + ' [name="dept[]"]').val(firstDeptVal);
    $(newOneBlockSelector + ' [name="vat[]"]').val(firstVatVal);
    $(newOneBlockSelector + ' [name="type[]"]').val(firstTypeVal);
    $(newOneBlockSelector + ' input[type!=submit]').val('');
    $(newOneBlockSelector + ' input[type=number]').val(0);
    $(`${newOneBlockSelector} input[name="qty[]"]`).val(1);
  }
  $('#edit-save, #edit-save-send, #edit-save-clone').removeAttr('disabled');
});


$("body").on('click', '.delete-row', function (e) {
  e.preventDefault();
  $(this).parent('.one-block').remove();
  if ($('.one-block').length === 0) {
    $('#edit-save, #edit-save-send, #edit-save-clone').attr('disabled', 'disabled');
  }
})


$('#edit-doc-type').change(function () {
  if ($(this).val() === 'c') {
    reasonNumberHtml = '<div id="reasonNumber">\
    <div class="js-form-item form-item js-form-type-textfield form-type-textfield">\
        <label class="js-form-required form-required">Reason:</label>\
        <input type="text" id="reason" name="reason" value="" size="60" maxlength="100" class="form-text required" required="required" aria-required="true">\
      </div>\
      <div class="js-form-item form-item js-form-type-textfield form-type-textfield">\
        <label class="js-form-required form-required">Original Invoice Number:</label>\
        <input type="number" oninput="doSearch(this)" id="oivNum" name="oiv-num" value="" size="15" maxlength="15" class="form-text required" required="required" aria-required="true">\
        <span id="oivInfo" class="color-red"></span>\
      </div>\
      </div>';
    $(reasonNumberHtml).insertAfter('.form-item-doc-type');
  }
  else {
    $('#reasonNumber').remove();
  }
})
var delayTimer;

function doSearch(oivInput) {
  clearTimeout(delayTimer);
  delayTimer = setTimeout(function () {
    let oiv = $(oivInput).val();
    let oivInfo = "";
    $.getJSON('/check-oiv?oiv=' + oiv)
      .done(function (res) {
        console.log(res);
        if (res) {
          if (res['doc_type'] !== "i") {
            oivInfo = "That invoice number is an existing credit note.";
          }
          else if (res['supplier_id'] !== qs['num']) {
            oivInfo = "That invoice is not matching the currently selected supplier.";
          }
          else {
            let docLink = res['doc_link'];
            if (docLink == null ||docLink === "") {

            }
            else{
              oivInfo = "That invoice already has a credit note no:" + docLink;
            }
          }
        }
        else {
          oivInfo = "The invoice number is not existing.";
        }
        $('#oivInfo').html(oivInfo);
        if (oivInfo !== "") {
          setTimeout(function () {
            $(oivInput).val("");
          }, 1000);
        }
      })
      .fail(function (res) {
        alert('Error ocured!');
        $(oivInput).val("");
      })
      .always(function () {
        // alert("complete");\edocinvoicing\vendor\guzzlehttp\guzzle\src\functions.
      });
  }, 1000); // Will do the ajax stuff after 1000 ms, or 1 s
}

function validatePeriod(from, to) {
  var fromSelector = '#edit-' + from;
  var toSelector = '#edit-' + to;
  var periodSelectors = fromSelector + ', ' + toSelector;
  $fromDate = $(fromSelector);
  $toDate = $(toSelector);
  $(document).on('focusin', periodSelectors, function () {
    console.log("Saving value " + $(this).val());
    $(this).data('val', $(this).val());
  }).on('change', periodSelectors, function () {
    var prev = $(this).data('val');
    var current = $(this).val();
    if ($(this).attr('id') === "edit-" + from) {
      if ($toDate.val() === '' || $toDate.val() < current) {
        $toDate.val(current);
      }
    }
    else {
      if (current < $fromDate.val()) {
        $(this).val(prev);
      }
    }
  });
}
$(document).on('change', `input[name="qty[]"], input[name="price_per[]"]`, function(){
  $parentBlock = $(this).closest('.one-block');
  const qty = $parentBlock.find(`input[name="qty[]"]`).val();
  const pricePer = $parentBlock.find(`input[name="price_per[]"]`).val();
  $parentBlock.find(`input[name="amount[]"]`).val(Math.round(qty * pricePer * 100) / 100);
})
