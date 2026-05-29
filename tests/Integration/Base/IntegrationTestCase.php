<?php

namespace Tests\Integration\Base;

use Tests\Unit\Base\UnitTestCase;

/**
 * Base class for integration tests that need a mock database
 * 
 * Creates isolated SQLite database for each test class
 */
abstract class IntegrationTestCase extends UnitTestCase
{
    protected static string $dbPath;
    protected static bool $dbInitialized = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Create temporary SQLite database
        self::$dbPath = sys_get_temp_dir() . '/pic1_integration_' . bin2hex(random_bytes(4)) . '.sqlite';
        self::initializeDatabase();
        self::$dbInitialized = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$dbPath)) {
            @unlink(self::$dbPath);
        }
        parent::tearDownAfterClass();
    }

    protected static function getPdo(): \PDO
    {
        $pdo = new \PDO('sqlite:' . self::$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
        $pdo->exec('PRAGMA busy_timeout=1000;');
        $pdo->exec('PRAGMA foreign_keys=ON;');
        return $pdo;
    }

    private static function initializeDatabase(): void
    {
        $pdo = self::getPdo();
        
        // Create User table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS User (
                id VARCHAR(16) NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                photo CLOB NOT NULL,
                role INTEGER NOT NULL,
                repository_user VARCHAR(255) NOT NULL,
                repository_etag VARCHAR(255) NOT NULL,
                repository_last_processed_id BIGINT NOT NULL,
                PRIMARY KEY (id)
            )
        ');

        // Create Session table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Session (
                id VARCHAR(32) NOT NULL,
                expires DATETIME NOT NULL,
                user_id VARCHAR(16) DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT FK_1FF9EC48A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)
            )
        ');

        // Create Shift table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Shift (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                year INTEGER NOT NULL,
                prof_id VARCHAR(16) DEFAULT NULL,
                CONSTRAINT FK_64CA1441ABC1F7FE FOREIGN KEY (prof_id) REFERENCES User (id)
            )
        ');

        // Create ProjGroup table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ProjGroup (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                group_number INTEGER NOT NULL,
                year INTEGER NOT NULL,
                project_name VARCHAR(255) NOT NULL,
                project_description VARCHAR(2000) NOT NULL,
                project_website VARCHAR(255) NOT NULL,
                repository VARCHAR(150) NOT NULL,
                cla BOOLEAN NOT NULL,
                dco BOOLEAN NOT NULL,
                major_users VARCHAR(255) NOT NULL,
                coding_style VARCHAR(255) NOT NULL,
                bugs_for_beginners VARCHAR(255) NOT NULL,
                project_ideas VARCHAR(255) NOT NULL,
                student_programs VARCHAR(1000) NOT NULL,
                getting_started_manual VARCHAR(255) NOT NULL,
                developers_manual VARCHAR(255) NOT NULL,
                testing_manual VARCHAR(255) NOT NULL,
                developers_mailing_list VARCHAR(255) NOT NULL,
                patch_submission VARCHAR(255) NOT NULL,
                hash_proposal_file VARCHAR(40) NOT NULL,
                url_proposal VARCHAR(255) NOT NULL,
                hash_final_report VARCHAR(40) NOT NULL,
                allow_modifications_date DATETIME NOT NULL,
                shift_id INTEGER DEFAULT NULL,
                CONSTRAINT FK_C49C4FD9BB70BC0E FOREIGN KEY (shift_id) REFERENCES Shift (id)
            )
        ');

        // Create Milestone table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Milestone (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                year INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                description VARCHAR(255) NOT NULL,
                page VARCHAR(255) NOT NULL,
                individual BOOLEAN NOT NULL,
                field1 VARCHAR(255) NOT NULL,
                points1 INTEGER NOT NULL,
                range1 INTEGER NOT NULL,
                field2 VARCHAR(255) NOT NULL,
                points2 INTEGER NOT NULL,
                range2 INTEGER NOT NULL,
                field3 VARCHAR(255) NOT NULL,
                points3 INTEGER NOT NULL,
                range3 INTEGER NOT NULL,
                field4 VARCHAR(255) NOT NULL,
                points4 INTEGER NOT NULL,
                range4 INTEGER NOT NULL,
                UNIQUE (year, name)
            )
        ');

        // Create Grade table (composite primary key)
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Grade (
                field1 INTEGER DEFAULT NULL,
                field2 INTEGER DEFAULT NULL,
                field3 INTEGER DEFAULT NULL,
                field4 INTEGER DEFAULT NULL,
                late_days INTEGER NOT NULL,
                user_id VARCHAR(16) NOT NULL,
                milestone_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, milestone_id),
                CONSTRAINT FK_989B8130A76ED395 FOREIGN KEY (user_id) REFERENCES User (id),
                CONSTRAINT FK_989B81304B3E2EDA FOREIGN KEY (milestone_id) REFERENCES Milestone (id)
            )
        ');

        // Create Patch table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Patch (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                status INTEGER NOT NULL,
                type INTEGER NOT NULL,
                hash VARCHAR(64) NOT NULL,
                video_url VARCHAR(255) NOT NULL,
                lines_added INTEGER NOT NULL,
                lines_deleted INTEGER NOT NULL,
                files_modified INTEGER NOT NULL,
                group_id INTEGER NOT NULL,
                platform VARCHAR(255) NOT NULL,
                repo_branch VARCHAR(255) DEFAULT NULL,
                src_branch VARCHAR(255) DEFAULT NULL,
                pr_number INTEGER DEFAULT NULL,
                CONSTRAINT FK_23EAB932FE54D947 FOREIGN KEY (group_id) REFERENCES ProjGroup (id)
            )
        ');

        // Create PatchComment table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS PatchComment (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                text CLOB NOT NULL,
                time DATETIME NOT NULL,
                patch_id INTEGER NOT NULL,
                user_id VARCHAR(16) DEFAULT NULL,
                CONSTRAINT FK_89134298CD00882C FOREIGN KEY (patch_id) REFERENCES Patch (id),
                CONSTRAINT FK_89134298A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)
            )
        ');

        // Create patch_user table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS patch_user (
                patch_id INTEGER NOT NULL,
                user_id VARCHAR(16) NOT NULL,
                PRIMARY KEY (patch_id, user_id),
                CONSTRAINT FK_3EAC6D63CD00882C FOREIGN KEY (patch_id) REFERENCES Patch (id),
                CONSTRAINT FK_3EAC6D63A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)
            )
        ');

        // Create FinalGrade table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS FinalGrade (
                year INTEGER NOT NULL,
                formula VARCHAR(255) NOT NULL,
                PRIMARY KEY (year)
            )
        ');

        // Create Deadline table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS Deadline (
                year INTEGER NOT NULL,
                proj_proposal DATETIME NOT NULL,
                bug_selection DATETIME NOT NULL,
                feature_selection DATETIME NOT NULL,
                patch_submission DATETIME NOT NULL,
                final_report DATETIME NOT NULL,
                PRIMARY KEY (year)
            )
        ');

        // Create projgroup_user table (many-to-many)
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS projgroup_user (
                projgroup_id INTEGER NOT NULL,
                user_id VARCHAR(16) NOT NULL,
                PRIMARY KEY (projgroup_id, user_id),
                CONSTRAINT FK_4E82DBC78B06FE40 FOREIGN KEY (projgroup_id) REFERENCES ProjGroup (id),
                CONSTRAINT FK_4E82DBC7A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)
            )
        ');

        // Create PatchCIError table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS PatchCIError (
                hash VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(512) NOT NULL,
                time DATETIME NOT NULL,
                patch_id INTEGER NOT NULL,
                PRIMARY KEY (patch_id, hash, name),
                CONSTRAINT FK_483CA63ECD00882C FOREIGN KEY (patch_id) REFERENCES Patch (id)
            )
        ');

        // Create SelectedBug table
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS SelectedBug (
                year INTEGER NOT NULL,
                issue_url VARCHAR(255) NOT NULL,
                repro_url VARCHAR(255) NOT NULL,
                description VARCHAR(4096) NOT NULL,
                user_id VARCHAR(16) NOT NULL,
                PRIMARY KEY (user_id, year),
                UNIQUE (year, issue_url),
                CONSTRAINT FK_A2B2B8B2A76ED395 FOREIGN KEY (user_id) REFERENCES User (id)
            )
        ');
    }

    protected function insertTestUser(string $userId, string $name, int $role = ROLE_STUDENT): void
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO User (id, name, email, photo, role, repository_user, repository_etag, repository_last_processed_id)
             VALUES (:id, :name, :email, :photo, :role, :repository_user, :repository_etag, :repository_last_processed_id)'
        )->execute([
            'id' => $userId,
            'name' => $name,
            'email' => $userId . '@test.example.com',
            'photo' => '',
            'role' => $role,
            'repository_user' => '',
            'repository_etag' => '',
            'repository_last_processed_id' => '0',
        ]);
    }

    protected function insertTestSession(string $sessionId, string $userId, ?\DateTimeImmutable $expires = null): void
    {
        if ($expires === null) {
            $expires = new \DateTimeImmutable('+90 days');
        }
        
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO Session (id, expires, user_id) VALUES (:id, :expires, :user_id)'
        )->execute([
            'id' => $sessionId,
            'expires' => $expires->format('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    protected function getSessionFromDatabase(string $sessionId): ?array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM Session WHERE id = ?');
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    protected function getUserFromDatabase(string $userId): ?array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM User WHERE id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    protected function insertTestMilestone(int $year, string $milestone, string $description = ''): int
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO Milestone (year, name, description, page, individual, field1, points1, range1, field2, points2, range2, field3, points3, range3, field4, points4, range4)
             VALUES (:year, :name, :description, :page, 0, :f1, 100, 100, :f2, 100, 100, :f3, 100, 100, \'\', 0, 0)'
        )->execute([
            'year' => $year,
            'name' => $milestone,
            'description' => $description,
            'page' => '',
            'f1' => 'Code Quality',
            'f2' => 'Functionality',
            'f3' => 'Documentation',
        ]);
        
        return (int)$pdo->lastInsertId();
    }

    protected function insertTestGrade(string $userId, int $milestoneId, int $field1, int $field2 = 0, int $field3 = 0, int $lateDays = 0): void
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO Grade (field1, field2, field3, field4, late_days, user_id, milestone_id)
             VALUES (:field1, :field2, :field3, NULL, :late_days, :user_id, :milestone_id)'
        )->execute([
            'field1' => $field1,
            'field2' => $field2,
            'field3' => $field3,
            'late_days' => $lateDays,
            'user_id' => $userId,
            'milestone_id' => $milestoneId,
        ]);
    }

    protected function getGradesFromDatabase(string $userId, int $milestoneId): ?array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM Grade WHERE user_id = ? AND milestone_id = ?');
        $stmt->execute([$userId, $milestoneId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    protected function insertTestShift(int $year, string $shiftName = 'Test Shift'): int
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO Shift (name, year, prof_id)
             VALUES (:name, :year, NULL)'
        )->execute([
            'name' => $shiftName,
            'year' => $year,
        ]);
        
        return (int)$pdo->lastInsertId();
    }

    protected function insertTestGroup(int $groupNumber, int $year, int $shiftId, string $projectName = ''): int
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO ProjGroup (group_number, year, shift_id, project_name, project_description, project_website, repository, cla, dco, major_users, coding_style, bugs_for_beginners, project_ideas, student_programs, getting_started_manual, developers_manual, testing_manual, developers_mailing_list, patch_submission, hash_proposal_file, url_proposal, hash_final_report, allow_modifications_date)
             VALUES (:group_number, :year, :shift_id, :project_name, :project_description, :project_website, :repository, 0, 0, :major_users, :coding_style, :bugs_for_beginners, :project_ideas, :student_programs, :getting_started_manual, :developers_manual, :testing_manual, :developers_mailing_list, :patch_submission, :hash_proposal_file, :url_proposal, :hash_final_report, :allow_modifications_date)'
        )->execute([
            'group_number' => $groupNumber,
            'year' => $year,
            'shift_id' => $shiftId,
            'project_name' => $projectName,
            'project_description' => '',
            'project_website' => '',
            'repository' => '',
            'major_users' => '',
            'coding_style' => '',
            'bugs_for_beginners' => '',
            'project_ideas' => '',
            'student_programs' => '',
            'getting_started_manual' => '',
            'developers_manual' => '',
            'testing_manual' => '',
            'developers_mailing_list' => '',
            'patch_submission' => '',
            'hash_proposal_file' => '',
            'url_proposal' => '',
            'hash_final_report' => '',
            'allow_modifications_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        
        return (int)$pdo->lastInsertId();
    }

    protected function insertGroupMembership(int $groupId, string $userId): void
    {
        $pdo = self::getPdo();
        $pdo->prepare(
            'INSERT INTO projgroup_user (projgroup_id, user_id) VALUES (:group_id, :user_id)'
        )->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);
    }

    protected function getGroupFromDatabase(int $groupId): ?array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM ProjGroup WHERE id = ?');
        $stmt->execute([$groupId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    protected function getGroupMembersFromDatabase(int $groupId): array
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT user_id FROM projgroup_user WHERE projgroup_id = ?');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
}
