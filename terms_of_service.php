<?php
/**
 * Terms of Service Page — Legal compliance
 * Public page — no login required
 */
$page_title = 'Terms of Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - ELMS Datamex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --maroon: #800000; --blue: #1a237e; }
        body { font-family: 'Public Sans', sans-serif; background: #f8f9fa; }
        .policy-container { max-width: 860px; margin: 0 auto; padding: 40px 20px; }
        .policy-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 50px; }
        .policy-card h1 { color: var(--maroon); font-weight: 700; margin-bottom: 8px; }
        .policy-card h2 { color: var(--blue); font-size: 1.25rem; font-weight: 700; margin-top: 32px; margin-bottom: 12px; }
        .policy-card p, .policy-card li { color: #555; line-height: 1.8; font-size: 0.95rem; }
        .policy-card ul { padding-left: 20px; }
        .effective-date { color: #888; font-size: 0.85rem; }
        .back-link { color: var(--maroon); text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="policy-container">
    <div class="mb-4"><a href="index.php" class="back-link"><i class="bi bi-arrow-left me-1"></i> Back to Login</a></div>
    <div class="policy-card">
        <h1><i class="bi bi-file-earmark-text me-2"></i>Terms of Service</h1>
        <p class="effective-date">Effective Date: March 1, 2026 | Last Updated: March 1, 2026</p>
        <hr>

        <p>These Terms of Service ("Terms") govern your access to and use of the Electronic Learning Management System (ELMS) operated by Datamex College of Saint Agnes ("the Institution"). By accessing or using this system, you agree to be bound by these Terms.</p>

        <h2>1. Account and Access</h2>
        <ul>
            <li>Accounts are created by authorized administrators. You may not share your credentials with any third party.</li>
            <li>You are responsible for maintaining the confidentiality of your login credentials.</li>
            <li>You must immediately notify the registrar or administrator of any unauthorized access to your account.</li>
            <li>The Institution reserves the right to suspend or terminate accounts for violation of these Terms.</li>
        </ul>

        <h2>2. Acceptable Use</h2>
        <p>When using ELMS, you agree to:</p>
        <ul>
            <li>Use the system only for its intended educational and administrative purposes</li>
            <li>Provide accurate and truthful information</li>
            <li>Respect the privacy and data of other users</li>
            <li>Not attempt to access data or functions beyond your assigned role</li>
            <li>Not upload malicious files, scripts, or harmful content</li>
            <li>Not attempt to disrupt, overload, or compromise system security</li>
            <li>Comply with all applicable Philippine laws and regulations</li>
        </ul>

        <h2>3. Academic Records</h2>
        <ul>
            <li>Grades, enrollment records, and academic data displayed in ELMS are official records of the Institution.</li>
            <li>Students may view their grades and enrollment status through their dashboard.</li>
            <li>Any discrepancies in academic records should be reported to the Registrar's Office.</li>
            <li>Official transcripts and certifications must be requested through proper institutional channels.</li>
        </ul>

        <h2>4. Tuition and Payments</h2>
        <ul>
            <li>Tuition fees, discounts, and penalties displayed in the system reflect current institutional policies.</li>
            <li>Payment records in the system are for informational purposes. Official receipts are issued by the Finance/Registrar's Office.</li>
            <li>Down payment requirements must be met as a condition of enrollment confirmation.</li>
            <li>Refund policies follow the Institution's established guidelines and applicable CHED regulations.</li>
        </ul>

        <h2>5. Learning Materials</h2>
        <ul>
            <li>Learning materials uploaded by teachers are for educational use within the Institution only.</li>
            <li>You may not redistribute, publish, or sell any materials obtained through ELMS.</li>
            <li>The Institution respects intellectual property rights. If you believe any material infringes on copyright, please contact the administrator.</li>
        </ul>

        <h2>6. Privacy and Data Protection</h2>
        <p>Your use of ELMS is also governed by our <a href="privacy_policy.php" class="text-decoration-none" style="color: var(--maroon); font-weight: 600;">Privacy Policy</a>, which details how we collect, use, and protect your personal information in compliance with RA 10173 (Data Privacy Act of 2012).</p>

        <h2>7. System Availability</h2>
        <ul>
            <li>We strive to maintain system availability but do not guarantee uninterrupted access.</li>
            <li>Scheduled maintenance may temporarily affect system availability.</li>
            <li>The Institution is not liable for any loss arising from system downtime or technical issues.</li>
        </ul>

        <h2>8. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, the Institution shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of ELMS.</p>

        <h2>9. Modifications</h2>
        <p>The Institution may update these Terms from time to time. Continued use of the system after changes constitutes acceptance of the modified Terms. Users will be notified of significant changes through the system.</p>

        <h2>10. Governing Law</h2>
        <p>These Terms are governed by the laws of the Republic of the Philippines. Any disputes shall be resolved in the appropriate courts of the Philippines.</p>

        <h2>11. Contact Information</h2>
        <p>For questions about these Terms, contact:</p>
        <ul>
            <li>Datamex College of Saint Agnes</li>
            <li>Email: admin@datamex.edu.ph</li>
        </ul>

        <hr class="mt-4">
        <p class="text-center text-muted small mt-3">&copy; <?php echo date('Y'); ?> Datamex College of Saint Agnes. All rights reserved.</p>
    </div>
</div>
</body>
</html>
