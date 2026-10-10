<?php
/**
 * &#x41e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43a;&#x430; &#x443;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x439; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430;&#x43c; &#x432; MAX &#x447;&#x435;&#x440;&#x435;&#x437; Green API.
 *
 * &#x41d;&#x430;&#x441;&#x442;&#x440;&#x43e;&#x439;&#x43a;&#x438; &#x447;&#x438;&#x442;&#x430;&#x44e;&#x442;&#x441;&#x44f; &#x438;&#x437; config.php (&#x43a;&#x43b;&#x44e;&#x447; 'green_api'). &#x415;&#x441;&#x43b;&#x438; apiUrl &#x43d;&#x435;
 * &#x437;&#x430;&#x43f;&#x43e;&#x43b;&#x43d;&#x435;&#x43d; &#x438;&#x43b;&#x438; &#x438;&#x43d;&#x442;&#x435;&#x433;&#x440;&#x430;&#x446;&#x438;&#x44f; &#x432;&#x44b;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x430; &#x2014; &#x441;&#x43e;&#x43e;&#x431;&#x449;&#x435;&#x43d;&#x438;&#x44f; &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43d;&#x435; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x44f;&#x44e;&#x442;&#x441;&#x44f;,
 * &#x44d;&#x442;&#x43e; &#x43d;&#x435; &#x43b;&#x43e;&#x43c;&#x430;&#x435;&#x442; &#x440;&#x430;&#x431;&#x43e;&#x442;&#x443; CRM (&#x441;&#x43c;&#x435;&#x43d;&#x430; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430; &#x432;&#x441;&#x451; &#x440;&#x430;&#x432;&#x43d;&#x43e; &#x441;&#x43e;&#x445;&#x440;&#x430;&#x43d;&#x44f;&#x435;&#x442;&#x441;&#x44f;).
 */

function crm_max_config(): array
{
    $cfg = crm_config();
    $defaults = [
        'enabled'          => false,
        'idInstance'       => '',
        'apiTokenInstance' => '',
        'apiUrl'           => '',
    ];
    return array_merge($defaults, $cfg['green_api'] ?? []);
}

function crm_max_log(string $line): void
{
    $path = __DIR__ . '/../max-notify.log';
    @file_put_contents($path, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/**
 * &#x41f;&#x440;&#x438;&#x432;&#x43e;&#x434;&#x438;&#x442; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x43a; chatId Green API &#x432;&#x438;&#x434;&#x430; 79001234567@c.us.
 * &#x41f;&#x43e;&#x434;&#x434;&#x435;&#x440;&#x436;&#x438;&#x432;&#x430;&#x435;&#x442; &#x440;&#x43e;&#x441;&#x441;&#x438;&#x439;&#x441;&#x43a;&#x438;&#x435;/&#x431;&#x435;&#x43b;&#x43e;&#x440;&#x443;&#x441;&#x441;&#x43a;&#x438;&#x435; &#x43d;&#x43e;&#x43c;&#x435;&#x440;&#x430;. &#x412;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x442; null, &#x435;&#x441;&#x43b;&#x438;
 * &#x43d;&#x43e;&#x43c;&#x435;&#x440; &#x43d;&#x435;&#x43b;&#x44c;&#x437;&#x44f; &#x43e;&#x434;&#x43d;&#x43e;&#x437;&#x43d;&#x430;&#x447;&#x43d;&#x43e; &#x43f;&#x440;&#x438;&#x432;&#x435;&#x441;&#x442;&#x438; (&#x441;&#x43b;&#x438;&#x448;&#x43a;&#x43e;&#x43c; &#x43a;&#x43e;&#x440;&#x43e;&#x442;&#x43a;&#x438;&#x439;, &#x431;&#x443;&#x43a;&#x432;&#x44b; &#x438; &#x442;.&#x43f;.).
 */
function crm_phone_to_chat_id(?string $phone): ?string
{
    $digits = crm_phone_digits($phone);
    return $digits ? $digits . '@c.us' : null;
}

/**
 * &#x41e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x44f;&#x435;&#x442; &#x442;&#x435;&#x43a;&#x441;&#x442;&#x43e;&#x432;&#x43e;&#x435; &#x441;&#x43e;&#x43e;&#x431;&#x449;&#x435;&#x43d;&#x438;&#x435; &#x432; MAX &#x447;&#x435;&#x440;&#x435;&#x437; Green API.
 * &#x41d;&#x438;&#x43a;&#x43e;&#x433;&#x434;&#x430; &#x43d;&#x435; &#x431;&#x440;&#x43e;&#x441;&#x430;&#x435;&#x442; &#x438;&#x441;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x438;&#x435; &#x2014; &#x43f;&#x440;&#x438; &#x43b;&#x44e;&#x431;&#x43e;&#x439; &#x43e;&#x448;&#x438;&#x431;&#x43a;&#x435; &#x432;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x442; false
 * &#x438; &#x43f;&#x438;&#x448;&#x435;&#x442; &#x43f;&#x440;&#x438;&#x447;&#x438;&#x43d;&#x443; &#x432; max-notify.log.
 */
function crm_send_max_message(string $chatId, string $text): bool
{
    $cfg = crm_max_config();

    if (empty($cfg['enabled'])) {
        crm_max_log("\u{41f}\u{440}\u{43e}\u{43f}\u{443}\u{449}\u{435}\u{43d}\u{43e} (\u{438}\u{43d}\u{442}\u{435}\u{433}\u{440}\u{430}\u{446}\u{438}\u{44f} \u{432}\u{44b}\u{43a}\u{43b}\u{44e}\u{447}\u{435}\u{43d}\u{430} \u{432} config.php): $chatId");
        return false;
    }
    if ($cfg['idInstance'] === '' || $cfg['apiTokenInstance'] === '') {
        crm_max_log("\u{41f}\u{440}\u{43e}\u{43f}\u{443}\u{449}\u{435}\u{43d}\u{43e} (\u{43d}\u{435} \u{437}\u{430}\u{43f}\u{43e}\u{43b}\u{43d}\u{435}\u{43d}\u{44b} idInstance/apiTokenInstance): $chatId");
        return false;
    }
    if ($cfg['apiUrl'] === '' || stripos($cfg['apiUrl'], "\u{417}\u{410}\u{41c}\u{415}\u{41d}\u{418}\u{422}\u{415}") !== false) {
        crm_max_log("\u{41f}\u{440}\u{43e}\u{43f}\u{443}\u{449}\u{435}\u{43d}\u{43e} (\u{43d}\u{435} \u{437}\u{430}\u{43f}\u{43e}\u{43b}\u{43d}\u{435}\u{43d} apiUrl \u{438}\u{437} \u{43a}\u{43e}\u{43d}\u{441}\u{43e}\u{43b}\u{438} Green API): $chatId");
        return false;
    }

    $url = rtrim($cfg['apiUrl'], '/') . '/waInstance' . $cfg['idInstance'] . '/sendMessage/' . $cfg['apiTokenInstance'];
    $payload = json_encode(['chatId' => $chatId, 'message' => $text], JSON_UNESCAPED_UNICODE);

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            crm_max_log("\u{41e}\u{448}\u{438}\u{431}\u{43a}\u{430} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43a}\u{438} ($httpCode) $chatId: " . ($error ?: $response));
            return false;
        }
        crm_max_log("\u{41e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{43e} $chatId: " . mb_substr($text, 0, 60));
        return true;
    } catch (Throwable $e) {
        crm_max_log("\u{418}\u{441}\u{43a}\u{43b}\u{44e}\u{447}\u{435}\u{43d}\u{438}\u{435} \u{43f}\u{440}\u{438} \u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43a}\u{435} $chatId: " . $e->getMessage());
        return false;
    }
}

function crm_max_status_message(string $companyName, int $orderId, string $fromCity, string $toCity, string $status): ?string
{
    $route = $fromCity . " \u{2192} " . $toCity;
    switch ($status) {
        case 'new':
            return "\u{ab}{$companyName}\u{bb}: \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{43b}\u{438} \u{432}\u{430}\u{448}\u{443} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{443} \u{2116}{$orderId} ({$route}). \u{421}\u{43e}\u{43e}\u{431}\u{449}\u{438}\u{43c}, \u{43a}\u{43e}\u{433}\u{434}\u{430} \u{43e}\u{43d}\u{430} \u{431}\u{443}\u{434}\u{435}\u{442} \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{442}\u{430} \u{432} \u{43e}\u{431}\u{440}\u{430}\u{431}\u{43e}\u{442}\u{43a}\u{443}.";
        case 'accepted':
            return "\u{ab}{$companyName}\u{bb}: \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$orderId} ({$route}) \u{43f}\u{440}\u{438}\u{43d}\u{44f}\u{442}\u{430} \u{432} \u{43e}\u{431}\u{440}\u{430}\u{431}\u{43e}\u{442}\u{43a}\u{443}.";
        case 'collecting':
            return "\u{ab}{$companyName}\u{bb}: \u{433}\u{440}\u{443}\u{437} \u{43f}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{435} \u{2116}{$orderId} ({$route}) \u{441}\u{43e}\u{431}\u{438}\u{440}\u{430}\u{435}\u{442}\u{441}\u{44f} \u{432} \u{440}\u{435}\u{439}\u{441}.";
        case 'in_transit':
            return "\u{ab}{$companyName}\u{bb}: \u{433}\u{440}\u{443}\u{437} \u{43f}\u{43e} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{435} \u{2116}{$orderId} ({$route}) \u{432} \u{43f}\u{443}\u{442}\u{438}.";
        case 'delivered':
            return "\u{ab}{$companyName}\u{bb}: \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$orderId} ({$route}) \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430}. \u{421}\u{43f}\u{430}\u{441}\u{438}\u{431}\u{43e}, \u{447}\u{442}\u{43e} \u{432}\u{44b}\u{431}\u{438}\u{440}\u{430}\u{435}\u{442}\u{435} \u{43d}\u{430}\u{441}!";
        case 'cancelled':
            return "\u{ab}{$companyName}\u{bb}: \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$orderId} ({$route}) \u{43e}\u{442}\u{43c}\u{435}\u{43d}\u{435}\u{43d}\u{430}. \u{415}\u{441}\u{43b}\u{438} \u{44d}\u{442}\u{43e} \u{43e}\u{448}\u{438}\u{431}\u{43a}\u{430} \u{2014} \u{441}\u{432}\u{44f}\u{436}\u{438}\u{442}\u{435}\u{441}\u{44c} \u{441} \u{43d}\u{430}\u{43c}\u{438}.";
        default:
            return null;
    }
}

/**
 * &#x41d;&#x430;&#x445;&#x43e;&#x434;&#x438;&#x442; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x43f;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435; &#x438;, &#x435;&#x441;&#x43b;&#x438; &#x443; &#x43d;&#x435;&#x433;&#x43e; &#x435;&#x441;&#x442;&#x44c; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;, &#x448;&#x43b;&#x451;&#x442; &#x435;&#x43c;&#x443;
 * &#x443;&#x432;&#x435;&#x434;&#x43e;&#x43c;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x43e; &#x43d;&#x43e;&#x432;&#x43e;&#x43c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x435;. &#x411;&#x435;&#x437;&#x43e;&#x43f;&#x430;&#x441;&#x43d;&#x43e; &#x432;&#x44b;&#x437;&#x44b;&#x432;&#x430;&#x442;&#x44c; &#x432;&#x441;&#x435;&#x433;&#x434;&#x430; &#x2014; &#x43f;&#x440;&#x438;
 * &#x43e;&#x442;&#x441;&#x443;&#x442;&#x441;&#x442;&#x432;&#x438;&#x438; &#x442;&#x435;&#x43b;&#x435;&#x444;&#x43e;&#x43d;&#x430; &#x438;&#x43b;&#x438; &#x432;&#x44b;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x43d;&#x43e;&#x439; &#x438;&#x43d;&#x442;&#x435;&#x433;&#x440;&#x430;&#x446;&#x438;&#x438; &#x43f;&#x440;&#x43e;&#x441;&#x442;&#x43e; &#x43d;&#x438;&#x447;&#x435;&#x433;&#x43e; &#x43d;&#x435; &#x434;&#x435;&#x43b;&#x430;&#x435;&#x442;.
 */
function crm_notify_client_status(PDO $pdo, array $order, string $newStatus): void
{
    try {
        $stmt = $pdo->prepare('SELECT phone FROM clients WHERE id = ?');
        $stmt->execute([$order['client_id']]);
        $client = $stmt->fetch();
        if (!$client || empty($client['phone'])) {
            return;
        }
        $chatId = crm_phone_to_chat_id($client['phone']);
        if (!$chatId) {
            crm_max_log("\u{41f}\u{440}\u{43e}\u{43f}\u{443}\u{449}\u{435}\u{43d}\u{43e} (\u{43d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{440}\u{430}\u{441}\u{43f}\u{43e}\u{437}\u{43d}\u{430}\u{442}\u{44c} \u{43d}\u{43e}\u{43c}\u{435}\u{440} \u{442}\u{435}\u{43b}\u{435}\u{444}\u{43e}\u{43d}\u{430}): client_id=" . $order['client_id']);
            return;
        }
        $cfg = crm_config();
        $companyName = $cfg['company_name'] ?? "\u{41f}\u{43e}\u{441}\u{44b}\u{43b}\u{43e}\u{447}\u{43a}\u{430}";
        $text = crm_max_status_message($companyName, (int) $order['id'], $order['from_city'] ?? '', $order['to_city'] ?? '', $newStatus);
        if ($text === null) {
            return;
        }
        crm_send_max_message($chatId, $text);
    } catch (Throwable $e) {
        crm_max_log("\u{418}\u{441}\u{43a}\u{43b}\u{44e}\u{447}\u{435}\u{43d}\u{438}\u{435} \u{432} crm_notify_client_status: " . $e->getMessage());
    }
}
