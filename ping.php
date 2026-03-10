<?php
// Simple health check - no redirects, no HTML
http_response_code(200);
header('Content-Type: text/plain');
echo 'pong';
exit;
