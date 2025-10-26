<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add commission coupon field to WooCommerce Block checkout
function add_commission_coupon_to_block_checkout() {
    // Only add on checkout page
    if (!is_checkout()) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Wait for the checkout blocks to load
        function addCommissionCouponToBlockCheckout() {
            var orderSummaryBlock = $('.wp-block-woocommerce-checkout-order-summary-block');
            var orderSummaryTitle = orderSummaryBlock.find('.wc-block-components-checkout-order-summary__title');
            
            if (orderSummaryTitle.length && !$('#commission-coupon-section').length) {
                var couponHtml = `
                    <div id="commission-coupon-section" class="commission-coupon-wrapper" style="margin: 15px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 16px;">折扣碼</h3>
                        <div class="commission-coupon-form" style="display: flex; gap: 10px; margin: 10px 0;">
                            <input type="text" id="commission-coupon-code" placeholder="輸入折扣碼" style="flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" />
                            <button type="button" id="apply-commission-coupon" class="button" style="padding: 8px 15px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">套用</button>
                        </div>
                        <div id="commission-coupon-message" style="margin: 10px 0;"></div>
                        <div id="applied-commission-coupon" style="display: none;">
                            <div class="applied-coupon-info" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;">
                                <span id="applied-coupon-text"></span>
                                <button type="button" id="remove-commission-coupon" style="background: none; border: none; color: #155724; text-decoration: underline; cursor: pointer;">移除</button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Insert before the order summary title
                orderSummaryTitle.before(couponHtml);
                
                // Bind events
                bindCommissionCouponEvents();
            }
        }
        
        function bindCommissionCouponEvents() {
            // Apply coupon
            $(document).off('click', '#apply-commission-coupon').on('click', '#apply-commission-coupon', function() {
                var couponCode = $('#commission-coupon-code').val().trim();
                if (!couponCode) {
                    showCouponMessage('請輸入折扣碼', 'error');
                    return;
                }
                
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
                            showCouponMessage(response.data.message, 'success');
                            $('#applied-coupon-text').text(response.data.coupon_code + ' - ' + response.data.discount_text);
                            $('#applied-commission-coupon').show();
                            $('.commission-coupon-form').hide();
                            
                            // Force refresh the checkout page to show discount
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showCouponMessage(response.data || '折扣碼無效', 'error');
                        }
                    },
                    error: function() {
                        showCouponMessage('發生錯誤，請稍後再試', 'error');
                    }
                });
            });
            
            // Remove coupon
            $(document).off('click', '#remove-commission-coupon').on('click', '#remove-commission-coupon', function() {
                $.ajax({
                    url: commission_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'remove_commission_coupon',
                        nonce: commission_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showCouponMessage('', '');
                            $('#applied-commission-coupon').hide();
                            $('.commission-coupon-form').show();
                            $('#commission-coupon-code').val('');
                            
                            // Force refresh the checkout page to remove discount
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        }
                    }
                });
            });
        }
        
        function showCouponMessage(message, type) {
            var messageDiv = $('#commission-coupon-message');
            messageDiv.removeClass('coupon-error coupon-success');
            
            if (type === 'error') {
                messageDiv.addClass('coupon-error').css({
                    'color': '#721c24',
                    'background': '#f8d7da',
                    'border': '1px solid #f5c6cb',
                    'padding': '8px',
                    'border-radius': '3px'
                });
            } else if (type === 'success') {
                messageDiv.addClass('coupon-success').css({
                    'color': '#155724',
                    'background': '#d4edda',
                    'border': '1px solid #c3e6cb',
                    'padding': '8px',
                    'border-radius': '3px'
                });
            }
            
            messageDiv.text(message);
        }
        
        // Try to add the coupon field immediately
        addCommissionCouponToBlockCheckout();
        
        // Also try after a short delay in case blocks are still loading
        setTimeout(addCommissionCouponToBlockCheckout, 1000);
        
        // Listen for checkout updates and re-add if needed
        $(document.body).on('updated_checkout', function() {
            setTimeout(addCommissionCouponToBlockCheckout, 500);
        });
        
        // Only check applied coupon if we're in the same session (not on page refresh)
        // checkAppliedCoupon(); // Commented out to prevent auto-loading previous coupons
        
        function checkAppliedCoupon() {
            // Check if there's an applied commission coupon
            $.ajax({
                url: commission_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_applied_commission_coupon',
                    nonce: commission_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.coupon_applied) {
                        // Show applied coupon state
                        $('#applied-coupon-text').text(response.data.coupon_code + ' - ' + response.data.discount_text);
                        $('#applied-commission-coupon').show();
                        $('.commission-coupon-form').hide();
                        showCouponMessage('Coupon applied: ' + response.data.coupon_code, 'success');
                    }
                }
            });
        }
    });
    </script>
    <?php
}

// Register commission coupon block (placeholder for future block development)
function register_commission_coupon_block() {
    // This is a placeholder for future WooCommerce block registration
    // For now, we're using JavaScript injection method above
}