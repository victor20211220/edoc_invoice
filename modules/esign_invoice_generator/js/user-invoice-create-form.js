$('#edit-save-send').click(function () {
  $('[name=send_to_signnow]').val(1);
});
$(function(){
  validatePeriod('uws-period-from', 'uws-period-to');
})
