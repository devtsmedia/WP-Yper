<?php
if (!defined('ABSPATH')) {
    exit; 
}

use Yper\SDK\Api;

class WP_Yper_API {
    private static $environment;
    private static $client_id;
    private static $client_secret;
    private static $retailpoint_id;
    private static $pro_id;
    private static $pro_secret_token;
    private static $api;

    /**
     * Constructor: Initialize API and authenticate.
     */
    public function __construct() {

        self::$environment = get_option('wp_yper_environment');
        self::$client_id = get_option('wp_yper_client_id');
        self::$client_secret = get_option('wp_yper_client_secret');
        self::$retailpoint_id = get_option('wp_yper_retailpoint_id');
        self::$pro_id = get_option('wp_yper_pro_id');
        self::$pro_secret_token = get_option('wp_yper_pro_secret_token');

        self::$api = new Api(self::$client_id, self::$client_secret, ['global'], self::$environment);

        try {
            self::$api->authenticate_pro_secret(self::$pro_id, self::$pro_secret_token);
        } catch (Exception $e) {
            //wc_add_notice(__('Yper API Error: '. $e->getMessage(), 'woocommerce'), 'error');
            error_log( 'Yper API Error: '. $e->getMessage() );
        }
    }

    /**
     * Check if the order uses a specific shipping method and contains a specific product category.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return bool True if criteria are met, false otherwise.
     */
    private static function check_shipping_method_and_product_category($order) {
        $is_paris_delivery = false;
        $instance_id = 8; // Local pickup for Paris

        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            $shipping_instance_id = $shipping_method->get_instance_id();

            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                if (has_term('paris', 'product_cat', $product_id) && $shipping_instance_id == $instance_id) {
                    $is_paris_delivery = true;
                    break 2; 
                }
            }
        }

        return $is_paris_delivery;
    }

    /**
     * Convert date and time range to ISO 8601 format.
     *
     * @param string $date The delivery date in 'm/d/Y' format.
     * @param string $time_range The time range in 'H:i - H:i' format.
     * @return array An associative array with 'start' and 'end' keys in ISO 8601 format.
     */
    private static function convertRangeToISO8601($date, $time_range) {
        try {
            if ($date && $time_range) {
                $date_formatted = DateTime::createFromFormat('m/d/Y', $date);
                if (!$date_formatted) throw new Exception('Invalid date format');
                $date_formatted = $date_formatted->format('Y-m-d');

                list($start_time, $end_time) = explode(' - ', $time_range);
                $start_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date_formatted $start_time", new DateTimeZone('UTC'));
                $end_datetime = DateTime::createFromFormat('Y-m-d H:i', "$date_formatted $end_time", new DateTimeZone('UTC'));

                if (!$start_datetime || !$end_datetime) throw new Exception('Invalid time format');

                return [
                    'start' => $start_datetime->format('Y-m-d\TH:i:s\Z'),
                    'end'   => $end_datetime->format('Y-m-d\TH:i:s\Z'),
                ];
            } else {
                $current_datetime = new DateTime('now', new DateTimeZone('UTC'));
                $start_datetime = $current_datetime->format('Y-m-d\TH:i:s\Z');
                $end_datetime = $current_datetime->add(new DateInterval('PT1H'))->format('Y-m-d\TH:i:s\Z');

                return [
                    'start' => $start_datetime,
                    'end'   => $end_datetime,
                ];
            }
        } catch (Exception $e) {
            //wc_add_notice(__('Yper API Error: '. $e->getMessage(), 'woocommerce'), 'error');
            error_log( 'Yper API Error: '. $e->getMessage() );
            return [
                'start' => '',
                'end'   => '',
            ];
        }
    }

    /**
     * Send an email notification to client
     */

     private static function send_email($order, $order_dates, $yper_id) {

        $to = $order->get_billing_email();
        $subject = 'Your Delivery has been Sent to Yper';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $email_content = '<p>Hello ' . esc_html($order->get_billing_first_name()) . ',</p>';
        $email_content .= '<p>We wanted to let you know that your delivery order has been successfully sent to Yper.</p>';
        $email_content .= '<p><strong>Order ID:</strong> ' . esc_html($order->get_id()) . '</p>';
        $email_content .= '<p><strong>Yper Delivery ID:</strong> ' . esc_html($yper_id) . '</p>';
        $email_content .= '<p><strong>Delivery Start:</strong> ' . esc_html($order_dates['delivery_start']) . '</p>';
        $email_content .= '<p><strong>Delivery End:</strong> ' . esc_html($order_dates['delivery_end']) . '</p>';
        $email_content .= '<p>Thank you for choosing our service!</p>';
        $email_content .= '<p>Best regards,<br>Christophe Louie</p>';

        wp_mail($to, $subject, $email_content, $headers);
     }

    /**
     * Create a delivery request using the Yper API.
     *
     * @param int $order_id The ID of the WooCommerce order.
     */
    public static function create_delivery($order_id) {
        if (!$order_id) {
            return; 
        }

        $order = wc_get_order($order_id);

        if (!self::check_shipping_method_and_product_category($order)) {
            return;
        }

        $order_dates = array('asap' => true);

        $delivery_date = $order->get_meta('bp_delivery_date');
        $delivery_time = $order->get_meta('bp_delivery_time');

        $delivery_date = $delivery_date ?: (isset($_POST['bp-woopick-delivery_date_field']) ? sanitize_text_field($_POST['bp-woopick-delivery_date_field']) : '');
        $delivery_time = $delivery_time ?: (isset($_POST['bp-woopick-delivery_time_field']) ? sanitize_text_field($_POST['bp-woopick-delivery_time_field']) : '');

        // Convert delivery date and time to ISO 8601 format

		$delivery_dates = self::convertRangeToISO8601($delivery_date, $delivery_time);
        $order_dates['delivery_start'] = $delivery_dates['start'];
        $order_dates['delivery_end'] = $delivery_dates['end'];
		
		$phone_number = $order->get_billing_phone();

		if (strpos($phone_number, '+') !== 0) {
			$phone_number = '+' . $phone_number;
		}
		
        // Prepare data for Yper API request
        $yper_data = array(
            'comment' => $order->get_customer_note(),
            'date' => $order_dates,
            'delivery_address' => array(
                'formatted_address' => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
                'additional_number' => '',
                'additional' => $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
            ),
            'number_of_items' => $order->get_item_count(),
            'options' => array('fresh'),
            'order_reference' => (string) $order->get_order_number(),
            'price' => array(
                'value' => $order->get_total(),
            ),
            'receiver' => array(
                'type' => 'user',
                'firstname' => $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
                'lastname' => $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
                'phone' => $phone_number,
                'email' => $order->get_billing_email(),
            ),
            'retailpoint_id' => self::$retailpoint_id,
            'transport_type' => "bike",
        );

        try {
            $response = self::$api->post("/delivery", $yper_data);
        
            if (isset($response['result']['_id'])) {
                $yper_id = $response['result']['_id'];
                $order->update_meta_data('_yper_delivery_id', $yper_id);
                $order->save();
                
                $yper_message = 'Your delivery order was successfully sent to Yper.';
                echo '<div class="woocommerce-message">' . esc_html($yper_message) . '</div>';
                //wc_add_notice(__($yper_message, 'woocommerce'), 'success');
                
                $YperResultText = '<p><strong>Yper Delivery ID:</strong> ' . esc_html($yper_id) . '</p>';
                echo wp_kses($YperResultText, ['p' => [], 'strong' => []]);                

                error_log($yper_message);
        
                try {
                    self::send_email($order, $order_dates, $yper_id);
                } catch (Exception $emailException) {
                    //wc_add_notice(__('Failed to send email: ' . $emailException->getMessage(), 'woocommerce'), 'error');
                    error_log('Failed to send email: ' . $emailException->getMessage());
                }
        
            } else {
                //wc_add_notice(__('Failed to retrieve Yper ID from response.', 'woocommerce'), 'error');
                error_log('Failed to retrieve Yper ID from response.');
            }
        
        } catch (Exception $e) {
            //wc_add_notice(__('Yper API Error: ' . $e->getMessage(), 'woocommerce'), 'error');
            error_log('Yper API Error: ' . $e->getMessage());
			error_log(print_r($yper_data, true));
        }
        
        
    }
}
?>
