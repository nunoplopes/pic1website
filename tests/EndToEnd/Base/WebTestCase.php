<?php

namespace Tests\EndToEnd\Base;

use Symfony\Component\HttpClient\HttpClient;
use Tests\Unit\Base\UnitTestCase;

/**
 * Base class for end-to-end tests that run the full web application
 * 
 * Provides isolated app environment with web server and real database
 */
abstract class WebTestCase extends UnitTestCase
{
    protected static $serverProcess;
    protected static string $appRoot;
    protected static string $baseUrl;
    protected static string $dbPath;
    protected static array $serverPipes = [];
    protected static string $githubPrependFile;
    protected static string $serverLogPath;
    protected static int $checkedServerLogBytes = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$appRoot = sys_get_temp_dir() . '/pic1_e2e_' . bin2hex(random_bytes(4));
        self::$dbPath = self::$appRoot . '/db.sqlite';
        self::$serverLogPath = self::$appRoot . '/server.log';
        self::$checkedServerLogBytes = 0;

        try {
            self::createIsolatedAppRoot();
            self::$baseUrl = 'http://127.0.0.1:' . self::findFreePort();
            self::$githubPrependFile = dirname(__DIR__) . '/FakeGithubClientPrepend.php';
            self::startServer();
        } catch (\Throwable $exception) {
            self::stopServer();
            self::removeDirectory(self::$appRoot);
            throw $exception;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
        self::removeDirectory(self::$appRoot);

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        $log = file_exists(self::$serverLogPath)
            ? (string)file_get_contents(self::$serverLogPath)
            : '';
        $newLog = substr($log, self::$checkedServerLogBytes);
        self::$checkedServerLogBytes = strlen($log);

        parent::tearDown();

        $this->assertDoesNotMatchRegularExpression(
            '/PHP (?:Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)/',
            $newLog,
            "PHP runtime diagnostic in the E2E web server log:\n" . $newLog
        );
    }

    protected static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('Could not find a free local port for the end-to-end test server.');
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int)substr(strrchr($name, ':'), 1);
    }

    private static function startServer(): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', self::$serverLogPath, 'a'],
            2 => ['file', self::$serverLogPath, 'a'],
        ];

        self::$serverProcess = proc_open(
            [
                PHP_BINARY,
                '-d',
                'auto_prepend_file=' . self::$githubPrependFile,
                '-S',
                parse_url(self::$baseUrl, PHP_URL_HOST) . ':' . parse_url(self::$baseUrl, PHP_URL_PORT),
            ],
            $descriptorSpec,
            self::$serverPipes,
            self::$appRoot
        );

        if (!is_resource(self::$serverProcess)) {
            throw new \RuntimeException('Could not start the PHP end-to-end test server.');
        }

        fclose(self::$serverPipes[0]);
        self::$serverPipes = [];

        $client = HttpClient::create(['max_redirects' => 0]);
        $deadline = microtime(true) + 5;
        do {
            try {
                $client->request('GET', self::$baseUrl . '/index.php')->getStatusCode();
                return;
            } catch (\Throwable) {
                usleep(100000);
            }
        } while (microtime(true) < $deadline);

        $log = file_exists(self::$serverLogPath) ? trim((string)file_get_contents(self::$serverLogPath)) : '';
        $detail = $log === '' ? '' : "\nServer log:\n" . $log;
        throw new \RuntimeException('The PHP end-to-end test server did not become ready.' . $detail);
    }

    private static function stopServer(): void
    {
        foreach (self::$serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        self::$serverPipes = [];

        if (is_resource(self::$serverProcess ?? null)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    protected static function pdo(): \PDO
    {
        $pdo = new \PDO('sqlite:' . self::$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        $pdo->exec('PRAGMA busy_timeout=1000;');
        $pdo->exec('PRAGMA foreign_keys=ON;');

        return $pdo;
    }

    private static function createIsolatedAppRoot(): void
    {
        mkdir(self::$appRoot, 0777, true);
        mkdir(self::$appRoot . '/.cache/twig', 0777, true);
        mkdir(self::$appRoot . '/uploads', 0777, true);

        foreach (['assets', 'entities', 'pages', 'templates'] as $directory) {
            self::copyDirectory(dirname(__DIR__, 3) . '/' . $directory, self::$appRoot . '/' . $directory);
        }

        foreach ([
            'auth.php',
            'config.php',
            'db.php',
            'fenix.php',
            'github.php',
            'include.php',
            'index.php',
            'logout.php',
            'review.php',
            'templates.php',
            'validation.php',
            'video.php',
        ] as $file) {
            copy(dirname(__DIR__, 3) . '/' . $file, self::$appRoot . '/' . $file);
        }

        symlink(dirname(__DIR__, 3) . '/vendor', self::$appRoot . '/vendor');
        copy(dirname(__DIR__, 3) . '/db.sqlite', self::$dbPath);
        $pdo = new \PDO('sqlite:' . self::$dbPath);
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA busy_timeout=1000;');
        $pdo = null;
    }

    protected static function copyDirectory(string $source, string $target): void
    {
        mkdir($target, 0777, true);
        $items = scandir($source);
        if ($items === false) {
            throw new \RuntimeException("Could not read directory: $source");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . '/' . $item;
            $to = $target . '/' . $item;

            if (is_dir($from)) {
                self::copyDirectory($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }

    protected static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath) && !is_link($fullPath)) {
                self::removeDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }

    protected static function insertUser(array $data): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO User (id, name, email, photo, role, repository_user, repository_etag, repository_last_processed_id)
             VALUES (:id, :name, :email, :photo, :role, :repository_user, :repository_etag, :repository_last_processed_id)'
        );
        $stmt->execute($data);
        $stmt = null;
        $pdo = null;
    }

    protected static function insertSession(
        string $sessionId,
        string $userId,
        string $expires = '+1 day'
    ): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO Session (id, expires, user_id) VALUES (:id, :expires, :user_id)'
        );
        $stmt->execute([
            'id' => $sessionId,
            'expires' => (new \DateTimeImmutable($expires))->format('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
        $stmt = null;
        $pdo = null;
    }

    protected static function deleteSessionAndUser(string $sessionId, string $userId): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare('DELETE FROM Session WHERE id = :id');
        $stmt->execute(['id' => $sessionId]);
        $stmt = $pdo->prepare('DELETE FROM User WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $stmt = null;
        $pdo = null;
    }

    protected static function insertShift(string $name, int $year, ?string $profId): int
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO Shift (name, year, prof_id) VALUES (:name, :year, :prof_id)'
        );
        $stmt->execute([
            'name' => $name,
            'year' => $year,
            'prof_id' => $profId,
        ]);
        $id = (int)$pdo->lastInsertId();
        $stmt = null;
        $pdo = null;

        return $id;
    }

    protected static function insertGroup(int $groupNumber, int $year, int $shiftId): int
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ProjGroup (
                group_number, year, project_name, project_description, project_website,
                repository, cla, dco, major_users, coding_style, bugs_for_beginners,
                project_ideas, student_programs, getting_started_manual,
                developers_manual, testing_manual, developers_mailing_list,
                patch_submission, hash_proposal_file, url_proposal, hash_final_report,
                allow_modifications_date, shift_id
             ) VALUES (
                :group_number, :year, :project_name, :project_description, :project_website,
                :repository, :cla, :dco, :major_users, :coding_style, :bugs_for_beginners,
                :project_ideas, :student_programs, :getting_started_manual,
                :developers_manual, :testing_manual, :developers_mailing_list,
                :patch_submission, :hash_proposal_file, :url_proposal, :hash_final_report,
                :allow_modifications_date, :shift_id
             )'
        );
        $stmt->execute([
            'group_number' => $groupNumber,
            'year' => $year,
            'project_name' => 'E2E Project ' . $groupNumber,
            'project_description' => 'End-to-end test project',
            'project_website' => 'https://example.org/project',
            'repository' => '',
            'cla' => 0,
            'dco' => 0,
            'major_users' => '',
            'coding_style' => 'https://example.org/style',
            'bugs_for_beginners' => 'https://example.org/bugs',
            'project_ideas' => 'https://example.org/ideas',
            'student_programs' => '',
            'getting_started_manual' => 'https://example.org/start',
            'developers_manual' => 'https://example.org/dev',
            'testing_manual' => 'https://example.org/test',
            'developers_mailing_list' => 'https://example.org/list',
            'patch_submission' => 'https://example.org/patch',
            'hash_proposal_file' => '',
            'url_proposal' => '',
            'hash_final_report' => '',
            'allow_modifications_date' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s'),
            'shift_id' => $shiftId,
        ]);
        $id = (int)$pdo->lastInsertId();
        $stmt = null;
        $pdo = null;

        return $id;
    }

    protected static function insertGroupMembership(int $groupId, string $userId): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO projgroup_user (projgroup_id, user_id) VALUES (:projgroup_id, :user_id)'
        );
        $stmt->execute([
            'projgroup_id' => $groupId,
            'user_id' => $userId,
        ]);
        $stmt = null;
        $pdo = null;
    }

    protected static function upsertDeadline(int $year, string $finalReportOffset): void
    {
        $pdo = self::pdo();
        $deadline = [
            'year' => $year,
            'proj_proposal' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'bug_selection' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'feature_selection' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'patch_submission' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
            'final_report' => (new \DateTimeImmutable($finalReportOffset))->format('Y-m-d H:i:s'),
        ];

        $exists = $pdo->prepare('SELECT COUNT(*) FROM Deadline WHERE year = :year');
        $exists->execute(['year' => $year]);
        if ((int)$exists->fetchColumn() > 0) {
            $stmt = $pdo->prepare(
                'UPDATE Deadline
                 SET proj_proposal = :proj_proposal,
                     bug_selection = :bug_selection,
                     feature_selection = :feature_selection,
                     patch_submission = :patch_submission,
                     final_report = :final_report
                 WHERE year = :year'
            );
            $stmt->execute($deadline);
            $stmt = null;
            $exists = null;
            $pdo = null;
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO Deadline (year, proj_proposal, bug_selection, feature_selection, patch_submission, final_report)
             VALUES (:year, :proj_proposal, :bug_selection, :feature_selection, :patch_submission, :final_report)'
        );
        $stmt->execute($deadline);
        $stmt = null;
        $exists = null;
        $pdo = null;
    }

    protected static function createTemporaryPdf(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pic1_' . $prefix . '_');
        $pdfPath = $path . '.pdf';
        rename($path, $pdfPath);
        file_put_contents(
            $pdfPath,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n"
        );

        return $pdfPath;
    }

    protected static function deleteGroupFixture(
        array $groupIds,
        int $shiftId,
        array $userIds,
        array $sessionIds,
        int $year
    ): void {
        $pdo = self::pdo();
        foreach ($groupIds as $groupId) {
            $pdo->prepare('DELETE FROM projgroup_user WHERE projgroup_id = :projgroup_id')
                ->execute(['projgroup_id' => $groupId]);
            $pdo->prepare('DELETE FROM ProjGroup WHERE id = :id')->execute(['id' => $groupId]);
        }
        $pdo->prepare('DELETE FROM Shift WHERE id = :id')->execute(['id' => $shiftId]);
        foreach ($sessionIds as $sessionId) {
            $pdo->prepare('DELETE FROM Session WHERE id = :id')->execute(['id' => $sessionId]);
        }
        foreach ($userIds as $userId) {
            $pdo->prepare('DELETE FROM User WHERE id = :id')->execute(['id' => $userId]);
        }
        $pdo->prepare('DELETE FROM Deadline WHERE year = :year')->execute(['year' => $year]);
    }

    protected static function insertMilestone(
        int $year,
        string $name,
        string $description,
        string $field1,
        int $points1,
        int $range1
    ): int {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO Milestone (
                year, name, description, page, individual,
                field1, points1, range1, field2, points2, range2,
                field3, points3, range3, field4, points4, range4
             ) VALUES (
                :year, :name, :description, :page, :individual,
                :field1, :points1, :range1, :field2, :points2, :range2,
                :field3, :points3, :range3, :field4, :points4, :range4
             )'
        );
        $stmt->execute([
            'year' => $year,
            'name' => $name,
            'description' => $description,
            'page' => 'grades',
            'individual' => 0,
            'field1' => $field1,
            'points1' => $points1,
            'range1' => $range1,
            'field2' => '',
            'points2' => 0,
            'range2' => 0,
            'field3' => '',
            'points3' => 0,
            'range3' => 0,
            'field4' => '',
            'points4' => 0,
            'range4' => 0,
        ]);
        $id = (int)$pdo->lastInsertId();
        $stmt = null;
        $pdo = null;

        return $id;
    }

    protected static function insertFinalGrade(int $year, string $formula): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO FinalGrade (year, formula) VALUES (:year, :formula)'
        );
        $stmt->execute([
            'year' => $year,
            'formula' => $formula,
        ]);
        $stmt = null;
        $pdo = null;
    }

    protected static function insertGrade(string $userId, int $milestoneId, int $field1, int $lateDays): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO Grade (field1, field2, field3, field4, late_days, user_id, milestone_id)
             VALUES (:field1, NULL, NULL, NULL, :late_days, :user_id, :milestone_id)'
        );
        $stmt->execute([
            'field1' => $field1,
            'late_days' => $lateDays,
            'user_id' => $userId,
            'milestone_id' => $milestoneId,
        ]);
        $stmt = null;
        $pdo = null;
    }

    protected static function deleteGradesFixture(
        int $year,
        int $milestoneId,
        array $studentIds,
        array $groupIds,
        int $shiftId,
        string $professorId,
        string $professorSessionId
    ): void {
        $pdo = self::pdo();
        foreach ($studentIds as $studentId) {
            $pdo->prepare('DELETE FROM Grade WHERE user_id = :user_id')->execute(['user_id' => $studentId]);
        }
        $pdo->prepare('DELETE FROM FinalGrade WHERE year = :year')->execute(['year' => $year]);
        $pdo->prepare('DELETE FROM Milestone WHERE id = :id')->execute(['id' => $milestoneId]);
        $pdo->prepare('DELETE FROM Deadline WHERE year = :year')->execute(['year' => $year]);
        foreach ($groupIds as $groupId) {
            $pdo->prepare('DELETE FROM projgroup_user WHERE projgroup_id = :projgroup_id')
                ->execute(['projgroup_id' => $groupId]);
            $pdo->prepare('DELETE FROM ProjGroup WHERE id = :id')->execute(['id' => $groupId]);
        }
        $pdo->prepare('DELETE FROM Shift WHERE id = :id')->execute(['id' => $shiftId]);
        $pdo->prepare('DELETE FROM Session WHERE id = :id')->execute(['id' => $professorSessionId]);
        foreach (array_merge([$professorId], $studentIds) as $userId) {
            $pdo->prepare('DELETE FROM User WHERE id = :id')->execute(['id' => $userId]);
        }
    }

    protected function request(string $method, string $path, array $options = [])
    {
        $client = HttpClient::create(['max_redirects' => 0]);

        return $client->request($method, self::$baseUrl . $path, $options);
    }
}
