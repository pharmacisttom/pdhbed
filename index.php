<?php
// ไฟล์: pdhbed/index.php (ฉบับสมบูรณ์: เพิ่มโชว์ค่าใช้จ่ายรวมในหน้ามัดจำ)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDHBed - สถานะผู้ป่วยใน</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .bed-card:hover { transform: translateY(-3px); }
        .fade-in { animation: fadeIn .3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">

<nav class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-4">
                <a href="index.php" class="flex items-center">
                    <span class="text-blue-600 text-2xl font-bold">
                        🏥 PDH<span class="text-gray-700">Bed</span>
                    </span>
                </a>
                <div class="hidden md:flex items-center space-x-1 ml-4 border-l pl-4 h-8">
                    <a href="report.php" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium transition flex items-center gap-2">
                        <i class="fa-solid fa-chart-line"></i> รายงานผู้บริหาร
                    </a>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex flex-col text-right mr-3">
                    <span class="text-sm font-bold text-blue-700">
                        <?php echo htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username']); ?>
                    </span>
                    <span class="text-[10px] text-gray-500 uppercase tracking-wider">
                        Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'user'); ?>
                    </span>
                </div>
                <a href="logout.php" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg hover:bg-red-100 transition text-sm font-medium border border-red-100">
                    <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <h2 class="text-xl font-bold text-gray-700 flex items-center gap-2 flex-wrap">
            <i class="fa-solid fa-hospital-user text-blue-500"></i>
            ผู้ป่วยแยกตามแผนก (Ward)
            <span id="totalCount" class="bg-blue-600 text-white text-sm px-3 py-1 rounded-full shadow-sm" title="จำนวนผู้ป่วยที่กำลังนอน รพ.">0 ราย</span>
            
            <span class="bg-green-100 text-green-700 border border-green-300 text-sm px-3 py-1 rounded-full shadow-sm flex items-center gap-1 ml-2">
                <i class="fa-solid fa-user-plus"></i> รับใหม่วันนี้: <span id="admitTodayCount" class="font-bold">0</span>
            </span>
            <span class="bg-red-100 text-red-700 border border-red-300 text-sm px-3 py-1 rounded-full shadow-sm flex items-center gap-1">
                <i class="fa-solid fa-house-chimney"></i> D/C วันนี้: <span id="dscTodayCount" class="font-bold">0</span>
            </span>
        </h2>
    </div>
    <div id="wardSummary" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6"></div>

    <div class="mb-8 border-t border-gray-200 pt-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-id-card text-orange-500"></i>
            ผู้ป่วยแยกตามสิทธิ (Insurance Scheme)
        </h2>
        <div id="rightSummary" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4"></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4 border border-gray-100">
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-search text-gray-400"></i>
            </div>
            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="ค้นหา HN, ชื่อ, เตียง หรือสิทธิ...">
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
            <a href="report.php" class="flex-1 sm:flex-none bg-indigo-50 text-indigo-700 border border-indigo-200 px-5 py-2 rounded-lg hover:bg-indigo-100 transition shadow-sm font-bold text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-chart-pie"></i> ดูรายงาน
            </a>
            <a href="report.php?section=collection" class="flex-1 sm:flex-none bg-emerald-50 text-emerald-700 border border-emerald-200 px-5 py-2 rounded-lg hover:bg-emerald-100 transition shadow-sm font-bold text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-cash-register"></i> รายงานเก็บเงิน
            </a>
            <a href="report.php?section=depositDue" class="flex-1 sm:flex-none bg-orange-50 text-orange-700 border border-orange-200 px-5 py-2 rounded-lg hover:bg-orange-100 transition shadow-sm font-bold text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar"></i> มัดจำรายวัน
            </a>
            <a href="export.php" target="_blank" class="flex-1 sm:flex-none bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition shadow-sm font-bold text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <div id="loading" class="text-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-500 animate-pulse">กำลังโหลดข้อมูล...</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="bedContainer"></div>

</div>

<div id="depositModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-[60] hidden flex items-center justify-center fade-in">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-orange-500 text-white px-5 py-3 flex justify-between items-center">
            <h3 class="font-bold text-lg"><i class="fa-solid fa-file-invoice-dollar"></i> ทะเบียนคุมเงินมัดจำ (Server 240)</h3>
            <button onclick="event.stopPropagation(); $('#depositModal').hide();" class="text-white hover:text-gray-200"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        
        <div class="p-5 overflow-y-auto">
            <div class="bg-gray-50 p-3 rounded-lg border mb-5 flex flex-wrap gap-4 text-sm justify-between items-center">
                <div class="flex gap-4 flex-wrap items-center">
                    <div><b>HN:</b> <span id="dep_hn" class="text-blue-600 font-mono"></span></div>
                    <div><b>AN:</b> <span id="dep_an" class="text-blue-600 font-mono"></span></div>
                    <div><b>ชื่อ:</b> <span id="dep_name" class="font-bold text-gray-800 text-base"></span></div>
                </div>
                <div class="bg-red-50 text-red-700 px-3 py-1.5 rounded border border-red-200 font-bold shadow-sm">
                    <i class="fa-solid fa-file-invoice-dollar"></i> ค่าใช้จ่ายปัจจุบัน: <span id="dep_total_cost" class="text-lg ml-1">฿0.00</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 border-r pr-4 relative">
                    <h4 id="formTitle" class="font-bold text-blue-700 mb-3 border-b pb-2">➕ เพิ่มรายการใหม่</h4>
                    <button id="cancelEditBtn" onclick="resetDepositForm()" class="hidden absolute top-0 right-4 text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">ยกเลิกแก้ไข</button>
                    
                    <form id="depositForm" onsubmit="saveDeposit(event)">
                        <input type="hidden" id="form_dep_id"> 
                        <input type="hidden" id="form_hn">
                        <input type="hidden" id="form_an">
                        <input type="hidden" id="form_name">
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-600 mb-1">วันที่รับเงิน</label>
                            <input type="date" id="form_date" class="w-full border rounded p-2 text-sm" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-600 mb-1">เลขที่ใบเสร็จ (ถ้ามี)</label>
                            <input type="text" id="form_receipt" class="w-full border rounded p-2 text-sm" placeholder="เช่น 7824/091">
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-600 mb-1">รายการ</label>
                            <div class="flex gap-2">
                                <select id="form_desc" class="w-full border rounded p-2 text-sm" required>
                                    <option value="">-- เลือกรายการ --</option>
                                </select>
                                <button type="button" onclick="openItemManager()" class="bg-gray-100 hover:bg-gray-200 border px-3 rounded" title="จัดการรายการ">
                                    <i class="fa-solid fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-600 mb-1">จำนวนเงิน (บาท)</label>
                            <input type="number" step="0.01" id="form_amount" class="w-full border rounded p-2 text-sm font-bold text-red-600" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-600 mb-1">หมายเหตุ</label>
                            <textarea id="form_note" class="w-full border rounded p-2 text-sm" rows="2" placeholder="ระบุหมายเหตุเพิ่มเติม..."></textarea>
                        </div>
                        <button type="submit" id="submitDepBtn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded shadow-sm">
                            <i class="fa-solid fa-save"></i> บันทึกข้อมูล
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2">
                    <div class="flex justify-between items-end border-b pb-2 mb-3">
                        <h4 class="font-bold text-gray-700">ประวัติการทำรายการ</h4>
                        <div class="text-sm font-bold">รวม: <span id="total_deposit_text" class="text-green-600 text-lg">฿0.00</span></div>
                    </div>
                    <div id="depositDueSharedNote" class="hidden mb-3 bg-amber-50 border border-amber-200 text-amber-900 rounded p-3 text-xs whitespace-normal"></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left whitespace-nowrap">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="p-2 border text-center">วัน/เวลา</th>
                                    <th class="p-2 border">ใบเสร็จ</th>
                                    <th class="p-2 border">รายการ</th>
                                    <th class="p-2 border text-right">จำนวนเงิน</th>
                                    <th class="p-2 border">หมายเหตุ</th>
                                    <th class="p-2 border text-center">ผู้บันทึก</th>
                                    <th class="p-2 border text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="depositTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="itemManagerModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-[70] hidden flex items-center justify-center fade-in">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col">
        <div class="bg-gray-800 text-white px-4 py-2 flex justify-between items-center">
            <h3 class="font-bold text-sm">⚙️ จัดการ Template รายการ</h3>
            <button onclick="$('#itemManagerModal').hide();" class="hover:text-red-400"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-4">
            <div class="flex gap-2 mb-4">
                <input type="text" id="newItemName" class="w-full border rounded p-2 text-sm" placeholder="พิมพ์ชื่อรายการใหม่...">
                <button onclick="saveItem()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded font-bold text-sm">เพิ่ม</button>
            </div>
            <ul id="itemList" class="border rounded divide-y max-h-60 overflow-y-auto text-sm"></ul>
        </div>
    </div>
</div>

<script>
// --- ดึงค่า Role จาก Session มาไว้ในตัวแปร JS เพื่อใช้เช็คสิทธิ์ปุ่ม "ลบ" ---
const currentUserRole = <?php echo json_encode($_SESSION['role'] ?? 'user'); ?>;
const canDeleteDeposit = String(currentUserRole).trim().toLowerCase() === 'admin';
let currentPatientsMap = new Map();

$(document).ready(function(){
    loadData();

    $('#searchInput').on('keyup', function(){
        let value = $(this).val().toLowerCase().replace(/\s+/g,'');
        $('.bed-card').each(function(){
            let card = $(this);
            let text = card.text().toLowerCase().replace(/\s+/g,'');
            if(text.indexOf(value) > -1) card.show(); else card.hide();
        });
    });
});

function loadData(){
    $('#loading').show();
    $.ajax({
        url:'api/get_patients.php',
        type:'GET',
        dataType:'json',
        success:function(response){
            $('#loading').hide();
            if(response.status === 'success'){
                renderDashboard(response.data);
                renderCards(response.data);
                let tempMap = new Map();
                response.data.forEach(pt => tempMap.set(pt.an, pt));
                currentPatientsMap = tempMap;
            } else {
                Swal.fire('แจ้งเตือน', response.message || 'โหลดข้อมูลไม่สำเร็จ', 'warning');
            }
        },
        error:function(){
            $('#loading').hide();
            Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลจาก Server ได้', 'error');
        }
    });
}

function getPastelColor(index){
    const colors = [
        'bg-blue-50 text-blue-700 border-blue-200', 'bg-green-50 text-green-700 border-green-200',
        'bg-purple-50 text-purple-700 border-purple-200', 'bg-orange-50 text-orange-700 border-orange-200',
        'bg-pink-50 text-pink-700 border-pink-200', 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'bg-indigo-50 text-indigo-700 border-indigo-200', 'bg-teal-50 text-teal-700 border-teal-200'
    ];
    return colors[index % colors.length];
}

function renderDashboard(data){
    let activePatients = data.filter(pt => !pt.is_dsc);
    
    // 🌟 คำนวณยอดรับใหม่และ D/C วันนี้
    let admitTodayCount = data.filter(pt => !pt.is_dsc && pt.los == 0).length;
    let dscTodayCount = data.filter(pt => pt.is_dsc).length;

    $('#totalCount').text(activePatients.length + ' ราย');
    $('#admitTodayCount').text(admitTodayCount + ' ราย');
    $('#dscTodayCount').text(dscTodayCount + ' ราย');

    let wardCounts = {};
    let rightStats = {};

    activePatients.forEach(pt => {
        let ward = pt.ward || 'ไม่ระบุ';
        wardCounts[ward] = (wardCounts[ward] || 0) + 1;

        let rightName = pt.right || 'ไม่ระบุสิทธิ';
        if(!rightStats[rightName]) rightStats[rightName] = { count: 0, sum: 0 };
        rightStats[rightName].count += 1;
        rightStats[rightName].sum += parseFloat(pt.total_amt) || 0;
    });

    let htmlWard = '';
    let i = 0;
    for(let ward in wardCounts){
        let colorClass = getPastelColor(i++);
        htmlWard += `
        <div class="${colorClass} border rounded-xl p-3 shadow-sm flex flex-col items-center justify-center text-center transition hover:scale-105 duration-200 cursor-pointer"
             onclick="$('#searchInput').val('${ward}').trigger('keyup')">
            <span class="text-2xl font-bold mb-1">${wardCounts[ward]}</span>
            <span class="text-xs font-medium truncate w-full" title="${ward}">${ward}</span>
        </div>`;
    }
    $('#wardSummary').html(htmlWard || '<div class="text-gray-400 col-span-full text-center">ไม่พบข้อมูล Ward</div>');

    let htmlRight = '';
    let j = 0;
    let sortedRights = Object.entries(rightStats).sort((a,b) => b[1].count - a[1].count);
    sortedRights.forEach(([rightName, stat]) => {
        let colorClass = getPastelColor(j++ + 3);
        let moneyFmt = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(stat.sum);
        htmlRight += `
        <div class="${colorClass} border rounded-xl p-2 px-3 shadow-sm flex flex-row items-center justify-between transition hover:scale-105 duration-200 cursor-pointer"
             onclick="$('#searchInput').val('${rightName}').trigger('keyup')">
            <div class="flex flex-col text-left overflow-hidden mr-2">
                <span class="text-[10px] text-gray-500 font-bold opacity-70">สิทธิ</span>
                <span class="text-xs font-semibold truncate" title="${rightName}">${rightName}</span>
                <span class="text-[11px] font-bold text-green-700 mt-0.5">฿${moneyFmt}</span>
            </div>
            <span class="text-lg font-bold bg-white/60 px-2 rounded-md">${stat.count}</span>
        </div>`;
    });
    $('#rightSummary').html(htmlRight || '<div class="text-gray-400 col-span-full text-center">ไม่พบข้อมูลสิทธิ</div>');
}

function renderCards(data){
    if(!data || data.length === 0){
        $('#bedContainer').html('<div class="col-span-full text-center text-gray-500 py-10">ไม่พบข้อมูล</div>');
        return;
    }

    let html = '';
    data.forEach(function(pt){
        let wardDisplay = pt.ward || '-';
        
        let cardClass = 'bg-white border-gray-100';
        let nameClass = 'text-gray-800';
        let barColor = 'bg-blue-500';
        let statusBadge = '';

        if (pt.is_dsc) {
            cardClass = 'bg-gray-50 border-gray-200 opacity-80';
            nameClass = 'text-gray-500';
            barColor = 'bg-gray-400';
            statusBadge = `<span class="absolute top-2 right-2 bg-red-100 text-red-600 text-[10px] font-bold px-2 py-1 rounded-full border border-red-200 flex items-center gap-1 shadow-sm z-10"><i class="fa-solid fa-house-chimney"></i> D/C วันนี้</span>`;
        } 
        else if (pt.los == 0) {
            // ไฮไลท์สำหรับเคสรับใหม่
            cardClass = 'bg-green-50/50 border-green-200'; 
            barColor = 'bg-green-500'; 
            statusBadge = `<span class="absolute top-2 right-2 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded-full border border-green-300 flex items-center gap-1 shadow-sm z-10 animate-pulse"><i class="fa-solid fa-user-plus"></i> รับใหม่วันนี้</span>`;
        }
        
        let moveBadges = (!pt.is_dsc && pt.is_moved) ? `<div class="mt-2 text-[10px] text-orange-700 bg-orange-100 border border-orange-200 px-2 py-1 rounded w-fit flex items-center gap-1 fade-in"><i class=\"fa-solid fa-arrow-right-to-bracket\"></i> : ${pt.old_ward || '-'}</div>` : '';
        
        let depositBadge = '';
        if (pt.deposit_amt > 0) {
            depositBadge = `
            <div class="mt-2 text-[11px] text-green-700 bg-green-50 border border-green-200 px-2 py-1 rounded flex justify-between items-center w-full fade-in shadow-sm">
                <span><i class="fa-solid fa-money-bill-wave"></i> มีมัดจำแล้ว</span>
                <span class="font-bold">฿${pt.deposit_fmt}</span>
            </div>`;
        }

        let jsonData = encodeURIComponent(JSON.stringify(pt));

        // 🌟 ส่งตัวแปร pt.total_amt_fmt เข้าไปในฟังก์ชัน openDepositModal
        html += `
        <div class="bed-card ${cardClass} rounded-xl shadow-sm hover:shadow-md transition border cursor-pointer group p-5 relative overflow-hidden flex flex-col h-full">
            <div class="absolute top-0 left-0 w-1.5 h-full ${barColor}" onclick="showDetail('${jsonData}')"></div>
            ${statusBadge}
            
            <div onclick="showDetail('${jsonData}')">
                <div class="flex justify-between items-start mb-2 pl-2">
                    <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded-md truncate max-w-[60%]">🏥 ${wardDisplay}</span>
                    <span class="text-xs text-gray-400"><i class="fa-regular fa-clock"></i> ${pt.regdate_thai || '-'}</span>
                </div>
                <div class="pl-2 flex-grow">
                    <span class="bg-blue-100 text-blue-700 border text-xs font-bold px-2 py-0.5 rounded border-blue-200">  ${pt.bed || '?'}</span>
                    <h3 class="font-bold ${nameClass} text-lg mt-2 truncate group-hover:text-blue-600 transition" title="${pt.name || ''}">${pt.name || '-'}</h3>
                    ${moveBadges}
                </div>
                
                <div class="pl-2 mt-3 pt-3 border-t border-dashed border-gray-100 text-sm space-y-1">
                    <div class="flex justify-between text-gray-600">
                        <span>HN: <span class="font-mono font-medium">${pt.hn || '-'}</span></span>
                        <span>AN: <span class="font-mono text-gray-400">${pt.an || '-'}</span></span>
                    </div>
                    <div class="text-xs text-gray-400 font-mono mt-0.5 mb-1 truncate"><i class="fa-solid fa-id-card"></i> ${pt.cardid || '-'}</div>
                    <div class="text-gray-600 truncate text-xs" title="${pt.right || ''}">💳 ${pt.right || '-'}</div>
                    
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-gray-500 text-xs">รวมค่าใช้จ่าย:</span>
                        <span class="font-bold text-green-600 text-base">฿${pt.total_amt_fmt || '0.00'}</span>
                    </div>
                    
                    ${depositBadge}
                </div>
            </div>
            
            <div class="pl-2 mt-3 pt-3 border-t border-gray-100">
                <button onclick="openDepositModal('${pt.hn}', '${pt.an}', '${pt.name.replace(/'/g, "\\'")}', '${pt.total_amt_fmt || '0.00'}')" class="w-full bg-orange-50 text-orange-600 hover:bg-orange-100 border border-orange-200 py-1.5 rounded-lg text-xs font-bold transition flex justify-center items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar"></i> บันทึกมัดจำ / ค้างชำระ
                </button>
            </div>
        </div>`;
    });
    $('#bedContainer').html(html);
}

function showDetail(encodedData){
    let pt = JSON.parse(decodeURIComponent(encodedData));
    let dscAlert = pt.is_dsc ? `<div class="bg-red-50 text-red-700 p-2 rounded mb-3 text-sm text-center border border-red-100 font-bold"><i class="fa-solid fa-check-circle"></i> </div>` : '';

    let depositSection = '';
    if (pt.deposit_amt > 0) {
        depositSection = `
        <div class="bg-orange-50 p-3 rounded-lg border border-orange-200 flex justify-between items-center shadow-sm">
            <span class="text-orange-800 font-bold"><i class="fa-solid fa-money-bill-wave"></i> ยอดมัดจำ/ชำระแล้ว (Server 240)</span>
            <span class="text-xl font-bold text-orange-600">฿${pt.deposit_fmt}</span>
        </div>`;
    }

    Swal.fire({
        width: 700,
        title: `<div class="text-xl font-bold text-blue-600 text-left flex items-center gap-2"><i class="fa-solid fa-user-injured"></i> ${pt.name || '-'}</div>`,
        html: `
        <div class="text-left space-y-4">
            ${dscAlert}
            
            <div class="bg-blue-50 p-3 rounded-lg flex justify-between items-start border border-blue-100">
                <div class="text-center px-2">
                    <div class="text-xs text-gray-500 mb-1">เตียง</div>
                    <div class="font-bold text-xl text-blue-800">${pt.bed || '-'}</div>
                </div>
                <div class="text-center px-2 border-l border-blue-200">
                    <div class="text-xs text-gray-500 mb-1">วันนอน</div>
                    <div class="font-bold text-xl text-blue-800">${pt.los || 0} วัน</div>
                </div>
                <div class="text-right px-2 border-l border-blue-200">
                    <div class="text-xs text-gray-500 mb-1">วันที่ Admit</div>
                    <div class="font-bold text-gray-700">${pt.regdate_thai || '-'}</div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-gray-50 p-3 rounded border"><div>HN</div><div class="font-bold">${pt.hn || '-'}</div></div>
                <div class="bg-gray-50 p-3 rounded border"><div>AN</div><div class="font-bold">${pt.an || '-'}</div></div>
                <div class="bg-gray-50 p-3 rounded border col-span-2"><div>เลขบัตรประชาชน</div><div class="font-bold">${pt.cardid || '-'}</div></div>
                <div class="bg-gray-50 p-3 rounded border"><div>Ward</div><div class="font-bold">${pt.ward || '-'}</div></div>
                <div class="bg-gray-50 p-3 rounded border"><div>สิทธิ</div><div class="font-bold">${pt.right || '-'}</div></div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg border border-green-200 shadow-sm">
                <div class="flex justify-between items-end border-b border-green-200 pb-2 mb-3">
                    <div class="text-green-800 font-bold text-lg"><i class="fa-solid fa-file-invoice-dollar"></i> ค่าใช้จ่ายรวม (HIS)</div>
                    <div class="text-2xl font-bold text-green-700">฿${pt.total_amt_fmt || '0.00'}</div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between bg-white p-2 rounded border border-green-100">
                        <span class="text-gray-600">💊 ค่ายา</span>
                        <span class="font-bold text-gray-800">฿${pt.amt_drug || '0.00'}</span>
                    </div>
                    <div class="flex justify-between bg-white p-2 rounded border border-green-100">
                        <span class="text-gray-600">🔬 ค่า Lab</span>
                        <span class="font-bold text-gray-800">฿${pt.amt_lab || '0.00'}</span>
                    </div>
                    <div class="flex justify-between bg-white p-2 rounded border border-green-100">
                        <span class="text-gray-600">🩻 ค่า X-Ray</span>
                        <span class="font-bold text-gray-800">฿${pt.amt_xray || '0.00'}</span>
                    </div>
                    <div class="flex justify-between bg-white p-2 rounded border border-green-100">
                        <span class="text-gray-600">📝 อื่นๆ</span>
                        <span class="font-bold text-gray-800">฿${pt.amt_other || '0.00'}</span>
                    </div>
                </div>
            </div>
            
            ${depositSection}
            
        </div>`,
        confirmButtonText: 'ปิด'
    });
}

// ==========================================
// ส่วนจัดการ Template รายการ (Dropdown)
// ==========================================
function loadDepositItems(selectedValue = '') {
    $.ajax({
        url: 'api/manage_deposit.php',
        type: 'GET',
        data: { action: 'get_items' },
        success: function(res) {
            if(res.status === 'success') {
                let options = '<option value="">-- เลือกรายการ --</option>';
                let listHtml = '';
                res.data.forEach(item => {
                    options += `<option value="${item.item_name}">${item.item_name}</option>`;
                    listHtml += `<li class="p-2 flex justify-between items-center hover:bg-gray-50">
                        <span>${item.item_name}</span>
                        <button type="button" onclick="deleteItem(${item.id})" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
                    </li>`;
                });
                $('#form_desc').html(options);
                $('#itemList').html(listHtml);
                if(selectedValue) $('#form_desc').val(selectedValue);
            }
        }
    });
}

function openItemManager() {
    $('#newItemName').val('');
    $('#itemManagerModal').css('display', 'flex').hide().fadeIn(150);
}

function saveItem() {
    let name = $('#newItemName').val().trim();
    if(!name) return Swal.fire('แจ้งเตือน', 'กรุณาพิมพ์ชื่อรายการ', 'warning');
    $.post('api/manage_deposit.php', { action: 'save_item', item_name: name }, function(res) {
        if(res.status === 'success') {
            $('#newItemName').val('');
            loadDepositItems($('#form_desc').val());
        }
    });
}

function deleteItem(id) {
    if(confirm(' Template ?')) {
        $.post('api/manage_deposit.php', { action: 'delete_item', id: id }, function(res) {
            if(res.status === 'success') loadDepositItems($('#form_desc').val());
        });
    }
}

// ==========================================
// ส่วนระบบมัดจำหลัก + แก้ไข + ลบ (Admin)
// ==========================================
function openDepositModal(hn, an, name, totalAmt) {
    if(event) event.stopPropagation(); 
    
    // 🌟 แสดงค่าใช้จ่ายปัจจุบันใน Modal
    $('#dep_total_cost').text('฿' + totalAmt);
    
    $('#dep_hn').text(hn); $('#dep_an').text(an); $('#dep_name').text(name);
    $('#form_hn').val(hn); $('#form_an').val(an); $('#form_name').val(name);
    
    resetDepositForm();
    loadDepositItems();
    $('#depositModal').css('display', 'flex').hide().fadeIn(200);
    loadDepositHistory(an);
}

function resetDepositForm() {
    $('#form_dep_id').val('');
    $('#formTitle').html('➕ เพิ่มรายการใหม่').removeClass('text-purple-700').addClass('text-blue-700');
    $('#cancelEditBtn').addClass('hidden');
    $('#submitDepBtn').html('<i class="fa-solid fa-save"></i> บันทึกข้อมูล').removeClass('bg-purple-600 hover:bg-purple-700').addClass('bg-green-600 hover:bg-green-700');
    
    $('#form_receipt').val('');
    $('#form_desc').val('');
    $('#form_amount').val('');
    $('#form_note').val('');
    $('#form_date').val(new Date().toISOString().split('T')[0]);
}

function escapeDepositHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function loadDepositHistory(an) {
    $('#depositTableBody').html('<tr><td colspan="7" class="text-center p-4 text-gray-400">กำลังโหลด...</td></tr>');
    $.ajax({
        url: 'api/manage_deposit.php',
        type: 'GET',
        data: { action: 'get', an: an },
        success: function(res) {
            if(res.status === 'success') {
                $('#total_deposit_text').text('฿' + res.total);
                if (res.due_note && res.due_note.note) {
                    $('#depositDueSharedNote')
                        .removeClass('hidden')
                        .html(`<div class="font-bold mb-1"><i class="fa-solid fa-note-sticky"></i> หมายเหตุสำหรับติดตามเก็บเงิน</div><div>${escapeDepositHtml(res.due_note.note).replace(/\n/g, '<br>')}</div>`);
                } else {
                    $('#depositDueSharedNote').addClass('hidden').empty();
                }
                let html = '';
                if(res.data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center p-4 text-gray-400">ไม่มีประวัติ</td></tr>';
                } else {
                    res.data.forEach(d => {
                        let rowData = encodeURIComponent(JSON.stringify(d));
                        let noteHtml = d.note ? escapeDepositHtml(d.note).replace(/\n/g, '<br>') : '<span class="text-gray-300">-</span>';
                        
                        let deleteBtnHtml = '';
                        if (canDeleteDeposit) {
                            deleteBtnHtml = `<button onclick="deleteDeposit(${d.id})" class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-[10px] font-bold ml-1" title="ลบข้อมูล"><i class="fa-solid fa-trash"></i> ลบ</button>`;
                        }

                        html += `<tr class="hover:bg-orange-50 border-b">
                            <td class="p-2 border-r text-center">
                                <div class="font-bold text-gray-700">${d.date_show}</div>
                                <div class="text-[10px] text-gray-400">${d.time_show} น.</div>
                            </td>
                            <td class="p-2 border-r text-center">${d.receipt_no || '-'}</td>
                            <td class="p-2 border-r">${d.item_desc}</td>
                            <td class="p-2 border-r text-right font-bold text-red-600">${d.amount}</td>
                            <td class="p-2 border-r whitespace-normal min-w-[140px]">${noteHtml}</td>
                            <td class="p-2 border-r text-center text-gray-500 text-[10px]"><i class="fa-solid fa-user"></i> ${d.recorded_by}</td>
                            <td class="p-2 text-center whitespace-nowrap">
                                <button onclick="editDeposit('${rowData}')" class="bg-purple-100 hover:bg-purple-200 text-purple-700 px-2 py-1 rounded text-[10px] font-bold">
                                    <i class="fa-solid fa-pen"></i> แก้ไข
                                </button>
                                ${deleteBtnHtml}
                            </td>
                        </tr>`;
                    });
                }
                $('#depositTableBody').html(html);
            }
        }
    });
}

function deleteDeposit(id) {
    Swal.fire({
        title: '?',
        text: "การลบจะถูกบันทึกประวัติไว้ กรุณาระบุเหตุผล:",
        input: 'text',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => {
            if (!value) { return 'กรุณาระบุเหตุผลในการลบด้วยครับ!' }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/manage_deposit.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: id,
                    reason: result.value
                },
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ', showConfirmButton: false, timer: 1500 });
                        loadDepositHistory($('#form_an').val());
                        loadData(); 
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }
    });
}

function editDeposit(encodedData) {
    let d = JSON.parse(decodeURIComponent(encodedData));
    loadDepositItems(d.item_desc);
    
    setTimeout(() => {
        if ($("#form_desc option[value='" + d.item_desc + "']").length === 0) {
            $('#form_desc').append(`<option value="${d.item_desc}">${d.item_desc}</option>`);
            $('#form_desc').val(d.item_desc);
        }
    }, 200);

    $('#form_dep_id').val(d.id);
    $('#form_date').val(d.receipt_date);
    $('#form_receipt').val(d.receipt_no);
    $('#form_amount').val(d.amount_raw);
    $('#form_note').val(d.note || '');

    $('#formTitle').html('✏️ แก้ไขรายการ').removeClass('text-blue-700').addClass('text-purple-700');
    $('#cancelEditBtn').removeClass('hidden');
    $('#submitDepBtn').html('<i class="fa-solid fa-check"></i> อัปเดตข้อมูล').removeClass('bg-green-600 hover:bg-green-700').addClass('bg-purple-600 hover:bg-purple-700');
}

function saveDeposit(e) {
    e.preventDefault();
    let btn = $('#submitDepBtn');
    let originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...');

    $.ajax({
        url: 'api/manage_deposit.php',
        type: 'POST',
        data: {
            action: 'save',
            id: $('#form_dep_id').val(),
            hn: $('#form_hn').val(),
            an: $('#form_an').val(),
            name: $('#form_name').val(),
            receipt_date: $('#form_date').val(),
            receipt_no: $('#form_receipt').val(),
            item_desc: $('#form_desc').val(),
            amount: $('#form_amount').val(),
            note: $('#form_note').val()
        },
        success: function(res) {
            btn.prop('disabled', false).html(originalText);
            if(res.status === 'success') {
                Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', showConfirmButton: false, timer: 1000 });
                loadDepositHistory($('#form_an').val());
                loadData(); 
                resetDepositForm();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function() {
            btn.prop('disabled', false).html(originalText);
            Swal.fire('Error', 'ไม่สามารถเชื่อมต่อ Server ได้', 'error');
        }
    });
}
</script>

</body>
</html>



