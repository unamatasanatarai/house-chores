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

    /**
     * @depends testUserCreation
     */
    public function testChoreLifecycle($userId) {
        // 1. Create a chore
        $choreData = ['title' => 'Lifecycle Test Chore'];
        $response = $this->post('/chores/add', $choreData, $userId);
        $this->assertTrue($response['success']);
        $choreId = $response['data']['id'];
        $this->assertEquals('available', $response['data']['status']);

        // 2. Verify it shows as available in the list
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertNotNull($chore);
        $this->assertEquals('available', $chore['status']);

        // 3. Claim the chore
        $response = $this->put("/chores/$choreId/claim", [], $userId);
        $this->assertTrue($response['success']);
        $this->assertEquals('claimed', $response['data']['new_status']);

        // 4. Verify it now shows as claimed in the list
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertEquals('claimed', $chore['status']);
        $this->assertEquals($userId, $chore['claimed_by']);

        // 5. Unclaim the chore
        $response = $this->put("/chores/$choreId/unclaim", [], $userId);
        $this->assertTrue($response['success']);
        $this->assertEquals('available', $response['data']['new_status']);

        // 6. Verify it's back to available
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertEquals('available', $chore['status']);
        $this->assertNull($chore['claimed_by']);

        // 7. Claim and then Complete
        $this->put("/chores/$choreId/claim", [], $userId);
        $response = $this->put("/chores/$choreId/done", [], $userId);
        $this->assertTrue($response['success']);
        $this->assertEquals('completed', $response['data']['new_status']);

        // 8. Verify it's completed
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertEquals('completed', $chore['status']);

        // 9. Archive it
        $response = $this->put("/chores/$choreId/archive", [], $userId);
        $this->assertTrue($response['success']);
        $this->assertEquals('archived', $response['data']['new_status']);

        // 10. Verify it's gone from the default list (which excludes archived)
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertNull($chore);
    }

    /**
     * @depends testUserCreation
     */
    public function testUnarchiveClearsClaim($userId) {
        // 1. Create and claim a chore
        $choreData = ['title' => 'Unarchive Test'];
        $res = $this->post('/chores/add', $choreData, $userId);
        $choreId = $res['data']['id'];
        $this->put("/chores/$choreId/claim", [], $userId);

        // 2. Archive it
        $this->put("/chores/$choreId/archive", [], $userId);

        // 3. Unarchive it
        $this->put("/chores/$choreId/unarchive", [], $userId);

        // 4. Verify it's available and has NO claimed_by
        $response = $this->get('/chores', $userId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertEquals('available', $chore['status']);
        $this->assertNull($chore['claimed_by'], "claimed_by should be NULL after unarchive");
    }

    /**
     * @depends testUserCreation
     */
    public function testClaimConflict($userId) {
        // 1. Create a chore
        $res = $this->post('/chores/add', ['title' => 'Conflict Test'], $userId);
        $choreId = $res['data']['id'];

        // 2. Create another user
        $res = $this->post('/users/add', ['name' => 'Other User']);
        $otherUserId = $res['data']['id'];

        // 3. User 1 claims it
        $this->put("/chores/$choreId/claim", [], $userId);

        // 4. User 2 tries to claim it -> Expect 409
        $response = $this->put("/chores/$choreId/claim", [], $otherUserId);
        $this->assertFalse($response['success']);
        $this->assertEquals('409_CONFLICT_ALREADY_CLAIMED', $response['error']['code']);
    }

    /**
     * @depends testUserCreation
     */
    public function testTakeOver($userId) {
        // 1. Create a chore and claim it
        $res = $this->post('/chores/add', ['title' => 'Takeover Test'], $userId);
        $choreId = $res['data']['id'];
        $this->put("/chores/$choreId/claim", [], $userId);

        // 2. Another user takes it over
        $res = $this->post('/users/add', ['name' => 'Takeover User']);
        $otherUserId = $res['data']['id'];
        
        $response = $this->put("/chores/$choreId/take-over", [], $otherUserId);
        $this->assertTrue($response['success']);
        
        // 3. Verify status and ownership
        $response = $this->get('/chores', $otherUserId);
        $chore = $this->findChore($response['data'], $choreId);
        $this->assertEquals('claimed', $chore['status']);
        $this->assertEquals($otherUserId, $chore['claimed_by']);
    }

    private function findChore($chores, $id) {
        foreach ($chores as $chore) {
            if ($chore['id'] === $id) return $chore;
        }
        return null;
    }

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
        $result = file_get_contents($this->baseUrl . $endpoint, false, $context);
        return json_decode($result, true);
    }
}
