jQuery(document).ready(function($) {
    
    // Note: Bonus settlement is now automatic when commission is recorded
    
    // Handle commission coupon application
    $(document).on('click', '#apply-commission-coupon', function() {
        var couponCode = $('#commission-coupon-code').val().trim();
        var button = $(this);
        
        if (!couponCode) {
            showCouponMessage('Please enter a coupon code', 'error');
            return;
        }
        
        button.prop('disabled', true).text('Applying...');
        
        $.ajax({
            url: commission_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'apply_commission_coupon',
                coupon_code: couponCode,
                nonce: commission_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAppliedCoupon(response.data.coupon_code, response.data.discount_text);
                    showCouponMessage(response.data.message, 'success');
                    $('#commission-coupon-code').val('');
                    
                    // Force refresh the checkout page to show discount
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showCouponMessage(response.data, 'error');
                }
                button.prop('disabled', false).text('Apply');
            },
            error: function() {
                showCouponMessage('An error occurred. Please try again.', 'error');
                button.prop('disabled', false).text('Apply');
            }
        });
    });
    
    // Handle commission coupon removal
    $(document).on('click', '#remove-commission-coupon', function() {
        var button = $(this);
        
        $.ajax({
            url: commission_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'remove_commission_coupon',
                nonce: commission_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    hideAppliedCoupon();
                    showCouponMessage('Coupon removed successfully', 'success');
                    
                    // Force refresh the checkout page to remove discount
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    showCouponMessage('Error removing coupon', 'error');
                }
            },
            error: function() {
                showCouponMessage('An error occurred. Please try again.', 'error');
            }
        });
    });
    
    // Handle Enter key in coupon input
    $(document).on('keypress', '#commission-coupon-code', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#apply-commission-coupon').click();
        }
    });
    
    // Show coupon message
    function showCouponMessage(message, type) {
        var messageDiv = $('#commission-coupon-message');
        var className = type === 'error' ? 'coupon-error' : 'coupon-success';
        
        messageDiv.html('<div class="' + className + '">' + message + '</div>');
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                messageDiv.html('');
            }, 3000);
        }
    }
    
    // Show applied coupon
    function showAppliedCoupon(couponCode, discountText) {
        $('#applied-coupon-text').text('Coupon "' + couponCode + '" applied: ' + discountText);
        $('#applied-commission-coupon').show();
        $('.commission-coupon-form').hide();
    }
    
    // Hide applied coupon
    function hideAppliedCoupon() {
        $('#applied-commission-coupon').hide();
        $('.commission-coupon-form').show();
        $('#commission-coupon-message').html('');
    }
    
    // Handle mark as processed button
    $('.send-points').on('click', function() {
        var recordId = $(this).data('id');
        var button = $(this);
        
        if (!confirm('Are you sure you want to mark this commission as processed?')) {
            return;
        }
        
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: commission_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'commission_send_points',
                record_id: recordId,
                nonce: commission_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Status updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Mark as Processed');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text('Mark as Processed');
            }
        });
    });
    
    // Handle edit coupon button
    $('.edit-coupon').on('click', function() {
        var couponId = $(this).data('id');
        
        // Get coupon data
        $.ajax({
            url: commission_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'commission_get_coupon_data',
                coupon_id: couponId,
                nonce: commission_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showEditCouponModal(response.data);
                } else {
                    alert('Error loading coupon data: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while loading coupon data.');
            }
        });
    });
    
    // Function to show edit coupon modal
    function showEditCouponModal(couponData) {
        var modalHtml = `
            <div id="edit-coupon-modal" style="display: none;">
                <div class="modal-overlay">
                    <div class="modal-content">
                        <h2>Edit Coupon</h2>
                        <form id="edit-coupon-form">
                            <input type="hidden" id="edit-coupon-id" value="${couponData.id}">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Coupon Code</th>
                                    <td><input type="text" id="edit-coupon-code" value="${couponData.coupon_code}" readonly class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Holder Email</th>
                                    <td><input type="email" id="edit-holder-email" value="${couponData.holder_email}" required class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Teacher Email</th>
                                    <td><input type="email" id="edit-teacher-email" value="${couponData.teacher_email}" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Director Email</th>
                                    <td><input type="email" id="edit-director-email" value="${couponData.director_email}" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Teacher Rate (%)</th>
                                    <td><input type="number" id="edit-teacher-rate" value="${couponData.teacher_rate}" min="0" max="100" step="0.01" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Director Rate (%)</th>
                                    <td><input type="number" id="edit-director-rate" value="${couponData.director_rate}" min="0" max="100" step="0.01" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row">Status</th>
                                    <td>
                                        <select id="edit-status" class="regular-text">
                                            <option value="active" ${couponData.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${couponData.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="modal-buttons">
                                <button type="submit" class="button button-primary">Update Coupon</button>
                                <button type="button" class="button close-modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        $('#edit-coupon-modal').remove();
        
        // Add modal to body
        $('body').append(modalHtml);
        
        // Show modal
        $('#edit-coupon-modal').show();
    }
    
    // Handle modal close
    $(document).on('click', '.close-modal, .modal-overlay', function(e) {
        if (e.target === this) {
            $('#edit-coupon-modal').remove();
        }
    });
    
    // Handle edit coupon form submission
    $(document).on('submit', '#edit-coupon-form', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'commission_update_coupon',
            coupon_id: $('#edit-coupon-id').val(),
            holder_email: $('#edit-holder-email').val(),
            teacher_email: $('#edit-teacher-email').val(),
            director_email: $('#edit-director-email').val(),
            teacher_rate: $('#edit-teacher-rate').val(),
            director_rate: $('#edit-director-rate').val(),
            status: $('#edit-status').val(),
            nonce: commission_ajax.nonce
        };
        
        $.ajax({
            url: commission_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Coupon updated successfully!');
                    $('#edit-coupon-modal').remove();
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });
    
    // Handle export report button
    $('.export-report').on('click', function() {
        var holderEmail = $('select[name="holder"]').val();
        var exportUrl = commission_ajax.ajax_url + '?action=commission_export_report';
        
        if (holderEmail) {
            exportUrl += '&holder=' + encodeURIComponent(holderEmail);
        }
        
        window.location.href = exportUrl;
    });
    
    // Form validation for add coupon
    $('form[action=""] input[name="action"][value="add_coupon"]').closest('form').on('submit', function(e) {
        var teacherRate = parseFloat($('input[name="teacher_rate"]').val()) || 0;
        var directorRate = parseFloat($('input[name="director_rate"]').val()) || 0;
        
        if (teacherRate + directorRate > 100) {
            e.preventDefault();
            alert('Total teacher and director rates cannot exceed 100%');
            return false;
        }
    });
    
    // Real-time rate validation
    $('input[name="teacher_rate"], input[name="director_rate"]').on('input', function() {
        var teacherRate = parseFloat($('input[name="teacher_rate"]').val()) || 0;
        var directorRate = parseFloat($('input[name="director_rate"]').val()) || 0;
        var total = teacherRate + directorRate;
        
        if (total > 100) {
            $(this).css('border-color', 'red');
            if (!$('.rate-warning').length) {
                $(this).closest('table').after('<div class="rate-warning" style="color: red; margin-top: 10px;">Warning: Total rates exceed 100%</div>');
            }
        } else {
            $('input[name="teacher_rate"], input[name="director_rate"]').css('border-color', '');
            $('.rate-warning').remove();
        }
    });
    
    // Dashboard stats auto-refresh (every 30 seconds)
    if ($('.commission-dashboard').length) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
    
    // Add loading states to buttons
    $('.button').on('click', function() {
        var button = $(this);
        if (!button.hasClass('no-loading')) {
            button.addClass('loading');
        }
    });
    
    // Commission calculation preview
    if ($('#commission-calculator').length) {
        $('#order-total, #downline-count').on('input', function() {
            calculateCommissionPreview();
        });
    }
    
    function calculateCommissionPreview() {
        var orderTotal = parseFloat($('#order-total').val()) || 0;
        var downlineCount = parseInt($('#downline-count').val()) || 0;
        var teacherRate = parseFloat($('#teacher-rate-preview').val()) || 5;
        var directorRate = parseFloat($('#director-rate-preview').val()) || 5;
        
        // Calculate holder rate based on downline count
        var holderRate = 30; // Default
        if (downlineCount > 200) {
            holderRate = 40;
        } else if (downlineCount > 100) {
            holderRate = 35;
        }
        
        var holderCommission = orderTotal * (holderRate / 100);
        var teacherCommission = orderTotal * (teacherRate / 100);
        var directorCommission = orderTotal * (directorRate / 100);
        
        $('#holder-rate-display').text(holderRate + '%');
        $('#holder-commission-display').text('$' + holderCommission.toFixed(2));
        $('#teacher-commission-display').text('$' + teacherCommission.toFixed(2));
        $('#director-commission-display').text('$' + directorCommission.toFixed(2));
        $('#total-commission-display').text('$' + (holderCommission + teacherCommission + directorCommission).toFixed(2));
    }
});

// CSS styles for modal and other elements
var commissionStyles = `
<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 20px;
    border-radius: 4px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-buttons {
    margin-top: 20px;
    text-align: right;
}

.modal-buttons .button {
    margin-left: 10px;
}

.button.loading {
    opacity: 0.6;
    pointer-events: none;
}

.rate-warning {
    background: #ffebe8;
    border: 1px solid #cc1818;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.commission-calculator {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.commission-preview {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
}

.commission-preview h4 {
    margin-top: 0;
}

.commission-item {
    display: flex;
    justify-content: space-between;
    margin: 5px 0;
}

.commission-item strong {
    border-top: 1px solid #ddd;
    padding-top: 5px;
}
</style>
`;

// Inject styles
jQuery('head').append(commissionStyles);