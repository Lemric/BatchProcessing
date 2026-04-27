<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

namespace Lemric\BatchProcessing\Tests\Transaction;

use Lemric\BatchProcessing\Transaction\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoTransactionManagerTest extends TestCase
{
    public function testCommitPersistsChanges(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $tx = new PdoTransactionManager($pdo);

        $tx->begin();
        $pdo->exec('INSERT INTO t (id) VALUES (1)');
        $tx->commit();

        self::assertSame(1, self::rowCount($pdo));
    }

    public function testNestedSavepoint(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $tx = new PdoTransactionManager($pdo);

        $tx->begin();
        $pdo->exec('INSERT INTO t (id) VALUES (1)');

        $tx->begin(); // savepoint
        $pdo->exec('INSERT INTO t (id) VALUES (2)');
        $tx->rollback(); // discard inner

        $tx->commit(); // commit outer

        self::assertSame(1, self::rowCount($pdo));
    }

    public function testRollbackDiscardsChanges(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $tx = new PdoTransactionManager($pdo);

        $tx->begin();
        $pdo->exec('INSERT INTO t (id) VALUES (2)');
        $tx->rollback();

        self::assertSame(0, self::rowCount($pdo));
    }

    private static function rowCount(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM t');
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }
}
