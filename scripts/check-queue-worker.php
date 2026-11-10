<?php

// Check the dedicated queue-worker process even when Docker uses init/tini as PID 1.
// Liveness only: b07:status checks queue age and failures separately.
foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
    $command = @file_get_contents($path);
    if ($command === false) {
        continue;
    }
    $command = str_replace("\0", ' ', $command);
    if (str_contains($command, 'artisan queue:work')) {
        echo "B07: queue worker process is running.\n";
        exit(0);
    }
}
fwrite(STDERR, "B07: queue worker process is not running.\n");
exit(1);
