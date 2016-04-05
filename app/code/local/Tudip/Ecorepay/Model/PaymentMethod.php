<?php

/**
* Our CC module adapter
*/
class Tudip_Ecorepay_Model_PaymentMethod extends Mage_Payment_Model_Method_Cc
{
    /**
    * Unique internal payment method identifier
    */
     protected $_code = 'ecorepay';

     /**
     * Is this payment method a gateway (online auth/charge) ?
     */
     protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund online?
     */
    protected $_canRefund = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    /**
     * This method handles the authorization mechanism used by the EcorePay payment gateway.
     *
     */
      public function authorize(Varien_Object $payment, $amount){
    		$order = $payment->getOrder();
		try {
    			$billingaddress = $order->getBillingAddress();
			ob_start();
			$regionModel = Mage::getModel('directory/region')->load($billingaddress->getData('region_id'));
			$dob = Mage::getModel('customer/customer')->load($order->getCustomerId())->getDob();
			$devMode = false;
			$ipAddress = $_SERVER['REMOTE_ADDR'];
			$dobStr = "";
			if(!isset($dob)){
				$dob = $order->getCustomerDob();
			}
			if(isset($dob)){
				$dobStr = str_replace("00:00:00", "", $dob);
				$dobStr = str_replace("-", "", $dobStr);
				$dobStr = trim($dobStr);
			}

			$totals = number_format($amount, 2, '.', '');
			$fields = array(
                                'Reference'=> $order->getId(),
                                'Amount'=> $totals,
                                'Currency'=> $order->getBaseCurrencyCode(),
                                'Email'=> $billingaddress->getData('email'),
                                'IPAddress'=> $_SERVER['REMOTE_ADDR'],
                                'Phone'=> $billingaddress->getData('telephone'),
                                'FirstName'=> $billingaddress->getData('firstname'),
                                'LastName'=> $billingaddress->getData('lastname'),
                                'DOB'=> $dobStr,
                                'Address'=> $billingaddress->getData('street'),
                                'City'=> $billingaddress->getData('city'),
                                'State'=> $regionModel->getCode(),
                                'PostCode'=> $billingaddress->getData('postcode'),
                                'Country'=> $billingaddress->getData('country_id'),
                                'CardNumber'=> $payment->getCcNumber(),
                                'CardExpMonth'=> $payment->getCcExpMonth(),
                                'CardExpYear'=> $payment->getCcExpYear(),
                                'CardCVV'=> $payment->getCcCid()
			);
			$accountId = 'Enter Your Account Id or API User Key';
			$accountAuth = 'Enter Your Account Auth or API Password Key';
			$fields_string="<?xml version=\"1.0\" encoding=\"UTF-8\"?><Request type=\"AuthorizeCapture\"><AccountID>".$accountId."</AccountID><AccountAuth>".$accountAuth."</AccountAuth><Transaction>";
			foreach($fields as $key=>$value) {
				$fields_string .= '<'.$key.'>'.$value.'</'.$key.'>';
			}
			$fields_string .= '</Transaction></Request>';
			//open connection
			$ch = curl_init('https://gateway.ecorepay.cc/');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST,1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$fields_string);
			curl_setopt($ch, CURLOPT_HEADER ,0); // DO NOT RETURN HTTP HEADERS
			curl_setopt($ch, CURLOPT_RETURNTRANSFER ,1); // RETURN THE CONTENTS OF THE CALL
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120); // Timeout on connect (2 minutes)
			//execute post
			$data = curl_exec($ch); //This value is the string returned from the bank...

            	if (!$data) {
                	throw new Exception(curl_error($ch));
            	}
	    	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            	if ($httpcode && substr($httpcode, 0, 2) != "20") { // @TODO This should be moved to it's own method.
                	throw new Exception("Returned HTTP CODE: " . $httpcode . " for this URL: " . $urlToPost);
            	}
            	curl_close($ch);
        } catch (Exception $e) {
            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $this->setStore($payment->getOrder()->getStoreId());
            Mage::throwException($e->getMessage());
        }
        
        $xmlResponse = new SimpleXmlElement($data); // @TODO Keep looking for some Magento parse
	$contents = ob_get_contents();
	ob_end_clean();
	
        $isPaymentAccepted = $xmlResponse->ResponseCode[0] == 100; // @TODO Should come from constant
        $this->setStore($payment->getOrder()->getStoreId());
        $payment->setAmount($amount);
        $payment->setLastTransId($orderId);
        
        if ($isPaymentAccepted) {
            $payment->setStatus(self::STATUS_APPROVED);
        } else {
            $payment->setStatus(self::STATUS_ERROR);
            Mage::throwException("Please check your credit card information.");
        }
        return $this;
    }
}

?>
