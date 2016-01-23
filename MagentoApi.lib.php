<?php

class Magento {

    public $conf = [
        'magento_host'         => '',
        'magentosoap_username' => '',
        'magentosoap_password' => '',
        'magentosoap_consumerKey' => '',
        'magentosoap_consumerSecret' => '',
        'magentosoap_AccessToken' => '',
        'magentosoap_AccessSecret' => ''
    ];

    public function __construct() {
        
    }

    public function REST_Request($callbackUrl, $url, $method, $data=array() ){

        /**
         * Example of simple product POST using Admin account via Magento REST API. OAuth authorization is used
         */

        $callbackUrl = $callbackUrl;
        $temporaryCredentialsRequestUrl = $this->conf['magento_host']."/oauth/initiate?oauth_callback=".urlencode($callbackUrl);
        $adminAuthorizationUrl = $this->conf['magento_host'].'/admin/oauth_authorize';
        $accessTokenRequestUrl = $this->conf['magento_host'].'/oauth/token';
        $apiUrl = $this->conf['magento_host'].'/api/rest';

        $consumerKey = $this->conf['magentosoap_consumerKey'];
        $consumerSecret = $this->conf['magentosoap_consumerSecret'];
        $AccessToken = $this->conf["magentosoap_AccessToken"];
        $AccessSecret = $this->conf["magentosoap_AccessSecret"];

        try{
            //$_SESSION['state'] = 2;
            $authType = (2 == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;
            $oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);
            $oauthClient->enableDebug();
            $oauthClient->disableSSLChecks();
            $oauthClient->setToken($AccessToken, $AccessSecret);
            $resourceUrl = $apiUrl.$url;
            $oauthClient->fetch($resourceUrl, $data, strtoupper($method), array("Content-Type" => "application/json","Accept" => "*/*"));
            //$oauthClient->fetch($resourceUrl);
            $ret = json_decode($oauthClient->getLastResponse());
            $ret = array(
                "error"=>0,
                "data"=>$ret,
            );
            return $ret;
        } catch (OAuthException $e) {

            $ret = array(
                "error"   => 1,
                "message" => "Checking quantity failed",
//                "data"    =>print_r($e, true)
            );
            return $ret;

        }

    }

    public function SOAP_CreateOrder($arrProducts){
        try {
            $config["customer_as_guest"] = TRUE;
            $config["customer_id"] = 1; //only if you don't want as Guest
            $proxy = new SoapClient($this->conf['magento_host'].'/index.php/api/soap/?wsdl', array('trace'=>1));
            $sessionId = $proxy->login($this->conf["magentosoap_username"], $this->conf["magentosoap_password"]);
            //~ echo $sessionId;
            $shoppingCartIncrementId = $proxy->call( $sessionId, 'cart.create',array( 1 ));

            $resultCartProductAdd = $proxy->call(
                $sessionId,
                "cart_product.add",
                array(
                    $shoppingCartIncrementId,
                    $arrProducts
                )
            );
            $shoppingCartId = $shoppingCartIncrementId;
            if ($config["customer_as_guest"]) {
                $customer = array(
                    "firstname" => "Name",
                    "lastname" => "Guest",
                    "website_id" => "1",
                    "group_id" => "1",
                    "store_id" => "1",
                    "email" => "l516077@rtrtr.com",
                    "mode" => "guest",
                );
            } else {
                $customer  = array(
                    "customer_id" => $config["customer_id"],
                    "website_id" => "1",
                    "group_id" => "1",
                    "store_id" => "1",
                    "mode" => "customer",
                );
            }
            //~ echo "\nSetting Customer...";
            $resultCustomerSet = $proxy->call($sessionId, 'cart_customer.set', array( $shoppingCartId, $customer) );

            // Set customer addresses, for example guest's addresses
            $arrAddresses = array(
                array(
                    "mode" => "shipping",
                    "firstname" => "Customername",
                    "lastname" => "Customerlastname",
                    "company" => "Company name",
                    "street" => "Street name",
                    "city" => "City",
                    "region" => "Region",
                    "postcode" => "51056",
                    "country_id" => "AL",
                    "telephone" => "0123456789",
                    "fax" => "0123456789",
                    "is_default_shipping" => 0,
                    "is_default_billing" => 0
                ),
                array(
                    "mode" => "billing",
                    "firstname" => "Firstname",
                    "lastname" => "Lastname",
                    "company" => "Company",
                    "street" => "Street",
                    "city" => "Tirane",
                    "region" => "TR",
                    "postcode" => "31056",
                    "country_id" => "AL",
                    "telephone" => "0123456789",
                    "fax" => "0123456789",
                    "is_default_shipping" => 0,
                    "is_default_billing" => 0
                )
            );
            //~ echo "\nSetting addresses...";
            $resultCustomerAddresses = $proxy->call($sessionId, "cart_customer.addresses", array($shoppingCartId, $arrAddresses));
            $resultShippingMethod = $proxy->call($sessionId, "cart_shipping.method", array($shoppingCartId, 'flatrate_flatrate'));
            // set payment method
            $paymentMethodString= "checkmo";
            //~ echo "\nPayment method $paymentMethodString.";
            $paymentMethod = array(
                "method" => $paymentMethodString
            );
            $resultPaymentMethod = $proxy->call($sessionId, "cart_payment.method", array($shoppingCartId, $paymentMethod));
            $licenseForOrderCreation = null;
            /*
            // get list of licenses
            $shoppingCartLicenses = $proxy->call($sessionId, "cart.license", array($shoppingCartId));
            print_r( $shoppingCartLicenses );
            // check if license is existed
            if (count($shoppingCartLicenses)) {
                $licenseForOrderCreation = array();
                foreach ($shoppingCartLicenses as $license) {
                    $licenseForOrderCreation[] = $license['agreement_id'];
                }
            }
            */
            // create order
            //~ echo "\nI will create the order: ";
            $resultOrderCreation = $proxy->call($sessionId,"cart.order",array($shoppingCartId, null, $licenseForOrderCreation));
            //~ echo "\nOrder created with code:".$resultOrderCreation."\n";
        } catch (SoapFault $e) {
            $res = ['error' => 1, 'message' => $e->faultstring, 'object'=> $e];
            return $res;
        }
        return $resultOrderCreation;
		// ob_clean();
    }

    public function SOAP_uploadImage($sku, $imgPath, &$f=null){
		//$sku= "hfjbdn86811690358785620e9d6e452b"; //example

        try {
            $proxy = new SoapClient($this->conf['magento_host'].'/api/soap/?wsdl');
            $sessionId = $proxy->login($this->conf["magentosoap_username"], $this->conf["magentosoap_password"]);

            $newImage = array(
                'file' => array(
                    'name' => 'file_name',
                    'content' => base64_encode(file_get_contents($imgPath)),
                    'mime'    => 'image/jpeg'
                ),
                'label'    => 'Image name',
                'position' => 1,
                'types'    => array('small_image'),
                'exclude'  => 0
            );
		//~ fwrite($f, $sku);
            $imageFilename = $proxy->call($sessionId, 'product_media.create', array($sku, $newImage));
            return $imageFilename;
        } catch (SoapFault $e) {
            $res = ['error' => 1, 'message' => $e->faultstring, 'object'=> $e];
            return $res;
        }
		
		// Newly created image file
		//~ $proxy->call($sessionId, 'product_media.list', $sku);

		//~ $proxy->call($sessionId, 'product_media.update', array(
				//~ $sku,
				//~ $imageFilename,
				//~ array('position' => 1, 'types' => array('image') /* Lets do it main image for product */)
		//~ ));

		//~ // Updated image file
		//~ $proxy->call($sessionId, 'product_media.list', 'Sku');
		//~
		//~ // Remove image file
		//~ $proxy->call($sessionId, 'product_media.remove', array('Sku', $imageFilename);
		//~
		//~ // Images without our file
		//~ $proxy->call($sessionId, 'product_media.list', 'Sku');
    }

    public function SOAP_updateProduct($productSku, $productData) {
        try {
            $proxy = new SoapClient($this->conf['magento_host'].'/api/soap/?wsdl');
            $sessionId = $proxy->login($this->conf["magentosoap_username"], $this->conf["magentosoap_password"]);

            $data = [
                $productSku,
                $productData
            ];
            $result = $proxy->call($sessionId, 'catalog_product.update', $data);
        } catch (SoapFault $e) {
            $res = ['error' => 1, 'message' => $e->faultstring, 'object'=> $e];
            return $res;
        }
        return $result;
    }

} 
