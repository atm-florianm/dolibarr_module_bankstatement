/**
 * Show a bank account selector instead of the ModuleBuilder default form input
 */
function showBankAccountSelect() {
	$('#fk_account').replaceWith($(jsonDataArray.accountForm));
}

$(function() {
	showBankAccountSelect();
});
