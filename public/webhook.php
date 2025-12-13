<?php
/**
 * GitHub Webhook Handler for Obsolio Backend
 * URL: https://api.obsolio.com/webhook.php
 */

$logFile = "/home/obsolio/logs/webhook.log";

function logMsg($msg) {
    global $logFile;
    @file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n", FILE_APPEND);
}

// Verify request
$headers = getallheaders();
$event = $headers['X-GitHub-Event'] ?? $headers['x-github-event'] ?? 'unknown';
logMsg("Webhook called - Event: " . $event);

// Only process push events
if ($event !== 'push') {
    logMsg("Ignoring non-push event: " . $event);
    echo "Ignored";
    exit(0);
}

// Get payload
$payload = json_decode(file_get_contents('php://input'), true);
$branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
logMsg("Branch: " . $branch);

// Only deploy on main branch
if ($branch !== 'main') {
    logMsg("Ignoring non-main branch: " . $branch);
    echo "Ignored";
    exit(0);
}

// Execute deploy script
logMsg("Starting deployment...");
$output = [];
$code = 0;
exec("sudo /home/obsolio/scripts/deploy-backend.sh 2>&1", $output, $code);
logMsg("Deploy finished with code: " . $code);
logMsg("Output: " . implode("\n", $output));

header('Content-Type: application/json');
echo json_encode([
    'success' => $code === 0,
    'message' => $code === 0 ? 'Deploy successful' : 'Deploy failed',
    'code' => $code
]);
