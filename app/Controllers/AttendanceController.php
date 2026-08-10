<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\GeoService;
use App\Services\View;

class AttendanceController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $branchId = $user['branch_id'] ?? (int) $db->query('SELECT id FROM branches WHERE merchant_id = ' . (int) $user['merchant_id'] . ' ORDER BY id LIMIT 1')->fetchColumn();

        $branchStmt = $db->prepare('SELECT * FROM branches WHERE id = ?');
        $branchStmt->execute([$branchId]);
        $branch = $branchStmt->fetch();

        $shiftStmt = $db->prepare('SELECT * FROM attendance_shifts WHERE branch_id = ? ORDER BY start_time LIMIT 1');
        $shiftStmt->execute([$branchId]);
        $shift = $shiftStmt->fetch();

        $lastLogStmt = $db->prepare('SELECT * FROM attendance_logs WHERE user_id = ? ORDER BY clocked_at DESC LIMIT 1');
        $lastLogStmt->execute([$user['id']]);
        $lastLog = $lastLogStmt->fetch();
        $nextAction = (!$lastLog || $lastLog['clock_type'] === 'out') ? 'in' : 'out';

        $monthStart = date('Y-m-01');
        $stats = [
            'on_time' => (int) $db->query("SELECT COUNT(DISTINCT DATE(clocked_at)) FROM attendance_logs WHERE user_id={$user['id']} AND clock_type='in' AND within_geofence=1 AND clocked_at >= '$monthStart'")->fetchColumn(),
            'out_of_area' => (int) $db->query("SELECT COUNT(*) FROM attendance_logs WHERE user_id={$user['id']} AND clock_type='in' AND within_geofence=0 AND clocked_at >= '$monthStart'")->fetchColumn(),
        ];

        View::render('attendance/index', compact('user', 'branch', 'shift', 'nextAction', 'stats'));
    }

    public function clock(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $branchId = $user['branch_id'] ?? (int) $db->query('SELECT id FROM branches WHERE merchant_id = ' . (int) $user['merchant_id'] . ' ORDER BY id LIMIT 1')->fetchColumn();

        $lat = (float) ($_POST['lat'] ?? 0);
        $lng = (float) ($_POST['lng'] ?? 0);
        $type = ($_POST['type'] ?? 'in') === 'out' ? 'out' : 'in';

        $branchStmt = $db->prepare('SELECT * FROM branches WHERE id = ?');
        $branchStmt->execute([$branchId]);
        $branch = $branchStmt->fetch();

        $distance = GeoService::distanceMeters($lat, $lng, (float) $branch['lat'], (float) $branch['lng']);
        $within = $distance <= (float) $branch['geofence_radius_m'];

        $shiftStmt = $db->prepare('SELECT id FROM attendance_shifts WHERE branch_id = ? ORDER BY start_time LIMIT 1');
        $shiftStmt->execute([$branchId]);
        $shiftId = $shiftStmt->fetchColumn() ?: null;

        $stmt = $db->prepare('INSERT INTO attendance_logs (user_id, branch_id, shift_id, clock_type, lat, lng, distance_m, within_geofence, device_fingerprint) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $branchId, $shiftId, $type, $lat, $lng, $distance, $within ? 1 : 0, $_SERVER['HTTP_USER_AGENT'] ?? null]);

        View::json([
            'ok' => true,
            'within_geofence' => $within,
            'distance_m' => round($distance, 1),
            'clock_type' => $type,
            'clocked_at' => date('H:i:s'),
        ]);
    }
}
