// Initiate clipboard JS instance for redirect uri copy button on admin form
var obj = new ClipboardJS('.inline-copy-btn');

// Hide required elements on load of settings page
if(!jQuery('.options-show').prop('checked')) {
    jQuery('.options-hidden').parents('tr').hide();
}

// Show hidden options when option is checked
jQuery('.options-show').click(function() {
    if(jQuery(this).prop('checked')) {
        jQuery('.options-hidden').parents('tr').show();
    } else {
        jQuery('.options-hidden').parents('tr').hide();
    }
})