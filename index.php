<?php
// index.php вЂ“ С‚РѕС‡РєР° РІС…РѕРґР°, СЂРѕСѓС‚РёРЅРі

header('Content-Type: text/html; charset=utf-8');

session_start();

// Р•СЃР»Рё РїРѕР»СЊР·РѕРІР°С‚РµР»СЊ РЅРµ Р°РІС‚РѕСЂРёР·РѕРІР°РЅ вЂ“ РїРѕРєР°Р·С‹РІР°РµРј С„РѕСЂРјСѓ Р»РѕРіРёРЅР°
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/auth.php';
    exit;
}

// РћРїСЂРµРґРµР»СЏРµРј РґРµР№СЃС‚РІРёРµ РїРѕ GET-РїР°СЂР°РјРµС‚СЂР°Рј
$action = $_GET['action'] ?? '';
$view = $_GET['view'] ?? '';

// РћР±СЂР°Р±РѕС‚РєР° СЃРїРµС†РёР°Р»СЊРЅС‹С… РєРѕРјР°РЅРґ (logout, change_password, force_change)
if ($action === 'logout' || $action === 'change_password' || isset($_GET['force_change'])) {
    require_once __DIR__ . '/auth.php';
    exit;
}

// Р•СЃР»Рё Р·Р°РїСЂРѕС€РµРЅС‹ Р»РѕРіРё
if ($view === 'logs' || isset($_GET['logs'])) {
    require_once __DIR__ . '/logs.php';
    exit;
}

// РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ вЂ“ СѓРїСЂР°РІР»РµРЅРёРµ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏРјРё
require_once __DIR__ . '/users.php';
