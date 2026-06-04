<?php
// core/UserAudit.php
// Append-only JSON Lines audit logger used across the ERP.
// Used for User mgmt + all transactional modules (Purchase, OtherExpense, Ledger, MoneyTransfer, etc.)
// Phase 4 modernization: rich details + branch context for full auditability before GL integration.

class UserAudit
{
    private string $logFile;

    public function __construct()
    {
        $logsDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }
        $this->logFile = $logsDir . '/user_audit.log';
    }

    /**
     * Log a user-related action.
     *
     * For non-user entities (Purchase, Ledger, etc.), the $targetUserId param is overloaded
     * to hold the entity ID (e.g. purchase_receive.id, journal_entry.id). This is historical
     * but works with the action prefix filter in getRecentLogs().
     *
     * Phase 4+: Always include rich details + 'branch_id' for traceability before GL integration.
     */
    public function log(
        int $performedByUserId,
        string $action,
        ?int $targetUserId = null,
        array $details = []
    ): void {
        // Auto-enrich with branch context (critical for multi-branch audit)
        if (!isset($details['branch_id'])) {
            $details['branch_id'] = $_SESSION['branch_id'] ?? null;
        }

        $entry = [
            'timestamp'     => date('Y-m-d H:i:s'),
            'performed_by'  => $performedByUserId,
            'action'        => $action,
            'target_user_id'=> $targetUserId,   // overloaded: entity id for purchase_* actions
            'ip'            => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'details'       => $details,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $fp = @fopen($this->logFile, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    public function logUserCreated(int $performedBy, int $newUserId, string $username): void
    {
        $this->log($performedBy, 'user_created', $newUserId, ['username' => $username]);
    }

    public function logUserStatusChanged(int $performedBy, int $targetUserId, bool $newStatus): void
    {
        $this->log($performedBy, 'user_status_changed', $targetUserId, [
            'new_status' => $newStatus ? 'active' : 'inactive'
        ]);
    }

    public function logPermissionsUpdated(int $performedBy, int $targetUserId): void
    {
        $this->log($performedBy, 'permissions_updated', $targetUserId);
    }

    /**
     * Get recent audit log entries (simple file-based reader)
     */
    public function getRecentLogs(int $limit = 200, ?string $actionContains = null): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        // Read from end (most recent first)
        $lines = array_reverse($lines);
        $logs = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) continue;

            if ($actionContains && stripos($entry['action'] ?? '', $actionContains) === false) {
                continue;
            }

            $logs[] = $entry;

            if (count($logs) >= $limit) break;
        }

        return $logs;
    }
}
?>