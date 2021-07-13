<?php

include_once(_PS_MODULE_DIR_ . 'ps_hesabfa/services/LogService.php');
include_once(_PS_MODULE_DIR_ . 'ps_hesabfa/services/SettingsService.php');
include_once(_PS_MODULE_DIR_ . 'ps_hesabfa/services/PsFaService.php');
include_once(_PS_MODULE_DIR_ . 'ps_hesabfa/services/HesabfaApiService.php');

class CustomerService
{
    public $idLang;

    public function __construct($idDefaultLang)
    {
        $this->idLang = $idDefaultLang;
    }

    public function saveCustomer($customerId, $addressId = 0)
    {
        LogService::writeLogStr("===save customer, customer id: " . $customerId);
        if (!isset($customerId))
            return false;
        $customer = new Customer($customerId);
        $hesabfaCustomer = $this->mapCustomer($customer, $customerId, false, $addressId);
        return $this->saveCustomerToHesabfa($hesabfaCustomer);;
    }

    public function saveCustomerToHesabfa($hesabfaCustomer)
    {
        $hesabfa = new HesabfaApiService(new SettingService());
        $response = $hesabfa->contactSave($hesabfaCustomer);

        if ($response->Success) {
            $psFaService = new PsFaService();
            $psFaService->saveCustomer($response->Result);
            return true;
        } else {
            LogService::writeLogStr("Cannot add/update Hesabfa customer. Error Code: " . (string)$response->ErrorCode . ". Error Message: $response->ErrorMessage.");
            return false;
        }
    }

    private function mapCustomer($customer, $id, $new = true, $addressId = 0)
    {
        $psFaService = new PsFaService();
        $code = $new ? null : $psFaService->getCustomerCodeByPrestaId($id);

        $name = $customer->firstname . ' ' . $customer->lastname;
        if (empty($customer->firstname) && empty($customer->lastname)) {
            $name = 'Guest Customer';
        }

        $address = null;
        $PostalCode = '';
        $state = '';
        $country = '';
        if ($addressId > 0) {
            $address = new Address($addressId);
            $PostalCode = mb_substr(preg_replace("/[^0-9]/", '', $address->postcode), 0, 9);
            $state = State::getNameById($address->id_state) == false ? null : State::getNameById($address->id_state);
            $country = Country::getNameById($this->idLang, $address->id_country) == false ? null : Country::getNameById($this->idLang, $address->id_country);
        }

        $settingService = new SettingService();

        return array(
            'Code' => $code,
            'Name' => $name,
            'FirstName' => $customer->firstname,
            'LastName' => $customer->lastname,
            'ContactType' => 1,
            'NodeFamily' => 'اشخاص :' . $settingService->getCustomersCategory(),
            'Email' => $this->validEmail($customer->email) ? $customer->email : null,
            'Tag' => json_encode(array('id_customer' => $id)),
            'Active' => $customer->active ? true : false,
            'Note' => 'Customer ID in OnlineStore: ' . $id,

            'NationalCode' => $address != null ? $address->dni : '',
            'EconomicCode' => $address != null ? $address->vat_number : '',
            'Address' => $address != null ? $address->address1 . ' ' . $address->address2 : '',
            'City' => $address != null ? $address->city : '',
            'State' => $state,
            'Country' => $country,
            'PostalCode' => $PostalCode,
            'Phone' => $address != null ? preg_replace("/[^0-9]/", "", $address->phone) : '',
            'Mobile' => $address != null ? preg_replace("/[^0-9]/", "", $address->phone_mobile) : ''
        );
    }

    public function validEmail($email)
    {
        $isValid = true;
        $atIndex = strrpos($email, "@");
        if (is_bool($atIndex) && !$atIndex) {
            $isValid = false;
        } else {
            $domain = Tools::substr($email, $atIndex + 1);
            $local = Tools::substr($email, 0, $atIndex);
            $localLen = Tools::strlen($local);
            $domainLen = Tools::strlen($domain);
            if ($localLen < 1 || $localLen > 64) {
                // local part length exceeded
                $isValid = false;
            } else if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
                // local part starts or ends with '.'
                $isValid = false;
            } else if (preg_match('/\\.\\./', $local)) {
                // local part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                // character not valid in domain part
                $isValid = false;
            } else if (preg_match('/\\.\\./', $domain)) {
                // domain part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) {
                    $isValid = false;
                }
            }
        }
        return $isValid;
    }

    public function deleteCustomer($customerId)
    {
        $psFaService = new PsFaService();
        $psFaObject = $psFaService->getPsFa('customer', $customerId);
        if($psFaObject == null)
            return;

        $hesabfaApi = new HesabfaApiService(new SettingService());
        $response = $hesabfaApi->contactDelete($psFaObject->idHesabfa);
        if ($response->Success) {
            $msg = "Customer successfully deleted, customer Hesabfa code: " . $psFaObject->idHesabfa . ", customer Prestashop id: " . $psFaObject->idPs;
            LogService::writeLogStr($msg);
        } else {
            $msg = 'Cannot delete customer in Hesabfa.Error Code: ' . $response->ErrorCode . ', Error Message: ' . $response->ErrorMessage . ', customer Hesabfa code: ' . $psFaObject->idHesabfa . ", customer Prestashop id: " . $psFaObject->idPs;
            LogService::writeLogStr($msg);
        }

        $psFaService->delete($psFaObject);
    }

}