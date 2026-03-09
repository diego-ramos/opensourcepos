<?php

namespace Tests\App\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\DianResponseProcessor;

/**
 * Tests for DianResponseProcessor::processSoapResponse()
 *
 * Assumes the 'tests' database group (ospos_test) already has the full
 * OSPOS schema loaded. FK checks are disabled during setUp so we can insert
 * minimal rows without needing all parent records.
 */
class DianResponseProcessorTest extends CIUnitTestCase
{
    protected int $queueId;
    protected int $saleId;

    /**
     * SOAP XML for a successfully accepted invoice.
     * - b:StatusCode    = 00
     * - b:StatusDescription = "Procesado Correctamente."
     * - b:XmlDocumentKey   = CUFE hash
     * - b:XmlBase64Bytes   = ApplicationResponse (base64)
     */
    protected string $acceptedSoapXml = <<<XML
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:a="http://www.w3.org/2005/08/addressing">
<s:Body>
<SendBillSyncResponse xmlns="http://wcf.dian.colombia">
<SendBillSyncResult xmlns:b="http://schemas.datacontract.org/2004/07/DianResponse" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
<b:ErrorMessage xmlns:c="http://schemas.microsoft.com/2003/10/Serialization/Arrays"/>
<b:IsValid>true</b:IsValid>
<b:StatusCode>00</b:StatusCode>
<b:StatusDescription>Procesado Correctamente.</b:StatusDescription>
<b:StatusMessage>La Factura electrónica SETP990000007, ha sido autorizada.</b:StatusMessage>
<b:XmlBase64Bytes>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiIHN0YW5kYWxvbmU9Im5vIj8+PEFwcGxpY2F0aW9uUmVzcG9uc2UgeG1sbnM9InVybjpvYXNpczpuYW1lczpzcGVjaWZpY2F0aW9uOnVibDpzY2hlbWE6eHNkOkFwcGxpY2F0aW9uUmVzcG9uc2UtMiI+PC9BcHBsaWNhdGlvblJlc3BvbnNlPg==</b:XmlBase64Bytes>
<b:XmlBytes i:nil="true"/>
<b:XmlDocumentKey>29c4084126ff1afb3b96f4039227953062de95a7f0485fec5626f43fa3a9308f5f4c2ec7610436b242e4840830494b6f</b:XmlDocumentKey>
<b:XmlFileName>29c4084126ff1afb3b96f4039227953062de95a7f0485fec5626f43fa3a9308f5f4c2ec7610436b242e4840830494b6f</b:XmlFileName>
</SendBillSyncResult>
</SendBillSyncResponse>
</s:Body>
</s:Envelope>
XML;

    // ------------------------------------------------------------------
    // Setup / Teardown
    // ------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect('tests');

        // Disable FK checks for the whole setup
        $db->query('SET FOREIGN_KEY_CHECKS=0');

        // Ensure ospos_sales has a cufe column (added by electronic invoicing feature)
        $hasCufe = $db->query("SHOW COLUMNS FROM `ospos_sales` LIKE 'cufe'")->getResultArray();
        if (empty($hasCufe)) {
            $db->query("ALTER TABLE `ospos_sales` ADD COLUMN `cufe` VARCHAR(200) NULL");
        }

        // Insert a minimal sale row using only confirmed columns
        $db->query("INSERT INTO `ospos_sales` (`sale_time`, `employee_id`, `customer_id`, `comment`)
                    VALUES (NOW(), 0, NULL, 'Unit test sale - DianResponseProcessorTest')");
        $this->saleId = $db->insertID();

        // Insert a queue entry referencing that sale
        $db->query("INSERT INTO `ospos_invoices_dian_queue` (`sale_id`, `status`, `created_at`, `updated_at`)
                    VALUES ({$this->saleId}, 'pending', NOW(), NOW())");
        $this->queueId = $db->insertID();

        $db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests');
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $db->query("DELETE FROM `ospos_invoices_dian_queue` WHERE `id` = {$this->queueId}");
        $db->query("DELETE FROM `ospos_sales` WHERE `sale_id` = {$this->saleId}");
        $db->query('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    /**
     * @test
     * An accepted DIAN response (StatusCode=00) should set queue status to
     * 'sent' and dian_status to 'accepted'.
     */
    public function testProcessSoapResponseSetsStatusSentOnAcceptedInvoice(): void
    {
        $result = DianResponseProcessor::processSoapResponse($this->queueId, $this->acceptedSoapXml);

        $this->assertTrue($result, 'processSoapResponse should return true for a valid queue ID');

        $db  = \Config\Database::connect('tests');
        $row = $db->table('ospos_invoices_dian_queue')->where('id', $this->queueId)->get()->getRowArray();

        $this->assertEquals('sent',     $row['status'],     'Queue status should be "sent"');
        $this->assertEquals('accepted', $row['dian_status'], 'DIAN status should be "accepted"');
    }

    /**
     * @test
     * Status code and human-readable description from b:StatusCode /
     * b:StatusDescription are persisted to the queue table.
     */
    public function testProcessSoapResponsePersistsResponseCodeAndDescription(): void
    {
        DianResponseProcessor::processSoapResponse($this->queueId, $this->acceptedSoapXml);

        $db  = \Config\Database::connect('tests');
        $row = $db->table('ospos_invoices_dian_queue')->where('id', $this->queueId)->get()->getRowArray();

        $this->assertEquals('00', $row['dian_response_code'], 'Status code should be 00');
        $this->assertEquals('Procesado Correctamente.', $row['dian_response_description'], 'Status description mismatch');
    }

    /**
     * @test
     * CUFE is extracted from <b:XmlDocumentKey> and stored in both
     * ospos_invoices_dian_queue.dian_cufe and ospos_sales.cufe.
     */
    public function testProcessSoapResponseExtractsCufeFromXmlDocumentKey(): void
    {
        $expectedCufe = '29c4084126ff1afb3b96f4039227953062de95a7f0485fec5626f43fa3a9308f5f4c2ec7610436b242e4840830494b6f';

        DianResponseProcessor::processSoapResponse($this->queueId, $this->acceptedSoapXml);

        $db       = \Config\Database::connect('tests');
        $queueRow = $db->table('ospos_invoices_dian_queue')->where('id', $this->queueId)->get()->getRowArray();
        $saleRow  = $db->table('ospos_sales')->where('sale_id', $this->saleId)->get()->getRowArray();

        $this->assertEquals($expectedCufe, $queueRow['dian_cufe'], 'CUFE should be saved in queue table');
        $this->assertEquals($expectedCufe, $saleRow['cufe'],        'CUFE should be propagated to sales table');
    }

    /**
     * @test
     * ApplicationResponse (base64) is extracted from <b:XmlBase64Bytes>
     * and stored in dian_application_response.
     */
    public function testProcessSoapResponseExtractsApplicationResponseFromXmlBase64Bytes(): void
    {
        DianResponseProcessor::processSoapResponse($this->queueId, $this->acceptedSoapXml);

        $db  = \Config\Database::connect('tests');
        $row = $db->table('ospos_invoices_dian_queue')->where('id', $this->queueId)->get()->getRowArray();

        $this->assertNotEmpty($row['dian_application_response'], 'ApplicationResponse should not be empty');
        $this->assertStringStartsWith(
            'PD94bWwg',
            $row['dian_application_response'],
            'ApplicationResponse should start with the expected base64 prefix'
        );
    }

    /**
     * @test
     * A non-existent queue ID returns false without throwing.
     */
    public function testProcessSoapResponseReturnsFalseForUnknownQueueId(): void
    {
        $result = DianResponseProcessor::processSoapResponse(999999, $this->acceptedSoapXml);
        $this->assertFalse($result, 'Should return false when queue record is not found');
    }

    /**
     * @test
     * A rejected DIAN response (IsValid=false) sets status='error',
     * dian_status='rejected', and persists error rule codes from the
     * b:ErrorMessage/c:string array.
     */
    public function testProcessSoapResponseSetsStatusErrorOnRejectedInvoice(): void
    {
        $rejectedXml = <<<XML
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Body>
<SendBillSyncResponse xmlns="http://wcf.dian.colombia">
<SendBillSyncResult xmlns:b="http://schemas.datacontract.org/2004/07/DianResponse" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
<b:ErrorMessage xmlns:c="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
<c:string>Regla: ZE02, Rechazo: Política de firma inválida.</c:string>
</b:ErrorMessage>
<b:IsValid>false</b:IsValid>
<b:StatusCode>99</b:StatusCode>
<b:StatusDescription>Rechazado</b:StatusDescription>
<b:StatusMessage>El documento fue rechazado.</b:StatusMessage>
<b:XmlBase64Bytes i:nil="true"/>
<b:XmlDocumentKey i:nil="true"/>
<b:XmlFileName i:nil="true"/>
</SendBillSyncResult>
</SendBillSyncResponse>
</s:Body>
</s:Envelope>
XML;

        DianResponseProcessor::processSoapResponse($this->queueId, $rejectedXml);

        $db  = \Config\Database::connect('tests');
        $row = $db->table('ospos_invoices_dian_queue')->where('id', $this->queueId)->get()->getRowArray();

        $this->assertEquals('error',    $row['status'],      'Queue status should be "error" for rejected invoice');
        $this->assertEquals('rejected', $row['dian_status'], 'DIAN status should be "rejected"');
        $this->assertStringContainsString(
            'ZE02',
            $row['error_message'],
            'Error message should contain the DIAN rule code'
        );
    }
}
