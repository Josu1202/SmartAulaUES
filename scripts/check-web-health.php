<?php

// Exercise the web process itself; a running container is not sufficient.
$context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
$body = @file_get_contents('http://127.0.0.1:8080/health/ready', false, $context);
if ($body === false || ! str_contains($http_response_header[0] ?? '', '200') || json_decode($body, true) !== ['status' => 'ok']) {
    fwrite(STDERR, "Web/database/schema/assets not ready. Inspect application logs.\n");
    exit(1);
}
echo "Web, database, schema and assets ready.\n";
