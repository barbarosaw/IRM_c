<?php

/**
 * AbroadWorks Management System
 * 
 * @author ikinciadam@gmail.com
 */

// Define root directory if not already defined
if (!isset($root_dir)) {
    $root_dir = dirname(__DIR__);
}
?>


</div> <!-- row -->
</div> <!-- container-fluid -->

<footer class="main-footer">
    <div class="float-end d-none d-sm-block">
        <b style="padding-right:60px">Chat Here</b>
    </div>
    <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#">AbroadWorks Management</a>.</strong> All rights reserved.
</footer>
</div> <!-- wrapper -->

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>

<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Sortable.js -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo isset($root_path) ? $root_path : ''; ?>assets/js/main.js"></script>

<!-- Icon Picker JavaScript -->
<script src="<?php echo isset($root_path) ? $root_path : ''; ?>assets/js/icon-picker.js"></script>

<script>
    $(document).ready(function() {
        // Select2 initialization
        $('.select2').select2();

        // DataTables initialization
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/en-GB.json'
            }
        });

        // Initialize Bootstrap 5 tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Initialize Bootstrap 5 popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });

        // Enable dismissal of alerts
        document.querySelectorAll('.alert .btn-close').forEach(function(button) {
            button.addEventListener('click', function() {
                this.closest('.alert').classList.add('d-none');
            });
        });

        // Initialize Bootstrap 5 modals properly with force z-index
        document.querySelectorAll('.modal').forEach(function(modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                // Force modal to be on top
                modalEl.style.zIndex = '9999';
                modalEl.style.position = 'fixed';
                
                // Ensure backdrop is behind modal
                setTimeout(function() {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.style.zIndex = '9998';
                    }
                }, 10);
            });
            
            modalEl.addEventListener('shown.bs.modal', function() {
                // Double check z-index after modal is shown
                modalEl.style.zIndex = '9999';
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '9998';
                }
            });
            
            modalEl.addEventListener('hidden.bs.modal', function() {
                // Clean up all backdrop elements
                document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                    backdrop.remove();
                });
                // Remove modal classes from body
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
                document.body.style.overflow = '';
            });
        });

        // Enhanced cancel button handling
        document.addEventListener('click', function(e) {
            if (e.target.matches('.modal [data-bs-dismiss="modal"]') || 
                e.target.closest('.modal [data-bs-dismiss="modal"]')) {
                e.preventDefault();
                e.stopPropagation();
                const modal = e.target.closest('.modal');
                if (modal) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    } else {
                        $(modal).modal('hide');
                    }
                }
            }
        });
    });
</script>

<script>
    // Fix for DataTables reinitialization error
    (function($) {
        $.fn.dataTable.ext.errMode = 'throw';

        // Wrap the dataTable initialization in a function that checks if the table is already a DataTable
        $.fn.safeDataTable = function(options) {
            var table = this;

            if ($.fn.dataTable.isDataTable(table)) {
                // Destroy existing DataTable instance before reinitializing
                table.DataTable().destroy();
            }

            // Initialize DataTable with provided options
            return table.DataTable(options);
        };

        // Initialize all tables with class 'datatable' safely
        $(document).ready(function() {
            $('.datatable').safeDataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25
            });
        });
    })(jQuery);
</script>

<link href="https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css" rel="stylesheet" />
<!--script type="module">
    import {
        createChat
    } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/chat.bundle.es.js';

    createChat({
        webhookUrl: 'https://n.20free.net/webhook/484c2a22-6dfe-4c8f-9483-bdfc13c3e896/chat',
        showWelcomeScreen: false,
        defaultLanguage: 'en',
        initialMessages: [
            'Hi there! 👋 How can I assist you today?'
        ],
        i18n: {
            en: {
                title: 'Welcome SageCommerce AI Chat! 👋',
                subtitle: "Ask or search anything you want. (memory limited to 10 messages)",
                footer: '',
                getStarted: 'New Conversation',
                inputPlaceholder: 'Type your question..',
            },
        },
    });
</script -->

</body>

</html>