<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'PHP fatal error: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
});

require __DIR__ . '/../config/db.php';
require __DIR__ . '/db_connect_pdo.php';
require __DIR__ . '/cost_helpers.php';

function utf8_clean_value($value) {
    if ($value === null) return '';
    $str = (string)$value;
    if (!mb_detect_encoding($str, 'utf-8', true)) {
        return iconv('tis-620', 'utf-8//IGNORE', $str);
    }
    return $str;
}

function clean_bed_value($value) {
    $str = utf8_clean_value($value);
    $str = str_replace("\xEF\xBF\xBD", '', $str);
    $str = preg_replace('/[^\p{Thai}A-Za-z0-9\/\-\s\.]/u', '', $str);
    $str = trim(preg_replace('/\s+/', ' ', $str));
    return $str !== '' ? $str : '-';
}

function thai_date($date) {
    if (!$date || $date === '0000-00-00') return '-';
    return date('d/m/', strtotime($date)) . ((int)date('Y', strtotime($date)) + 543);
}

function is_valid_ymd_date($date) {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function is_self_pay_right($text) {
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($text === '') return false;

    $patterns = ['ชำระ', 'ชาระ', 'เงินสด', 'self', 'cash', 'pay'];
    foreach ($patterns as $pattern) {
        if (mb_stripos($text, $pattern, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

function send_json($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function app_table_exists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function app_column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (!isset($_SESSION['logged_in'])) {
        send_json(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $periodStart = $_GET['start'] ?? date('Y-m-d');
    $periodEnd = $_GET['end'] ?? $periodStart;
    if (!is_valid_ymd_date($periodStart) || !is_valid_ymd_date($periodEnd)) {
        send_json(['status' => 'error', 'message' => 'Invalid date format']);
        exit;
    }
    if ($periodStart > $periodEnd) {
        [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
    }

    $sql = "SELECT 
                i.an, i.hn, i.regdate, i.frequency, i.opd_date, i.dateadm, i.bed, i.now_ward,
                o.fullname AS name, o.ptclass AS right_code,
                ins.Name AS right_name,
                r.roomname AS ward_name,
                COALESCE(
                    NULLIF((SELECT SUM(totalprice) FROM hos_bill.billipd WHERE an = i.an), 0),
                    NULLIF((SELECT SUM(price) FROM hos_bill.billipdlist WHERE an = i.an), 0),
                    0
                ) AS bill_total
            FROM ipd.ipd i
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate AND i.frequency = o.frequency
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '')
            ORDER BY r.roomname ASC, i.bed ASC, i.regdate ASC";

    $patients = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $costs = [];
    $deposits = [];
    $periodDeposits = [];
    $notes = [];
    $paymentNotes = [];

    if (count($patients) > 0) {
        $ans = array_values(array_filter(array_column($patients, 'an')));
        if (count($ans) > 0) {
            $inClause = "'" . implode("','", array_map('addslashes', $ans)) . "'";

            $costs = load_patient_costs($pdo, $patients);

            try {
                $pdoApp = get_connection('app', 'test');
                $hasDueNoteTable = app_table_exists($pdoApp, 'deposit_due_notes');
                $hasDepositNoteColumn = app_column_exists($pdoApp, 'custom_deposits', 'note');

                $stmtDep = $pdoApp->query("SELECT an, SUM(amount) AS total_dep FROM custom_deposits WHERE an IN ({$inClause}) GROUP BY an");
                while ($row = $stmtDep->fetch()) {
                    $deposits[$row['an']] = (float)$row['total_dep'];
                }

                $stmtPeriodDep = $pdoApp->prepare("
                    SELECT an, SUM(amount) AS total_dep
                    FROM custom_deposits
                    WHERE an IN ({$inClause})
                      AND receipt_date BETWEEN ? AND ?
                    GROUP BY an
                ");
                $stmtPeriodDep->execute([$periodStart, $periodEnd]);
                while ($row = $stmtPeriodDep->fetch()) {
                    $periodDeposits[$row['an']] = (float)$row['total_dep'];
                }

                if ($hasDueNoteTable) {
                    $stmtNote = $pdoApp->query("SELECT an, note, updated_by, updated_at FROM deposit_due_notes WHERE an IN ({$inClause})");
                    while ($row = $stmtNote->fetch()) {
                        $notes[$row['an']] = [
                            'note' => $row['note'] ?? '',
                            'updated_by' => $row['updated_by'] ?? '',
                            'updated_at' => $row['updated_at'] ?? ''
                        ];
                    }
                }

                if ($hasDepositNoteColumn) {
                    $stmtPaymentNote = $pdoApp->prepare("
                        SELECT an, receipt_date, item_desc, note, recorded_by
                        FROM custom_deposits
                        WHERE an IN ({$inClause})
                          AND receipt_date BETWEEN ? AND ?
                          AND note IS NOT NULL
                          AND TRIM(note) <> ''
                        ORDER BY receipt_date DESC, created_at DESC, id DESC
                    ");
                    $stmtPaymentNote->execute([$periodStart, $periodEnd]);
                    while ($row = $stmtPaymentNote->fetch()) {
                        $anKey = $row['an'];
                        if (!isset($paymentNotes[$anKey])) {
                            $paymentNotes[$anKey] = [];
                        }
                        if (count($paymentNotes[$anKey]) < 3) {
                            $paymentNotes[$anKey][] = [
                                'date' => $row['receipt_date'],
                                'date_thai' => thai_date($row['receipt_date']),
                                'item_desc' => $row['item_desc'] ?? '',
                                'note' => $row['note'] ?? '',
                                'recorded_by' => $row['recorded_by'] ?? ''
                            ];
                        }
                    }
                }
            } catch (Exception $e) { }
        }
    }

    $rows = [];
    $totals = ['cost' => 0, 'deposit' => 0, 'period_deposit' => 0, 'balance' => 0];

    foreach ($patients as $patient) {
        $an = $patient['an'];
        $rightName = utf8_clean_value($patient['right_name']);
        $rightCode = utf8_clean_value($patient['right_code']);
        $rightDisplay = trim($rightName) !== '' ? $rightName : $rightCode;

        if (!is_self_pay_right($rightDisplay)) {
            continue;
        }

        $patientCosts = $costs[$an] ?? [];
        $costFromOrders = ($patientCosts['drug'] ?? 0)
            + ($patientCosts['lab'] ?? 0)
            + ($patientCosts['xray'] ?? 0)
            + ($patientCosts['other'] ?? 0);
        $costFromBill = (float)($patient['bill_total'] ?? 0);
        $cost = $costFromBill > 0 ? $costFromBill : $costFromOrders;
        $deposit = $deposits[$an] ?? 0;
        $balance = $cost - $deposit;

        $rows[] = [
            'name' => utf8_clean_value($patient['name']) ?: '-',
            'hn' => $patient['hn'],
            'an' => $an,
            'regdate' => $patient['regdate'],
            'regdate_thai' => thai_date($patient['regdate']),
            'right' => $rightDisplay ?: '-',
            'ward' => utf8_clean_value($patient['ward_name']) ?: ($patient['now_ward'] ?: '-'),
            'bed' => clean_bed_value($patient['bed']),
            'cost' => $cost,
            'deposit' => $deposit,
            'period_deposit' => $periodDeposits[$an] ?? 0,
            'balance' => $balance,
            'note' => $notes[$an]['note'] ?? '',
            'note_updated_by' => $notes[$an]['updated_by'] ?? '',
            'note_updated_at' => $notes[$an]['updated_at'] ?? '',
            'payment_notes' => $paymentNotes[$an] ?? []
        ];

        $totals['cost'] += $cost;
        $totals['deposit'] += $deposit;
        $totals['period_deposit'] += $periodDeposits[$an] ?? 0;
        $totals['balance'] += $balance;
    }

    send_json([
        'status' => 'success',
        'report_date' => date('Y-m-d'),
        'report_date_thai' => thai_date(date('Y-m-d')),
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'period_start_thai' => thai_date($periodStart),
        'period_end_thai' => thai_date($periodEnd),
        'data' => $rows,
        'totals' => $totals
    ]);
} catch (Exception $e) {
    send_json(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
