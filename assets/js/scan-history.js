jQuery(document).ready(function($) {
    var table = $('#ush-scan-history-table').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        columnDefs: [
            { orderable: false, targets: -1 }, // Action column not orderable
        ]
    });

    // Set placeholder for global search
    $('#ush-scan-history-table_filter input').attr('placeholder', ush_scan_history.filter_placeholder || 'Search all columns...');

    // Datepicker for date filter
    $('#ush-scan-history-date-filter').datepicker({
        dateFormat: 'M d, yy',
        changeMonth: true,
        changeYear: true,
        showButtonPanel: true,
        onSelect: function(dateText) {
            // Filter DataTable by date column (first column)
            table.column(0).search(dateText).draw();
        }
    });

    // Clear date filter if input is cleared
    $('#ush-scan-history-date-filter').on('change keyup', function() {
        if (!$(this).val()) {
            table.column(0).search('').draw();
        }
    });

    // Delete scan record (Dashicon)
    $(document).on('click', '.ush-delete-scan', function(e) {
        e.preventDefault();
        var $icon = $(this);
        var id = $icon.data('id');
        var datetime = $icon.data('datetime');
        if (!confirm(ush_scan_history.confirm_delete)) return;
        $icon.addClass('deleting');
        $.post(ush_scan_history.ajax_url, {
            action: 'ush_delete_scan_record',
            nonce: ush_scan_history.nonce,
            id: id,
            datetime: datetime
        }, function(res) {
            if (res && res.success) {
                // Remove row from table
                var row = $icon.closest('tr');
                table.row(row).remove().draw();
                alert(ush_scan_history.success_delete);
            } else {
                alert((res && res.data && res.data.message) ? res.data.message : ush_scan_history.error_delete);
                $icon.removeClass('deleting');
            }
        }).fail(function() {
            alert(ush_scan_history.error_delete);
            $icon.removeClass('deleting');
        });
    });
});
