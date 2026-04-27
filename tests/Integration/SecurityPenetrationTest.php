<?php

use PHPUnit\Framework\TestCase;

class SecurityPenetrationTest extends TestCase {
    private $baseUrl = 'http://localhost/api';

    private function getUserId() {
        $res = $this->post('/users/add', ['name' => 'Hacker User']);
        return $res['data']['id'];
    }

    public function testSqlInjectionInStatusFilter() {
        $userId = $this->getUserId();
        
        // Attacking the status filter which is used in COUNT(*) query
        // Payload: ' OR 1=1 --
        $payload = "' OR 1=1 --";
        $encodedPayload = urlencode($payload);
        
        $response = $this->get("/chores?status=$encodedPayload", $userId);
        
        // After fix, the 'total' should be 0 because there is no chore with status "' OR 1=1 --"
        // If it was vulnerable, it would return the total of ALL chores.
        $this->assertEquals(0, $response['meta']['total'], "SQL Injection vulnerability: Malicious payload was executed instead of being treated as a string.");
    }

    public function testUuidFormatEnforcement() {
        $userId = $this->getUserId();
        
        // Trying to inject into the chore ID parameter
        // Regex /^\/chores\/([a-f\d\-]{36})\/.../ should block this
        $response = $this->put("/chores/NOT-A-UUID-';-DROP-TABLE-chores/claim", [], $userId);
        
        $this->assertFalse($response['success'], "Should not allow malformed UUIDs");
        $this->assertEquals('404_NOT_FOUND', $response['error']['code']);
    }

    public function testLargePayload() {
        $userId = $this->getUserId();
        
        // 1MB of text
        $largeTitle = str_repeat('A', 1024 * 1024);
        $response = $this->post('/chores/add', ['title' => $largeTitle], $userId);
        
        // PHP might hit memory limits or MySQL might hit max_allowed_packet
        // A robust API should handle this gracefully (either save it or return 413)
        if (!$response['success']) {
            $this->assertContains($response['error']['code'], ['500_INTERNAL_ERROR', '413_PAYLOAD_TOO_LARGE']);
        }
    }

    public function testInvalidJson() {
        $userId = $this->getUserId();
        
        $headers = "Content-type: application/json\r\nAuthorization: Bearer $userId\r\n";
        $options = [
            'http' => [
                'header'  => $headers,
                'method'  => 'POST',
                'content' => "{ 'invalid': json, }", // Malformed JSON
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($this->baseUrl . '/chores/add', false, $context);
        $response = json_decode($result, true);
        
        // Should not crash the server
        $this->assertFalse($response['success']);
    }

    // Helpers
    private function post($endpoint, $data, $token = null) {
        return $this->request('POST', $endpoint, $data, $token);
    }

    private function get($endpoint, $token = null) {
        return $this->request('GET', $endpoint, null, $token);
    }

    private function put($endpoint, $data, $token = null) {
        return $this->request('PUT', $endpoint, $data, $token);
    }

    private function request($method, $endpoint, $data = null, $token = null) {
        $headers = "Content-type: application/json\r\n";
        if ($token) {
            $headers .= "Authorization: Bearer $token\r\n";
        }
        $options = [
            'http' => [
                'header'  => $headers,
                'method'  => $method,
                'content' => $data ? json_encode($data) : null,
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $url = $this->baseUrl . $endpoint;
        if ($method === 'GET') {
            $separator = strpos($url, '?') === false ? '?' : '&';
            $url .= $separator . 'limit=1000';
        }
        $result = @file_get_contents($url, false, $context);
        return json_decode($result, true);
    }
}
