<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Dian extends BaseConfig
{
    public string $wsdl_url = (ENVIRONMENT === 'production') ? 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?singleWsdl' : 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?singleWsdl';
    public string $catalog_url = (ENVIRONMENT === 'production') ? 'https://catalogo-vpfe.dian.gov.co/' : 'https://catalogo-vpfe-hab.dian.gov.co/';
}