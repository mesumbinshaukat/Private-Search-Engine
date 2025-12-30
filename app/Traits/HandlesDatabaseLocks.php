<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait HandlesDatabaseLocks
{
    /**
     * Execute a database operation with automatic retry on lock errors.
     *
     * @param callable $callback The database operation to execute
     * @param int $maxAttempts Maximum number of retry attempts
     * @param int $baseDelay Base delay in milliseconds (will use exponential backoff)
     * @return mixed The result of the callback
     * @throws \Exception If all retries fail
     */
    protected function retryOnLock(callable $callback, int $maxAttempts = 5, int $baseDelay = 100)
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                return DB::transaction($callback);
            } catch (\PDOException $e) {
                $lastException = $e;
                
                // Check if it's a database lock error
                if ($this->isDatabaseLockError($e)) {
                    if ($attempt < $maxAttempts) {
                        // Exponential backoff: 100ms, 200ms, 400ms, 800ms, 1600ms
                        $delay = $baseDelay * pow(2, $attempt - 1);
                        
                        Log::warning('Database locked, retrying', [
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'delay_ms' => $delay,
                            'error' => $e->getMessage(),
                        ]);
                        
                        usleep($delay * 1000); // Convert to microseconds
                        $attempt++;
                        continue;
                    }
                }
                
                // Not a lock error or max attempts reached
                throw $e;
            } catch (\Illuminate\Database\QueryException $e) {
                $lastException = $e;
                
                if ($this->isDatabaseLockError($e)) {
                    if ($attempt < $maxAttempts) {
                        $delay = $baseDelay * pow(2, $attempt - 1);
                        
                        Log::warning('Database locked (QueryException), retrying', [
                            'attempt' => $attempt,
                            'max_attempts' => $maxAttempts,
                            'delay_ms' => $delay,
                            'error' => $e->getMessage(),
                        ]);
                        
                        usleep($delay * 1000);
                        $attempt++;
                        continue;
                    }
                }
                
                throw $e;
            }
        }

        // All retries failed
        Log::error('Database operation failed after all retries', [
            'max_attempts' => $maxAttempts,
            'last_error' => $lastException->getMessage(),
        ]);

        throw $lastException;
    }

    /**
     * Check if an exception is a database lock error.
     */
    private function isDatabaseLockError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        return str_contains($message, 'database is locked') ||
               str_contains($message, 'database locked') ||
               str_contains($message, 'sqlstate[hy000]: general error: 5');
    }

    /**
     * Execute a non-critical database operation with lock handling.
     * If it fails after retries, log the error but don't throw.
     */
    protected function tryWithLockHandling(callable $callback, string $operationName = 'database operation'): bool
    {
        try {
            $this->retryOnLock($callback);
            return true;
        } catch (\Exception $e) {
            Log::error("Non-critical {$operationName} failed after retries", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
