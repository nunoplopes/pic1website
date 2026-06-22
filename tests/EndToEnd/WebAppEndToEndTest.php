<?php

namespace Tests\EndToEnd;

use Tests\EndToEnd\Base\WebTestCase;

class WebAppEndToEndTest extends WebTestCase
{
    private static ?string $studentId = null;
    private static ?string $studentSessionId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createStudentSession();
    }

    public static function tearDownAfterClass(): void
    {
        self::deleteStudentSession();
        parent::tearDownAfterClass();
    }

    public function testUnauthenticatedRequestRedirectsToFenixLogin(): void
    {
        $response = $this->request('GET', '/index.php?page=profile');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString(
            'fenix.tecnico.ulisboa.pt/oauth/userdialog',
            $response->getHeaders(false)['location'][0] ?? ''
        );
    }

    public function testExpiredStudentSessionRedirectsToFenixLogin(): void
    {
        $sessionId = bin2hex(random_bytes(16));
        self::insertSession($sessionId, self::$studentId, '-1 day');

        try {
            $response = $this->request(
                'GET',
                '/index.php?page=profile',
                ['headers' => ['Cookie' => 'sessid=' . $sessionId]]
            );

            $this->assertSame(302, $response->getStatusCode());
            $this->assertStringContainsString(
                'fenix.tecnico.ulisboa.pt/oauth/userdialog',
                $response->getHeaders(false)['location'][0] ?? ''
            );
        } finally {
            self::pdo()->prepare('DELETE FROM Session WHERE id = :id')
                ->execute(['id' => $sessionId]);
        }
    }

    public function testAuthenticatedStudentCanOpenProfilePage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=profile',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );
        $html = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<title>PIC1: Edit profile</title>', $html);
        $this->assertStringContainsString('E2E Student', $html);
        $this->assertStringContainsString('User ID: ' . self::$studentId, $html);
        $this->assertStringContainsString('name="form[repository_user]"', $html);
    }

    public function testAuthenticatedStudentCanUpdateRepositoryUserOnProfilePage(): void
    {
        $studentId = 'e2eprof' . substr(bin2hex(random_bytes(4)), 0, 8);
        $sessionId = bin2hex(random_bytes(16));

        self::insertUser([
            'id' => $studentId,
            'name' => 'Profile Student',
            'email' => 'profile@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertSession($sessionId, $studentId);
        gc_collect_cycles();

        try {
            $response = $this->request(
                'POST',
                '/index.php?page=profile',
                [
                    'headers' => ['Cookie' => 'sessid=' . $sessionId],
                    'body' => [
                        'form' => [
                            'repository_user' => 'github:alice-student',
                            'submit' => '',
                        ],
                    ],
                ]
            );
            $html = $response->getContent();

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('Database updated!', $html);
            $this->assertStringContainsString('github:alice-student', $html);
            $this->assertStringContainsString('https://github.com/alice-student', $html);
            $this->assertStringContainsString('Open Source Lab', $html);

            $stmt = self::pdo()->prepare('SELECT repository_user FROM User WHERE id = :id');
            $stmt->execute(['id' => $studentId]);
            $repositoryUser = $stmt->fetchColumn();
            $this->assertSame('github:alice-student', $repositoryUser);
        } finally {
            self::deleteSessionAndUser($sessionId, $studentId);
        }
    }

    public function testStudentCannotOpenProfessorOnlyPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=shifts',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenDeadlinesPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=deadlines',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenGradingPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=grading',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenChangeRolePage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=changerole',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenImpersonatePage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=impersonate',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenCronPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=cron',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testStudentCannotOpenRmpatchPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=rmpatch',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Unauthorized access', trim($response->getContent()));
    }

    public function testAuthenticatedRequestToUnknownPageReturnsInvalidPage(): void
    {
        $response = $this->request(
            'GET',
            '/index.php?page=does-not-exist',
            ['headers' => ['Cookie' => $this->studentCookie()]]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Invalid page', trim($response->getContent()));
    }

    public function testAuthenticatedStudentCanUploadAndDownloadFinalReport(): void
    {
        $year = get_current_year() + 7;
        $studentId = 'e2ereport' . substr(bin2hex(random_bytes(4)), 0, 6);
        $sessionId = bin2hex(random_bytes(16));
        $otherStudentId = 'e2eother' . substr(bin2hex(random_bytes(4)), 0, 6);
        $otherSessionId = bin2hex(random_bytes(16));
        $shiftId = self::insertShift('T1-E2E-REPORT', $year, null);
        $groupId = self::insertGroup(3101, $year, $shiftId);
        $otherGroupId = self::insertGroup(3102, $year, $shiftId);
        $pdfPath = self::createTemporaryPdf('report');
        $expectedHash = sha1(file_get_contents($pdfPath));
        $storedPath = self::$appRoot . '/uploads/' . $expectedHash;

        self::insertUser([
            'id' => $studentId,
            'name' => 'Report Student',
            'email' => 'report@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertUser([
            'id' => $otherStudentId,
            'name' => 'Other Report Student',
            'email' => 'other.report@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertSession($sessionId, $studentId);
        self::insertSession($otherSessionId, $otherStudentId);
        self::insertGroupMembership($groupId, $studentId);
        self::insertGroupMembership($otherGroupId, $otherStudentId);
        self::upsertDeadline($year, '+1 day');
        gc_collect_cycles();

        $handle = null;
        try {
            $handle = fopen($pdfPath, 'r');
            stream_context_set_option($handle, 'http', 'content_type', 'application/pdf');
            $response = $this->request(
                'POST',
                '/index.php?page=report',
                [
                    'headers' => ['Cookie' => 'sessid=' . $sessionId],
                    'body' => [
                        'form' => [
                            'file' => $handle,
                            'submit' => '',
                        ],
                    ],
                ]
            );
            $html = $response->getContent();
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('File uploaded successfully!', $html);
            $this->assertStringContainsString('index.php?download=' . $groupId . '&amp;page=report', $html);

            $hash = self::pdo()
                ->query("SELECT hash_final_report FROM ProjGroup WHERE id = $groupId")
                ->fetchColumn();
            $this->assertSame($expectedHash, $hash);
            $this->assertFileExists($storedPath);

            $download = $this->request(
                'GET',
                '/index.php?page=report&download=' . $groupId,
                ['headers' => ['Cookie' => 'sessid=' . $sessionId]]
            );

            $this->assertSame(200, $download->getStatusCode());
            $this->assertStringContainsString('application/pdf', $download->getHeaders(false)['content-type'][0] ?? '');
            $this->assertSame(file_get_contents($storedPath), $download->getContent());

            $forbiddenDownload = $this->request(
                'GET',
                '/index.php?page=report&download=' . $groupId,
                ['headers' => ['Cookie' => 'sessid=' . $otherSessionId]]
            );

            $this->assertSame(200, $forbiddenDownload->getStatusCode());
            $this->assertSame('No permissions', trim($forbiddenDownload->getContent()));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
            if (file_exists($storedPath)) {
                @unlink($storedPath);
            }
            self::deleteGroupFixture(
                [$groupId, $otherGroupId],
                $shiftId,
                [$studentId, $otherStudentId],
                [$sessionId, $otherSessionId],
                $year
            );
        }
    }

    public function testAuthenticatedProfessorCanViewAndDownloadGrades(): void
    {
        $year = get_current_year() + 8;
        $professorId = 'e2eprofessor' . substr(bin2hex(random_bytes(4)), 0, 6);
        $professorSessionId = bin2hex(random_bytes(16));
        $student1Id = 'e2egradea' . substr(bin2hex(random_bytes(3)), 0, 6);
        $student2Id = 'e2egradeb' . substr(bin2hex(random_bytes(3)), 0, 6);

        self::insertUser([
            'id' => $professorId,
            'name' => 'Professor Grades',
            'email' => 'prof.grades@example.com',
            'photo' => '',
            'role' => ROLE_PROF,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertUser([
            'id' => $student1Id,
            'name' => 'Alice Student',
            'email' => 'alice.grades@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertUser([
            'id' => $student2Id,
            'name' => 'Bob Student',
            'email' => 'bob.grades@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
        self::insertSession($professorSessionId, $professorId);

        $shiftId = self::insertShift('T1-E2E-GRADES', $year, $professorId);
        $group1Id = self::insertGroup(1001, $year, $shiftId);
        $group2Id = self::insertGroup(2001, $year, $shiftId);
        self::insertGroupMembership($group1Id, $student1Id);
        self::insertGroupMembership($group2Id, $student2Id);
        $milestoneId = self::insertMilestone($year, 'M1', 'Milestone One', 'Code Quality', 200, 100);
        self::insertFinalGrade($year, 'M1');
        self::insertGrade($student1Id, $milestoneId, 80, 0);
        self::insertGrade($student2Id, $milestoneId, 50, 6);
        gc_collect_cycles();

        try {
            $response = $this->request(
                'GET',
                '/index.php?page=grades&year=' . $year . '&all_shifts=1',
                ['headers' => ['Cookie' => 'sessid=' . $professorSessionId]]
            );
            $html = $response->getContent();

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('<title>PIC1: Grades</title>', $html);
            $this->assertStringContainsString('Alice Student', $html);
            $this->assertStringContainsString('Bob Student', $html);
            $this->assertStringContainsString('Download grades of group 1', $html);
            $this->assertStringContainsString('Download grades of group 2', $html);

            $passingDownload = $this->request(
                'GET',
                '/index.php?page=grades&download=1&all_shifts=1&year=' . $year,
                ['headers' => ['Cookie' => 'sessid=' . $professorSessionId]]
            );

            $this->assertSame(200, $passingDownload->getStatusCode());
            $this->assertStringContainsString(
                'text/plain',
                $passingDownload->getHeaders(false)['content-type'][0] ?? ''
            );
            $this->assertSame($student1Id . "\t16\n", $passingDownload->getContent());

            $failingDownload = $this->request(
                'GET',
                '/index.php?page=grades&download=2&all_shifts=1&year=' . $year,
                ['headers' => ['Cookie' => 'sessid=' . $professorSessionId]]
            );

            $this->assertSame(200, $failingDownload->getStatusCode());
            $this->assertStringContainsString(
                'text/plain',
                $failingDownload->getHeaders(false)['content-type'][0] ?? ''
            );
            $this->assertSame($student2Id . "\tNA\n", $failingDownload->getContent());
        } finally {
            self::deleteGradesFixture(
                $year,
                $milestoneId,
                [$student1Id, $student2Id],
                [$group1Id, $group2Id],
                $shiftId,
                $professorId,
                $professorSessionId
            );
        }
    }

    private static function createStudentSession(): void
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        self::$studentId = 'e2e' . $suffix;
        self::$studentSessionId = bin2hex(random_bytes(16));

        $pdo = self::pdo();
        $pdo->prepare(
            'INSERT INTO User (id, name, email, photo, role, repository_user, repository_etag, repository_last_processed_id)
             VALUES (:id, :name, :email, :photo, :role, :repository_user, :repository_etag, :repository_last_processed_id)'
        )->execute([
            'id' => self::$studentId,
            'name' => 'E2E Student',
            'email' => 'e2e@example.com',
            'photo' => '',
            'role' => ROLE_STUDENT,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);

        $pdo->prepare(
            'INSERT INTO Session (id, expires, user_id) VALUES (:id, :expires, :user_id)'
        )->execute([
            'id' => self::$studentSessionId,
            'expires' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            'user_id' => self::$studentId,
        ]);
    }

    private static function deleteStudentSession(): void
    {
        if (!self::$studentId && !self::$studentSessionId) {
            return;
        }

        $pdo = self::pdo();
        if (self::$studentSessionId) {
            $pdo->prepare('DELETE FROM Session WHERE id = :id')
                ->execute(['id' => self::$studentSessionId]);
            self::$studentSessionId = null;
        }
        if (self::$studentId) {
            $pdo->prepare('DELETE FROM User WHERE id = :id')
                ->execute(['id' => self::$studentId]);
            self::$studentId = null;
        }
    }

    private function studentCookie(): string
    {
        return 'sessid=' . self::$studentSessionId;
    }
}
