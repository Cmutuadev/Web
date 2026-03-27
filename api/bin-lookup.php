<?php
header('Content-Type: application/json');
$bin = substr(preg_replace('/[^0-9]/', '', $_GET['bin'] ?? ''), 0, 6);
if (strlen($bin) < 6) exit(json_encode(['error' => 'Invalid BIN']));
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://bins.antipublic.cc/bins/{$bin}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $data = json_decode($response, true);
    if ($data) exit(json_encode(['bin'=>$bin,'brand'=>$data['brand']??'Unknown','type'=>$data['type']??'Unknown','bank'=>$data['bank']??'Unknown','country'=>$data['country']??'XX','country_name'=>$data['country_name']??'Unknown']));
}
exit(json_encode(['bin'=>$bin,'brand'=>'Unknown','type'=>'Unknown','bank'=>'Unknown','country'=>'XX','country_name'=>'Unknown']));
?>
