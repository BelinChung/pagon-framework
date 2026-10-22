<?php

use Pagon\Logger;

class LoggerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Logger
     */
    protected $logger;

    public function setUp(): void
    {
        $this->logger = new Logger();
    }

    public function testVariables()
    {
        $token = $this->logger->token;
        $this->assertEquals(6, strlen($token));

        $new_token = $this->logger->token;
        $this->assertEquals($token, $new_token);
    }
}
