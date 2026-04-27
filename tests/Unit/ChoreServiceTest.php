<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../public/api/ChoreService.php';

class ChoreServiceTest extends TestCase {
    private $pdo;
    private $service;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        $this->service = new ChoreService($this->pdo);
    }

    public function testCreateChoreValidatesTitle() {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createChore('user-id', '   ');
    }

    public function testCreateChoreInsertsData() {
        $stmt = $this->createMock(PDOStatement::class);
        
        // Expect two prepares: one for INSERT chore, one for INSERT log
        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->exactly(2))
            ->method('execute')
            ->willReturn(true);

        $result = $this->service->createChore('user-123', 'Test Chore');
        
        $this->assertEquals('Test Chore', $result['title']);
        $this->assertEquals('available', $result['status']);
        $this->assertNotNull($result['id']);
    }

    public function testClaimChoreSuccess() {
        $stmt = $this->createMock(PDOStatement::class);
        
        $this->pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->exactly(2))
            ->method('execute')
            ->willReturn(true);

        // Mock rowCount for the UPDATE statement
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result = $this->service->claimChore('chore-123', 'user-456');
        $this->assertTrue($result);
    }

    public function testClaimChoreFailure() {
        $stmt = $this->createMock(PDOStatement::class);
        
        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $this->expectException(RuntimeException::class);
        $this->service->claimChore('chore-123', 'user-456');
    }
}
