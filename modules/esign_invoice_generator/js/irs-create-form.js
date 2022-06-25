$(function () {
  validatePeriod('start-date', 'end-date');
  if(typeof drupalSettings.manage !== "undefined"){
    $('#edit-start-date, #edit-end-date').attr('disabled', 'disabled');
  }
});
$('#edit-save-clone').click(function () {
  $('[name=save_and_clone]').val(1);
});
$('[data-manage-btn]').click(function () {
  $('[name=manage]').val($(this).data('manage-btn'));
});
