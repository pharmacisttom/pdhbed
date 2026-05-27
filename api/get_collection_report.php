<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db_connect_pdo.php';

function is_valid_date($date) {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function fiscal_range($today) {
    $year = (int)date('Y', strtotime($today));
    $month = (int)date('m', strtotime($today));
    $startYear = $month >= 10 ? $year : $year - 1;
    return [$startYear . '-10-01', ($startYear + 1) . '-09-30'];
}

function period_key_and_label($date, $mode) {
    $ts = strtotime($date);
    if ($mode === 'weekly') {
        return [
            date('o-\WW', $ts),
            'สัปดาห์ ' . date('W', $ts) . '/' . ((int)date('o', $ts) + 543)
        ];
    }
    if ($mode === 'monthly') {
        return [
            date('Y-m', $ts),
            date('m', $ts) . '/' . ((int)date('Y', $ts) + 543)
        ];
    }
    if ($mode === 'fiscal') {
        $year = (int)date('Y', $ts);
        $month = (int)date('m', $ts);
        $fiscalYear = $month >= 10 ? $year + 1 : $year;
        return [
            (string)$fiscalYear,
            'ปีงบ ' . ($fiscalYear + 543)
        ];
    }
    return [
        date('Y-m-d', $ts),
        date('d/m/', $ts) . ((int)date('Y', $ts) + 543)
    ];
}

try {
    if (!isset($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $mode = $_GET['mode'] ?? 'daily';
    $allowedModes = ['daily', 'weekly', 'monthly', 'fiscal', 'custom'];
    if (!in_array($mode, $allowedModes, true)) {
        $mode = 'daily';
    }

    $today = date('Y-m-d');
    if ($mode === 'daily') {
        $startDate = $today;
        $endDate = $today;
        $groupMode = 'daily';
    } elseif ($mode === 'weekly') {
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $groupMode = 'daily';
    } elseif ($mode === 'monthly') {
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $groupMode = 'daily';
    } elseif ($mode === 'fiscal') {
        [$startDate, $endDate] = fiscal_range($today);
        $groupMode = 'monthly';
    } else {
        $startDate = $_GET['start'] ?? date('Y-m-01');
        $endDate = $_GET['end'] ?? $today;
        $groupMode = $_GET['group'] ?? 'daily';
        if (!in_array($groupMode, ['daily', 'weekly', 'monthly', 'fiscal'], true)) {
            $groupMode = 'daily';
        }
    }

    if (!is_valid_date($startDate) || !is_valid_date($endDate)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
        exit;
    }
    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $pdo = get_connection('app', 'test');
    $stmt = $pdo->prepare(
        "SELECT id, hn, an, pt_name, receipt_date, receipt_no, item_desc, amount, recorded_by, created_at
         FROM custom_deposits
         WHERE receipt_date BETWEEN :start_date AND :end_date
         ORDER BY receipt_date ASC, created_at ASC, id ASC"
    );
    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [];
    $items = [];
    $recorders = [];
    $totalAmount = 0;

    foreach ($rows as $row) {
        $amount = (float)$row['amount'];
        [$key, $label] = period_key_and_label($row['receipt_date'], $groupMode);

        if (!isset($summary[$key])) {
            $summary[$key] = ['key' => $key, 'label' => $label, 'amount' => 0, 'count' => 0];
        }
        $summary[$key]['amount'] += $amount;
        $summary[$key]['count']++;

        $item = trim((string)$row['item_desc']);
        if ($item === '') {
            $item = 'ไม่ระบุรายการ';
        }
        if (!isset($items[$item])) {
            $items[$item] = ['name' => $item, 'amount' => 0, 'count' => 0];
        }
        $items[$item]['amount'] += $amount;
        $items[$item]['count']++;

        $recorder = trim((string)$row['recorded_by']);
        if ($recorder === '') {
            $recorder = 'ไม่ระบุผู้บันทึก';
        }
        if (!isset($recorders[$recorder])) {
            $recorders[$recorder] = ['name' => $recorder, 'amount' => 0, 'count' => 0];
        }
        $recorders[$recorder]['amount'] += $amount;
        $recorders[$recorder]['count']++;

        $totalAmount += $amount;
    }

    usort($items, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($recorders, fn($a, $b) => $b['amount'] <=> $a['amount']);

    echo json_encode([
        'status' => 'success',
        'mode' => $mode,
        'group' => $groupMode,
        'start' => $startDate,
        'end' => $endDate,
        'summary' => array_values($summary),
        'items' => array_slice(array_values($items), 0, 10),
        'recorders' => array_slice(array_values($recorders), 0, 10),
        'transactions' => array_reverse($rows),
        'totals' => [
            'amount' => $totalAmount,
            'count' => count($rows),
            'average' => count($rows) > 0 ? $totalAmount / count($rows) : 0
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
