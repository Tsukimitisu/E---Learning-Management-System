<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    header('Location: ../../../index.php');
    exit();
}

$student_id = (int)($_GET['student_id'] ?? 0);
$semester = clean_input($_GET['semester'] ?? '');
$academic_year = clean_input($_GET['academic_year'] ?? '');

if ($student_id <= 0) {
    echo 'Invalid student ID';
    exit();
}

$student = $conn->query("
    SELECT s.student_no, CONCAT(up.first_name, ' ', up.last_name) as student_name,
           c.course_code, c.title as course_title
    FROM students s
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN courses c ON s.course_id = c.id
    WHERE s.user_id = $student_id
")->fetch_assoc();

$payments = $conn->query("
    SELECT amount, status, created_at
    FROM payments
    WHERE student_id = $student_id AND status = 'verified'
    ORDER BY created_at DESC
");

$total_paid = 0;
$payment_rows = [];
while ($row = $payments->fetch_assoc()) {
    $payment_rows[] = $row;
    $total_paid += (float)$row['amount'];
}

$enrollments = $conn->query("
    SELECT e.status, e.created_at
    FROM enrollments e
    WHERE e.student_id = $student_id
    ORDER BY e.created_at DESC
");

$clearance_status = $total_paid > 0 ? 'CLEARED' : 'NOT CLEARED';
$reference_no = 'CLR-' . date('Ymd') . '-' . str_pad($student_id, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Financial Clearance</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .clearance-report { max-width: 900px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        header { text-align: center; margin-bottom: 20px; }
        h2 { margin: 10px 0; }
        .student-info, .summary { margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f8f9fa; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .badge.success { background: #28a745; color: #fff; }
        .badge.danger { background: #dc3545; color: #fff; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .print-btn { margin: 10px 0; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <div class="clearance-report">
        <header>
            <img src="../../../assets/image/datamexlogo.png" alt="Logo" height="60">
            <h2>Financial Clearance Certificate</h2>
            <small>Reference No: <?php echo htmlspecialchars($reference_no); ?></small>
        </header>

        <section class="student-info">
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student['student_name'] ?? 'N/A'); ?></p>
            <p><strong>Student Number:</strong> <?php echo htmlspecialchars($student['student_no'] ?? 'N/A'); ?></p>
            <p><strong>Program:</strong> <?php echo htmlspecialchars($student['course_code'] ?? 'SHS'); ?> - <?php echo htmlspecialchars($student['course_title'] ?? 'N/A'); ?></p>
            <?php if ($semester): ?><p><strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?></p><?php endif; ?>
            <?php if ($academic_year): ?><p><strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?></p><?php endif; ?>
        </section>

        <section class="payment-history">
            <h4>Payment History (Verified)</h4>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payment_rows) === 0): ?>
                        <tr><td colspan="3" style="text-align:center;">No verified payments found</td></tr>
                    <?php else: ?>
                        <?php foreach ($payment_rows as $p): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                                <td><?php echo format_currency($p['amount']); ?></td>
                                <td>Verified</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="summary">
            <p><strong>Total Paid:</strong> <?php echo format_currency($total_paid); ?></p>
            <p><strong>Clearance Status:</strong> 
                <span class="badge <?php echo $clearance_status === 'CLEARED' ? 'success' : 'danger'; ?>">
                    <?php echo $clearance_status; ?>
                </span>
            </p>
        </section>

        <section class="enrollment-history">
            <h4>Enrollment Records</h4>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($enroll = $enrollments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($enroll['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($enroll['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <div class="footer">
            <div>
                <p>Registrar Signature:</p>
                <div style="border-top: 1px solid #333; width: 200px;"></div>
            </div>
            <div>
                <p>Date Generated: <?php echo date('M d, Y'); ?></p>
            </div>
        </div>

        <div class="print-btn">
            <button onclick="window.print();">Print</button>
        </div>
    </div>
</body>
</html>
