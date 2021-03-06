<?php
    if (!class_exists('Conekta')) {
        require_once("lib/conekta-php/lib/Conekta.php");
    }
    /*
     * Title   : Conekta Payment extension for WooCommerce
     * Author  : Cristina Randall
     * Url     : https://wordpress.org/plugins/conekta-woocommerce
     */
   
    class WC_Conekta_Card_Gateway extends WC_Payment_Gateway
    {
        protected $GATEWAY_NAME               = "WC_Conekta_Card_Gateway";
        protected $usesandboxapi              = true;
        protected $enablemeses                = false;
        protected $order                      = null;
        protected $transactionId              = null;
        protected $transactionErrorMessage    = null;
        protected $conektaTestApiKey          = '';
        protected $conektaLiveApiKey          = '';
        protected $publishable_key            = '';
        
        public function __construct()
        {
            $this->id              = 'ConektaCard';
            $this->has_fields      = true;
            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->settings['title'];
            $this->description        = '';
            $this->icon 		      = $this->settings['alternate_imageurl'] ? $this->settings['alternate_imageurl']  : WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/credits.png';
            $this->usesandboxapi      = strcmp($this->settings['debug'], 'yes') == 0;
            $this->enablemeses = strcmp($this->settings['meses'], 'yes') == 0;
            $this->testApiKey 		  = $this->settings['test_api_key'];
            $this->liveApiKey 		  = $this->settings['live_api_key'];
            $this->testPublishableKey = $this->settings['test_publishable_key'];
            $this->livePublishableKey = $this->settings['live_publishable_key'];
            //$this->useUniquePaymentProfile = strcmp($this->settings['enable_unique_profile'], 'yes') == 0;
            $this->publishable_key    = $this->usesandboxapi ? $this->testPublishableKey : $this->livePublishableKey;
            $this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;
            add_action('woocommerce_update_options_payment_gateways_' . $this->id , array($this, 'process_admin_options'));
            add_action('admin_notices'                              , array(&$this, 'perform_ssl_check'    ));
 
        }        
        
        /**
        * Checks to see if SSL is configured and if plugin is configured in production mode 
        * Forces use of SSL if not in testing 
        */ 
        public function perform_ssl_check()
        {
            if (!$this->usesandboxapi && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
                echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
            endif;
        }
        
        public function init_form_fields()
        {
            $this->form_fields = array(
                                       'enabled' => array(
                                                          'type'        => 'checkbox',
                                                          'title'       => __('Enable/Disable', 'woothemes'),
                                                          'label'       => __('Enable Credit Card Payment', 'woothemes'),
                                                          'default'     => 'yes'
                                                          ),
                                       'meses' => array(
                                                        'type'        => 'checkbox',
                                                        'title'       => __('Meses sin Intereses', 'woothemes'),
                                                        'label'       => __('Enable Meses sin Intereses', 'woothemes'),
                                                        'default'     => 'no'
                                                        ),
                                       'debug' => array(
                                                        'type'        => 'checkbox',
                                                        'title'       => __('Testing', 'woothemes'),
                                                        'label'       => __('Turn on testing', 'woothemes'),
                                                        'default'     => 'no'
                                                        ),
                                       'title' => array(
                                                        'type'        => 'text',
                                                        'title'       => __('Title', 'woothemes'),
                                                        'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                                                        'default'     => __('Credit Card Payment', 'woothemes')
                                                        ),
                                       'test_api_key' => array(
                                                               'type'        => 'text',
                                                               'title'       => __('Conekta API Test Private key', 'woothemes'),
                                                               'default'     => __('', 'woothemes')
                                                               ),
                                       'test_publishable_key' => array(
                                                                       'type'        => 'text',
                                                                       'title'       => __('Conekta API Test Public key', 'woothemes'),
                                                                       'default'     => __('', 'woothemes')
                                                                       ),
                                       'live_api_key' => array(
                                                               'type'        => 'text',
                                                               'title'       => __('Conekta API Live Private key', 'woothemes'),
                                                               'default'     => __('', 'woothemes')
                                                               ),
                                       'live_publishable_key' => array(
                                                                       'type'        => 'text',
                                                                       'title'       => __('Conekta API Live Public key', 'woothemes'),
                                                                       'default'     => __('', 'woothemes')
                                                                       ),
                                       'alternate_imageurl' => array(
                                                                     'type'        => 'text',
                                                                     'title'       => __('Alternate Image to display on checkout, use fullly qualified url, served via https', 'woothemes'),
                                                                     'default'     => __('', 'woothemes')
                                                                     ),
                                       
                                       
                                       );
        }
        
        /**
        * Load the admin fields 
        */
        public function admin_options()
        {
            include_once('templates/admin.php');
        }
        /**
        * Load the credit card fields into the checkout view
        * Contains js for tokenizing the credit card 
        */        
        public function payment_fields()
        {
            include_once('templates/payment.php');
        }
        
        protected function send_to_conekta()
        {
            global $woocommerce;
            include_once('conekta_gateway_helper.php');
            Conekta::setApiKey($this->secret_key);
            Conekta::setLocale("es");	
            $data = getRequestData($this->order);
            
            try {
              
                $line_items = array();
                $items = $this->order->get_items();
                $line_items = build_line_items($items);
                $details = build_details($data, $line_items);
                
                $charge = Conekta_Charge::create(array(
                            "amount"      => $data['amount'],
                            "currency"    => $data['currency'],
                            "monthly_installments" => $data['monthly_installments'] > 1 ? $data['monthly_installments'] : null,
                            "card"        => $data['token'],
                            "reference_id" => $this->order->id,
                            "description" => "Compra con orden # ". $this->order->id,
                            "details"     => $details,
                        ));
                
                $this->transactionId = $charge->id;
                if ($data['monthly_installments'] > 1) {
                update_post_meta( $this->order->id, 'meses-sin-intereses', $data['monthly_installments']);
                }
                update_post_meta( $this->order->id, 'transaction_id', $this->transactionId);
                return true;
                
            } catch(Conekta_Error $e) {
                $description = $e->message_to_purchaser;

        		global $wp_version;
        		if (version_compare($wp_version, '4.1', '>=')) {
        			wc_add_notice(__('Error: ', 'woothemes') . $description , $notice_type = 'error');
        		} else {
               		error_log('Gateway Error:' . $description . "\n");
                	$woocommerce->add_error(__('Error: ', 'woothemes') . $description);
        		}
                return false;
            }
        }
        
        public function process_payment($order_id)
        {
            global $woocommerce;
            $this->order        = new WC_Order($order_id);
            if ($this->send_to_conekta())
            {
                $this->completeOrder();
                
                $result = array(
                                'result' => 'success',
                                'redirect' => $this->get_return_url($this->order)
                                );
                return $result;
            }
            else
            {
                $this->markAsFailedPayment();
            }
        }
        
        protected function markAsFailedPayment()
        {
            $this->order->add_order_note(
                                         sprintf(
                                                 "%s Credit Card Payment Failed : '%s'",
                                                 $this->GATEWAY_NAME,
                                                 $this->transactionErrorMessage
                                                 )
                                         );
        }
        
        protected function completeOrder()
        {
            global $woocommerce;
            
            if ($this->order->status == 'completed')
                return;
           
            // adjust stock levels and change order status
            $this->order->payment_complete();
            $woocommerce->cart->empty_cart();
            
            $this->order->add_order_note(
                                         sprintf(
                                                 "%s payment completed with Transaction Id of '%s'",
                                                 $this->GATEWAY_NAME,
                                                 $this->transactionId
                                                 )
                                         );
            
            unset($_SESSION['order_awaiting_payment']);
        }
        
        
    }
    
    function conekta_card_order_status_completed($order_id = null)
    {
        global $woocommerce;
        if (!$order_id)
            $order_id = $_POST['order_id'];
        
        $data = get_post_meta( $order_id );
        $total = $data['_order_total'][0] * 100;
        
        $params = array();
        if(isset($_POST['amount']) && $amount = $_POST['amount'])
        {
            $params['amount'] = round($amount);
        }
    }
   
    // tell WC that WC_Conekta_Card_Gateway class exists 
    function conektacheckout_add_card_gateway($methods)
    {
        array_push($methods, 'WC_Conekta_Card_Gateway');
        return $methods;
    }
    
    add_filter('woocommerce_payment_gateways',                      'conektacheckout_add_card_gateway');
    add_action('woocommerce_order_status_processing_to_completed',  'conekta_card_order_status_completed' );
