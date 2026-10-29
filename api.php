<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
$dataDir = __DIR__ . '/data';
$settingsFile = $dataDir . '/settings.json';
$devicesFile = $dataDir . '/devices.json';
function json_file($file, $fallback) { if (!is_file($file)) return $fallback; $value = json_decode(file_get_contents($file), true); return is_array($value) ? $value : $fallback; }
function save_json($file, $value) { file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX); }
function fail_api($message, $status = 400) { http_response_code($status); echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE); exit; }
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals(PUBLIC_APP_KEY, (string)($payload['app_key'] ?? ''))) fail_api('Chave do aplicativo inválida.', 401);
if (($payload['action'] ?? '') !== 'device_status') fail_api('Ação inválida.');
$deviceId = trim((string)($payload['device_id'] ?? ''));
if ($deviceId === '' || strlen($deviceId) > 180) fail_api('Identificador do aparelho inválido.');
$settings = json_file($settingsFile, ['plan_active' => true, 'lock_message' => '', 'dialog_message' => '']);
$devices = json_file($devicesFile, []);
$index = null;
foreach ($devices as $key => $device) if (($device['device_id'] ?? '') === $deviceId) $index = $key;
$record = $index === null ? ['device_id' => $deviceId, 'blocked' => false, 'message' => ''] : $devices[$index];
$record['imei'] = trim((string)($payload['imei'] ?? ''));
$record['model'] = trim((string)($payload['model'] ?? ''));
$record['android_version'] = trim((string)($payload['android_version'] ?? ''));
$record['last_seen'] = date('c');
if ($index === null) $devices[] = $record; else $devices[$index] = $record;
save_json($devicesFile, $devices);
$blocked = !empty($record['blocked']);
$message = $blocked && !empty($record['message']) ? $record['message'] : (string)($settings['lock_message'] ?? '');
echo json_encode(['ok' => true, 'plan_active' => !empty($settings['plan_active']), 'device_blocked' => $blocked, 'message' => $message, 'dialog' => (string)($settings['dialog_message'] ?? '')], JSON_UNESCAPED_UNICODE);
