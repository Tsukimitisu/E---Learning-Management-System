<?php
require_once '../../../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != ROLE_REGISTRAR) {
    die('Unauthorized');
}

$payment_id = (int)($_GET['id'] ?? 0);

if ($payment_id <= 0) {
    die('Invalid payment ID');
}

// Get payment details
$payment = $conn->query("
    SELECT p.*, s.student_no, 
           CONCAT(up.first_name, ' ', up.last_name) as student_name,
           CONCAT(rec.first_name, ' ', rec.last_name) as recorded_by_name,
           b.name as branch_name,
           ay.year_name
    FROM payments p
    INNER JOIN students s ON p.student_id = s.user_id
    INNER JOIN user_profiles up ON s.user_id = up.user_id
    LEFT JOIN user_profiles rec ON p.recorded_by = rec.user_id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN academic_years ay ON p.academic_year_id = ay.id
    WHERE p.id = $payment_id
")->fetch_assoc();

if (!$payment) {
    die('Payment not found');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Official Receipt - <?php echo $payment['or_number']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            max-width: 350px;
            margin: 0 auto;
            border: 1px solid #000;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
        }
        .header p {
            margin: 5px 0;
            font-size: 11px;
        }
        .or-number {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #c00;
            margin: 15px 0;
        }
        .details {
            margin: 15px 0;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 5px 0;
            vertical-align: top;
        }
        .details .label {
            font-weight: bold;
            width: 120px;
        }
        .amount-box {
            background: #f5f5f5;
            border: 2px solid #000;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
        }
        .amount-box .label {
            font-size: 11px;
            color: #666;
        }
        .amount-box .amount {
            font-size: 24px;
            font-weight: bold;
            color: #006600;
        }
        .footer {
            border-top: 1px dashed #999;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
        .signature {
            margin-top: 30px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin: 0 auto;
            padding-top: 5px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h2>OFFICIAL RECEIPT</h2>
            <p><strong><?php echo htmlspecialchars($payment['branch_name'] ?? 'ELMS'); ?></strong></p>
            <p>Academic Year: <?php echo htmlspecialchars($payment['year_name'] ?? 'N/A'); ?></p>
        </div>
        
        <div class="or-number">
            OR No: <?php echo htmlspecialchars($payment['or_number']); ?>
        </div>
        
        <div class="details">
            <table>
                <tr>
                    <td class="label">Date:</td>
                    <td><?php echo date('F d, Y', strtotime($payment['created_at'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Time:</td>
                    <td><?php echo date('h:i A', strtotime($payment['created_at'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Reference No:</td>
                    <td><?php echo htmlspecialchars($payment['reference_no']); ?></td>
                </tr>
                <tr>
                    <td class="label">Student No:</td>
                    <td><?php echo htmlspecialchars($payment['student_no']); ?></td>
                </tr>
                <tr>
                    <td class="label">Student Name:</td>
                    <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                </tr>
                <tr>
                    <td class="label">Payment For:</td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Semester:</td>
                    <td><?php echo ucfirst($payment['semester'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">Payment Method:</td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                </tr>
                <?php if ($payment['description']): ?>
                <tr>
                    <td class="label">Description:</td>
                    <td><?php echo htmlspecialchars($payment['description']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="amount-box">
            <div class="label">AMOUNT PAID</div>
            <div class="amount">₱<?php echo number_format($payment['amount'], 2); ?></div>
        </div>
        
        <div class="signature">
            <div class="signature-line">
                <?php echo htmlspecialchars($payment['recorded_by_name'] ?? 'Registrar'); ?>
                <br><small>Cashier/Registrar</small>
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated receipt.</p>
            <p>Printed: <?php echo date('M d, Y h:i A'); ?></p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer;">
            Print Receipt
        </button>
    </div>
</body>
</html>
