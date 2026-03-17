#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') die("CLI only.\n");

$root = __DIR__;
$csv = $root . '/recalls.csv';
if (file_exists($root . '/testRabbitMQ.ini')) {
    $ini = $root . '/testRabbitMQ.ini';
} else {
    $ini = $root . '/../Webserver/testRabbitMQ.ini';
}
if (!file_exists($ini)) { echo "Missing testRabbitMQ.ini\n"; exit(1); }

$pushOnly = in_array('--push-only', $argv ?? []);

if (!$pushOnly) {
    $rows = [];
    $seen = [];
    foreach ([['toyota','camry','2020'], ['ford','f-150','2019'], ['honda','accord','2021']] as $v) {
        $response = @file_get_contents(sprintf('https://api.nhtsa.gov/recalls?make=%s&model=%s&modelYear=%s', rawurlencode($v[0]), rawurlencode($v[1]), $v[2]));
        $d = $response ? json_decode($response, true) : null;
        if (empty($d['results'])) continue;
        foreach ($d['results'] as $r) {
            $id = isset($r['campaignId']) ? $r['campaignId'] : (isset($r['nhtsaCampaignNumber']) ? $r['nhtsaCampaignNumber'] : '');
            if ($id === '' || isset($seen[$id])) continue;
            $seen[$id] = true;
            $rows[] = [
                'nhtsa_id' => $id,
                'make' => $v[0],
                'model' => $v[1],
                 'year' => (int)$v[2],
                'component' => $r['typeCode'] ?? '',
                'summary' => $r['subject'] ?? $r['consequence'] ?? '',
                'recall_date' => $r['recall573ReceivedDate'] ?? $r['createDate'] ?? ''
            ];
        }
    }
    if (empty($rows)) { echo "No data from NHTSA\n"; exit(1); }
    $cols = ['nhtsa_id','make','model','year','component','summary','recall_date'];
    $f = fopen($csv, 'w');
    fputcsv($f, $cols);
    for ($i = 0; $i < count($rows); $i++) {
        fputcsv($f, [$rows[$i]['nhtsa_id'], $rows[$i]['make'], $rows[$i]['model'], $rows[$i]['year'], $rows[$i]['component'], $rows[$i]['summary'], $rows[$i]['recall_date']]);
    }
    fclose($f);
    echo count($rows) . " to csv\n";
} else {
    if (!file_exists($csv)) { echo "No csv yet, run without --push-only\n"; exit(1); }
    $rows = [];
    $f = fopen($csv, 'r');
    $header = fgetcsv($f);
    while (($line = fgetcsv($f)) !== false && count($line) >= count($header)) $rows[] = array_combine($header, array_slice($line, 0, count($header)));
    fclose($f);
}

if (empty($rows)) { echo "nothing to push\n"; exit(0); }

require_once $root . '/path.inc';
require_once $root . '/get_host_info.inc';
require_once $root . '/rabbitMQLib.inc';

chdir(realpath(dirname($ini)) ?: $root);
$client = new rabbitMQClient($ini, 'testServer');
foreach (array_chunk($rows, 500) as $batch) $client->publish(['type' => 'INGEST_RECALL_BATCH', 'batch' => $batch]);
echo count($rows) . " pushed\n";
