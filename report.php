<?php
// ไฟล์: pdhbed/report.php
session_start();
if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit; }

// ตั้งค่าปีงบประมาณ
$curMonth = date('m');
$curYear  = date('Y');
if ($curMonth >= 10) { $startYear = $curYear; } else { $startYear = $curYear - 1; }
$fiscalStartDate = "$startYear-10-01";
$currentDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Report - PDHBed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { font-family: 'Sarabun', sans-serif; background-color: #f3f4f6; } 
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .table-compact th, .table-compact td { padding: 6px 10px; font-size: 13px; }
        
        .menu-item.active { background-color: #EFF6FF; color: #2563EB; border-right: 4px solid #2563EB; font-weight: bold; }
        .menu-item:hover:not(.active) { background-color: #F9FAFB; }
        
        .time-btn.active { background-color: #10B981; color: white; border-color: #10B981; }
        .time-btn:hover:not(.active) { background-color: #F3F4F6; }
        .collection-btn.active { background-color: #059669; color: white; border-color: #059669; }
        .collection-btn:hover:not(.active) { background-color: #F3F4F6; }
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            html, body {
                width: 297mm;
                min-height: 210mm;
                background: white !important;
            }
            body {
                margin: 0 !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            body * { visibility: hidden; }
            #depositDuePrintArea, #depositDuePrintArea * { visibility: visible; }
            #depositDuePrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 281mm !important;
                max-width: 281mm !important;
                box-shadow: none !important;
                border: 0 !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            #depositDuePrintArea .overflow-auto {
                overflow: visible !important;
            }
            #depositDuePrintArea table {
                width: 100% !important;
                table-layout: fixed;
                border-collapse: collapse;
                font-size: 8px;
                line-height: 1.15;
                white-space: normal !important;
            }
            #depositDuePrintArea thead {
                display: table-header-group;
            }
            #depositDuePrintArea tfoot {
                display: table-row-group;
            }
            #depositDuePrintArea th,
            #depositDuePrintArea td {
                padding: 2px 3px !important;
                border: 1px solid #cfd4dc !important;
                word-break: break-word;
                overflow-wrap: anywhere;
                vertical-align: middle;
            }
            #depositDuePrintArea .sticky {
                position: static !important;
            }
            #depositDuePrintArea .deposit-due-note-action {
                display: none !important;
            }
            #depositDuePrintArea th:nth-child(1), #depositDuePrintArea td:nth-child(1) { width: 5mm; }
            #depositDuePrintArea th:nth-child(2), #depositDuePrintArea td:nth-child(2) { width: 39mm; }
            #depositDuePrintArea th:nth-child(3), #depositDuePrintArea td:nth-child(3) { width: 15mm; }
            #depositDuePrintArea th:nth-child(4), #depositDuePrintArea td:nth-child(4) { width: 17mm; }
            #depositDuePrintArea th:nth-child(5), #depositDuePrintArea td:nth-child(5) { width: 17mm; }
            #depositDuePrintArea th:nth-child(6), #depositDuePrintArea td:nth-child(6) { width: 38mm; }
            #depositDuePrintArea th:nth-child(7), #depositDuePrintArea td:nth-child(7) { width: 31mm; }
            #depositDuePrintArea th:nth-child(8), #depositDuePrintArea td:nth-child(8) { width: 10mm; }
            #depositDuePrintArea th:nth-child(9), #depositDuePrintArea td:nth-child(9) { width: 19mm; }
            #depositDuePrintArea th:nth-child(10), #depositDuePrintArea td:nth-child(10) { width: 19mm; }
            #depositDuePrintArea th:nth-child(11), #depositDuePrintArea td:nth-child(11) { width: 19mm; }
            #depositDuePrintArea th:nth-child(12), #depositDuePrintArea td:nth-child(12) { width: 22mm; }
        }

        /* Modal Styles */
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside id="sidebar" class="bg-white w-64 flex-shrink-0 border-r border-gray-200 flex flex-col transition-all duration-300 absolute md:relative z-30 h-full transform -translate-x-full md:translate-x-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-100">
            <span class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-blue-600"></i> PDH Report
            </span>
        </div>
        <div class="flex-1 overflow-y-auto py-4 space-y-1">
            <a href="#" onclick="showSection('overview', this)" class="menu-item active flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-gauge-high w-6 text-center"></i> <span class="ml-2">ภาพรวม (Overview)</span>
            </a>
            <a href="#" onclick="showSection('financial', this)" class="menu-item flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-coins w-6 text-center"></i> <span class="ml-2">การเงิน (Financial)</span>
            </a>
            <a href="#" onclick="showSection('collection', this)" class="menu-item flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-cash-register w-6 text-center"></i> <span class="ml-2">รายงานการเก็บเงิน</span>
            </a>
            <a href="#" onclick="showSection('depositDue', this)" class="menu-item flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-file-invoice-dollar w-6 text-center"></i> <span class="ml-2">มัดจำรายวัน</span>
            </a>
            <a href="#" onclick="showSection('rights', this)" class="menu-item flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-address-card w-6 text-center"></i> <span class="ml-2">สิทธิการรักษา</span>
            </a>
            <a href="#" onclick="showSection('daily', this)" class="menu-item flex items-center px-6 py-3 text-gray-600 transition">
                <i class="fa-solid fa-table-list w-6 text-center"></i> <span class="ml-2">ตารางรายวัน</span>
            </a>
            <div class="border-t border-gray-100 my-2 pt-2">
                <a href="index.php" class="flex items-center px-6 py-3 text-gray-500 hover:text-blue-600 transition">
                    <i class="fa-solid fa-arrow-left w-6 text-center"></i> <span class="ml-2">กลับหน้าหลัก</span>
                </a>
            </div>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
            User: <span class="font-bold text-gray-700"><?php echo $_SESSION['username']; ?></span>
        </div>
    </aside>

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        
        <header class="bg-white shadow-sm border-b border-gray-200 z-10">
            <div class="px-6 py-3 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-bold text-gray-800" id="pageTitle">ภาพรวมสถิติ (Overview)</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <div class="flex items-center bg-gray-50 rounded-lg px-2 py-1 border">
                        <span class="text-gray-500 mr-2"><i class="fa-regular fa-calendar"></i></span>
                        <input type="date" id="startDate" class="bg-transparent outline-none w-28" value="<?php echo $fiscalStartDate; ?>">
                        <span class="mx-2 text-gray-400">-</span>
                        <input type="date" id="endDate" class="bg-transparent outline-none w-28" value="<?php echo $currentDate; ?>">
                    </div>
                    <div class="flex items-center bg-gray-50 rounded-lg px-2 py-1 border" title="Capacity">
                        <i class="fa-solid fa-bed text-gray-400 mr-2"></i>
                        <input type="number" id="bedCapacity" class="bg-transparent outline-none w-12 text-center" value="100">
                    </div>
                    <button onclick="loadReport()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition shadow-sm font-bold flex items-center gap-1">
                        <i class="fa-solid fa-filter"></i> <span class="hidden sm:inline">กรอง</span>
                    </button>
                    <button onclick="exportExcel()" class="bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition shadow-sm font-bold flex items-center gap-1">
                        <i class="fa-solid fa-file-excel"></i> <span class="hidden sm:inline">Excel</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 scroll-smooth">
            
            <div id="sec-overview" class="section-content space-y-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500 relative">
                        <div class="text-xs text-gray-500 mb-1">อัตราครองเตียง</div>
                        <div class="text-2xl font-bold text-blue-700"><span id="avgOccupancy">0</span>%</div>
                        <i class="fa-solid fa-bed absolute right-3 bottom-3 text-blue-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500 relative">
                        <div class="text-xs text-gray-500 mb-1">รายได้รวม (D/C)</div>
                        <div class="text-2xl font-bold text-green-700">฿<span id="totalRevenue">0</span></div>
                        <i class="fa-solid fa-money-bill-wave absolute right-3 bottom-3 text-green-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-purple-500 relative">
                        <div class="text-xs text-gray-500 mb-1">จำหน่ายรวม (ราย)</div>
                        <div class="text-2xl font-bold text-purple-700"><span id="totalDischarge">0</span></div>
                        <i class="fa-solid fa-user-check absolute right-3 bottom-3 text-purple-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-orange-500 relative">
                        <div class="text-xs text-gray-500 mb-1">วันนอนเฉลี่ย (วัน)</div>
                        <div class="text-2xl font-bold text-orange-700"><span id="avgLosAll">0</span></div>
                        <i class="fa-solid fa-clock-rotate-left absolute right-3 bottom-3 text-orange-100 text-4xl"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-chart-column text-blue-500"></i> แนวโน้มผู้ป่วย (Census Trend)
                    </h3>
                    <div class="h-64 w-full">
                        <canvas id="censusChart"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-percent text-indigo-500"></i> อัตราครองเตียง (Occupancy Rate)
                        </h3>
                        <div class="h-60 w-full">
                            <canvas id="occupancyChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-hourglass-half text-orange-500"></i> วันนอนเฉลี่ย (Avg LOS Trend)
                        </h3>
                        <div class="h-60 w-full">
                            <canvas id="losChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div id="sec-financial" class="section-content hidden space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2">
                                <i class="fa-solid fa-chart-mixed text-green-600"></i> วิเคราะห์รายรับและค่าเฉลี่ย (Revenue Analysis)
                            </h3>
                            <div class="flex bg-gray-100 rounded-lg p-1 mt-2 sm:mt-0">
                                <button onclick="updateFinancialChart('daily')" class="time-btn active px-3 py-1 rounded text-xs font-bold transition" id="btn-daily">รายวัน</button>
                                <button onclick="updateFinancialChart('weekly')" class="time-btn px-3 py-1 rounded text-xs font-bold transition text-gray-600" id="btn-weekly">รายสัปดาห์</button>
                                <button onclick="updateFinancialChart('monthly')" class="time-btn px-3 py-1 rounded text-xs font-bold transition text-gray-600" id="btn-monthly">รายเดือน</button>
                            </div>
                        </div>
                        <div class="h-80 w-full">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                        <div class="px-5 py-4 border-b border-gray-100 bg-yellow-50">
                            <h3 class="font-bold text-yellow-800 flex items-center gap-2">
                                <i class="fa-solid fa-trophy text-yellow-500"></i> 10 อันดับ ค่าใช้จ่ายสูงสุด (High Cost)
                            </h3>
                        </div>
                        <div class="overflow-auto flex-1">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-yellow-100/50 text-yellow-800 font-bold">
                                    <tr>
                                        <th class="px-4 py-2 w-10 text-center">#</th>
                                        <th class="px-4 py-2">AN</th>
                                        <th class="px-4 py-2">ชื่อ-สกุล</th>
                                        <th class="px-4 py-2">สิทธิ</th>
                                        <th class="px-4 py-2 text-right">ยอดเงิน</th>
                                    </tr>
                                </thead>
                                <tbody id="top10TableBody" class="divide-y divide-gray-100 text-gray-700"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="sec-collection" class="section-content hidden space-y-6">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <i class="fa-solid fa-cash-register text-emerald-600"></i> ระบบรายงานการเก็บเงิน
                            </h3>
                            <p class="text-xs text-gray-500 mt-1" id="collectionRangeText">-</p>
                        </div>
                        <div class="flex flex-col md:flex-row gap-2 md:items-center">
                            <div class="flex bg-gray-100 rounded-lg p-1 overflow-x-auto">
                                <button onclick="loadCollectionReport('daily')" class="collection-btn active px-3 py-1.5 rounded text-xs font-bold transition whitespace-nowrap" id="collection-btn-daily">รายวัน</button>
                                <button onclick="loadCollectionReport('weekly')" class="collection-btn px-3 py-1.5 rounded text-xs font-bold transition text-gray-600 whitespace-nowrap" id="collection-btn-weekly">รายสัปดาห์</button>
                                <button onclick="loadCollectionReport('monthly')" class="collection-btn px-3 py-1.5 rounded text-xs font-bold transition text-gray-600 whitespace-nowrap" id="collection-btn-monthly">รายเดือน</button>
                                <button onclick="loadCollectionReport('fiscal')" class="collection-btn px-3 py-1.5 rounded text-xs font-bold transition text-gray-600 whitespace-nowrap" id="collection-btn-fiscal">รายปีงบ</button>
                                <button onclick="loadCollectionReport('custom')" class="collection-btn px-3 py-1.5 rounded text-xs font-bold transition text-gray-600 whitespace-nowrap" id="collection-btn-custom">กำหนดเอง</button>
                            </div>
                            <select id="collectionGroup" onchange="loadCollectionReport('custom')" class="border rounded-lg px-3 py-2 text-xs bg-white">
                                <option value="daily">รวมรายวัน</option>
                                <option value="weekly">รวมรายสัปดาห์</option>
                                <option value="monthly">รวมรายเดือน</option>
                                <option value="fiscal">รวมรายปีงบ</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-emerald-500 relative">
                        <div class="text-xs text-gray-500 mb-1">ยอดเก็บเงินรวม</div>
                        <div class="text-2xl font-bold text-emerald-700">฿<span id="collectionTotal">0.00</span></div>
                        <i class="fa-solid fa-sack-dollar absolute right-3 bottom-3 text-emerald-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-sky-500 relative">
                        <div class="text-xs text-gray-500 mb-1">จำนวนรายการ</div>
                        <div class="text-2xl font-bold text-sky-700"><span id="collectionCount">0</span></div>
                        <i class="fa-solid fa-receipt absolute right-3 bottom-3 text-sky-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-amber-500 relative">
                        <div class="text-xs text-gray-500 mb-1">เฉลี่ยต่อรายการ</div>
                        <div class="text-2xl font-bold text-amber-700">฿<span id="collectionAverage">0.00</span></div>
                        <i class="fa-solid fa-calculator absolute right-3 bottom-3 text-amber-100 text-4xl"></i>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-chart-column text-emerald-600"></i> ยอดเก็บเงินตามช่วงเวลา
                        </h3>
                        <div class="h-80 w-full">
                            <canvas id="collectionChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 bg-emerald-50">
                            <h3 class="font-bold text-emerald-800 flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-emerald-600"></i> รายการเก็บเงินสูงสุด
                            </h3>
                        </div>
                        <div class="overflow-auto max-h-80">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600 sticky top-0">
                                    <tr>
                                        <th class="p-2 text-left">รายการ</th>
                                        <th class="p-2 text-right">จำนวน</th>
                                        <th class="p-2 text-right">ยอดเงิน</th>
                                    </tr>
                                </thead>
                                <tbody id="collectionItemBody" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                        <h3 class="font-bold text-gray-700 flex items-center gap-2">
                            <i class="fa-solid fa-table text-gray-500"></i> รายละเอียดรายการรับเงิน
                        </h3>
                        <button onclick="loadCollectionReport(currentCollectionMode || 'custom')" class="bg-white border text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-100 text-xs font-bold">
                            <i class="fa-solid fa-rotate"></i> รีเฟรช
                        </button>
                    </div>
                    <div class="overflow-auto max-h-[440px]">
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead class="bg-gray-100 text-gray-600 sticky top-0">
                                <tr>
                                    <th class="p-2 text-center">วันที่</th>
                                    <th class="p-2 text-left">HN/AN</th>
                                    <th class="p-2 text-left">ชื่อผู้ป่วย</th>
                                    <th class="p-2 text-left">ใบเสร็จ</th>
                                    <th class="p-2 text-left">รายการ</th>
                                    <th class="p-2 text-right">จำนวนเงิน</th>
                                    <th class="p-2 text-left">ผู้บันทึก</th>
                                    <th class="p-2 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="collectionTransactionBody" class="divide-y divide-gray-100 text-gray-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="sec-depositDue" class="section-content hidden space-y-6">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <i class="fa-solid fa-file-invoice-dollar text-orange-600"></i> รายงานประจำวันผู้ป่วยยังไม่ D/C สิทธิชำระเงิน
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">ผู้ป่วยในปัจจุบันที่ต้องติดตาม/เก็บมัดจำรายวัน</p>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="flex items-center bg-gray-50 rounded-lg px-2 py-1 border text-xs">
                                <i class="fa-regular fa-calendar text-gray-400 mr-2"></i>
                                <input type="date" id="depositDueStartDate" class="bg-transparent outline-none w-28" value="<?php echo $currentDate; ?>" onchange="updateDepositDuePeriodText()">
                                <span class="mx-2 text-gray-400">-</span>
                                <input type="date" id="depositDueEndDate" class="bg-transparent outline-none w-28" value="<?php echo $currentDate; ?>" onchange="updateDepositDuePeriodText()">
                            </div>
                            <div id="depositDuePeriodText" class="text-xs text-gray-500 whitespace-nowrap"></div>
                            <button onclick="loadDepositDueReport()" class="bg-white border text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-100 text-xs font-bold">
                                <i class="fa-solid fa-rotate"></i> รีเฟรช
                            </button>
                            <button onclick="exportDepositDueExcel()" class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 text-xs font-bold">
                                <i class="fa-solid fa-file-excel"></i> Excel
                            </button>
                            <button onclick="printDepositDueReport()" class="bg-orange-600 text-white px-3 py-2 rounded-lg hover:bg-orange-700 text-xs font-bold">
                                <i class="fa-solid fa-print"></i> พิมพ์รายงาน
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500 relative">
                        <div class="text-xs text-gray-500 mb-1">จำนวนผู้ป่วย</div>
                        <div class="text-2xl font-bold text-blue-700"><span id="depositDueCount">0</span></div>
                        <i class="fa-solid fa-user-injured absolute right-3 bottom-3 text-blue-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-rose-500 relative">
                        <div class="text-xs text-gray-500 mb-1">ค่ารักษารวม</div>
                        <div class="text-2xl font-bold text-rose-700">฿<span id="depositDueCost">0.00</span></div>
                        <i class="fa-solid fa-notes-medical absolute right-3 bottom-3 text-rose-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-emerald-500 relative">
                        <div class="text-xs text-gray-500 mb-1">มัดจำ/ชำระแล้ว</div>
                        <div class="text-2xl font-bold text-emerald-700">฿<span id="depositDuePaid">0.00</span></div>
                        <i class="fa-solid fa-sack-dollar absolute right-3 bottom-3 text-emerald-100 text-4xl"></i>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-orange-500 relative">
                        <div class="text-xs text-gray-500 mb-1">คงเหลือต้องเก็บ</div>
                        <div class="text-2xl font-bold text-orange-700">฿<span id="depositDueBalance">0.00</span></div>
                        <i class="fa-solid fa-hand-holding-dollar absolute right-3 bottom-3 text-orange-100 text-4xl"></i>
                    </div>
                </div>

                <div id="depositDuePrintArea" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-gray-800">ทะเบียนคุมผู้ป่วยใน สิทธิชำระเงินเอง</h3>
                            <p class="text-xs text-gray-500">ประจำวันที่ <span id="depositDueDateText">-</span></p>
                        </div>
                        <div class="text-xs text-gray-500">แสดงเฉพาะผู้ป่วยที่ยังไม่ D/C</div>
                    </div>
                    <div class="overflow-auto">
                        <table class="w-full text-xs whitespace-nowrap">
                            <thead class="bg-gray-100 text-gray-700 sticky top-0">
                                <tr>
                                    <th class="p-2 border text-center">No.</th>
                                    <th class="p-2 border text-left min-w-[180px]">ชื่อ-นามสกุล</th>
                                    <th class="p-2 border text-center">HN</th>
                                    <th class="p-2 border text-center">AN</th>
                                    <th class="p-2 border text-center">วันที่ Admit</th>
                                    <th class="p-2 border text-left min-w-[170px]">สิทธิ/ประเภทชำระ</th>
                                    <th class="p-2 border text-center">ตึกผู้ป่วย</th>
                                    <th class="p-2 border text-center">เตียง</th>
                                    <th class="p-2 border text-right">ค่ารักษา<br>ปัจจุบัน</th>
                                    <th class="p-2 border text-right">มัดจำ/<br>ชำระแล้ว</th>
                                    <th class="p-2 border text-right">คงเหลือ<br>ต้องเก็บ</th>
                                    <th class="p-2 border text-left min-w-[140px]">หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody id="depositDueTableBody" class="divide-y divide-gray-100 text-gray-700"></tbody>
                            <tfoot class="bg-orange-50 font-bold text-gray-800">
                                <tr>
                                    <td class="p-2 border text-right" colspan="8">รวม</td>
                                    <td class="p-2 border text-right" id="depositDueFootCost">0.00</td>
                                    <td class="p-2 border text-right" id="depositDueFootPaid">0.00</td>
                                    <td class="p-2 border text-right" id="depositDueFootBalance">0.00</td>
                                    <td class="p-2 border"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div id="sec-rights" class="section-content hidden space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[500px]">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-pink-500"></i> สัดส่วนสิทธิการรักษา
                        </h3>
                        <div class="flex-1 relative min-h-0">
                            <canvas id="rightsChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-list-ul text-gray-500"></i> รายละเอียดตามสิทธิ
                        </h3>
                        <div class="overflow-auto flex-1 border rounded-lg">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 font-bold text-gray-600 sticky top-0">
                                    <tr>
                                        <th class="p-3">ชื่อสิทธิ</th>
                                        <th class="p-3 text-right">จำนวน (คน)</th>
                                        <th class="p-3 text-right">รายได้ (บาท)</th>
                                    </tr>
                                </thead>
                                <tbody id="rightsTableBody" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="sec-daily" class="section-content hidden h-full flex flex-col">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-full">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center flex-shrink-0">
                        <h3 class="font-bold text-gray-700 flex items-center gap-2">
                            <i class="fa-solid fa-calendar-days text-blue-500"></i> ตารางข้อมูลรายวัน
                        </h3>
                        <span class="text-xs text-gray-400 hidden sm:block">* คลิกที่หัวข้อตารางเพื่อแสดงกราฟแนวโน้ม</span>
                    </div>
                    <div class="flex-grow overflow-auto relative w-full bg-white rounded-b-xl"> 
                        <table class="w-full text-left whitespace-nowrap table-compact" id="reportTable">
                            <thead class="bg-gray-100 text-gray-600 uppercase font-bold sticky top-0 z-20 shadow-sm">
                                <tr id="tableHeaderRow"></tr>
                            </thead>
                            <tbody id="tableBody" class="divide-y divide-gray-100 text-gray-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div id="trendModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-4xl mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="modal-content py-4 text-left px-6">
                <div class="flex justify-between items-center pb-3 border-b">
                    <p class="text-2xl font-bold text-gray-800" id="modalTitle">หัวข้อกราฟ</p>
                    <div class="modal-close cursor-pointer z-50" onclick="toggleModal()">
                        <i class="fa-solid fa-xmark text-2xl text-gray-500"></i>
                    </div>
                </div>
                <div class="my-5 h-[400px]">
                    <canvas id="modalChart"></canvas>
                </div>
                <div class="flex justify-end pt-2 border-t">
                    <button class="px-4 bg-blue-500 p-2 rounded-lg text-white hover:bg-blue-400 font-bold" onclick="toggleModal()">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <div id="depositDueNoteModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-[60] hidden items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="bg-orange-500 text-white px-4 py-3 flex items-center justify-between">
                <h3 class="font-bold text-base"><i class="fa-solid fa-note-sticky"></i> หมายเหตุรายงานมัดจำ</h3>
                <button type="button" onclick="closeDepositDueNoteModal()" class="text-white hover:text-gray-200">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-4 space-y-3">
                <div class="bg-gray-50 border rounded p-3 text-sm">
                    <div class="font-bold text-gray-800" id="depositDueNotePatient">-</div>
                    <div class="text-xs text-gray-500 mt-1">
                        HN: <span id="depositDueNoteHn" class="font-mono"></span>
                        <span class="mx-2">|</span>
                        AN: <span id="depositDueNoteAn" class="font-mono"></span>
                    </div>
                </div>
                <input type="hidden" id="depositDueNoteHiddenHn">
                <input type="hidden" id="depositDueNoteHiddenAn">
                <input type="hidden" id="depositDueNoteHiddenName">
                <textarea id="depositDueNoteText" class="w-full border rounded p-3 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400" rows="5" placeholder="ระบุหมายเหตุสำหรับติดตามรายนี้..."></textarea>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeDepositDueNoteModal()" class="px-4 py-2 rounded border text-gray-700 hover:bg-gray-50 text-sm font-bold">ยกเลิก</button>
                    <button type="button" onclick="saveDepositDueNote()" id="depositDueNoteSaveBtn" class="px-4 py-2 rounded bg-orange-600 text-white hover:bg-orange-700 text-sm font-bold">
                        <i class="fa-solid fa-save"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let censusChart, revenueChart, rightsChart, occupancyChart, losChart, modalChart, collectionChart;
    let cachedData = null; 
    let currentCollectionMode = 'daily';
    let depositDueLoaded = false;
    let currentDepositDueRows = [];
    let currentDepositDueMeta = {};
    const currentUserRole = <?php echo json_encode($_SESSION['role'] ?? 'user'); ?>;
    const canDeleteCollection = String(currentUserRole).trim().toLowerCase() === 'admin';

    $(document).ready(function() {
        updateDepositDuePeriodText();
        loadReport();
        const requestedSection = new URLSearchParams(window.location.search).get('section');
        if (requestedSection) {
            const menu = document.querySelector(`a[onclick="showSection('${requestedSection}', this)"]`);
            if (menu) showSection(requestedSection, menu);
        }
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    function showSection(sectionId, element) {
        document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
        if(element) element.classList.add('active');

        document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('sec-' + sectionId).classList.remove('hidden');

        const titles = {
            'overview': 'ภาพรวมสถิติ (Overview)',
            'financial': 'รายงานการเงิน (Financial)',
            'collection': 'รายงานการเก็บเงิน',
            'depositDue': 'รายงานมัดจำรายวัน',
            'rights': 'สถิติสิทธิการรักษา (Rights)',
            'daily': 'ตารางข้อมูลรายวัน (Daily Data)'
        };
        document.getElementById('pageTitle').innerText = titles[sectionId];

        if(window.innerWidth < 768) toggleSidebar();

        if (sectionId === 'overview') {
            if(censusChart) censusChart.resize();
            if(occupancyChart) occupancyChart.resize();
            if(losChart) losChart.resize();
        }
        if (sectionId === 'financial' && revenueChart) revenueChart.resize();
        if (sectionId === 'collection') {
            if(collectionChart) collectionChart.resize();
            if(!collectionChart) loadCollectionReport(currentCollectionMode);
        }
        if (sectionId === 'depositDue' && !depositDueLoaded) loadDepositDueReport();
        if (sectionId === 'rights' && rightsChart) rightsChart.resize();
    }

    function toggleModal() {
        const body = document.querySelector('body');
        const modal = document.querySelector('.modal');
        modal.classList.toggle('opacity-0');
        modal.classList.toggle('pointer-events-none');
        body.classList.toggle('modal-active');
    }

    function exportExcel() {
        const start = $('#startDate').val();
        const end = $('#endDate').val();
        window.open(`export_report.php?start=${start}&end=${end}`, '_blank');
    }

    function loadReport() {
        const start = $('#startDate').val();
        const end = $('#endDate').val();
        const capacity = parseInt($('#bedCapacity').val()) || 100;

        $.ajax({
            url: 'api/get_report_data.php',
            data: { start: start, end: end },
            success: function(response) {
                if(response.status === 'success') {
                    cachedData = response; 
                    updateAllVisuals(response, capacity);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() { alert('เชื่อมต่อ Server ไม่ได้'); }
        });
    }

    function updateAllVisuals(data, capacity) {
        calculateKPI(data.data, capacity);
        renderTable(data.data, data.wards, capacity);
        renderTop10(data.top10);
        renderRightsTable(data.rights);

        const chartData = [...data.data].reverse(); 
        
        renderCensusChart(chartData);
        renderOccupancyChart(chartData, capacity);
        renderLosChart(chartData);
        renderRightsChart(data.rights);
        
        updateFinancialChart('daily');
    }

    // ฟังก์ชันใหม่สำหรับแสดง Modal กราฟ
    function showTrendModal(type, title, wardName = null) {
        if(!cachedData) return;
        const rawData = [...cachedData.data].reverse();
        const labels = rawData.map(d => d.date_thai);
        let datasetLabel = title;
        let dataValues = [];
        let color = '#3B82F6';

        if (type === 'census') dataValues = rawData.map(d => d.census);
        else if (type === 'admit') { dataValues = rawData.map(d => d.admit); color = '#10B981'; }
        else if (type === 'discharge') { dataValues = rawData.map(d => d.discharge); color = '#EF4444'; }
        else if (type === 'revenue') { dataValues = rawData.map(d => d.revenue); color = '#F59E0B'; }
        else if (type === 'los') { dataValues = rawData.map(d => d.avg_los); color = '#8B5CF6'; }
        else if (type === 'occupancy') { 
            const cap = parseInt($('#bedCapacity').val()) || 100;
            dataValues = rawData.map(d => ((d.census / cap) * 100).toFixed(1)); 
            color = '#6366F1';
        }
        else if (type === 'ward_admit') {
            dataValues = rawData.map(d => (d.ward_breakdown[wardName] ? d.ward_breakdown[wardName].a : 0));
            datasetLabel = `รับใหม่: ${wardName}`;
            color = '#10B981';
        }
        else if (type === 'ward_dsc') {
            dataValues = rawData.map(d => (d.ward_breakdown[wardName] ? d.ward_breakdown[wardName].d : 0));
            datasetLabel = `จำหน่าย: ${wardName}`;
            color = '#EF4444';
        }

        $('#modalTitle').text(title);
        toggleModal();

        const ctx = document.getElementById('modalChart').getContext('2d');
        if(modalChart) modalChart.destroy();
        
        modalChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: dataValues,
                    borderColor: color,
                    backgroundColor: color + '20',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function updateFinancialChart(mode) {
        $('.time-btn').removeClass('active').addClass('text-gray-600');
        $('#btn-'+mode).addClass('active').removeClass('text-gray-600');

        if(!cachedData) return;
        const rawData = [...cachedData.data].reverse();

        let labels = [], revenues = [], avgCosts = [];

        if (mode === 'daily') {
            labels = rawData.map(d => d.date_thai);
            revenues = rawData.map(d => d.revenue);
            avgCosts = rawData.map(d => d.discharge > 0 ? (d.revenue / d.discharge) : 0);
        } else if (mode === 'weekly' || mode === 'monthly') {
            let grouped = {};
            rawData.forEach(d => {
                let key;
                if(mode === 'monthly') {
                    key = d.date.substring(0, 7);
                } else {
                    const date = new Date(d.date);
                    const startOfYear = new Date(date.getFullYear(), 0, 1);
                    const pastDays = (date - startOfYear) / 86400000;
                    const weekNum = Math.ceil((pastDays + startOfYear.getDay() + 1) / 7);
                    key = `W${weekNum}-${date.getFullYear()}`;
                }
                if(!grouped[key]) grouped[key] = { rev: 0, dis: 0, count: 0, label: key };
                grouped[key].rev += d.revenue;
                grouped[key].dis += d.discharge;
                grouped[key].count++;
            });
            Object.values(grouped).forEach(g => {
                labels.push(g.label);
                revenues.push(g.rev);
                avgCosts.push(g.dis > 0 ? (g.rev / g.dis) : 0);
            });
        }
        renderRevenueChart(labels, revenues, avgCosts, mode);
    }

    function renderRevenueChart(labels, revenues, avgCosts, mode) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        if(revenueChart) revenueChart.destroy();

        revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'รายรับรวม (บาท)', data: revenues, backgroundColor: '#10B981', order: 2, yAxisID: 'y' },
                    { label: 'เฉลี่ยต่อราย (บาท/คน)', data: avgCosts, type: 'line', borderColor: '#F59E0B', backgroundColor: 'rgba(245, 158, 11, 0.1)', borderWidth: 2, tension: 0.3, order: 1, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'รายรับรวม (บาท)' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'เฉลี่ยต่อราย' } }
                },
                plugins: {
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('en-US', { style: 'currency', currency: 'THB' }).format(ctx.parsed.y) } }
                }
            }
        });
    }

    function loadCollectionReport(mode = 'daily') {
        currentCollectionMode = mode;
        $('.collection-btn').removeClass('active').addClass('text-gray-600');
        $('#collection-btn-' + mode).addClass('active').removeClass('text-gray-600');

        const params = { mode: mode };
        if (mode === 'custom') {
            params.start = $('#startDate').val();
            params.end = $('#endDate').val();
            params.group = $('#collectionGroup').val() || 'daily';
        }

        $('#collectionTransactionBody').html('<tr><td colspan="8" class="text-center py-6 text-gray-400">กำลังโหลดข้อมูล...</td></tr>');

        $.ajax({
            url: 'api/get_collection_report.php',
            data: params,
            success: function(response) {
                if (response.status !== 'success') {
                    alert('Error: ' + response.message);
                    return;
                }
                renderCollectionReport(response);
            },
            error: function() {
                alert('เชื่อมต่อ Server รายงานการเก็บเงินไม่ได้');
            }
        });
    }

    function renderCollectionReport(data) {
        const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const number = new Intl.NumberFormat('en-US');
        const modeText = {
            daily: 'รายวัน',
            weekly: 'รายสัปดาห์',
            monthly: 'รายเดือน',
            fiscal: 'รายปีงบประมาณ',
            custom: 'ตามช่วงวันที่ที่กำหนด'
        };

        $('#collectionRangeText').text(`${modeText[data.mode] || ''} | ${formatThaiDate(data.start)} - ${formatThaiDate(data.end)} | การรวมผล: ${data.group}`);
        $('#collectionTotal').text(money.format(data.totals.amount));
        $('#collectionCount').text(number.format(data.totals.count));
        $('#collectionAverage').text(money.format(data.totals.average));

        renderCollectionChart(data.summary || []);
        renderCollectionItems(data.items || []);
        renderCollectionTransactions(data.transactions || []);
    }

    function renderCollectionChart(summary) {
        const ctx = document.getElementById('collectionChart').getContext('2d');
        if(collectionChart) collectionChart.destroy();

        collectionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: summary.map(row => row.label),
                datasets: [
                    {
                        label: 'ยอดเก็บเงิน (บาท)',
                        data: summary.map(row => row.amount),
                        backgroundColor: '#059669',
                        borderRadius: 5,
                        yAxisID: 'y'
                    },
                    {
                        label: 'จำนวนรายการ',
                        data: summary.map(row => row.count),
                        type: 'line',
                        borderColor: '#0284C7',
                        backgroundColor: 'rgba(2, 132, 199, 0.12)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'บาท' } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'รายการ' } }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(ctx.parsed.y)
                        }
                    }
                }
            }
        });
    }

    function renderCollectionItems(items) {
        if (!items.length) {
            $('#collectionItemBody').html('<tr><td colspan="3" class="text-center py-6 text-gray-400">ไม่พบข้อมูล</td></tr>');
            return;
        }
        const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const html = items.map(item => `
            <tr class="hover:bg-emerald-50/50">
                <td class="p-2 font-medium text-gray-700">${escapeHtml(item.name)}</td>
                <td class="p-2 text-right text-gray-600">${item.count}</td>
                <td class="p-2 text-right font-bold text-emerald-700">฿${money.format(item.amount)}</td>
            </tr>
        `).join('');
        $('#collectionItemBody').html(html);
    }

    function renderCollectionTransactions(rows) {
        if (!rows.length) {
            $('#collectionTransactionBody').html('<tr><td colspan="8" class="text-center py-8 text-gray-400">ไม่พบรายการรับเงินในช่วงนี้</td></tr>');
            return;
        }
        const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const html = rows.map(row => {
            const deleteButton = canDeleteCollection
                ? `<button onclick="deleteCollectionDeposit(${parseInt(row.id, 10) || 0})" class="bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded text-[10px] font-bold" title="ลบข้อมูล"><i class="fa-solid fa-trash"></i> ลบ</button>`
                : '<span class="text-gray-300">-</span>';
            return `
            <tr class="hover:bg-gray-50">
                <td class="p-2 text-center">${formatThaiDate(row.receipt_date)}</td>
                <td class="p-2 font-mono text-xs text-blue-700">
                    <div>HN: ${escapeHtml(row.hn || '-')}</div>
                    <div class="text-gray-400">AN: ${escapeHtml(row.an || '-')}</div>
                </td>
                <td class="p-2 font-medium">${escapeHtml(row.pt_name || '-')}</td>
                <td class="p-2">${escapeHtml(row.receipt_no || '-')}</td>
                <td class="p-2">${escapeHtml(row.item_desc || '-')}</td>
                <td class="p-2 text-right font-bold text-emerald-700">฿${money.format(parseFloat(row.amount) || 0)}</td>
                <td class="p-2 text-xs text-gray-500">${escapeHtml(row.recorded_by || '-')}</td>
                <td class="p-2 text-center">${deleteButton}</td>
            </tr>
        `}).join('');
        $('#collectionTransactionBody').html(html);
    }

    function deleteCollectionDeposit(id) {
        if (!canDeleteCollection || !id) return;

        const reason = prompt('กรุณาระบุเหตุผลในการลบข้อมูล');
        if (reason === null) return;
        if (!reason.trim()) {
            alert('กรุณาระบุเหตุผลในการลบ');
            return;
        }
        if (!confirm('ยืนยันการลบรายการนี้?')) return;

        $.ajax({
            url: 'api/manage_deposit.php',
            type: 'POST',
            data: {
                action: 'delete',
                id: id,
                reason: reason.trim()
            },
            success: function(res) {
                if (res.status === 'success') {
                    alert('ลบข้อมูลสำเร็จ');
                    loadCollectionReport(currentCollectionMode || 'custom');
                } else {
                    alert(res.message || 'ไม่สามารถลบข้อมูลได้');
                }
            },
            error: function() {
                alert('เชื่อมต่อ Server ไม่ได้');
            }
        });
    }

    function loadDepositDueReport() {
        depositDueLoaded = true;
        updateDepositDuePeriodText();
        const start = $('#depositDueStartDate').val();
        const end = $('#depositDueEndDate').val();
        $('#depositDueTableBody').html('<tr><td colspan="12" class="text-center py-8 text-gray-400">กำลังโหลดข้อมูล...</td></tr>');
        $('#depositDueDateText').text('-');

        $.ajax({
            url: 'api/get_daily_deposit_due.php',
            type: 'GET',
            data: { start: start, end: end },
            dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') {
                    renderDepositDueError(response.message || 'โหลดรายงานไม่สำเร็จ');
                    return;
                }
                renderDepositDueReport(response);
            },
            error: function(xhr) {
                let message = 'เชื่อมต่อ Server รายงานมัดจำรายวันไม่ได้';
                if (xhr.responseText) {
                    message += '\n' + xhr.responseText.substring(0, 300);
                }
                renderDepositDueError(message);
            }
        });
    }

    function renderDepositDueError(message) {
        $('#depositDueCount').text('0');
        $('#depositDueCost').text('0.00');
        $('#depositDuePaid').text('0.00');
        $('#depositDueBalance').text('0.00');
        $('#depositDueFootCost').text('0.00');
        $('#depositDueFootPaid').text('0.00');
        $('#depositDueFootBalance').text('0.00');
        $('#depositDueTableBody').html(`<tr><td colspan="12" class="text-center py-8 text-red-600 whitespace-normal">${escapeHtml(message)}</td></tr>`);
    }

    function renderDepositDueReport(response) {
        const rows = response.data || [];
        currentDepositDueRows = rows;
        currentDepositDueMeta = response || {};
        const totals = response.totals || { cost: 0, deposit: 0, balance: 0 };
        const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        $('#depositDueDateText').text(response.period_start_thai && response.period_end_thai
            ? `${response.period_start_thai} - ${response.period_end_thai}`
            : (response.report_date_thai || formatThaiDate(response.report_date)));
        $('#depositDueCount').text(new Intl.NumberFormat('en-US').format(rows.length));
        $('#depositDueCost').text(money.format(totals.cost || 0));
        $('#depositDuePaid').text(money.format(totals.deposit || 0));
        $('#depositDueBalance').text(money.format(totals.balance || 0));
        $('#depositDueFootCost').text(money.format(totals.cost || 0));
        $('#depositDueFootPaid').text(money.format(totals.deposit || 0));
        $('#depositDueFootBalance').text(money.format(totals.balance || 0));

        if (!rows.length) {
            $('#depositDueTableBody').html('<tr><td colspan="12" class="text-center py-8 text-gray-400">ไม่พบผู้ป่วยสิทธิชำระเงินที่ยังไม่ D/C</td></tr>');
            return;
        }

        const html = rows.map((row, index) => {
            const balance = parseFloat(row.balance) || 0;
            const balanceClass = balance > 0 ? 'text-red-700 bg-red-50' : 'text-emerald-700 bg-emerald-50';
            const patientNote = row.note ? `
                <div class="mb-1">
                    <span class="inline-block bg-amber-100 text-amber-800 rounded px-1.5 py-0.5 text-[10px] font-bold mb-1">ติดตาม</span>
                    <div>${escapeHtml(row.note).replace(/\n/g, '<br>')}</div>
                </div>
            ` : '';
            const paymentNoteList = (row.payment_notes || [])
                .filter(item => String(item.note || '').trim() !== String(row.note || '').trim())
                .map(item => `
                <div class="mt-1 pt-1 border-t border-gray-100">
                    <span class="inline-block bg-blue-100 text-blue-800 rounded px-1.5 py-0.5 text-[10px] font-bold">${escapeHtml(item.date_thai || '-')}</span>
                    <span class="text-gray-500 text-[10px]">${escapeHtml(item.item_desc || '')}</span>
                    <div>${escapeHtml(item.note || '').replace(/\n/g, '<br>')}</div>
                </div>
            `).join('');
            const noteText = (patientNote || paymentNoteList)
                ? `${patientNote}${paymentNoteList}`
                : '<span class="text-gray-300">-</span>';
            const notePayload = encodeURIComponent(JSON.stringify({
                hn: row.hn || '',
                an: row.an || '',
                name: row.name || '',
                note: row.note || ''
            }));
            return `
                <tr class="hover:bg-orange-50/50">
                    <td class="p-2 border text-center">${index + 1}</td>
                    <td class="p-2 border font-medium">${escapeHtml(row.name || '-')}</td>
                    <td class="p-2 border text-center font-mono">${escapeHtml(row.hn || '-')}</td>
                    <td class="p-2 border text-center font-mono">${escapeHtml(row.an || '-')}</td>
                    <td class="p-2 border text-center">${escapeHtml(row.regdate_thai || '-')}</td>
                    <td class="p-2 border">${escapeHtml(row.right || '-')}</td>
                    <td class="p-2 border text-center">${escapeHtml(row.ward || '-')}</td>
                    <td class="p-2 border text-center">${escapeHtml(row.bed || '-')}</td>
                    <td class="p-2 border text-right font-mono">${money.format(parseFloat(row.cost) || 0)}</td>
                    <td class="p-2 border text-right font-mono">${money.format(parseFloat(row.deposit) || 0)}</td>
                    <td class="p-2 border text-right font-mono font-bold ${balanceClass}">${money.format(balance)}</td>
                    <td class="p-2 border whitespace-normal min-w-[140px]">
                        <div class="mb-1">${noteText}</div>
                        <button type="button" onclick="openDepositDueNoteModal('${notePayload}')" class="deposit-due-note-action bg-orange-100 hover:bg-orange-200 text-orange-700 px-2 py-1 rounded text-[10px] font-bold">
                            <i class="fa-solid fa-pen"></i> แก้ไข
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        $('#depositDueTableBody').html(html);
    }

    function openDepositDueNoteModal(encodedData) {
        const row = JSON.parse(decodeURIComponent(encodedData));
        $('#depositDueNotePatient').text(row.name || '-');
        $('#depositDueNoteHn').text(row.hn || '-');
        $('#depositDueNoteAn').text(row.an || '-');
        $('#depositDueNoteHiddenHn').val(row.hn || '');
        $('#depositDueNoteHiddenAn').val(row.an || '');
        $('#depositDueNoteHiddenName').val(row.name || '');
        $('#depositDueNoteText').val(row.note || '');
        $('#depositDueNoteModal').css('display', 'flex').hide().fadeIn(150);
    }

    function closeDepositDueNoteModal() {
        $('#depositDueNoteModal').fadeOut(120);
    }

    function saveDepositDueNote() {
        const btn = $('#depositDueNoteSaveBtn');
        const original = btn.html();
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...');

        $.ajax({
            url: 'api/save_deposit_due_note.php',
            type: 'POST',
            dataType: 'json',
            data: {
                hn: $('#depositDueNoteHiddenHn').val(),
                an: $('#depositDueNoteHiddenAn').val(),
                name: $('#depositDueNoteHiddenName').val(),
                note: $('#depositDueNoteText').val()
            },
            success: function(res) {
                btn.prop('disabled', false).html(original);
                if (res.status === 'success') {
                    closeDepositDueNoteModal();
                    loadDepositDueReport();
                } else {
                    alert(res.message || 'บันทึกหมายเหตุไม่สำเร็จ');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(original);
                alert('เชื่อมต่อ Server ไม่ได้');
            }
        });
    }

    function updateDepositDuePeriodText() {
        const start = $('#depositDueStartDate').val();
        const end = $('#depositDueEndDate').val();
        const text = start && end ? `ช่วง ${formatThaiDate(start)} - ${formatThaiDate(end)}` : '';
        $('#depositDuePeriodText').text(text);
    }

    function buildDepositDueNoteText(row) {
        const notes = [];
        if (String(row.note || '').trim() !== '') {
            notes.push(String(row.note).trim());
        }
        (row.payment_notes || []).forEach(item => {
            const note = String(item.note || '').trim();
            if (note !== '' && !notes.includes(note)) {
                notes.push(`${item.date_thai || '-'} ${item.item_desc || ''}: ${note}`);
            }
        });
        return notes.join('\n');
    }

    function exportDepositDueExcel() {
        const start = $('#depositDueStartDate').val();
        const end = $('#depositDueEndDate').val();
        if (!start || !end) {
            alert('กรุณาเลือกช่วงวันที่');
            return;
        }

        $.ajax({
            url: 'api/get_daily_deposit_due.php',
            type: 'GET',
            dataType: 'json',
            data: { start: start, end: end },
            success: function(response) {
                if (response.status !== 'success') {
                    alert(response.message || 'ส่งออก Excel ไม่สำเร็จ');
                    return;
                }
                downloadDepositDueExcel(response);
            },
            error: function() {
                alert('เชื่อมต่อ Server ไม่ได้');
            }
        });
    }

    function downloadDepositDueExcel(response) {
        const rows = response.data || [];
        const totals = response.totals || {};
        const periodText = `${response.period_start_thai || formatThaiDate(response.period_start)} - ${response.period_end_thai || formatThaiDate(response.period_end)}`;
        const money = value => Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const cell = value => escapeHtml(value ?? '').replace(/\n/g, '<br>');

        const bodyRows = rows.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td class="text-left">${cell(row.name || '-')}</td>
                <td>${cell(row.hn || '-')}</td>
                <td>${cell(row.an || '-')}</td>
                <td>${cell(row.regdate_thai || '-')}</td>
                <td class="text-left">${cell(row.right || '-')}</td>
                <td>${cell(row.ward || '-')}</td>
                <td>${cell(row.bed || '-')}</td>
                <td class="num">${money(row.cost)}</td>
                <td class="num">${money(row.deposit)}</td>
                <td class="num">${money(row.period_deposit)}</td>
                <td class="num">${money(row.balance)}</td>
                <td class="text-left">${cell(buildDepositDueNoteText(row) || '-')}</td>
            </tr>
        `).join('');

        const html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Tahoma, Arial, sans-serif; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #999; padding: 5px; font-size: 12px; vertical-align: top; }
                    th { background: #f97316; color: #fff; text-align: center; font-weight: bold; }
                    .title { font-size: 18px; font-weight: bold; text-align: center; }
                    .subtitle { text-align: center; margin-bottom: 12px; }
                    .text-left { text-align: left; }
                    .num { text-align: right; mso-number-format:'\\#\\,\\#\\#0\\.00'; }
                    .total td { background: #fff7ed; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="title">รายงานติดตามมัดจำผู้ป่วยใน สิทธิชำระเงินเอง</div>
                <div class="subtitle">ช่วงวันที่ ${cell(periodText)}</div>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>HN</th>
                            <th>AN</th>
                            <th>วันที่ Admit</th>
                            <th>สิทธิ/ประเภทชำระ</th>
                            <th>ตึกผู้ป่วย</th>
                            <th>เตียง</th>
                            <th>ค่ารักษาปัจจุบัน</th>
                            <th>มัดจำ/ชำระแล้วทั้งหมด</th>
                            <th>ชำระในช่วงวันที่เลือก</th>
                            <th>คงเหลือต้องเก็บ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bodyRows || '<tr><td colspan="13">ไม่พบข้อมูล</td></tr>'}
                        <tr class="total">
                            <td colspan="8" class="text-left">รวม</td>
                            <td class="num">${money(totals.cost)}</td>
                            <td class="num">${money(totals.deposit)}</td>
                            <td class="num">${money(totals.period_deposit)}</td>
                            <td class="num">${money(totals.balance)}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        `;

        const filename = `DepositDue_${startSafeDate(response.period_start)}_to_${startSafeDate(response.period_end)}.xls`;
        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function startSafeDate(value) {
        return String(value || '').replace(/[^0-9-]/g, '') || new Date().toISOString().slice(0, 10);
    }

    function printDepositDueReport() {
        if (!depositDueLoaded) {
            loadDepositDueReport();
            setTimeout(() => window.print(), 800);
            return;
        }
        window.print();
    }

    function formatThaiDate(dateStr) {
        if (!dateStr) return '-';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        return `${parts[2]}/${parts[1]}/${parseInt(parts[0], 10) + 543}`;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function calculateKPI(data, capacity) {
        let totalRevenue = 0; let totalDischarge = 0;
        let sumOccupancy = 0; let sumLos = 0; let daysWithDischarge = 0;
        data.forEach(d => {
            totalRevenue += d.revenue;
            totalDischarge += d.discharge;
            let occupancy = (d.census / capacity) * 100;
            sumOccupancy += occupancy;
            if(d.discharge > 0) { sumLos += d.avg_los; daysWithDischarge++; }
        });
        $('#totalRevenue').text(new Intl.NumberFormat('en-US', { notation: "compact", maximumFractionDigits: 1 }).format(totalRevenue));
        $('#totalDischarge').text(new Intl.NumberFormat().format(totalDischarge));
        $('#avgOccupancy').text((data.length > 0 ? sumOccupancy / data.length : 0).toFixed(1));
        $('#avgLosAll').text(daysWithDischarge > 0 ? (sumLos / daysWithDischarge).toFixed(1) : 0);
    }

    function renderCensusChart(data) {
        const ctx = document.getElementById('censusChart').getContext('2d');
        if(censusChart) censusChart.destroy();
        censusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.date_thai),
                datasets: [
                    { label: 'คงเหลือ', data: data.map(d => d.census), type: 'line', borderColor: '#3B82F6', backgroundColor: 'rgba(59, 130, 246, 0.1)', borderWidth: 2, tension: 0.3, fill: true, yAxisID: 'y' },
                    { label: 'รับใหม่', data: data.map(d => d.admit), backgroundColor: '#10B981', borderRadius: 4 },
                    { label: 'จำหน่าย', data: data.map(d => d.discharge), backgroundColor: '#EF4444', borderRadius: 4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false } }
        });
    }

    function renderOccupancyChart(data, capacity) {
        const ctx = document.getElementById('occupancyChart').getContext('2d');
        if(occupancyChart) occupancyChart.destroy();
        const occupancyData = data.map(d => ((d.census / capacity) * 100).toFixed(1));
        occupancyChart = new Chart(ctx, {
            type: 'line',
            data: { labels: data.map(d => d.date_thai), datasets: [{ label: 'อัตราครองเตียง (%)', data: occupancyData, borderColor: '#6366F1', backgroundColor: 'rgba(99, 102, 241, 0.1)', borderWidth: 2, tension: 0.3, fill: true }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, suggestedMax: 100 } }, plugins: { tooltip: { callbacks: { label: ctx => ctx.parsed.y + '%' } } } }
        });
    }

    function renderLosChart(data) {
        const ctx = document.getElementById('losChart').getContext('2d');
        if(losChart) losChart.destroy();
        losChart = new Chart(ctx, {
            type: 'bar',
            data: { labels: data.map(d => d.date_thai), datasets: [{ label: 'วันนอนเฉลี่ย (วัน)', data: data.map(d => d.avg_los), backgroundColor: '#F97316', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' วัน' } } } }
        });
    }

    function renderRightsChart(rightsData) {
        let labels = [], counts = [];
        let i = 0;
        for (let [name, stat] of Object.entries(rightsData)) {
            if(i++ > 8) break;
            labels.push(name.length > 20 ? name.substr(0,20)+'..' : name);
            counts.push(stat.count);
        }
        const ctx = document.getElementById('rightsChart').getContext('2d');
        if(rightsChart) rightsChart.destroy();
        rightsChart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: counts, backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#6366F1', '#14B8A6', '#9CA3AF'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    }

    function renderRightsTable(rightsData) {
        let html = '';
        let totalCount = 0;
        let totalRevenue = 0;

        for (let [name, stat] of Object.entries(rightsData)) {
            totalCount += stat.count;
            totalRevenue += stat.revenue;
            html += `<tr class="hover:bg-gray-50">
                <td class="p-3 truncate max-w-[200px]" title="${name}">${name}</td>
                <td class="p-3 text-right font-bold text-gray-700">${stat.count}</td>
                <td class="p-3 text-right text-green-600">฿${new Intl.NumberFormat().format(stat.revenue)}</td>
            </tr>`;
        }
        
        html += `<tr class="bg-gray-100 font-bold border-t-2 border-gray-200">
            <td class="p-3 text-gray-800">รวมทั้งหมด (Total)</td>
            <td class="p-3 text-right text-blue-700">${new Intl.NumberFormat().format(totalCount)}</td>
            <td class="p-3 text-right text-green-700">฿${new Intl.NumberFormat().format(totalRevenue)}</td>
        </tr>`;

        $('#rightsTableBody').html(html);
    }

    function renderTop10(data) {
        let html = '';
        if(!data || data.length === 0) html = '<tr><td colspan="5" class="text-center py-4 text-gray-400">ไม่พบข้อมูล</td></tr>';
        else {
            data.forEach((item, index) => {
                let rank = index + 1;
                let icon = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : (rank === 3 ? '🥉' : rank));
                let hl = rank <= 3 ? 'font-bold text-red-600' : 'text-gray-600';
                html += `<tr class="hover:bg-yellow-50/50"><td class="px-4 py-2 text-center ${hl}">${icon}</td><td class="px-4 py-2 font-mono text-blue-600">${item.an}</td><td class="px-4 py-2 font-bold text-gray-700">${item.name}</td><td class="px-4 py-2 text-gray-500 text-xs truncate max-w-[100px]" title="${item.right}">${item.right}</td><td class="px-4 py-2 text-right font-mono font-bold text-red-600">฿${new Intl.NumberFormat().format(item.total_amt)}</td></tr>`;
            });
        }
        $('#top10TableBody').html(html);
    }

    function renderTable(data, wards, capacity) {
        const thead = $('#tableHeaderRow');
        const tbody = $('#tableBody');
        
        // หัวตารางแบบคลิกได้เพื่อโชว์กราฟแนวโน้ม
        let headerHtml = `
            <th class="sticky left-0 bg-gray-100 z-30 shadow-sm min-w-[100px] text-center">วันที่</th>
            <th class="text-center text-blue-600 min-w-[60px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('census', 'แนวโน้มผู้ป่วยคงเหลือ (Census)')">คงเหลือ</th>
            <th class="text-center text-green-600 min-w-[50px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('admit', 'แนวโน้มการรับใหม่ (Admit)')">รับ</th>
            <th class="text-center text-red-500 min-w-[50px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('discharge', 'แนวโน้มการจำหน่าย (D/C)')">ออก</th>`;
        
        wards.forEach(w => { 
            let short = w.length > 10 ? w.substr(0,10)+'..' : w; 
            headerHtml += `<th class="text-center min-w-[80px] border-l border-gray-200 truncate px-1 group relative" title="${w}">
                ${short}
                <div class="hidden group-hover:flex absolute top-full left-0 bg-white border shadow p-1 z-50 flex-col gap-1 text-[10px]">
                    <button class="bg-green-100 text-green-700 px-1 rounded" onclick="showTrendModal('ward_admit', 'แนวโน้มรับใหม่: ${w}', '${w}')">กราฟรับ</button>
                    <button class="bg-red-100 text-red-700 px-1 rounded" onclick="showTrendModal('ward_dsc', 'แนวโน้มจำหน่าย: ${w}', '${w}')">กราฟออก</button>
                </div>
            </th>`; 
        });
        
        headerHtml += `
            <th class="text-right border-l border-gray-200 min-w-[80px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('revenue', 'แนวโน้มรายรับ (Revenue)')">รายรับ</th>
            <th class="text-center min-w-[50px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('los', 'แนวโน้มวันนอนเฉลี่ย (LOS)')">LOS</th>
            <th class="text-center min-w-[50px] cursor-pointer hover:bg-gray-200" onclick="showTrendModal('occupancy', 'แนวโน้มอัตราครองเตียง (%)')">%</th>`;
        
        thead.html(headerHtml);

        let bodyHtml = '';
        data.forEach(d => {
            let occupancy = ((d.census / capacity) * 100).toFixed(0);
            let occColor = occupancy > 100 ? 'text-red-600 font-bold' : (occupancy > 85 ? 'text-orange-500' : 'text-gray-600');
            let wardCells = '';
            wards.forEach(w => {
                let s = d.ward_breakdown[w] || {a:0,d:0};
                let txt = (s.a===0 && s.d===0) ? '<span class="text-gray-100">.</span>' : `${s.a>0 ? '<span class="text-green-600 font-bold">+'+s.a+'</span>':'-'} / ${s.d>0 ? '<span class="text-red-500 font-bold">-'+s.d+'</span>':'-'}`;
                wardCells += `<td class="text-center border-l border-gray-100 bg-white text-xs">${txt}</td>`;
            });
            bodyHtml += `<tr class="hover:bg-blue-50 transition group"><td class="sticky left-0 bg-white group-hover:bg-blue-50 z-10 border-r border-gray-200 font-medium text-gray-900 shadow-sm text-center">${d.date_thai} <span class="text-[9px] text-gray-400 block">${d.date}</span></td><td class="text-center font-bold text-blue-600 bg-blue-50/30">${d.census}</td><td class="text-center text-green-600">+${d.admit}</td><td class="text-center text-red-500">-${d.discharge}</td>${wardCells}<td class="text-right font-mono border-l border-gray-100 text-gray-600 text-xs">${d.revenue>0 ? new Intl.NumberFormat().format(d.revenue):'-'}</td><td class="text-center text-gray-500">${d.avg_los||'-'}</td><td class="text-center ${occColor}">${occupancy}%</td></tr>`;
        });
        if(data.length===0) bodyHtml=`<tr><td colspan="${7+wards.length}" class="text-center py-8 text-gray-400">ไม่พบข้อมูล</td></tr>`;
        tbody.html(bodyHtml);
    }
    </script>
</body>
</html>
