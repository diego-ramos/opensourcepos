<?php

namespace App\Libraries;

use App\Models\InvoiceDianQueue;
use App\Models\Sale;
use DOMDocument;
use DOMXPath;

class DianResponseProcessor
{
    public static function processSoapResponse(int $queueId, string $soapResponseXml): bool
    {
        $queue = (new InvoiceDianQueue())->find($queueId);

        if (!$queue) {
            log_message('error', "DIAN Queue ID {$queueId} not found.");
            return false;
        }

        // Parsear XML SOAP
        $dom = new DOMDocument();
        $dom->loadXML($soapResponseXml);
        $xpath = new DOMXPath($dom);

        // Registrar los namespaces típicos de la DIAN
        $xpath->registerNamespace('env', 'http://www.w3.org/2003/05/soap-envelope');
        $xpath->registerNamespace('appresp', 'http://www.dian.gov.co/appresp/');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // CUFE
        $cufeNode = $xpath->query('//appresp:DocumentResponse/appresp:DocumentReference/appresp:UUID')->item(0);
        $cufe = $cufeNode ? $cufeNode->textContent : null;

        // Código y descripción
        $statusCodeNode = $xpath->query('//appresp:Response/appresp:ResponseCode')->item(0);
        $descriptionNode = $xpath->query('//appresp:Response/appresp:Description')->item(0);

        $responseCode = $statusCodeNode ? $statusCodeNode->textContent : null;
        $responseDescription = $descriptionNode ? $descriptionNode->textContent : null;

        // Respuesta en base64 (ApplicationResponse)
        $appResponseNode = $xpath->query('//appresp:ApplicationResponse')->item(0);
        $applicationResponse = $appResponseNode ? $appResponseNode->textContent : null;

        // Determinar estado DIAN
        $dianStatus = (stripos($responseDescription, 'aceptado') !== false) ? 'accepted' : 'rejected';

        $dataToUpdate = [
            'status'                     => 'sent',
            'dian_cufe'                 => $cufe,
            'dian_response_code'        => $responseCode,
            'dian_response_description' => $responseDescription,
            'dian_application_response' => $applicationResponse,
            'dian_status'               => $dianStatus,
            'dian_sent_at'              => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s')
        ];

        (new InvoiceDianQueue())->update($queueId, $dataToUpdate);

        // Guardar CUFE en tabla sales si aplica
        if (!empty($cufe)) {
            $saleModel = new Sale();
            $saleModel->update($queue['sale_id'], ['cufe' => $cufe]);
        }

        return true;
    }

    public static function processError(int $queueId, string $errorMessage): bool
    {
        $queue = (new InvoiceDianQueue())->find($queueId);

        if (!$queue) {
            log_message('error', "DIAN Queue ID {$queueId} not found for error handling.");
            return false;
        }

        $dataToUpdate = [
            'status'        => 'error',
            'error_message' => $errorMessage,
            'dian_sent_at'  => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s')
        ];

        (new InvoiceDianQueue())->update($queueId, $dataToUpdate);

        return true;
    }
}
