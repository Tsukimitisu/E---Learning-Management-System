<?php
require_once __DIR__ . '/../config/db.php';

echo "=== SHS TRACKS ===\n";
$r = $conn->query("SELECT * FROM shs_tracks ORDER BY id");
while ($row = $r->fetch_assoc()) {
    echo "ID: {$row['id']} | Code: {$row['track_code']} | Name: {$row['track_name']} | Active: {$row['is_active']}\n";
}

echo "\n=== SHS STRANDS (with track_id) ===\n";
$r2 = $conn->query("SELECT s.id, s.strand_code, s.strand_name, s.track_id, s.is_active, t.track_name, t.track_code as t_code FROM shs_strands s LEFT JOIN shs_tracks t ON s.track_id = t.id ORDER BY s.id");
while ($row = $r2->fetch_assoc()) {
    echo "ID:{$row['id']} | Code:{$row['strand_code']} | Name:{$row['strand_name']} | track_id:{$row['track_id']} | TrackCode:{$row['t_code']} | TrackName:{$row['track_name']} | Active:{$row['is_active']}\n";
}

echo "\n=== SHS GRADE LEVELS ===\n";
$r3 = $conn->query("SELECT * FROM shs_grade_levels ORDER BY id");
while ($row = $r3->fetch_assoc()) {
    echo "ID:{$row['id']} | strand_id:{$row['strand_id']} | Level:{$row['grade_level']} | Name:{$row['grade_name']}\n";
}
