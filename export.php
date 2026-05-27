<?php
// ไฟล์: pdhbed/export.php
require 'config/db.php';
require 'includes/functions.php';

$filename = "IPD_Report_" . date('Y-m-d_His') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // --- ใช้ SQL logic เดียวกับ API ที่แก้แล้ว ---
    // ใช้ SUM(totalprice) เพื่อรวมค่าใช้จ่ายทุกวัน
    $sql = "SELECT i.an, i.hn, i.regdate, i.bed, i.now_ward, i.sendward,
            o.fullname as name,
            o.ptclass as right_code,
            ins.Name as right_name,
            r.roomname as ward_name,
            r2.roomname as sendward_name,
            COALESCE(
                (SELECT SUM(totalprice) FROM hos_bill.billipd WHERE an = i.an),
                (SELECT SUM(price) FROM hos_bill.billipdlist WHERE an = i.an),
                0
            ) as total_amt
            FROM ipd.ipd i 
            LEFT JOIN opd.opd o ON i.hn = o.hn AND i.regdate = o.regdate
            LEFT JOIN hos.insclasses ins ON o.ptclass = ins.code
            LEFT JOIN hos.roomno r ON i.now_ward = r.roomcode
            LEFT JOIN hos.roomno r2 ON i.sendward = r2.roomcode
            WHERE (i.datedsc IS NULL OR i.datedsc = '0000-00-00' OR i.datedsc = '') 
            GROUP BY i.an
            ORDER BY i.bed ASC";

    $stmt = $pdo->query($sql);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #2563EB; color: white; padding: 10px; border: 1px solid #000; }
        td { border: 1px solid #000; padding: 5px; vertical-align: middle; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">รายงานสถานะผู้ป่วยใน (IPD Status)</h2>
    <p style="text-align: center;">ข้อมูล ณ วันที่: <?php echo date('d/m/Y H:i'); ?></p>
    
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>เตียง</th>
                <th>แผนก/ตึกปัจจุบัน</th>
                <th>ประวัติการย้าย</th>
                <th>HN</th>
                <th>AN</th>
                <th>ชื่อ-สกุล</th>
                <th>สิทธิการรักษา</th>
                <th>วันที่ Admit</th>
                <th>ค่าใช้จ่ายรวม (บาท)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            while($row = $stmt->fetch()): 
                $name = $row['name'];
                $ward = $row['ward_name'];
                $right = $row['right_name'] ? $row['right_name'] : $row['right_code'];
                $moved_from_name = $row['sendward_name'] ? $row['sendward_name'] : $row['sendward'];
                
                // แปลง Encoding ถ้าจำเป็น
                if(!mb_detect_encoding($name, 'utf-8', true)) $name = iconv("tis-620", "utf-8//IGNORE", $name);
                if(!mb_detect_encoding($ward, 'utf-8', true)) $ward = iconv("tis-620", "utf-8//IGNORE", $ward);
                if(!mb_detect_encoding($right, 'utf-8', true)) $right = iconv("tis-620", "utf-8//IGNORE", $right);
                if(!mb_detect_encoding($moved_from_name, 'utf-8', true)) $moved_from_name = iconv("tis-620", "utf-8//IGNORE", $moved_from_name);

                $wardDisplay = !empty($ward) ? $ward : $row['now_ward'];
                $total = $row['total_amt'] ? $row['total_amt'] : 0;
                
                $moveInfo = "-";
                if($row['sendward'] != $row['now_ward']) {
                    $moveInfo = "ย้ายมาจาก: " . $moved_from_name;
                }
            ?>
            <tr>
                <td style="text-align:center;"><?php echo $i++; ?></td>
                <td style="text-align:center;"><?php echo $row['bed']; ?></td>
                <td><?php echo $wardDisplay; ?></td>
                <td style="color: #ea580c;"><?php echo $moveInfo; ?></td>
                <td style="text-align:center; mso-number-format:'@'"><?php echo $row['hn']; ?></td>
                <td style="text-align:center; mso-number-format:'@'"><?php echo $row['an']; ?></td>
                <td><?php echo $name; ?></td>
                <td><?php echo $right; ?></td>
                <td style="text-align:center;"><?php echo $row['regdate']; ?></td>
                <td style="text-align:right;"><?php echo number_format($total, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>