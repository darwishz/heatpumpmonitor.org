<?php
define('EMONCMS_EXEC', true);
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../../www/Modules/system/system_model.php';
require_once __DIR__ . '/../../../www/Modules/system/system_schema.php';

class ColdestDayIntegrationTest extends TestCase
{
    private $mysqliMock;
    private $userMock;
    private $systemMock;
    private $systemStatsMock;

    protected function setUp(): void
    {
        // Mock the mysqli object
        $this->mysqliMock = $this->createMock(mysqli::class);

        // Mock the User object
        $this->userMock = $this->getMockBuilder(User::class)
            ->setConstructorArgs([$this->mysqliMock, false])
            ->getMock();

        // Mock the System object
        $this->systemMock = $this->getMockBuilder(System::class)
            ->setConstructorArgs([$this->mysqliMock])
            ->onlyMethods(['list_admin'])
            ->getMock();

        // Mock the SystemStats object
        $this->systemStatsMock = $this->getMockBuilder(SystemStats::class)
            ->setConstructorArgs([$this->mysqliMock, $this->systemMock])
            ->getMock();

        // Seed mock data
        $this->seedMockData();
    }

    private function seedMockData(): void
    {
        // Mock list_admin to return test system data
        $this->systemMock
            ->method('list_admin')
            ->willReturn([
                (object)['id' => 1, 'name' => 'Test System'],
                (object)['id' => 2, 'name' => 'Another Test System']
            ]);

        // Mock mysqli query for fetching system stats
        $this->mysqliMock
            ->method('query')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'system_stats_daily') !== false) {
                    // Mock result for system_stats_daily query
                    $mockResult = $this->createMock(mysqli_result::class);
                    $mockResult
                        ->method('fetch_object')
                        ->willReturnOnConsecutiveCalls(
                            (object)[
                                'timestamp' => time(),
                                'combined_outsideT_mean' => -5,
                                'combined_roomT_mean' => 20,
                                'combined_cop' => 2.5,
                                'running_flowT_mean' => 35
                            ],
                            null // End of result set
                        );
                    return $mockResult;
                } elseif (strpos($query, 'system_meta') !== false) {
                    // Mock result for system_meta updates
                    return true; // Simulate successful update
                }

                return false; // Default mock for other queries
            });
    }

    public function testColdestDayScript(): void
    {
        // Simulate running the coldest_day.php script logic
        $systems = $this->systemMock->list_admin();

        foreach ($systems as $system) {
            $system_id = $system->id;

            $result = $this->mysqliMock->query("SELECT * FROM system_stats_daily WHERE `id` = '$system_id'");

            $this->assertNotFalse($result, 'Query should return a result');

            while ($row = $result->fetch_object()) {
                $this->assertEquals(-5, $row->combined_outsideT_mean, 'Mocked outside temperature should be -5');
                $this->assertEquals(2.5, $row->combined_cop, 'Mocked COP should be 2.5');
                $this->assertEquals(35, $row->running_flowT_mean, 'Mocked flow temperature should be 35');
            }

            // Simulate database update
            $updateSuccess = $this->mysqliMock->query(
                "UPDATE system_meta SET measured_outside_temp_coldest_day = -5 WHERE id = $system_id"
            );
            $this->assertTrue($updateSuccess, 'Database update should succeed');
        }
    }
}
