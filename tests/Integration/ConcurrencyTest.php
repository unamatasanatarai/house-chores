<?php

use PHPUnit\Framework\TestCase;

class ConcurrencyTest extends TestCase {
    private $baseUrl = 'http://localhost/api';

    public function testSimultaneousClaim() {
        // 1. Setup: Create a chore and two users
        $user1Res = $this->post('/users/add', ['name' => 'Race User 1']);
        $user2Res = $this->post('/users/add', ['name' => 'Race User 2']);
        $user1Id = $user1Res['data']['id'];
        $user2Id = $user2Res['data']['id'];

        $choreRes = $this->post('/chores/add', ['title' => 'Race Condition Chore'], $user1Id);
        $choreId = $choreRes['data']['id'];

        // 2. Prepare concurrent requests
        $urls = [
            ['url' => "/chores/$choreId/claim", 'userId' => $user1Id],
            ['url' => "/chores/$choreId/claim", 'userId' => $user2Id]
        ];

        $mh = curl_multi_init();
        $handles = [];

        foreach ($urls as $i => $item) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $item['url']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $item['userId']
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        // 3. Execute simultaneously
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM || $active);

        // 4. Collect results
        $responses = [];
        foreach ($handles as $ch) {
            $responses[] = json_decode(curl_multi_getcontent($ch), true);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        // 5. Assertions: Exactly one should succeed, one should fail with 409
        $successCount = 0;
        $conflictCount = 0;

        foreach ($responses as $resp) {
            if ($resp['success']) {
                $successCount++;
            } else if ($resp['error']['code'] === '409_CONFLICT_ALREADY_CLAIMED') {
                $conflictCount++;
            }
        }

        $this->assertEquals(1, $successCount, "Exactly one user must successfully claim the chore.");
        $this->assertEquals(1, $conflictCount, "The other user must receive a 409 Conflict.");
    }

    private function post($endpoint, $data, $token = null) {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers = ['Content-Type: application/json'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}
