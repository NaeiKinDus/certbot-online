<?php
declare(strict_types=1);

namespace PounceTech\Providers;

use PounceTech\Models\ResourceRecord;
use Symfony\Component\Process\{
    Process,
    ExecutableFinder
};

class DnsClient
{
    /** @var string */
    private static ?string $digPath = null;
    /** @var string[] */
    private static array $traces = [];

    /**
     * @return string|null
     * @throws \RuntimeException Thrown if dig binary was not found
     */
    public static function getDigPath(): ?string
    {
        if (!self::$digPath) {
            self::$digPath = (new ExecutableFinder())->find('dig', null, ['/usr/bin', '/bin']);
            if (!self::$digPath) {
                throw new \RuntimeException('Dig binary not found');
            }
        }

        return self::$digPath;
    }

    /**
     * @return string[]
     */
    public static function getTraces(): array
    {
        return self::$traces;
    }

    /**
     * @param string $recordName
     * @param string $recordType
     * @param string|null $dnsResolver
     * @return ResourceRecord[]
     */
    public static function getRecord(string $recordName, string $recordType, ?string $dnsResolver = null): array
    {
        $cmdLine = [
            self::getDigPath(),
            '+noall',
            '+answer',
            '+short',
            '-q',
            $recordName,
            '-t',
            $recordType
        ];
        if ($dnsResolver) {
            $cmdLine[] = '@' . $dnsResolver;
        }
        self::$traces[] = 'DIG CMD: ' . json_encode($cmdLine);

        $process = new Process($cmdLine);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Dig call failed. Message: '. $process->getErrorOutput());
        }

        $output = explode(PHP_EOL, trim($process->getOutput()));
        self::$traces[] = 'OUTPUT: ' . json_encode($output);
        $results = [];
        foreach ($output as $line) {
            $rr = new ResourceRecord();
            $rr->name = $recordName;
            $rr->data = trim($line, "\t\n\r\v\0\x0B\"'");
            $rr->type = $recordType;

            $results[] = $rr;
        }
        self::$traces[] = 'RECORD SET: ' . json_encode($results);

        return $results;
    }
}
