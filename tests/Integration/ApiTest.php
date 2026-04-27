<?php

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
    private $baseUrl = 'http://localhost/api';

    public function testUserCreation() {
        $data = ['name' => 'Test User'];
        $response = $this->post('/users/add', $data);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('Test User', $response['data']['name']);
        $this->assertNotNull($response['data']['id']);
        
        return $response['data']['id'];
    }

    /**
     * @depends testUserCreation
     */
    public function testGetChoresRequiresAuth($userId) {
        $response = $this->get('/chores'); // No token
        $this->assertFalse($response['success']);
        $this->assertEquals('401_UNAUTHORIZED', $response['error']['code']);
    }

    /**
     * @depends testUserCreation
     */
    public function testGetChoresWithAuth($userId) {
        $response = $this->get('/chores', $userId);
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    private function post($endpoint, $data) {
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($this->baseUrl . $endpoint, false, $context);
        return json_decode($result, true);
    }

    private function get($endpoint, $token = null) {
        $headers = "Content-type: application/json\r\n";
        if ($token) {
            $headers .= "Authorization: Bearer $token\r\n";
        }
        $options = [
            'http' => [
                'header'  => $headers,
                'method'  => 'GET',
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($this->baseUrl . $endpoint, false, $context);
        return json_decode($result, true);
    }
}
