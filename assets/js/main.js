/**
 * AbroadWorks Management System - Main JavaScript
 * 
 * @author ikinciadam@gmail.com
 */

/**
 * Form validation with Bootstrap
 */
function validateForm(formId) {
    // Fetch the form to validate
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.classList.add('was-validated');
    
    // Stop form submission if validation fails
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, false);
}

/**
 * Toggle sidebar visibility for mobile view
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (window.bootstrap && window.bootstrap.Tooltip) {
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Mobile sidebar toggle
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
    const sidebar = document.querySelector('.main-sidebar');
    const body = document.body;
    
    if (toggleSidebarBtn && sidebar) {
        toggleSidebarBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            body.classList.toggle('sidebar-open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (
                window.innerWidth < 992 && 
                sidebar.classList.contains('show') && 
                !sidebar.contains(e.target) && 
                !toggleSidebarBtn.contains(e.target)
            ) {
                sidebar.classList.remove('show');
                body.classList.remove('sidebar-open');
            }
        });
        
        // Expand/collapse sidebar menu items
        const navItems = document.querySelectorAll('.nav-sidebar .nav-item');
        navItems.forEach(function(item) {
            const link = item.querySelector('.nav-link');
            const treeview = item.querySelector('.nav-treeview');
            
            if (link && treeview) {
                link.addEventListener('click', function(e) {
                    if (e.target === link || link.contains(e.target)) {
                        e.preventDefault();
                        item.classList.toggle('menu-open');
                    }
                });
                
                // Auto-expand menu for active pages
                const activeLink = treeview.querySelector('.nav-link.active');
                if (activeLink) {
                    item.classList.add('menu-open');
                }
            }
        });
    }
    
    // Add an event listener for window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992 && sidebar) {
            sidebar.classList.remove('show');
            body.classList.remove('sidebar-open');
        }
    });
    
    // Initialize Select2
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.select2').select2({
            width: '100%',
            dropdownAutoWidth: true
        });
    }
    
    // Initialize DataTables with proper check
    if (window.jQuery && typeof jQuery.fn.DataTable !== 'undefined') {
        initializeDataTables();
    } else {
        // Poll for jQuery and DataTable to be ready
        const dataTablesCheckInterval = setInterval(function() {
            if (window.jQuery && typeof jQuery.fn.DataTable !== 'undefined') {
                clearInterval(dataTablesCheckInterval);
                initializeDataTables();
            }
        }, 100);
        
        // Clear the interval after 10 seconds to prevent memory leaks
        setTimeout(function() {
            clearInterval(dataTablesCheckInterval);
            console.warn('DataTables initialization timed out - jQuery or DataTables not loaded');
        }, 10000);
    }
});

/**
 * Initialize DataTables separately to handle potential timing issues
 */
function initializeDataTables() {
    try {
        // Check if any datatables need initialization
        const tables = jQuery('.datatable:not(.dataTable)');
        if (tables.length === 0) {
            return; // No tables to initialize
        }
        
        // Regular datatables (excluding specifically handled tables)
        jQuery('.datatable:not(#activity-logs-table)').each(function() {
            // Skip if already initialized as a DataTable
            if (jQuery.fn.DataTable.isDataTable(this)) {
                return;
            }
            
            jQuery(this).DataTable({
                "responsive": true,
                "language": {
                    "lengthMenu": "_MENU_ kayıt/sayfa",
                    "zeroRecords": "Eşleşen kayıt bulunamadı",
                    "info": "_TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor",
                    "infoEmpty": "Kayıt yok",
                    "search": "Ara:",
                    "paginate": {
                        "first": "İlk",
                        "last": "Son",
                        "next": "Sonraki",
                        "previous": "Önceki"
                    }
                }
            });
        });
        
        // Activity logs table gets special treatment if it exists
        if (jQuery('#activity-logs-table').length && !jQuery.fn.DataTable.isDataTable('#activity-logs-table')) {
            jQuery('#activity-logs-table').DataTable({
                "responsive": true,
                "ordering": true,
                "order": [[ 1, "desc" ]],
                "paging": false, // Pagination is handled by the backend
                "searching": true,
                "language": {
                    "lengthMenu": "_MENU_ kayıt/sayfa",
                    "zeroRecords": "Eşleşen kayıt bulunamadı",
                    "info": "_TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor",
                    "infoEmpty": "Kayıt yok",
                    "search": "Ara:",
                    "paginate": {
                        "first": "İlk",
                        "last": "Son", 
                        "next": "Sonraki",
                        "previous": "Önceki"
                    }
                }
            });
        }
    } catch (e) {
        console.error("DataTables initialization error:", e);
        
        // Additional error details for debugging
        if (e.stack) {
            console.error("Stack trace:", e.stack);
        }
    }
}

/**
 * Handle delete confirmations with SweetAlert2
 */
function confirmDelete(message) {
    return new Promise((resolve) => {
        Swal.fire({
            title: 'Are you sure?',
            text: message || 'Are you sure you want to delete this? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            resolve(result.isConfirmed);
        });
    });
}

/**
 * SweetAlert2 Helper Functions
 */

/**
 * Show a success alert
 * 
 * @param {string} title Alert title
 * @param {string} message Alert message
 * @param {function} callback Optional callback function
 */
function showSuccessAlert(title, message, callback) {
    Swal.fire({
        title: title || 'Success!',
        text: message || 'Operation completed successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    }).then(callback || function(){});
}

/**
 * Show an error alert
 * 
 * @param {string} title Alert title
 * @param {string} message Alert message
 * @param {function} callback Optional callback function
 */
function showErrorAlert(title, message, callback) {
    Swal.fire({
        title: title || 'Error!',
        text: message || 'An error occurred during the operation.',
        icon: 'error',
        confirmButtonText: 'OK'
    }).then(callback || function(){});
}

/**
 * Show a warning alert
 * 
 * @param {string} title Alert title
 * @param {string} message Alert message
 * @param {function} callback Optional callback function
 */
function showWarningAlert(title, message, callback) {
    Swal.fire({
        title: title || 'Warning!',
        text: message || 'Are you sure you want to perform this action?',
        icon: 'warning',
        confirmButtonText: 'OK'
    }).then(callback || function(){});
}

/**
 * Show an info alert
 * 
 * @param {string} title Alert title
 * @param {string} message Alert message
 * @param {function} callback Optional callback function
 */
function showInfoAlert(title, message, callback) {
    Swal.fire({
        title: title || 'Information',
        text: message || '',
        icon: 'info',
        confirmButtonText: 'OK'
    }).then(callback || function(){});
}

/**
 * Show a confirmation alert
 * 
 * @param {string} title Alert title
 * @param {string} message Alert message
 * @param {function} callback Callback function with result
 * @param {string} confirmText Text for confirm button
 * @param {string} cancelText Text for cancel button
 */
function showConfirmAlert(title, message, callback, confirmText, cancelText) {
    Swal.fire({
        title: title || 'Are you sure?',
        text: message || 'Are you sure you want to perform this action?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: confirmText || 'Yes',
        cancelButtonText: cancelText || 'Cancel'
    }).then(callback || function(){});
}

/**
 * Show a toast notification
 * 
 * @param {string} message Toast message
 * @param {string} icon Toast icon (success, error, warning, info, question)
 * @param {string} position Toast position
 */
function showToast(message, icon, position) {
    const Toast = Swal.mixin({
        toast: true,
        position: position || 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: icon || 'success',
        title: message
    });
}

/**
 * Format date based on system settings
 */
function formatDate(dateString, format = null) {
    if (!dateString) return '';
    const date = new Date(dateString);
    
    if (isNaN(date.getTime())) {
        return dateString; // Return original if invalid date
    }
    
    // Default format if none provided
    if (!format) {
        return date.toLocaleDateString();
    }
    
    return format
        .replace('YYYY', date.getFullYear())
        .replace('MM', String(date.getMonth() + 1).padStart(2, '0'))
        .replace('DD', String(date.getDate()).padStart(2, '0'))
        .replace('HH', String(date.getHours()).padStart(2, '0'))
        .replace('mm', String(date.getMinutes()).padStart(2, '0'))
        .replace('ss', String(date.getSeconds()).padStart(2, '0'));
}

/**
 * Show spinner during AJAX requests
 */
function showSpinner() {
    var spinner = document.getElementById('ajax-spinner');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'ajax-spinner';
        spinner.className = 'ajax-spinner';
        spinner.innerHTML = '<div class="spinner"><i class="fas fa-circle-notch fa-spin"></i></div>';
        document.body.appendChild(spinner);
    }
    spinner.style.display = 'flex';
}

function hideSpinner() {
    var spinner = document.getElementById('ajax-spinner');
    if (spinner) {
        spinner.style.display = 'none';
    }
}
