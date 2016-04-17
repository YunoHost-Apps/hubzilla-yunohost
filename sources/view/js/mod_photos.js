/**
 * JavaScript used by mod/photos
 */

// is this variable used anywhere?
var ispublic = aStr.everybody;

$(document).ready(function() {

	$("#photo-edit-newtag").contact_autocomplete(baseurl + '/acl', 'p', false, function(data) {
		$("#photo-edit-newtag").val('@' + data.name);
	});

	$('#id_body').bbco_autocomplete('bbcode');

	$('#contact_allow, #contact_deny, #group_allow, #group_deny').change(function() {
		var selstr;
		$('#contact_allow option:selected, #contact_deny option:selected, #group_allow option:selected, #group_deny option:selected').each( function() {
			selstr = $(this).text();
			$('#jot-perms-icon').removeClass('icon-unlock').addClass('icon-lock');
			$('#jot-public').hide();
		});
		if(selstr === null) {
			$('#jot-perms-icon').removeClass('icon-lock').addClass('icon-unlock');
			$('#jot-public').show();
		}
	}).trigger('change');

	showHideBodyTextarea();

});

function showHideBodyTextarea() {
	if( $('#id_visible').is(':checked'))
		$('#body-textarea').slideDown();
	else
		$('#body-textarea').slideUp();
}
