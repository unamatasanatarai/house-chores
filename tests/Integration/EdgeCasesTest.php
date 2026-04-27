<?php

use PHPUnit\Framework\TestCase;

class EdgeCasesTest extends TestCase {
    private $baseUrl = 'http://localhost/api';

    private function getUserId() {
        $res = $this->post('/users/add', ['name' => 'Edge Case User']);
        return $res['data']['id'];
    }

    private function createChore($userId, $title = 'Edge Chore') {
        $res = $this->post('/chores/add', ['title' => $title], $userId);
        return $res['data']['id'];
    }

    public function testValidationMissingTitle() {
        $userId = $this->getUserId();
        $response = $this->post('/chores/add', ['description' => 'No title'], $userId);
        $this->assertFalse($response['success']);
        $this->assertEquals('422_VALIDATION_ERROR', $response['error']['code']);
    }

    public function testValidationEmptyTitle() {
        $userId = $this->getUserId();
        $response = $this->post('/chores/add', ['title' => '   '], $userId);
        $this->assertFalse($response['success']);
        $this->assertEquals('422_VALIDATION_ERROR', $response['error']['code']);
    }

    public function testForbiddenUnclaimByOthers() {
        $user1 = $this->getUserId();
        $user2 = $this->getUserId();
        $choreId = $this->createChore($user1);

        // User 1 claims it
        $this->put("/chores/$choreId/claim", [], $user1);

        // User 2 tries to UNCLAIM it -> Should fail 403
        $response = $this->put("/chores/$choreId/unclaim", [], $user2);
        $this->assertFalse($response['success']);
        $this->assertEquals('403_FORBIDDEN', $response['error']['code']);
    }

    public function testForbiddenDoneByOthers() {
        $user1 = $this->getUserId();
        $user2 = $this->getUserId();
        $choreId = $this->createChore($user1);

        // User 1 claims it
        $this->put("/chores/$choreId/claim", [], $user1);

        // User 2 tries to mark as DONE -> Should fail 403
        $response = $this->put("/chores/$choreId/done", [], $user2);
        $this->assertFalse($response['success']);
        $this->assertEquals('403_FORBIDDEN', $response['error']['code']);
    }

    public function testInvalidActionTransitions() {
        $userId = $this->getUserId();
        $choreId = $this->createChore($userId);

        // 1. Try to mark AVAILABLE chore as DONE (must be claimed first)
        // Note: Currently handlers.php doesn't strictly block this if we don't check status,
        // but the 'Only owner can mark as done' check (claimed_by === userId) will fail 
        // because claimed_by is NULL.
        $response = $this->put("/chores/$choreId/done", [], $userId);
        $this->assertFalse($response['success']);
        $this->assertEquals('403_FORBIDDEN', $response['error']['code']);
    }

    public function testActivityLogEntries() {
        $userId = $this->getUserId();
        $choreId = $this->createChore($userId);

        // Perform sequence: Claim -> Take Over -> Done
        $this->put("/chores/$choreId/claim", [], $userId);
        
        $otherUser = $this->getUserId();
        $this->put("/chores/$choreId/take-over", [], $otherUser);
        $this->put("/chores/$choreId/done", [], $otherUser);

        // Fetch logs
        $response = $this->get("/logs?chore_id=$choreId", $userId);
        $this->assertTrue($response['success']);
        
        $actions = array_column($response['data'], 'action');
        
        // Should have: completed, taken_over, claimed, created (desc order)
        $this->assertContains('completed', $actions);
        $this->assertContains('taken_over', $actions);
        $this->assertContains('claimed', $actions);
        $this->assertContains('created', $actions);
    }

    public function testSpecialCharacters() {
        $userId = $this->getUserId();
        $title = "Chore with Emoji 🚀 & Symbols ' \" ; --";
        $choreId = $this->createChore($userId, $title);

        $response = $this->get("/chores", $userId);
        $chore = null;
        foreach ($response['data'] as $c) {
            if ($c['id'] === $choreId) $chore = $c;
        }
        
        $this->assertNotNull($chore);
        $this->assertEquals($title, $chore['title']);
    }

    public function testMalformedAuthToken() {
        $options = [
            'http' => [
                'header'  => "Authorization: Bearer not-a-uuid\r\n",
                'method'  => 'GET',
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($this->baseUrl . '/chores', false, $context);
        $response = json_decode($result, true);
        
        $this->assertFalse($response['success']);
        $this->assertEquals('401_UNAUTHORIZED', $response['error']['code']);
    }

    // Helper methods
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
