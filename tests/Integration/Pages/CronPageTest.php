<?php

namespace Tests\Integration\Pages;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;


class CronPageTest extends PageTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCronPageBuildsRunLinksWhenNoTaskIsSelected()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpCronStubDirectory();

        try {
            $prof = $this->createPageUser('ist9970', 'Professor Cron', ROLE_PROF);
            $this->setUpPageRuntime('cron', $prof);
            $this->runPage('cron');

            $this->assertCount(2, $GLOBALS['lists']['Run']);
            $this->assertSame('Prune cache', $GLOBALS['lists']['Run'][0]['label']);
            $this->assertSame(
                'index.php?task=prune_cache&page=cron',
                $GLOBALS['lists']['Run'][0]['url']
            );
            $this->assertSame('Update repository information', $GLOBALS['lists']['Run'][1]['label']);
            $this->assertSame(
                'index.php?task=repository&page=cron',
                $GLOBALS['lists']['Run'][1]['url']
            );
            $this->assertSame('', $GLOBALS['monospace']);
            $this->assertNull($GLOBALS['success_message']);
            $this->assertNull($GLOBALS['info_message']);
        } finally {
            $this->tearDownCronStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCronPageShowsContinuationMessageWhenCheckpointIsPresent()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpCronStubDirectory();

        try {
            $prof = $this->createPageUser('ist9971', 'Professor Cron', ROLE_PROF);
            $this->setUpPageRuntime('cron', $prof, 'GET', ['checkpoint' => 15]);
            $this->runPage('cron');

            $this->assertSame('Continuing with offset 15...', $GLOBALS['info_message']);
            $this->assertSame('', $GLOBALS['monospace']);
            $this->assertCount(2, $GLOBALS['lists']['Run']);
        } finally {
            $this->tearDownCronStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCronPageMarksTaskAsDoneAndCapturesOutput()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpCronStubDirectory('success');

        try {
            $prof = $this->createPageUser('ist9972', 'Professor Cron', ROLE_PROF);
            $this->setUpPageRuntime('cron', $prof, 'GET', ['task' => 'repository']);
            $this->runPage('cron');

            $this->assertSame('All done!', $GLOBALS['success_message']);
            $this->assertSame("Running requested cron task\n", $GLOBALS['monospace']);
            $this->assertNull($GLOBALS['refresh_url']);
            $this->assertCount(2, $GLOBALS['lists']['Run']);
        } finally {
            $this->tearDownCronStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCronPageBuildsRefreshUrlWhenCheckpointExceptionIsThrown()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpCronStubDirectory('checkpoint');

        try {
            $prof = $this->createPageUser('ist9973', 'Professor Cron', ROLE_PROF);
            $this->setUpPageRuntime('cron', $prof, 'GET', ['task' => 'repository']);
            $this->runPage('cron');

            $this->assertSame(
                'index.php?task=repository&checkpoint=42&page=cron',
                $GLOBALS['refresh_url']
            );
            $this->assertSame('Will continue with offset 42...', $GLOBALS['info_message']);
            $this->assertSame("Processed chunk before checkpoint\n", $GLOBALS['monospace']);
            $this->assertNull($GLOBALS['success_message']);
        } finally {
            $this->tearDownCronStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    private function setUpCronStubDirectory(string $mode = 'default'): array
    {
        $oldWorkingDirectory = getcwd();
        $stubDir = sys_get_temp_dir() . '/pic1_cron_stubs_' . bin2hex(random_bytes(4));
        mkdir($stubDir);

        $modeExport = var_export($mode, true);
        $code = <<<PHP
<?php
if (!class_exists('CheckPointException', false)) {
    class CheckPointException extends Exception
    {
        public int \$idx;

        public function __construct(int \$idx)
        {
            \$this->idx = \$idx;
            parent::__construct();
        }
    }
}

\$tasks = [
    'prune_cache' => 'Prune cache',
    'repository' => 'Update repository information',
];

\$mode = $modeExport;
if (\$mode === 'success') {
    echo "Running requested cron task\\n";
} elseif (\$mode === 'checkpoint') {
    echo "Processed chunk before checkpoint\\n";
    throw new CheckPointException(42);
}
PHP;

        file_put_contents($stubDir . '/cron.php', $code);
        chdir($stubDir);

        return [$oldWorkingDirectory, $stubDir];
    }

    private function tearDownCronStubDirectory(string|false $oldWorkingDirectory, string $stubDir): void
    {
        if ($oldWorkingDirectory !== false) {
            chdir($oldWorkingDirectory);
        }
        @unlink($stubDir . '/cron.php');
        @rmdir($stubDir);
    }
}
