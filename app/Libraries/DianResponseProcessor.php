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

        // Código y descripción
        $statusCodeNode = $xpath->query('//appresp:Response/appresp:ResponseCode')->item(0);
        $descriptionNode = $xpath->query('//appresp:Response/appresp:Description')->item(0);

        // Fallback para SendBillSyncResponse
        $xpath->registerNamespace('b', 'http://schemas.datacontract.org/2004/07/DianResponse');
        if (!$cufeNode) {
            $cufeNode = $xpath->query('//b:XmlDocumentKey')->item(0);
        }
        if (!$statusCodeNode) {
            $statusCodeNode = $xpath->query('//b:StatusCode')->item(0);
        }
        if (!$descriptionNode) {
            $descriptionNode = $xpath->query('//b:StatusDescription')->item(0);
        }

        $responseCode = $statusCodeNode ? $statusCodeNode->textContent : null;
        $responseDescription = $descriptionNode ? $descriptionNode->textContent : null;
        $cufe = $cufeNode ? $cufeNode->textContent : null;

        // Respuesta en base64 (ApplicationResponse)
        $appResponseNode = $xpath->query('//appresp:ApplicationResponse')->item(0);
        if (!$appResponseNode) {
            $appResponseNode = $xpath->query('//b:XmlBase64Bytes')->item(0);
        }
        $applicationResponse = $appResponseNode ? $appResponseNode->textContent : null;

        // Determinar estado DIAN
        $dianStatus = (stripos($responseDescription ?: '', 'aceptado') !== false || stripos($responseDescription ?: '', 'procesado') !== false) ? 'accepted' : 'rejected';

        $errorMessage = null;
        if ($dianStatus !== 'accepted') {
            $xpath->registerNamespace('c', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');
            $errorNodes = $xpath->query('//b:ErrorMessage/c:string');
            if ($errorNodes && $errorNodes->length > 0) {
                $errors = [];
                foreach ($errorNodes as $node) {
                    $errors[] = $node->nodeValue;
                }
                $errorMessage = implode(' | ', $errors);
            }
            
            if (!$errorMessage) {
                $statusMsgNode = $xpath->query('//b:StatusMessage')->item(0);
                if ($statusMsgNode) {
                    $errorMessage = $statusMsgNode->nodeValue;
                }
            }
        }

        $dataToUpdate = [
            'status'                     => $dianStatus === 'accepted' ? 'sent' : 'error',
            'dian_cufe'                 => $cufe,
            'dian_response_code'        => $responseCode,
            'dian_response_description' => $responseDescription,
            'dian_application_response' => $applicationResponse,
            'dian_status'               => $dianStatus,
            'error_message'             => $errorMessage,
            'dian_sent_at'              => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s')
        ];

        (new InvoiceDianQueue())->update($queueId, $dataToUpdate);

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
