<?php

/*
 * Title   : Conekta Payment Extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */

    /**
     * Build the line items hash
     * @param array $items
     */
    function build_line_items($items)
    {
         $line_items = array();
        foreach ($items as $item) {
            $productmeta = new WC_Product( $item['product_id']);
            $sku = $productmeta->get_sku();
            $line_items = array_merge($line_items, array(array(
                                                               'name' => $item['name'],
                                                               'unit_price' => floatval($item['line_total']) * 100,
                                                               'description' =>$item['name'],
                                                               'quantity' =>$item['qty'],
                                                               'sku' =>$sku,
                                                               'type' => $item['type']
                                                               ))
                                      );
        }
        return $line_items;
        
    }
    
    /**
     * Build the detail hash
     * @param hash $data
     * @param array $line_items
     */
    
    function build_details($data, $line_items)
    {
        $details = array();
        $details = array(
                         "email" => $data['card']['email'],
                         "name" => $data['card']['name'],
                         "phone" => $data['card']['phone'],
                         "line_items"  => $line_items,
                         "billing_address"  => array(
                                                     "company_name"=> $data['card']['billing_company'],
                                                     "street1" => $data['card']['address_line1'],
                                                     "street2" => $data['card']['address_line2'],
                                                     "zip" => $data['card']['address_zip'],
                                                     "city" => $data['card']['address_city'],
                                                     "phone" => $data['card']['phone'],
                                                     "email" => $data['card']['email'],
                                                     "country" => $data['card']['address_country'],
                                                     "state" => $data['card']['address_state']
                                                     ),
                         "shipment"  => array(
                                               "service"=> $data['shipping_method'],
                                               "price" => $data['shipping_cost'],
                                               "address"=>array(
                                                     "street1" => $data['card']['shipping_address_line1'],
                                                     "street2" => $data['card']['shipping_address_line2'],
                                                     "zip" => $data['card']['shipping_address_zip'],
                                                     "city" => $data['card']['shipping_address_city'],
                                                     "phone" => $data['card']['shipping_phone'],
                                                     "country" => $data['card']['shipping_address_country'],
                                                     "state" => $data['card']['shipping_address_state']
                                                     )
                                              )
                         );
        return $details;
        
    }

    
    /**
     * Bundle and format the order information
     * @param WC_Order $order
     * Send as much information about the order as possible to Conekta
     */
    function getRequestData($order)
    {
        if ($order AND $order != null)
        {
            return array(
                         "amount"      => (float)$order->get_total() * 100,
                         "token"       => $_POST['conektaToken'],
                         "shipping_method"       => $order->get_shipping_method(),
                         "shipping_cost" => (float)$order->get_total_shipping() * 100,  //get_shipping_cost
                         "monthly_installments" => (int)$_POST['monthly_installments'],
                         "currency"    => strtolower(get_woocommerce_currency()),
                         "description" => sprintf("Charge for %s", $order->billing_email),
                         "card"        => array(
                                                "name"            => sprintf("%s %s", $order->billing_first_name, $order->billing_last_name),
                                                "address_line1"   => $order->billing_address_1,
                                                "address_line2"   => $order->billing_address_2,
                                                "billing_company"   => $order->billing_company,
                                                 "phone"   => $order->billing_phone,
                                                "email"   => $order->billing_email,
                                                "address_city"     => $order->billing_city,
                                                "address_zip"     => $order->billing_postcode,
                                                "address_state"   => $order->billing_state,
                                                "address_country" => $order->billing_country,
                                                "shipping_address_line1"   => $order->shipping_address_1,
                                                "shipping_address_line2"   => $order->shipping_address_2,
                                                "shipping_phone"   => $order->shipping_phone,
                                                "shipping_email"   => $order->shipping_email,
                                                "shipping_address_city"     => $order->shipping_city,
                                                "shipping_address_zip"     => $order->shipping_postcode,
                                                "shipping_address_state"   => $order->shipping_state,
                                                "shipping_address_country" => $order->shipping_country,
                                                )
                         );
        }
        return false;
    }
