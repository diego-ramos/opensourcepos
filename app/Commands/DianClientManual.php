<?php
namespace App\Commands;

class DianClientManual
{
    private string $certPath;
    private string $certPass;
    private \SoapClient $client;
    private array $contextOptions;
    
    public function __construct(string $certPath, string $certPass)
    {
        $this->certPath = $certPath;
        $this->certPass = $certPass;
        
        // SSL stream context for mTLS
        $this->contextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'local_cert' => $this->certPath,
                'passphrase' => $this->certPass,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
            ]
        ];
        
        // WSDL-less SoapClient
        $this->client = new \SoapClient(
            null,
            [
                'location' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                'uri' => 'http://wcf.dian.colombia/',
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'exceptions' => true,
                'stream_context' => stream_context_create($this->contextOptions),
            ]
            );
    }
    
    /**
     * Send a manual SOAP request
     */
    public function sendRequest(string $soapAction, string $xml): string
    {
        try {
            $response = $this->client->__doRequest(
                $xml,
                'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                $soapAction,
                SOAP_1_2
                );
            
            if ($response === null) {
                throw new \Exception("SOAP returned null (likely TLS/client certificate issue).");
            }
            
            return $response;
        } catch (\SoapFault $e) {
            throw new \Exception("SOAP Fault: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
    
    
    
    /**
     * Example: GetStatus
     */
    public function getStatus(string $trackId): string
    {
        $xml = <<<XML
<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://wcf.dian.colombia/">
  <soap12:Body>
    <ns:GetStatus>
      <ns:trackId>{$trackId}</ns:trackId>
    </ns:GetStatus>
  </soap12:Body>
</soap12:Envelope>
XML;
        
        return $this->sendRequest('http://wcf.dian.colombia/IService/GetStatus', $xml);
    }
    
    /**
     * Example: SendBillSync
     */
    public function sendBillSync(string $fileName, string $content): string
    {
        $xml = <<<XML
<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://wcf.dian.colombia/">
  <soap12:Body>
    <ns:SendBillSync>
      <ns:fileName>{$fileName}</ns:fileName>
      <ns:content>{$content}</ns:content>
    </ns:SendBillSync>
  </soap12:Body>
</soap12:Envelope>
XML;
        
        return $this->sendRequest('http://wcf.dian.colombia/IService/SendBillSync', $xml);
    }
    
    /**
     * Debug last request/response
     */
    public function getLastRequest(): string
    {
        return $this->client->__getLastRequest();
    }
    
    public function getLastResponse(): string
    {
        return $this->client->__getLastResponse();
    }
}
