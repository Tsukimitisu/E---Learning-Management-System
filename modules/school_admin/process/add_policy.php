<?php
header('Content-Type: application/json');
http_response_code(410);
echo json_encode(['status' => 'error', 'message' => 'Academic policies feature has been removed']);
exit();
?>
