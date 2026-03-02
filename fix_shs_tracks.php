<?php
/**
 * Fix SHS Tracks: Consolidate to 2 proper DepEd K-12 tracks
 *   1. Academic Track (ACAD) — strands: STEM, ABM, HUMSS, GAS
 *   2. TVL Track (TVL) — strands: ICT, HE (and any future TVL strands)
 * 
 * Deactivates: Arts and Design Track, Sports Track, and the individual
 * Academic sub-tracks that were incorrectly stored as separate tracks.
 */
require_once __DIR__ . '/config/db.php';

echo "<h2>SHS Tracks Fix Migration</h2><pre>\n";

$statements = [
    // 1. Rename track 1 from "Academic Track - STEM" to proper "Academic Track"
    "UPDATE shs_tracks SET track_name = 'Academic Track', track_code = 'ACAD', description = 'Prepares students who wish to pursue higher education or college degrees. Contains strands like STEM, ABM, HUMSS, and GAS.' WHERE id = 1",

    // 2. Rename track 4 to clean "TVL Track" label and code
    "UPDATE shs_tracks SET track_name = 'TVL Track', track_code = 'TVL', description = 'Technical-Vocational-Livelihood track for students pursuing technical/vocational skills. Contains strands like ICT, Home Economics, and more.' WHERE id = 4",

    // 3. Deactivate incorrect/extra tracks (2=ABM, 3=HUMSS, 5=ARTS, 6=SPORTS)
    "UPDATE shs_tracks SET is_active = 0 WHERE id IN (2, 3, 5, 6)",

    // 4. Reassign all Academic strands (STEM=1, ABM=2, HUMSS=3, GAS=4) to track 1 (Academic Track)
    "UPDATE shs_strands SET track_id = 1 WHERE id IN (1, 2, 3, 4)",

    // 5. Reassign all TVL strands (ICT=5, HE=6) to track 4 (TVL Track)
    "UPDATE shs_strands SET track_id = 4 WHERE id IN (5, 6)",
];

$success = 0;
$failed = 0;

foreach ($statements as $sql) {
    if ($conn->query($sql)) {
        echo "✅ OK: " . substr($sql, 0, 80) . "...\n";
        $success++;
    } else {
        echo "❌ FAIL: " . $conn->error . "\n   SQL: $sql\n";
        $failed++;
    }
}

echo "\n--- Results: $success passed, $failed failed ---\n\n";

// Verify
echo "=== TRACKS (after fix) ===\n";
$r = $conn->query("SELECT id, track_code, track_name, is_active FROM shs_tracks ORDER BY id");
while ($row = $r->fetch_assoc()) {
    $status = $row['is_active'] ? 'ACTIVE' : 'INACTIVE';
    echo "  ID:{$row['id']} | {$row['track_code']} | {$row['track_name']} | $status\n";
}

echo "\n=== STRANDS (after fix) ===\n";
$r2 = $conn->query("SELECT s.id, s.strand_code, s.strand_name, s.track_id, t.track_name FROM shs_strands s LEFT JOIN shs_tracks t ON s.track_id = t.id ORDER BY s.track_id, s.id");
while ($row = $r2->fetch_assoc()) {
    echo "  ID:{$row['id']} | {$row['strand_code']} | {$row['strand_name']} → Track: {$row['track_name']} (id:{$row['track_id']})\n";
}

echo "\n</pre><p><strong>Done!</strong> You can delete this file after running.</p>";
