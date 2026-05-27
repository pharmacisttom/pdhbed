<?php
function ThaiBahtConversion($amount_number) {
    $amount_number = number_format($amount_number, 2, ".", "");
    $pt = strpos($amount_number, ".");
    $number = $fraction = "";
    if ($pt === false) 
        $number = $amount_number;
    else {
        $number = substr($amount_number, 0, $pt);
        $fraction = substr($amount_number, $pt + 1);
    }
    
    $ret = "";
    $baht = ReadNumber($number);
    if ($baht != "") $ret .= $baht . "บาท";
    
    $satang = ReadNumber($fraction);
    if ($satang != "") $ret .=  $satang . "สตางค์";
    else $ret .= "ถ้วน";
    
    return $ret;
}

function ReadNumber($number) {
    $position_call = array("แสน", "หมื่น", "พัน", "ร้อย", "สิบ", "");
    $number_call = array("", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
    $number = $number + 0;
    $ret = "";
    if ($number == 0) return $ret;
    $length = strlen($number);
    for ($i = 0; $i < $length; $i++) {
        $n = substr($number, $i, 1);
        if ($n != 0) {
            if ($i == ($length - 1) && $n == 1) $ret .= "เอ็ด";
            else if ($i == ($length - 2) && $n == 2) $ret .= "ยี่";
            else if ($i == ($length - 2) && $n == 1) $ret .= "";
            else $ret .= $number_call[$n];
            $ret .= $position_call[$i];
        }
    }
    return $ret;
}
?>