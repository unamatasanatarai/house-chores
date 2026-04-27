<?php

use PHPUnit\Framework\TestCase;

if (!defined('PHPUNIT_RUNNING')) define('PHPUNIT_RUNNING', true);
require_once __DIR__ . '/../../public/api/Response.php';

class ResponseTest extends TestCase {
    public function testBuildSuccess() {
        $data = ['foo' => 'bar'];
        $meta = ['total' => 10];
        
        $payload = Response::buildSuccess($data, $meta);
        
        $this->assertTrue($payload['success']);
        $this->assertEquals($data, $payload['data']);
        $this->assertEquals($meta, $payload['meta']);
    }

    public function testBuildError() {
        $code = 'TEST_ERROR';
        $message = 'Test message';
        $details = ['key' => 'val'];
        
        $payload = Response::buildError($code, $message, $details);
        
        $this->assertFalse($payload['success']);
        $this->assertEquals($code, $payload['error']['code']);
        $this->assertEquals($message, $payload['error']['message']);
        $this->assertEquals($details, $payload['error']['details']);
    }

    public function testSuccessWithDefaults() {
        $payload = Response::buildSuccess();
        $this->assertTrue($payload['success']);
        $this->assertEquals([], $payload['data']);
        $this->assertEquals([], $payload['meta']);
    }
}
