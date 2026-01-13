<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;

class ImportUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from storage/app/users.csv using PDO with validation and hashing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filepath = storage_path('app/users.csv');
        $logPrefix = '[import-users]';

        if (!file_exists($filepath)) {
            $this->error('CSV file not found at ' . $filepath);
            \Log::error("$logPrefix CSV file not found at $filepath");
            return self::FAILURE;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_DATABASE', 'forge')
        );
        $dbUser = env('DB_USERNAME', 'forge');
        $dbPass = env('DB_PASSWORD', '');

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            \Log::error("$logPrefix DB connection failed: " . $e->getMessage());
            return self::FAILURE;
        }

        if (($file = fopen($filepath, 'r')) === false) {
            $this->error('Unable to open CSV file.');
            \Log::error("$logPrefix Unable to open CSV file at $filepath");
            return self::FAILURE;
        }

        // Skip header
        fgetcsv($file);

        $insert = $pdo->prepare(
            'INSERT INTO users (user_id, username, email, password, created_at, updated_at)
             VALUES (:user_id, :username, :email, :password, NOW(), NOW())'
        );

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 4) {
                \Log::warning("$logPrefix Skipping row with insufficient columns: " . json_encode($row));
                $skipped++;
                continue;
            }

            [$userId, $username, $email, $password] = $row;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                \Log::warning("$logPrefix Invalid email '$email' for user_id $userId");
                $skipped++;
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
                \Log::warning("$logPrefix Invalid username '$username' for user_id $userId");
                $skipped++;
                continue;
            }

            if ($password === '' || $password === null) {
                \Log::warning("$logPrefix Missing password for user_id $userId");
                $skipped++;
                continue;
            }

            try {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $insert->execute([
                    ':user_id' => $userId,
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $hashed,
                ]);
                $imported++;
            } catch (Throwable $e) {
                \Log::error("$logPrefix Insert failed for user_id $userId: " . $e->getMessage());
                $skipped++;
            }
        }

        fclose($file);

        $this->info("Users import complete. Imported: $imported, Skipped: $skipped");
        \Log::info("$logPrefix Completed. Imported: $imported, Skipped: $skipped");

        return self::SUCCESS;
    }
}

