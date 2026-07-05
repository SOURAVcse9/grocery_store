<?php
/**
 * ==========================================================================
 * admin/includes/email_helpers.php — Reusable Email Ready Architecture
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * get_email_header()
 * Shared company styling header template.
 */
function get_email_header(string $title): string
{
    $logo = asset('images/logo.png');
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>{$title}</title>
        <style>
            body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #333333; }
            .container { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
            .header { background-color: #0ca678; padding: 30px; text-align: center; color: #ffffff; }
            .content { padding: 30px; line-height: 1.6; }
            .btn { display: inline-block; padding: 12px 24px; background-color: #0ca678; color: #ffffff; text-decoration: none; border-radius: 20px; font-weight: bold; margin-top: 20px; }
            .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #888888; border-top: 1px solid #eeeeee; }
            .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .table th { background-color: #f1f3f5; padding: 10px; text-align: left; font-size: 12px; font-weight: bold; }
            .table td { padding: 10px; border-bottom: 1px solid #eeeeee; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0; font-size:24px;'>GroCo Grocery Store</h1>
            </div>
            <div class='content'>
    ";
}

/**
 * get_email_footer()
 * Shared footer signature.
 */
function get_email_footer(): string
{
    return "
            </div>
            <div class='footer'>
                <p>This is an automated system email notification from GroCo. Please do not reply directly.</p>
                <p>&copy; " . date('Y') . " GroCo Store. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * get_order_confirmation_email()
 */
function get_order_confirmation_email(array $order, array $items): string
{
    $html = get_email_header("Order Confirmation #{$order['order_number']}");
    $html .= "
        <h2>Thank you for your order!</h2>
        <p>Hi {$order['full_name']},</p>
        <p>Your order has been placed successfully and is currently awaiting confirmation. Here is your summary:</p>
        <p><strong>Order Number:</strong> #{$order['order_number']}<br>
        <strong>Payment Method:</strong> " . strtoupper($order['payment_method']) . "<br>
        <strong>Grand Total:</strong> ৳" . number_format((float)$order['total_amount'], 2) . "</p>
        
        <table class='table'>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
    ";
    
    foreach ($items as $item) {
        $html .= "
            <tr>
                <td>{$item['name']}</td>
                <td>{$item['quantity']}</td>
                <td>৳" . number_format((float)$item['price'], 2) . "</td>
            </tr>
        ";
    }
    
    $html .= "
            </tbody>
        </table>
        
        <p style='margin-top:20px;'>We will notify you once your delivery leaves our warehouse.</p>
    ";
    $html .= get_email_footer();
    return $html;
}

/**
 * get_order_shipped_email()
 */
function get_order_shipped_email(array $order): string
{
    $html = get_email_header("Order Shipped #{$order['order_number']}");
    $html .= "
        <h2>Your package is on its way!</h2>
        <p>Hi {$order['full_name']},</p>
        <p>Exciting news! Your order <strong>#{$order['order_number']}</strong> has been shipped and is with our courier.</p>
        <p><strong>Courier Service:</strong> " . e($order['courier'] ?? 'GroCo Delivery Fleet') . "<br>
        <strong>Tracking Code:</strong> " . e($order['tracking_number'] ?? 'N/A') . "</p>
        
        <p>Estimated Delivery time is within 24-48 hours. Thank you for shopping with us!</p>
    ";
    $html .= get_email_footer();
    return $html;
}

/**
 * get_order_cancelled_email()
 */
function get_order_cancelled_email(array $order, string $reason): string
{
    $html = get_email_header("Order Cancelled #{$order['order_number']}");
    $html .= "
        <h2>Order Cancelled</h2>
        <p>Hi {$order['full_name']},</p>
        <p>We regret to inform you that your order <strong>#{$order['order_number']}</strong> has been cancelled.</p>
        <p><strong>Cancellation Reason:</strong> " . e($reason) . "</p>
        
        <p>If you have already paid for this order, a full refund will be processed back to your original payment account shortly.</p>
    ";
    $html .= get_email_footer();
    return $html;
}

/**
 * get_password_reset_email()
 */
function get_password_reset_email(array $user, string $resetUrl): string
{
    $html = get_email_header("Password Reset Request");
    $html .= "
        <h2>Reset Your Password</h2>
        <p>Hi {$user['full_name']},</p>
        <p>We received a request to reset the login password for your GroCo account. Click the button below to choose a new password:</p>
        <div style='text-align:center;'>
            <a href='{$resetUrl}' class='btn' style='color:#fff;'>Reset Password</a>
        </div>
        <p style='margin-top:20px; font-size:11px; color:#888;'>If you did not request this change, you can safely ignore this email.</p>
    ";
    $html .= get_email_footer();
    return $html;
}
