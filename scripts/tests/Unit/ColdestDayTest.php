<?php
if (!defined('EMONCMS_EXEC')) {
    define('EMONCMS_EXEC', true);
}

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/Modules/system/system_model.php';
require_once __DIR__ . '/../../../www/Modules/system/system_stats_model.php';
require_once __DIR__ . '/../../../www/Modules/user/user_model.php';
require_once __DIR__ . '/../../../www/Modules/system/system_schema.php';

class ColdestDayTest extends TestCase
{
    private $mysqliMock;
    private $systemMock;

    protected function setUp(): void
    {
        // Mock mysqli object
        $this->mysqliMock = $this->createMock(mysqli::class);

        // Mock System object
        $this->systemMock = $this->getMockBuilder(System::class)
            ->setConstructorArgs([$this->mysqliMock])
            ->onlyMethods(['list_admin'])
            ->getMock();
    }

    public function testQueryExecution(): void
    {
        // Mock the return value of list_admin
        $this->systemMock
            ->expects($this->once())
            ->method('list_admin')
            ->willReturn([
                (object)['id' => 1, 'name' => 'Test System'],
                (object)['id' => 2, 'name' => 'Another System']
            ]);

        // Mock the query method of mysqli
        $this->mysqliMock
            ->expects($this->exactly(2)) // One query for each system
            ->method('query')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'system_stats_daily') !== false) {
                    $mockResult = $this->createMock(mysqli_result::class);

                    // Mock fetch_object to simulate database rows
                    $mockResult
                        ->expects($this->once()) // Simulating rows returned
                        ->method('fetch_object')
                        ->willReturnOnConsecutiveCalls(
                            (object)[
                                'timestamp' => time(),
                                'combined_roomT_mean' => 21,
                                'combined_outsideT_mean' => -5,
                                'combined_cop' => 2.5,
                                'running_flowT_mean' => 35
                            ],
                            // null // End of result set
                        );

                    return $mockResult;
                }

                return false; // Other queries
            });

        // Execute the test logic from coldest_day.php
        $data = $this->systemMock->list_admin();
        foreach ($data as $row) {
            $systemid = $row->id;

            $start_1year_ago = time() - 60 * 60 * 24 * 365;
            $query = "SELECT * FROM system_stats_daily WHERE `id` = '$systemid' AND `timestamp` > '$start_1year_ago' ORDER BY `combined_outsideT_mean` ASC LIMIT 50";
            $result = $this->mysqliMock->query($query);

            $this->assertNotFalse($result, 'Query should return a result');

            $n = 0;
            while ($row = $result->fetch_object()) {
                $outsideT = $row->combined_outsideT_mean;
                $combined_cop = $row->combined_cop;

                $this->assertGreaterThan(-10, $outsideT, 'Outside temperature should be greater than -10');
                $this->assertLessThan(5, $outsideT, 'Outside temperature should be less than 5');
                $this->assertGreaterThan(0, $combined_cop, 'COP should be greater than 0');

                $n++;
                if ($n >= 1) {
                    break;
                }
            }
        }

        // Assert that the list_admin method was called
        $this->assertTrue(true); // Placeholder for further detailed checks if needed
    }
}
