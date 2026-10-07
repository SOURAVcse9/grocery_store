<?php
/**
 * ==============================================================================
 * tools/backup_database.php — Production MySQL Database Backup & Pruning Tool
 * ==============================================================================
 * CLI script to generate atomic, portable MySQL database backups and
 * automatically prune archives past the retention limit.
 *
 * Usage:
 *   php tools/backup_database.php [--keep=7] [--compress] [--quiet]
 *
 * Options:
 *   --keep=N      Days of backup history to retain (default: 7)
 *   --compress    Compress output file with Gzip (.sql.gz)
 *   --quiet       Suppress terminal output (ideal for cron jobs)
 * ==============================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI execution only.\n";
    exit(1);
}

define('GROCO_CLI_TEST_MODE', true);
require_once dirname(__DIR__) . '/public/dbconnect.php';

// Parse command line arguments
$options = getopt('', ['keep::', 'compress', 'quiet', 'help']);

if (isset($options['help'])) {
    echo "GroCo Database Backup Utility\n";
    echo "Usage: php tools/backup_database.php [options]\n";
    echo "  --keep=N     Retention threshold in days (default: 7)\n";
    echo "  --compress   Compress backup with Gzip\n";
    echo "  --quiet      Quiet mode for cron jobs\n";
    exit(0);
}

$retentionDays = isset($options['keep']) ? max(1, (int)$options['keep']) : 7;
$compress = isset($options['compress']) && function_exists('gzopen');
$quiet = isset($options['quiet']);

function out(string $message, bool $quiet = false): void
{
    if (!$quiet) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
}

out("Starting database backup...", $quiet);

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
        fwrite(STDERR, "Error: Unable to create backup directory: {$backupDir}\n");
        exit(1);
    }
}

$timestamp = date('Y-m-d_His');
$baseName = DB_NAME . '_backup_' . $timestamp . '.sql';
$targetFile = $backupDir . '/' . $baseName . ($compress ? '.gz' : '');

$startTime = microtime(true);

try {
    $pdo = db();
    
    // Fetch all tables
    $tables = [];
    $stmt = $pdo->query('SHOW TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    if (empty($tables)) {
        throw new RuntimeException("No tables found in database " . DB_NAME);
    }

    $handle = $compress ? gzopen($targetFile, 'w9') : fopen($targetFile, 'wb');
    if (!$handle) {
        throw new RuntimeException("Cannot open destination file: {$targetFile}");
    }

    $write = function(string $data) use ($handle, $compress): void {
        if ($compress) {
            gzwrite($handle, $data);
        } else {
            fwrite($handle, $data);
        }
    };

    // Header
    $header = "-- ====================================================================\n"
            . "-- GroCo Grocery Store Database Backup\n"
            . "-- Database: " . DB_NAME . "\n"
            . "-- Host: " . DB_HOST . "\n"
            . "-- Date: " . date('Y-m-d H:i:s') . " (Asia/Dhaka)\n"
            . "-- PHP Version: " . PHP_VERSION . "\n"
            . "-- ====================================================================\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
            . "SET AUTOCOMMIT = 0;\n"
            . "START TRANSACTION;\n\n";
    $write($header);

    $totalRows = 0;

    foreach ($tables as $table) {
        $write("\n-- --------------------------------------------------------------------\n");
        $write("-- Table structure for `{$table}`\n");
        $write("-- --------------------------------------------------------------------\n");
        $write("DROP TABLE IF EXISTS `{$table}`;\n");

        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        if ($createRow && isset($createRow[1])) {
            $write($createRow[1] . ";\n\n");
        }

        // Table rows
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $rowCount = (int)$countStmt->fetchColumn();

        if ($rowCount > 0) {
            $write("-- Dumping data for table `{$table}` ({$rowCount} rows)\n");
            $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
            
            $batchSize = 250;
            $batch = [];

            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                $escapedValues = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $escapedValues[] = 'NULL';
                    } elseif (is_numeric($val) && !is_string($val)) {
                        $escapedValues[] = $val;
                    } else {
                        $escapedValues[] = $pdo->quote((string)$val);
                    }
                }
                $batch[] = '(' . implode(', ', $escapedValues) . ')';

                if (count($batch) >= $batchSize) {
                    $write("INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
                $totalRows++;
            }

            if (!empty($batch)) {
                $write("INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
            }
            $write("\n");
        }
    }

    // Footer
    $footer = "COMMIT;\n"
            . "SET FOREIGN_KEY_CHECKS = 1;\n"
            . "-- Dump completed on " . date('Y-m-d H:i:s') . "\n";
    $write($footer);

    if ($compress) {
        gzclose($handle);
    } else {
        fclose($handle);
    }

    $duration = round(microtime(true) - $startTime, 2);
    $fileBytes = filesize($targetFile);
    $sizeFormatted = round($fileBytes / (1024 * 1024), 2) . ' MB (' . number_format($fileBytes) . ' bytes)';

    out("Backup completed successfully!", $quiet);
    out("Archive: {$targetFile}", $quiet);
    out("Tables: " . count($tables) . " | Total Rows: {$totalRows} | Size: {$sizeFormatted} | Time: {$duration}s", $quiet);

    // Prune old backups
    out("Checking retention policy ({$retentionDays} days)...", $quiet);
    $pruneCutoff = time() - ($retentionDays * 86400);
    $prunedCount = 0;

    $backupFiles = glob($backupDir . '/*.sql*') ?: [];
    foreach ($backupFiles as $f) {
        if (is_file($f) && filemtime($f) < $pruneCutoff) {
            if (@unlink($f)) {
                out("Pruned expired backup: " . basename($f), $quiet);
                $prunedCount++;
            }
        }
    }

    out("Pruning complete. {$prunedCount} old backup(s) pruned.", $quiet);

    // Record in app log
    if (function_exists('log_action')) {
        log_action('DATABASE_BACKUP', sprintf(
            "Backup generated: %s (%s, %d tables, %d rows in %ss, %d pruned)",
            basename($targetFile),
            $sizeFormatted,
            count($tables),
            $totalRows,
            $duration,
            $prunedCount
        ));
    }

    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "\n[BACKUP FAILED] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    if (function_exists('log_action')) {
        log_action('DATABASE_BACKUP_FAILED', $e->getMessage());
    }
    if (file_exists($targetFile)) {
        @unlink($targetFile);
    }
    exit(1);
}
