/**
 * Notifications Module JavaScript
 * 
 * Handles AJAX form submissions for notifications module
 */

// Helper functions for alerts if not defined in the page
if (typeof showSuccessAlert === 'undefined') {
    window.showSuccessAlert = function(title, message, redirectUrl) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../modules/notifications/';
                }
            });
        } else {
            alert(title + ': ' + message);
            window.location.href = '../../modules/notifications/';
        }
    };
}

if (typeof showErrorAlert === 'undefined') {
    window.showErrorAlert = function(title, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        } else {
            alert(title + ': ' + message);
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle AJAX form submissions
    const ajaxForms = document.querySelectorAll('.form-ajax');
    
    ajaxForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading indicator
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonContent = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            submitButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Get the form action URL
            const actionUrl = form.getAttribute('action') || 'index.php';
            
            // Send AJAX request
            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                // Reset button
                submitButton.innerHTML = originalButtonContent;
                submitButton.disabled = false;
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Log raw response for debugging
                const responseClone = response.clone();
                responseClone.text().then(text => {
                    console.log('Raw response:', text);
                });
                
                // Try to parse as JSON
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse as JSON:', e);
                        showErrorAlert('Error', 'Server response is not in the expected format.');
                        throw new Error('Response is not JSON');
                    }
                });
            })
            .then(data => {
                console.log('Server response:', data);
                
                if (data && data.success) {
                    // Direct redirect without showing alert
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.href = '../../modules/notifications/';
                    }
                } else {
                    showErrorAlert('Error', data && data.message ? data.message : 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorAlert('Error', 'An error occurred. Please try again.');
            });
        });
    });
    
    // Make compose form use AJAX
    const composeForm = document.getElementById('composeForm');
    if (composeForm) {
        composeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading indicator
            const submitButton = composeForm.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
            submitButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData(composeForm);
            
            // Get the form action URL
            const actionUrl = composeForm.getAttribute('action') || 'index.php';
            
            // Send AJAX request
            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                // Reset button
                submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Message';
                submitButton.disabled = false;
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Log raw response for debugging
                const responseClone = response.clone();
                responseClone.text().then(text => {
                    console.log('Raw response:', text);
                });
                
                // Try to parse as JSON
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse as JSON:', e);
                        showErrorAlert('Error', 'Server response is not in the expected format.');
                        throw new Error('Response is not JSON');
                    }
                });
            })
            .then(data => {
                console.log('Server response:', data);
                
                if (data && data.success) {
                    // Direct redirect without showing alert
                    window.location.href = '../../modules/notifications/index.php?tab=inbox&status=success&message=Message+sent+successfully';
                } else {
                    showErrorAlert('Error', data && data.message ? data.message : 'Failed to send message. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorAlert('Error', 'An error occurred. Please try again.');
            });
        });
    }
    
    // Make notification form use AJAX
    const notificationForm = document.getElementById('notificationForm');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            // Validate specific users selection
            if (document.getElementById('specific_users') && document.getElementById('specific_users').checked) {
                const selectedUsers = document.getElementById('user_ids').selectedOptions;
                if (selectedUsers.length === 0) {
                    e.preventDefault();
                    showErrorAlert('Error', 'Please select at least one user.');
                    return;
                }
            }
            
            e.preventDefault();
            
            // Show loading indicator
            const submitButton = notificationForm.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
            submitButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData(notificationForm);
            
            // Get the form action URL
            const actionUrl = notificationForm.getAttribute('action') || 'index.php';
            
            // Send AJAX request
            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                // Reset button
                submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Notification';
                submitButton.disabled = false;
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Log raw response for debugging
                const responseClone = response.clone();
                responseClone.text().then(text => {
                    console.log('Raw response:', text);
                });
                
                // Try to parse as JSON
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse as JSON:', e);
                        showErrorAlert('Error', 'Server response is not in the expected format.');
                        throw new Error('Response is not JSON');
                    }
                });
            })
            .then(data => {
                console.log('Server response:', data);
                
                if (data && data.success) {
                    // Direct redirect without showing alert
                    window.location.href = '../../modules/notifications/index.php?tab=notifications&status=success&message=Notification+sent+successfully';
                } else {
                    showErrorAlert('Error', data && data.message ? data.message : 'Failed to send notification. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorAlert('Error', 'An error occurred. Please try again.');
            });
        });
    }
});
