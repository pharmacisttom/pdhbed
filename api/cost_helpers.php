<?php

function quote_sql_list(PDO $pdo, array $values) {
    $quoted = [];
    foreach ($values as $value) {
        if ($value !== null && $value !== '') {
            $quoted[] = $pdo->quote((string)$value);
        }
    }
    return implode(',', array_unique($quoted));
}

function build_patient_visit_table(PDO $pdo, array $patients) {
    $rows = [];
    foreach ($patients as $patient) {
        $an = $patient['an'] ?? '';
        $hn = $patient['hn'] ?? '';
        $regdate = $patient['opd_date'] ?? ($patient['regdate'] ?? '');
        if ($regdate === '' || $regdate === '0000-00-00') {
            $regdate = $patient['regdate'] ?? '';
        }
        $frequency = $patient['frequency'] ?? 0;

        if ($an === '' || $hn === '' || $regdate === '' || $regdate === '0000-00-00') {
            continue;
        }

        $rows[] = 'SELECT ' .
            $pdo->quote((string)$an) . ' AS an, ' .
            $pdo->quote((string)$hn) . ' AS hn, ' .
            $pdo->quote((string)$regdate) . ' AS regdate, ' .
            (int)$frequency . ' AS frequency';
    }

    return implode(' UNION ALL ', $rows);
}

function load_patient_costs(PDO $pdo, array $patients) {
    $costs = [];
    if (count($patients) === 0) {
        return $costs;
    }

    $ans = array_values(array_filter(array_column($patients, 'an')));
    $inClause = quote_sql_list($pdo, $ans);
    if ($inClause === '') {
        return $costs;
    }

    $tables = [
        'drug' => ['table' => 'ipd.drug_order_ipd', 'where' => ''],
        'lab' => ['table' => 'ipd.lab_order_ipd', 'where' => ''],
        'xray' => ['table' => 'ipd.xray_order_ipd', 'where' => "AND COALESCE(status_xray, '') <> 'SXRAY0'"],
        'other' => ['table' => 'ipd.other_order_ipd', 'where' => '']
    ];

    foreach ($tables as $key => $config) {
        $table = $config['table'];
        $where = $config['where'];
        try {
            $stmt = $pdo->query("SELECT an, SUM(price) AS total FROM {$table} WHERE an IN ({$inClause}) {$where} GROUP BY an");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $costs[$row['an']][$key] = (float)$row['total'];
            }
        } catch (Exception $e) { }
    }

    // Lab results can originate from OPD/pre-admit workflows and may not have a
    // matching row in ipd.lab_order_ipd, while HIS still includes them in IPD cost.
    try {
        $stmt = $pdo->query("
            SELECT result_cost.an, SUM(result_cost.std_price) AS total
            FROM (
                SELECT r.an, r.orderno, r.labcode, MAX(COALESCE(l.stdPrice, 0)) AS std_price
                FROM ipd.result_lab_ipd r
                LEFT JOIN hos.lablist l ON r.labcode = l.Code
                WHERE r.an IN ({$inClause})
                GROUP BY r.an, r.orderno, r.labcode
            ) result_cost
            LEFT JOIN (
                SELECT DISTINCT an, orderno
                FROM ipd.lab_order_ipd
                WHERE an IN ({$inClause})
            ) lab_order ON lab_order.an = result_cost.an
                       AND lab_order.orderno = result_cost.orderno
            WHERE lab_order.orderno IS NULL
            GROUP BY result_cost.an
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $an = $row['an'];
            $extra = (float)$row['total'];
            if ($extra > 0) {
                $costs[$an]['lab_opd_transfer'] = $extra;
                $costs[$an]['lab'] = ($costs[$an]['lab'] ?? 0) + $extra;
            }
        }
    } catch (Exception $e) { }

    $visitTable = build_patient_visit_table($pdo, $patients);
    if ($visitTable !== '') {
        $opdTables = [
            ['key' => 'drug', 'table' => 'opd.drug_order_opd'],
            ['key' => 'lab', 'table' => 'opd.lab_order_opd'],
            ['key' => 'xray', 'table' => 'opd.xray_order_opd'],
            ['key' => 'other', 'table' => 'opd.other_order_opd'],
            ['key' => 'other', 'table' => 'opd.dent_order_opd']
        ];

        foreach ($opdTables as $opdTable) {
            $key = $opdTable['key'];
            $table = $opdTable['table'];
            try {
                $stmt = $pdo->query("
                    SELECT p.an, SUM(o.price) AS total
                    FROM ({$visitTable}) p
                    INNER JOIN {$table} o ON o.hn = p.hn
                        AND o.regdate = p.regdate
                        AND o.frequency = p.frequency
                    WHERE (o.billno IS NULL OR TRIM(o.billno) = '')
                    GROUP BY p.an
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $an = $row['an'];
                    $amount = (float)$row['total'];
                    if ($amount > 0) {
                        $costs[$an][$key] = ($costs[$an][$key] ?? 0) + $amount;
                        $costs[$an]['opd_unpaid'] = ($costs[$an]['opd_unpaid'] ?? 0) + $amount;
                    }
                }
            } catch (Exception $e) { }
        }
    }

    return $costs;
}

?>
