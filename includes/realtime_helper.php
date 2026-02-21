<?php
// Helper to send real-time events to Socket.IO server
function send_realtime_update($event, $data, $role = null) {
    $url = 'http://localhost:3000/api/broadcast';
    $payload = [
        'event' => $event,
        'data' => $data,
        'role' => $role
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload),
            'timeout' => 2
        ]
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
