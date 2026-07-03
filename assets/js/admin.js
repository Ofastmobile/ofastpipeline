/* OFast Pipeline — Admin JS */
(function($) {
    'use strict';

    // Toggle CRM plan selector visibility based on CRM checkbox
    function togglePlanField() {
        var $crmCheckbox  = $('input[name="want_crm"]');
        var $planField    = $('#ofp-plan-field');

        if ( $crmCheckbox.length && $planField.length ) {
            $planField.toggle( $crmCheckbox.is(':checked') );

            $crmCheckbox.on('change', function() {
                $planField.toggle( $(this).is(':checked') );
            });
        }
    }

    // Auto-dismiss notices after 5 seconds
    function autoDismissNotices() {
        setTimeout(function() {
            $('.ofp-notice.notice-success').fadeOut(400);
        }, 5000);
    }

    // Confirm before dangerous actions (belt and suspenders on top of onclick)
    function confirmDangerousActions() {
        $(document).on('submit', 'form:has([name="action"][value="ofp_delete_client"])', function(e) {
            if ( ! window.confirm('Are you sure you want to cancel this client? This action cannot be easily undone.') ) {
                e.preventDefault();
            }
        });
    }

    $(document).ready(function() {
        togglePlanField();
        autoDismissNotices();
        confirmDangerousActions();
    });

})(jQuery);
