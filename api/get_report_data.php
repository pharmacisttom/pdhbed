<?php
// ไฟล์: pdhbed/api/get_report_data.php (Census Logic Fixed Version)
error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json');
require '../config/db.php';

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');

// ฟังก์ชันทำความสะอาดข้อความ
function clean_string($str) {
    if ($str === null) return '';
    $str = (string)$str;
    if (!mb_detect_encoding($str, 'utf-8', true)) {
        return iconv("tis-620", "utf-8//IGNORE", $str);
    }
    return $str;
}

try {
    // 1. Query ข้อมูล (ดึงยอดเงินจาก hos_bill เพื่อความรวดเร็ว)
    $sql = "SELECT i.an, i.hn, i.regdate, i.datedsc, i.now_ward, 
            r.roomname,
            o.fullname, 
            o.ptclass as right_code,
            ins.Name as right_name,
            
            COALESCE(
                (SELECT SUM(totalprice) FROM hos_bill.billipd WHERE an = i.an),
                (SELECT SUM(price) FROM hos_bill.billipdlist WHERE an = i.an),
                0
            ) as total_amt
            
            FROM ipd.ipd i
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate AND i.frequency = o.frequency
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            
            WHERE i.regdate <= :end_date 
            AND (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '' OR i.datedsc >= :start_date)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. ประมวลผล
    $dailyStats = [];
    $allWards = [];
    $rightStats = [];
    $top10List = [];

    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );

    foreach ($patients as $pt) {
        $name = clean_string($pt['fullname']);
        if (empty($name)) $name = 'ไม่ระบุชื่อ';

        $rName = clean_string($pt['right_name']);
        if (empty($rName)) $rName = $pt['right_code'] ? $pt['right_code'] : 'ไม่ระบุสิทธิ';
        
        $wardName = clean_string($pt['roomname']);
        if (empty($wardName)) $wardName = $pt['now_ward'] ? $pt['now_ward'] : 'ไม่ระบุ';

        // เก็บสถิติสิทธิ
        if (!isset($rightStats[$rName])) {
            $rightStats[$rName] = ['count' => 0, 'revenue' => 0];
        }
        $rightStats[$rName]['count']++;
        
        $money = floatval($pt['total_amt']);
        
        // ถ้ายอดจำหน่ายเกิดขึ้นในช่วงนี้ ให้นับเงินเข้าสิทธิ
        if ($pt['datedsc'] && $pt['datedsc'] != '0000-00-00') {
             $rightStats[$rName]['revenue'] += $money;
        }

        // เก็บข้อมูล Top 10
        if ($money > 0) {
            $top10List[] = [
                'an' => $pt['an'],
                'hn' => $pt['hn'],
                'name' => $name,
                'ward' => $wardName,
                'right' => $rName,
                'total_amt' => $money
            ];
        }
    }

    // เรียงลำดับ Top 10
    usort($top10List, function($a, $b) {
        if ($a['total_amt'] == $b['total_amt']) return 0;
        return ($a['total_amt'] > $b['total_amt']) ? -1 : 1;
    });
    $top10List = array_slice($top10List, 0, 10);

    // --- วนลูปรายวัน (จุดที่แก้ไขลอจิกตัวเลขไม่ตรง) ---
    foreach ($period as $dt) {
        $currentDate = $dt->format('Y-m-d');
        $census = 0; $admit = 0; $discharge = 0; $revenue = 0; $los_sum = 0;
        $wardStats = []; 

        foreach ($patients as $pt) {
            $reg = $pt['regdate'];
            $dsc = ($pt['datedsc'] == '' || $pt['datedsc'] == '0000-00-00') ? null : $pt['datedsc'];
            
            $ward = clean_string($pt['roomname']);
            if (empty($ward)) $ward = $pt['now_ward'] ? $pt['now_ward'] : 'ไม่ระบุ';
            if (!in_array($ward, $allWards)) $allWards[] = $ward;

            // [🔥 FIXED] แก้ไขจาก >= เป็น > เพื่อไม่นับคนที่ D/C วันนี้รวมในยอดคงเหลือของวันนี้
            if ($reg <= $currentDate && ($dsc === null || $dsc > $currentDate)) { 
                $census++; 
            }
            
            if ($reg == $currentDate) {
                $admit++;
                if(!isset($wardStats[$ward])) $wardStats[$ward] = ['a' => 0, 'd' => 0];
                $wardStats[$ward]['a']++;
            }
            
            if ($dsc == $currentDate) {
                $discharge++;
                $revenue += floatval($pt['total_amt']);
                $admDate = new DateTime($reg);
                $dscDate = new DateTime($dsc);
                $diff = $admDate->diff($dscDate);
                $los_sum += ($diff->days + 1); // บวก 1 เพื่อให้นับวันแรกด้วย (ตามหลัก สปสช.)
                if(!isset($wardStats[$ward])) $wardStats[$ward] = ['a' => 0, 'd' => 0];
                $wardStats[$ward]['d']++;
            }
        }
        $avg_los = ($discharge > 0) ? round($los_sum / $discharge, 1) : 0;
        
        $dailyStats[] = [
            'date' => $currentDate,
            'date_thai' => date('d/m', strtotime($currentDate)),
            'census' => $census,
            'admit' => $admit,
            'discharge' => $discharge,
            'revenue' => $revenue,
            'avg_los' => $avg_los,
            'ward_breakdown' => $wardStats
        ];
    }

    // เรียงวันที่ ล่าสุด -> อดีต
    usort($dailyStats, function($a, $b) { 
        return strtotime($b['date']) - strtotime($a['date']); 
    });
    sort($allWards);
    
    // เรียงสิทธิ มาก -> น้อย
    uasort($rightStats, function($a, $b) { 
        return $b['count'] - $a['count']; 
    });

    echo json_encode([
        'status' => 'success', 
        'data' => $dailyStats,
        'wards' => $allWards,
        'rights' => $rightStats,
        'top10' => $top10List
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>