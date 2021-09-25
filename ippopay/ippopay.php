<?php
/**
/**
 * Ippopay - Payment Gateway
 *
 * Order Validation Controller
 *
 * @author Ippopay
 */
require_once __DIR__.'/ippopay-sdk/Ippopay.php';
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter;
use Ippopay\Api\Api;




if (!defined('_PS_VERSION_')) {
    exit;
}

class IppoPay extends PaymentModule
{
    private $_html = '';
    private $PUBLIC_KEY = null;
    private $SECRET_KEY = null;
    private $ENABLE_REDIRECT = null;
    private $_postErrors = array();

    const IPPOPAY_CHECKOUT_URL = 'https://js.ippopay.com/scripts/ippopay.v1.js';

    public $address;

    /**
     * Ippopay constructor.
     *
     * Set the information about this module
     */
    public function __construct()
    {

        $this->name                   = 'ippopay';
        $this->tab                    = 'payments_gateways';
        $this->version                = '1.0';
        $this->author                 = 'IppoPay Technologies Private Limited';
        $this->controllers            = array('payment', 'validation');
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;
        $this->displayName            = 'IppoPay';
        $this->description            = 'The Ippopay Payments plugin allows you to accept credit card, UPI, Netbanking and debit card payments via Ippopay payment gateway.';
        $this->confirmUninstall       = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);

        $config = Configuration::getMultiple([
            'IPPOPAY_KEY_ID',
            'IPPOPAY_KEY_SECRET',
            'IPPOPAY_ENABLE_REDIRECT'
        ]);
         
        if (array_key_exists('IPPOPAY_KEY_ID', $config))
        {
            $this->KEY_ID = $config['IPPOPAY_KEY_ID'];
        }

        if (array_key_exists('IPPOPAY_KEY_SECRET', $config))
        {
            $this->KEY_SECRET = $config['IPPOPAY_KEY_SECRET'];
        }

        if (array_key_exists('IPPOPAY_ENABLE_REDIRECT', $config))
        {
            $this->ENABLE_REDIRECT_OPTION = $config['IPPOPAY_ENABLE_REDIRECT'];
        }

        parent::__construct();
            
        if (array_key_exists('PUBLIC_KEY', $config))
        {
            $this->KEY_ID = $config['PUBLIC_KEY'];
        }

        if (array_key_exists('SECRET_KEY', $config))
        {
            $this->KEY_SECRET = $config['SECRET_KEY'];
        }

        if (array_key_exists('ENABLE_REDIRECT', $config))
        {
            $this->ENABLE_REDIRECT_OPTION = $config['ENABLE_REDIRECT'];
        }
         
        // Both are set to NULL by default
        if ($this->KEY_ID === null OR $this->KEY_SECRET === null)
        {
            $this->warning = $this->l('your IppoPay key must be configured in order to use this module correctly');
        }
    }
   

    private function _displayForm()
    {
        $modippopay                = $this->l('Ippopay Configuration');
        $modippopayDesc        = $this->l('Please specify the Ippopay Public Key and Secret Key.');
        $modredirectDesc        = $this->l('Enable the Redirect option.');
        $modClientLabelKeyId      = $this->l('Ippopay Public Key');
        $modClientLabelKeySecret       = $this->l('Ippopay Secret Key');
        $modClientValueKeyId      = $this->KEY_ID;
        $modClientValueKeySecret       = $this->KEY_SECRET;
        $modredirect       = ($this->ENABLE_REDIRECT_OPTION  === 'on') ? 'checked' : '';
        $modUpdateSettings      = $this->l('Update settings');
      

        $this->_html .= '<div class="ippopay-config">
            <div class="config-title">Ippopay Configuration</div>
            <p class="sub-desc2">'.$modippopayDesc.'</p>
            <form class="ippopay-payment-section-form" action="'.$_SERVER['REQUEST_URI'].'" method="post">
                <div class="input-frm">
                    <label for="public-key">'.$modClientLabelKeyId.'</label>
                    <input type="text" class="cont-form" name="KEY_ID" value="'.$modClientValueKeyId.'" />
                </div>
                <div class="input-frm">
                    <label>'.$modClientLabelKeySecret.'</label>
                    <input type="text" class="cont-form" name="KEY_SECRET" value="'.$modClientValueKeySecret.'"/>
                </div>
                <div class="input-frm">
                    <label for="enable_redirect">'.$modredirectDesc.'</label>
                    <div class="enable_section">
                        <label class="enable-check-label">
                          <input type="checkbox" id="enable_redirect" name="ENABLE_REDIRECT_OPTION" '.$modredirect.'>
                          <span class="mark"></span>
                        </label>
                    </div>
                </div>
                <div class="input-frm">
                    <input type="submit" class="btn update-set" name="btnSubmit" value="'.$modUpdateSettings.'" >
                </div>
            </form>
        </div>';
    }

    /**
     * Install this module and register the following Hooks:
     *
     * @return bool
     */
    public function install()
    {
        if (parent::install() and
            $this->registerHook('header') and
            $this->registerHook('orderConfirmation') and
            $this->registerHook('paymentOptions') and
            $this->registerHook('paymentReturn'))
        {
            return true;
        }

        return false;
    }

    /**
     * Uninstall this module and remove it from all hooks
     *
     * @return bool
     */
    public function uninstall()
    {
        Configuration::deleteByName('IPPOPAY_KEY_ID');
        Configuration::deleteByName('IPPOPAY_KEY_SECRET');
        Configuration::deleteByName('IPPOPAY_ENABLE_REDIRECT');

        return parent::uninstall();
    }

    /**
     * Returns a string containing the HTML necessary to
     * generate a configuration screen on the admin
     *
     * @return string
     */
    public function getContent()
    {
       
        $this->_html = '<div class="ippopay-payment-section">';
        if (Tools::isSubmit('btnSubmit'))
        {
            $this->_postValidation();
            if (empty($this->_postErrors))
            {
                $this->_postProcess();
            }
            else
            {
                foreach ($this->_postErrors AS $err)
                {
                    $this->_html .= "<div class='alert error'>ERROR: {$err}</div>";
                }
            }
        }

        $this->_displayippopay();
        $this->_displayForm();
        $this->_html .= '</div>';
        return $this->_html;
    }
    

    public function hookHeader()
    {

        if (Tools::getValue('controller') == "order")
        {
            $this->context->controller->registerJavascript(
               'remote-ippopay-checkout',
               self::IPPOPAY_CHECKOUT_URL,
               ['server' => 'remote', 'position' => 'head', 'priority' => 20]
            );

            $this->context->controller->registerJavascript(
                'options',
                'modules/' . $this->name.'/ajax.js',
                ['position' => 'bottom', 'priority' => 30]
            );
        }

    }

    public function createOrder()
    {

        $cart_presenter = new CartPresenter();

        $amount = ($this->context->cart->getOrderTotal() * 100);
        $ip_order_id = "";
        $currency = $this->context->currency;

        $ip_order_id = "";
        $ip_public_key = "";
        $array = [];
        $resarray = [];
        $returnUrl = "";
        $redirect = 0;
        $address = new Address($this->context->cart->id_address_delivery);

        if($this->ENABLE_REDIRECT_OPTION) {
            $returnUrl = $this->context->link->getModuleLink($this->name, 'redirectvalidation', array(), true);
            $redirect = 1;
        }
        

        $data = [
            "amount" => $amount / 100,
            "currency" => $currency->iso_code,
            "payment_modes" => "cc,dc,nb,upi",
            "return_url" => $returnUrl,
            "customer" => [
                "name" => $this->context->customer->firstname,
                "email" => $this->context->customer->email,
                "phone" => [
                    "country_code" => "",
                    "national_number" => ""
                ]
            ]

        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://".$this->KEY_ID.":".$this->KEY_SECRET."@api.ippopay.com/v1/order/create",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json"
        ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);  

        if ($err) {
            echo "Order creation failed with the error " . $err; die;
        } else {      
            $array = json_decode($response,true);
            $ip_order_id = $array['data']['order']['order_id'];
            $ip_public_key = $array['data']['order']['public_key'];
        }
        
        if(!isset($_SESSION)) 
        { 
            session_start(); 
        } 

        $resArray = [
            "ip_order_id" => $ip_order_id,
            "ip_public_key" => $ip_public_key,
            "redirect" => $redirect
        ];
        print_r(json_encode($resArray)); die;
    }

    /**
     * Display this module as a payment option during the checkout
     *
     * @param array $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        /*
         * Verify if this module is active
         */
        if (!$this->active) {
            return;
        }

        /**
         * Form action URL. The form data will be sent to the
         * validation controller when the user finishes
         * the order process.
         */
        $formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);

        /**
         * Assign the url form action to the template var $action
         */
        $actionUrl = Tools::getHttpHost(true).__PS_BASE_URI__; 

        $this->smarty->assign(['actionUrl' => $actionUrl]);
        $this->smarty->assign(['action' => $formAction]);

        /**
         *  Load form template to be displayed in the checkout step
         */
        $paymentForm = $this->fetch('module:ippopay/views/templates/hook/payment_options.tpl');

        /**
         * Create a PaymentOption object containing the necessary data
         * to display this module in the checkout
         */
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)
            ->setCallToActionText($this->displayName)
            ->setLogo('../modules/ippopay/logo.png')
            ->setAction($formAction)
            //->setForm($paymentForm)
            ->setAdditionalInformation('<input type="hidden" id="actionUrl" value='.$actionUrl.'>
                <p>Pay using Credit/Debit Card, NetBanking, Wallets, or UPI</p>');
    
        $payment_options = array(
            $newOption
        );

        return $payment_options;
    }

    /**
     * Display a message in the paymentReturn hook
     * 
     * @param array $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if ((!isset($params['order'])) or
            ($params['order']->module != $this->name))
        {
            return false;
        }

        if ((isset($params['order'])) and
            (Validate::isLoadedObject($params['order'])) &&
            (isset($params['order']->valid)))
        {
            $this->smarty->assign([
                'id_order'  => $params['order']->id,
                'valid'     => $params['order']->valid,
            ]);
        }

        if ((isset($params['order']->reference)) and
            (!empty($params['order']->reference))) {
            $this->smarty->assign('reference', $params['order']->reference);
        }

        $this->smarty->assign([
            'shop_name'     => $this->context->shop->name,
            'reference'     => $params['order']->reference,
            'contact_url'   => $this->context->link->getPageLink('contact', true),
        ]);

        return $this->fetch('module:ippopay/views/templates/hook/payment_return.tpl');
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            $keyId = Tools::getValue('KEY_ID');
            $keySecret = Tools::getValue('KEY_SECRET');

            if (empty($keyId))
            {
                $this->_postErrors[] = $this->l('Your Public Key Id is required.');
            }
            if (empty($keySecret))
            {
                $this->_postErrors[] = $this->l('Your Secret Key is required.');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            Configuration::updateValue('IPPOPAY_KEY_ID', Tools::getValue('KEY_ID'));
            Configuration::updateValue('IPPOPAY_KEY_SECRET', Tools::getValue('KEY_SECRET'));
            Configuration::updateValue('IPPOPAY_ENABLE_REDIRECT', Tools::getValue('ENABLE_REDIRECT_OPTION'));

            $this->KEY_ID= Tools::getValue('KEY_ID');
            $this->KEY_SECRET= Tools::getValue('KEY_SECRET');
            $this->ENABLE_REDIRECT_OPTION= Tools::getValue('ENABLE_REDIRECT_OPTION');
        }

        $ok = $this->l('Ok');
        $updated = $this->l('Settings Updated');
        $this->_html .= "<div class='conf confirm'>{$updated}</div>";
    }

    private function _displayippopay()
    {
        $modDesc    = $this->l('This module allows you to accept payments using Ippopay.');
        $modStatus  = $this->l('Ippopay online payment service is the right solution for you if you are accepting payments');
        $modconfirm = $this->l('');
        $this->_html .= '<style type="text/css">.ippopay-payment-section *{margin:0;padding:0;box-sizing:border-box}.ippopay-payment-section img{max-width:100%}.ippopay-payment-section{font-family:inherit;float:left;width:100%}.ippopay-payment{float:left;width:100%}.ippopay-title{float:left;width:100%;font-size:25px;color:#585757;margin-bottom:15px}.ippopay-desc{float:left;width:100%}.ippopay-desc img{float:left}.ippopay-cont{float:left;width:calc (100% - 200px);margin-left:20px}.bld-cont{float:left;width:100%;color:#3a3838}.sub-desc1{float:left;width:100%;color:#4d4c4c}.ippopay-config{float:left;width:100%;margin-top:50px}.config-title{float:left;width:100%;color:#1b1b1b;padding-bottom:5px;margin-bottom:10px;font-size:17px;font-weight:600;}.sub-desc2{float:left;width:100%;color:#727272;margin-bottom:15px}.input-frm{float:left;width:100%;margin-bottom:15px}.input-frm .enable_section,.input-frm label{float:left;width:200px}.input-frm input,.input-frm input.cont-form{float:left;width:400px;border:1px solid #e1e1e1;padding:7px;border-radius:2px}.cont-form{padding:6px 15px}.ippopay-payment-section-form{float:left;width:100%}.input-frm input.update-set,.update-set{background-color:#001a6c;color:#fff;padding:10px 25px;border-radius:5px;float:left;width:300px;border-radius:5px;cursor:pointer;font-size:17px;font-weight:500;margin:10px 0 0 200px}.enable-check-label{float:left;width:100%;position:relative}.enable-check-label input{position:absolute;opacity:0;cursor:pointer;width:0;display:none}.enable-check-label .mark{float:left;height:24px;width:24px;background-color:transparent;border-radius:5px;transition:all .3s ease-in;border:2px solid #e1e1e1;padding:0!important;cursor:pointer;}.enable-check-label input:checked~.mark{background-color:#fff;border-radius:5px;transform:rotate(0) scale(1);opacity:1;border:2px solid #e1e1e1;cursor:pointer;}.enable-check-label .mark::after{position:absolute;content:"";border-radius:5px}.enable-check-label input:checked~.mark::after{transform:rotate(45deg) scale(1);left:6px;top:2px;width:6px;height:12px;border:solid #001a6c;border-width:0 2px 2px 0;border-radius:0}.iplogo{float:left;margin:13.5px 0 0;}.input-frm input.update-set:hover, .update-set:hover{color:#f1f1f1;}.error{color:#ff0000;}.confirm{color:#008000;}</style>';
        $this->_html .= '<div class="ippopay-payment"><div class="ippopay-title">'.$this->displayName.'</div><div class="ippopay-desc"><img class="iplogo" src="../modules/ippopay/logo.png"><div class="ippopay-cont"><h4 class="bld-cont">'.$modDesc.'</h4><p class="sub-desc1">'.$modStatus.'</p></div></div></div>';
    }

    //Returns API instance
    public function getIppopayApiInstance()
    {
        return new Api($this->KEY_ID, $this->KEY_SECRET);
    }

}