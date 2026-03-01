<?php
/**
 * Privacy Policy Page — RA 10173 (Data Privacy Act of 2012) Compliance
 * Public page — no login required
 */
$page_title = 'Privacy Policy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - ELMS Datamex</title>
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
        <h1><i class="bi bi-shield-lock me-2"></i>Privacy Policy</h1>
        <p class="effective-date">Effective Date: March 1, 2026 | Last Updated: March 1, 2026</p>
        <hr>

        <p>Datamex College of Saint Agnes ("we", "us", "the Institution") operates the Electronic Learning Management System (ELMS). This Privacy Policy explains how we collect, use, store, and protect your personal information in accordance with <strong>Republic Act No. 10173</strong> (Data Privacy Act of 2012), its Implementing Rules and Regulations (IRR), and the issuances of the National Privacy Commission (NPC).</p>

        <h2>1. Information We Collect</h2>
        <p>We collect and process the following personal data:</p>
        <ul>
            <li><strong>Identity Data:</strong> Full name, student/employee number, date of birth, gender</li>
            <li><strong>Contact Data:</strong> Email address, phone number, home address</li>
            <li><strong>Academic Data:</strong> Enrollment records, grades, class schedules, curriculum information, certificates</li>
            <li><strong>Financial Data:</strong> Tuition fee records, payment history, discounts, penalties</li>
            <li><strong>Account Data:</strong> Login credentials (email, hashed password), Google OAuth tokens (if linked)</li>
            <li><strong>Technical Data:</strong> IP address, browser type, login timestamps, session data</li>
            <li><strong>Audit Data:</strong> System activity logs for security monitoring</li>
        </ul>

        <h2>2. Legal Basis for Processing</h2>
        <p>We process your personal data based on the following lawful criteria under RA 10173:</p>
        <ul>
            <li><strong>Consent:</strong> You provide consent when you or your authorized representative agree to the terms at the time of enrollment or account creation.</li>
            <li><strong>Contractual Necessity:</strong> Processing is necessary for the performance of educational services.</li>
            <li><strong>Legal Obligation:</strong> Compliance with CHED, DepEd, or other regulatory requirements.</li>
            <li><strong>Legitimate Interest:</strong> Ensuring system security, preventing fraud, and improving educational services.</li>
        </ul>

        <h2>3. How We Use Your Information</h2>
        <ul>
            <li>Managing student enrollment and academic records</li>
            <li>Processing tuition fees, payments, and financial aid</li>
            <li>Facilitating online learning and class management</li>
            <li>Generating academic reports, certificates, and transcripts</li>
            <li>Communicating announcements and notifications</li>
            <li>Ensuring system security and preventing unauthorized access</li>
            <li>Complying with regulatory reporting requirements</li>
        </ul>

        <h2>4. Data Sharing and Disclosure</h2>
        <p>We do <strong>not</strong> sell your personal data. We may share your information with:</p>
        <ul>
            <li><strong>Authorized school personnel:</strong> Administrators, registrars, and teachers who need access to perform their duties</li>
            <li><strong>Government agencies:</strong> CHED, DepEd, or other regulatory bodies as required by law</li>
            <li><strong>Service providers:</strong> Email service providers, hosting providers (bound by data processing agreements)</li>
            <li><strong>Legal authorities:</strong> When required by law, court order, or legal process</li>
        </ul>

        <h2>5. Data Storage and Security</h2>
        <ul>
            <li>Passwords are stored using industry-standard one-way hashing (bcrypt)</li>
            <li>All sessions are protected with HTTP-only, same-site cookies</li>
            <li>Access to personal data is controlled by role-based access control (RBAC)</li>
            <li>System activity is logged for security auditing purposes</li>
            <li>Data is stored on secured servers with restricted access</li>
            <li>Regular security reviews and updates are performed</li>
        </ul>

        <h2>6. Data Retention</h2>
        <p>We retain your personal data for the following periods:</p>
        <ul>
            <li><strong>Academic records:</strong> Permanently, as required by CHED for transcript verification</li>
            <li><strong>Financial records:</strong> 10 years, in compliance with BIR requirements</li>
            <li><strong>Login/audit logs:</strong> 1 year, then anonymized or deleted</li>
            <li><strong>Inactive accounts:</strong> Data is anonymized after 5 years of inactivity</li>
        </ul>

        <h2>7. Your Rights Under RA 10173</h2>
        <p>As a data subject, you have the following rights:</p>
        <ul>
            <li><strong>Right to be Informed:</strong> You have the right to know how your data is being processed.</li>
            <li><strong>Right to Access:</strong> You may request a copy of your personal data that we hold.</li>
            <li><strong>Right to Rectification:</strong> You may request correction of inaccurate or incomplete data.</li>
            <li><strong>Right to Erasure:</strong> You may request deletion of your data, subject to legal retention requirements.</li>
            <li><strong>Right to Data Portability:</strong> You may request your data in a machine-readable format.</li>
            <li><strong>Right to Object:</strong> You may object to the processing of your data in certain circumstances.</li>
            <li><strong>Right to File a Complaint:</strong> You may file a complaint with the National Privacy Commission.</li>
        </ul>
        <p>To exercise these rights, please use the <strong>Data Privacy</strong> section in your Account Settings, or contact us directly.</p>

        <h2>8. Cookies</h2>
        <p>This system uses:</p>
        <ul>
            <li><strong>Session cookies:</strong> Essential for authentication and maintaining your login session. These are deleted when you log out or close your browser.</li>
            <li><strong>No tracking cookies:</strong> We do not use advertising or analytics tracking cookies.</li>
        </ul>

        <h2>9. Data Protection Officer</h2>
        <p>For questions, concerns, or to exercise your data privacy rights, contact:</p>
        <ul>
            <li><strong>Data Protection Officer</strong></li>
            <li>Datamex College of Saint Agnes</li>
            <li>Email: dpo@datamex.edu.ph</li>
        </ul>

        <h2>10. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. Significant changes will be communicated through the system. The "Last Updated" date at the top indicates the most recent revision.</p>

        <h2>11. Governing Law</h2>
        <p>This Privacy Policy is governed by the laws of the Republic of the Philippines, specifically RA 10173 (Data Privacy Act of 2012) and its Implementing Rules and Regulations.</p>

        <hr class="mt-4">
        <p class="text-center text-muted small mt-3">&copy; <?php echo date('Y'); ?> Datamex College of Saint Agnes. All rights reserved.</p>
    </div>
</div>
</body>
</html>
