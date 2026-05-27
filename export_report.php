<?php
// ไฟล์: pdhbed/export_report.php
require 'config/db.php';

// รับค่าวันที่จาก URL
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');

$filename = "Executive_Report_" . $start_date . "_to_" . $end_date . ".xls";

// ตั้งค่า Header ให้ Browser รู้ว่าเป็นไฟล์ Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// ฟังก์ชันแปลงภาษา
function clean_string($str) {
    if ($str === null) return '';
    if (!mb_detect_encoding($str, 'utf-8', true)) {
        return iconv("tis-620", "utf-8//IGNORE", $str);
    }
    return $str;
}

try {
    // 1. Query ข้อมูล (เพิ่มการ Join สิทธิเหมือนใน API)
    $sql = "SELECT i.an, i.regdate, i.datedsc, i.now_ward, 
            r.roomname,
            o.ptclass as right_code,
            ins.Name as right_name,
            IFNULL(drug.total, 0) + IFNULL(lab.total, 0) + IFNULL(xray.total, 0) + IFNULL(other.total, 0) as total_amt
            FROM ipd.ipd i
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            -- Join เพื่อหาสิทธิ
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            
            LEFT JOIN (SELECT an, SUM(price) as total FROM ipd.drug_order_ipd GROUP BY an) drug ON drug.an = i.an
            LEFT JOIN (SELECT an, SUM(price) as total FROM ipd.lab_order_ipd GROUP BY an) lab ON lab.an = i.an
            LEFT JOIN (SELECT an, SUM(price) as total FROM ipd.xray_order_ipd GROUP BY an) xray ON xray.an = i.an
            LEFT JOIN (SELECT an, SUM(price) as total FROM ipd.other_order_ipd GROUP BY an) other ON other.an = i.an
            WHERE i.regdate <= :end_date 
            AND (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '' OR i.datedsc >= :start_date)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. ประมวลผล
    $dailyStats = [];
    $allWards = []; 
    $rightStats = []; // ตัวแปรเก็บยอดสรุปตามสิทธิ

    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );

    // วนลูปเก็บข้อมูลสิทธิก่อน
    foreach ($patients as $pt) {
        $rName = clean_string($pt['right_name']);
        if (empty($rName)) $rName = $pt['right_code'] ? $pt['right_code'] : 'ไม่ระบุสิทธิ';
        
        if (!isset($rightStats[$rName])) {
            $rightStats[$rName] = ['count' => 0, 'revenue' => 0];
        }
        $rightStats[$rName]['count']++;
        
        // ถ้ายอดจำหน่ายเกิดขึ้นในช่วงนี้ ให้นับเงิน
        if ($pt['datedsc'] && $pt['datedsc'] != '0000-00-00') {
             $rightStats[$rName]['revenue'] += floatval($pt['total_amt']);
        }
    }
    // เรียงสิทธิจากมากไปน้อย
    uasort($rightStats, function($a, $b) { return $b['count'] - $a['count']; });

    // วนลูปเก็บข้อมูลรายวัน
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

            // Census
            if ($reg <= $currentDate && ($dsc === null || $dsc >= $currentDate)) { $census++; }
            // Admit
            if ($reg == $currentDate) {
                $admit++;
                if(!isset($wardStats[$ward])) $wardStats[$ward] = ['a' => 0, 'd' => 0];
                $wardStats[$ward]['a']++;
            }
            // Discharge
            if ($dsc == $currentDate) {
                $discharge++;
                $revenue += floatval($pt['total_amt']);
                $admDate = new DateTime($reg);
                $dscDate = new DateTime($dsc);
                $diff = $admDate->diff($dscDate);
                $los_sum += ($diff->days + 1);

                if(!isset($wardStats[$ward])) $wardStats[$ward] = ['a' => 0, 'd' => 0];
                $wardStats[$ward]['d']++;
            }
        }
        $avg_los = ($discharge > 0) ? round($los_sum / $discharge, 1) : 0;

        $dailyStats[] = [
            'date' => $currentDate,
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

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #2563EB; color: white; border: 1px solid #000; padding: 8px; text-align: center; }
        td { border: 1px solid #000; padding: 5px; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .header-title { font-size: 18px; font-weight: bold; text-align: center; margin: 10px 0; }
        .sub-header { text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header-title">รายงานสถิติผู้ป่วยใน (Executive Report)</div>
    <div class="sub-header">ช่วงวันที่: <?php echo date('d/m/Y', strtotime($start_date)); ?> ถึง <?php echo date('d/m/Y', strtotime($end_date)); ?></div>
    
    <h3>1. สรุปยอดแยกตามสิทธิ (Insurance Scheme Summary)</h3>
    <table style="width: 50%;">
        <thead>
            <tr>
                <th style="background-color: #4B5563;">สิทธิการรักษา</th>
                <th style="background-color: #4B5563;">จำนวนผู้ป่วย (คน)</th>
                <th style="background-color: #4B5563;">รายได้รวม (บาท)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rightStats as $rightName => $stat): ?>
            <tr>
                <td class="text-left"><?php echo $rightName; ?></td>
                <td><?php echo number_format($stat['count']); ?></td>
                <td class="text-right" style="mso-number-format:'\#\,\#\#0\.00';"><?php echo $stat['revenue']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <br>

    <h3>2. ตารางข้อมูลรายวัน (Daily Breakdown)</h3>
    <table>
        <thead>
            <tr>
                <th rowspan="2">วันที่</th>
                <th rowspan="2">คงเหลือ (Census)</th>
                <th rowspan="2">รับใหม่ (Admit)</th>
                <th rowspan="2">จำหน่าย (D/C)</th>
                <th rowspan="2">รายรับ (Revenue)</th>
                <th rowspan="2">วันนอนเฉลี่ย (LOS)</th>
                <?php foreach($allWards as $w): ?>
                    <th colspan="2" style="background-color: #6B7280;"><?php echo $w; ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach($allWards as $w): ?>
                    <th style="background-color: #10B981; font-size: 10px;">รับ</th>
                    <th style="background-color: #EF4444; font-size: 10px;">ออก</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($dailyStats as $d): ?>
            <tr>
                <td class="text-left" style="mso-number-format:'Short Date';"><?php echo date('d/m/Y', strtotime($d['date'])); ?></td>
                <td style="font-weight:bold; background-color: #EFF6FF;"><?php echo $d['census']; ?></td>
                <td style="color:green;">+<?php echo $d['admit']; ?></td>
                <td style="color:red;">-<?php echo $d['discharge']; ?></td>
                <td class="text-right" style="mso-number-format:'\#\,\#\#0\.00';"><?php echo $d['revenue']; ?></td>
                <td><?php echo $d['avg_los']; ?></td>
                
                <?php foreach($allWards as $w): 
                    $stats = $d['ward_breakdown'][$w] ?? ['a'=>0, 'd'=>0];
                ?>
                    <td style="color:green; font-size: 11px;"><?php echo $stats['a'] > 0 ? '+'.$stats['a'] : '-'; ?></td>
                    <td style="color:red; font-size: 11px;"><?php echo $stats['d'] > 0 ? '-'.$stats['d'] : '-'; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>