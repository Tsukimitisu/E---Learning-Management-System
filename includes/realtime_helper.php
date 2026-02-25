<?php

if (!function_exists('normalize_realtime_role')) {
    function normalize_realtime_role($role) {
        if ($role === null || $role === '') {
            return null;
        }

        $normalized = strtolower(trim((string)$role));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized);

        $aliases = [
            '1' => 'super_admin',
            '2' => 'school_admin',
            '3' => 'branch_admin',
            '4' => 'registrar',
            '5' => 'teacher',
            '6' => 'student',
            'superadmin' => 'super_admin',
            'super_admin' => 'super_admin',
            'super admin' => 'super_admin',
            'schooladmin' => 'school_admin',
            'school_admin' => 'school_admin',
            'school admin' => 'school_admin',
            'school_head' => 'school_admin',
            'school head' => 'school_admin',
            'branchadmin' => 'branch_admin',
            'branch_admin' => 'branch_admin',
            'branch admin' => 'branch_admin',
            'registrar' => 'registrar',
            'teacher' => 'teacher',
            'student' => 'student'
        ];

        return $aliases[$normalized] ?? null;
    }
}

if (!function_exists('send_realtime_update')) {
    function send_realtime_update($event, $data, $role = null, $user_ids = null) {
        $url = defined('ELMS_REALTIME_BROADCAST_URL')
            ? ELMS_REALTIME_BROADCAST_URL
            : 'http://127.0.0.1:3000/api/broadcast';

        $safe_event = is_string($event) && trim($event) !== '' ? trim($event) : 'update';
        $normalized_user_ids = [];

        if ($user_ids !== null) {
            if (!is_array($user_ids)) {
                $user_ids = [$user_ids];
            }

            foreach ($user_ids as $user_id) {
                $user_id = (int)$user_id;
                if ($user_id > 0) {
                    $normalized_user_ids[$user_id] = $user_id;
                }
            }

            $normalized_user_ids = array_values($normalized_user_ids);
        }

        $payload = [
            'event' => $safe_event,
            'data' => $data,
            'role' => normalize_realtime_role($role)
        ];

        if (!empty($normalized_user_ids)) {
            $payload['user_ids'] = $normalized_user_ids;
            $payload['role'] = null;
        }

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        return $result !== false;
    }
}
