# Admin System - Quick Reference Card

## 🚀 Quick Access Links

```
Dashboard:           /modules/super_admin/dashboard.php
Settings:            /modules/super_admin/system_settings.php
Security/Audit:      /modules/super_admin/security.php
Branches (School):   /modules/school_admin/branches.php

API Tests:           /modules/super_admin/api_tests.php
DB Verify:           /modules/super_admin/process/verify_database.php
```

---

## 📌 First Time Setup (5 Minutes)

### Step 1: Create Database Tables
```
1. Click: /modules/super_admin/process/verify_database.php
2. Verify all tables show "success"
3. Default settings auto-created
```

### Step 2: Run API Tests
```
1. Click: /modules/super_admin/api_tests.php
2. Click "Test Dashboard API" 
3. Verify "PASS" status
4. All other tests should also PASS
```

### Step 3: Access Features
```
Super Admin: /modules/super_admin/dashboard.php
School Admin: /modules/school_admin/branches.php
```

---

## 🎯 Feature Quick Start

### Dashboard - See Live Metrics
```
What you see:
├─ Total Users (live count)
├─ Total Branches (live count)
├─ Failed Logins Today (live count)
├─ Locked Accounts (live count)
├─ System Health Status
├─ Maintenance Mode Badge
└─ Recent Audit Logs

Auto-refreshes: Every 30 seconds
No action required: Just view!
```

### Settings - Configure System Rules
```
What you can change:
├─ Allow/disable user registration
├─ Set max login attempts (5-20)
├─ Set lockout duration (10-60 min)
├─ Set password min length
├─ Require uppercase in passwords
├─ Require numbers in passwords
├─ Require special characters
├─ Set session timeout (10-480 min)
└─ Enable/disable maintenance mode

How to change:
1. Modify the dropdown or input
2. Click "Save" button
3. See success message
4. Changes apply to ALL users on next login
```

### Audit Logs - Track All Admin Actions
```
What you see:
├─ Who performed the action
├─ What they did
├─ When they did it
└─ Their IP address

How to use:
1. View table with 50 records per page
2. Search by action or user name
3. Filter by date range
4. Click "Export Audit Logs" for CSV
5. Download contains up to 10,000 records
```

### Security Logs - Monitor Events
```
What you see:
├─ Failed login attempts
├─ Suspicious activities
├─ Force logouts
├─ Severity level (critical→info)
└─ Event details

How to use:
1. View all security events
2. Filter by event type
3. Filter by severity level
4. Search in event details
5. Export for compliance
```

### Force Logout - Terminate User Sessions
```
How to use:
1. Go to Security tab → Session Management
2. Search for user by name
3. Click "Force Logout"
4. Confirm in dialog
5. User will be logged out immediately

Important:
- Cannot logout yourself (safety feature)
- User can login again immediately
- Logged as security event
```

### Branch Management - Manage Locations
```
What you can do:
├─ Add new branch (name, address, contact)
├─ Edit existing branch details
├─ View staff count per branch
├─ Delete branch (only if no staff)
└─ Prevent duplicate names

How to use:
1. Go to Branches page
2. Click "Add Branch" button
3. Fill in details and save
4. Edit any branch by clicking pencil icon
5. Delete only appears if no staff
```

---

## 🔑 API Reference Cheat Sheet

### Get Dashboard Stats
```
URL: /modules/super_admin/process/dashboard_stats_api.php
Method: GET
Returns: {
    success: true,
    stats: {
        total_users: 150,
        total_branches: 5,
        failed_logins_today: 3,
        locked_accounts: 2,
        system_health: "Normal",
        is_maintenance: false
    }
}
```

### List Audit Logs
```
URL: /modules/super_admin/process/audit_logs_api.php
Method: GET
Parameters:
  - action: "list" (or "export")
  - page: 1, 2, 3...
  - search: optional
  - start_date: YYYY-MM-DD (optional)
Returns: {
    status: "success",
    logs: [...],
    pagination: {page, limit, total, pages}
}
```

### List Security Logs
```
URL: /modules/super_admin/process/security_logs_api.php
Method: GET
Parameters:
  - action: "list" (or "export")
  - page: 1, 2, 3...
  - event_type: optional
  - severity: optional
  - search: optional
Returns: {
    status: "success",
    logs: [...],
    pagination: {page, limit, total, pages}
}
```

### Update Setting
```
URL: /modules/super_admin/process/update_global_setting.php
Method: POST
Body:
  setting_key: "maintenance_mode"
  setting_value: "1" (or "0")
Returns: {
    status: "success",
    message: "Setting updated"
}
```

### List Branches
```
URL: /modules/school_admin/process/branch_management_api.php
Method: POST
Parameters:
  action: "list"
Returns: {
    status: "success",
    branches: [...]
}
```

### Create Branch
```
URL: /modules/school_admin/process/branch_management_api.php
Method: POST
Parameters:
  action: "create"
  branch_name: "Main Campus"
  branch_address: "123 Main St"
  branch_contact: "+63 9XX XXXX"
Returns: {
    status: "success",
    message: "Branch created"
}
```

### Force Logout
```
URL: /modules/super_admin/process/force_logout.php
Method: POST
Parameters:
  user_id: 123
Returns: {
    status: "success",
    message: "User logged out",
    sessions_invalidated: 1
}
```

---

## 🎯 Common Tasks

### Task: Change Session Timeout
```
1. Go to: System Settings
2. Find: "Session Timeout"
3. Change value (in minutes)
4. Click Save
5. Applies to next login
```

### Task: Lock Out a User
```
1. Go to: Settings → Max Login Attempts
2. Set to lower number (e.g., 3)
3. After 3 failed logins, user is locked
4. Unlock by editing user or waiting for timeout
```

### Task: Enable Maintenance Mode
```
1. Go to: System Settings
2. Find: "Maintenance Mode"
3. Set to "Enabled"
4. Click Save
5. All users logged out, login disabled
6. Only super_admin can login
```

### Task: Find Who Changed Settings
```
1. Go to: Security → Audit Trail
2. Search for "setting" or "admin"
3. See who changed what and when
4. Export for compliance
```

### Task: Export Logs for Audit
```
1. Go to: Security → Audit Trail
2. Click "Export Audit Logs"
3. CSV downloads automatically
4. Open in Excel to analyze
```

### Task: Monitor Failed Logins
```
1. Dashboard shows: "Failed Logins Today"
2. Go to: Security Logs
3. Filter by: Event Type = "Login Failed"
4. Review IP addresses of attempts
5. Consider blocking if too many
```

---

## ⚠️ Important Notes

### Settings Changes
- ✅ Apply to ALL users
- ✅ Take effect on NEXT login
- ✅ Don't affect current sessions
- ❌ Don't kick out logged-in users

### Force Logout
- ✅ Kicks user out IMMEDIATELY
- ✅ User can login again right away
- ✅ Logged as security event
- ❌ Cannot logout yourself

### Maintenance Mode
- ✅ Disables user logins
- ✅ Super Admin can still login
- ✅ Shows "System Under Maintenance" message
- ❌ Existing sessions not kicked out

### Audit Logs
- ✅ Cannot be edited
- ✅ Cannot be deleted
- ✅ Include IP address
- ✅ Include timestamps
- ❌ Not real-time (slight delay)

### Branch Management
- ✅ Can create new branch
- ✅ Can edit branch details
- ✅ Cannot delete if staff assigned
- ❌ Cannot have duplicate names

---

## 🔒 Security Best Practices

### Password Settings
```
Minimum Length: 8+ characters
Require Uppercase: Enable
Require Lowercase: Enable
Require Numbers: Enable
Require Special: Optional (disable for now)
```

### Login Security
```
Max Attempts: 5
Lockout Duration: 30 minutes
Session Timeout: 30 minutes
```

### Monitoring
```
Check Failed Logins: Daily
Review Audit Logs: Weekly
Check Suspicious Activity: Daily
Export Reports: Monthly
```

---

## 🆘 Troubleshooting

### Problem: Dashboard cards show 0
```
Solution:
1. Run API test (/api_tests.php)
2. Check browser console for errors
3. Verify database tables exist
4. Check PHP error logs
```

### Problem: Settings not applying
```
Solution:
1. Settings apply on NEXT login
2. User must logout and login again
3. Or use Force Logout feature
4. Check if setting saved successfully
```

### Problem: Export button not working
```
Solution:
1. Check browser console
2. Verify downloads folder exists
3. Allow pop-ups in browser
4. Try different browser
```

### Problem: Can't delete branch
```
Solution:
1. Branch must have no staff
2. Reassign staff to other branch first
3. Then delete branch
4. Check if you have permission
```

### Problem: Force logout not working
```
Solution:
1. Cannot logout yourself
2. Verify user exists
3. Check if already logged out
4. Try again after refresh
```

---

## 📞 Quick Help

| Issue | Solution |
|-------|----------|
| Database error | Run verify_database.php |
| API error | Run api_tests.php |
| JavaScript error | Check browser console |
| Permission denied | Verify your role |
| Settings not applying | User must re-login |
| Export not downloading | Check browser download settings |
| Branch delete disabled | Reassign staff first |

---

## 🚀 Performance Tips

### For Large Datasets
- Use pagination (50 records per page)
- Filter before exporting
- Export limit is 10,000 rows
- Use date range filters

### For Better Performance
- Avoid exporting all-time data
- Use date range filters
- Let dashboard auto-refresh
- Don't refresh manually too often

### For Security
- Review logs regularly
- Monitor failed logins
- Check for suspicious activities
- Archive old logs (90+ days)

---

## 📱 Mobile Access

All admin pages work on mobile:
- Dashboard: Responsive design
- Settings: Full access
- Audit Logs: Paginated view
- Security: Tab navigation
- Branches: Responsive table

**Note**: Some features work better on desktop

---

## ✅ Status Indicators

### Dashboard Cards
- 🟢 Green = Normal
- 🟡 Yellow = Warning (high counts)
- 🔴 Red = Critical (very high counts)

### Maintenance Mode Badge
- 🟢 GREEN = Normal Operation
- 🟠 ORANGE = Maintenance Mode

### Security Log Severity
- 🔴 Critical = Immediate action needed
- 🟠 High = Review soon
- 🟡 Medium = Monitor
- 🟢 Low = Logged for audit
- ⚪ Info = Informational

---

**Last Updated**: January 30, 2026

*Keep this card handy for quick reference!*
