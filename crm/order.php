<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/clientapi.php';
require_once __DIR__ . '/includes/yookassa.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/max.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$order = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        die("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{439}\u{434}\u{435}\u{43d}\u{430}.");
    }
    crm_ensure_order_packages($pdo, $id, max(1, (int) $order['places_count']));
}

$clients = $pdo->query('SELECT id, name, phone FROM clients ORDER BY name')->fetchAll();
$couriers = $pdo->query("SELECT id, name FROM users WHERE role = 'courier' AND active = 1 ORDER BY name")->fetchAll();
$formingRuns = $pdo->query("SELECT id, from_city, to_city, run_date FROM shipment_runs WHERE status = 'forming' ORDER BY run_date IS NULL, run_date, id DESC")->fetchAll();

// &#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x438;&#x435; &#x43d;&#x43e;&#x432;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $pickupType = ($_POST['pickup_type'] ?? '') === 'courier' ? 'courier' : 'self';
    $placesCount = max(1, (int) ($_POST['places_count'] ?? 1));
    $isCod = !empty($_POST['is_cod']) ? 1 : 0;
    $codAmount = $isCod && $_POST['cod_amount'] !== '' ? (float) $_POST['cod_amount'] : null;
    if (!$clientId) {
        crm_flash_set("\u{412}\u{44b}\u{431}\u{435}\u{440}\u{438}\u{442}\u{435} \u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442}\u{430}.", 'err');
    } else {
        $stmt = $pdo->prepare('INSERT INTO orders
            (client_id, created_by, from_city, to_city, from_address, to_address, cargo_description, weight_kg, places_count, declared_value, price, planned_date, comment, pickup_type, is_cod, cod_amount, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'new\')');
        $stmt->execute([
            $clientId,
            $user['id'],
            trim($_POST['from_city'] ?? '') ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}",
            trim($_POST['to_city'] ?? '') ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}",
            trim($_POST['from_address'] ?? '') ?: null,
            trim($_POST['to_address'] ?? '') ?: null,
            trim($_POST['cargo_description'] ?? '') ?: null,
            $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
            $placesCount,
            $_POST['declared_value'] !== '' ? (float) $_POST['declared_value'] : null,
            $_POST['price'] !== '' ? (float) $_POST['price'] : null,
            $_POST['planned_date'] !== '' ? $_POST['planned_date'] : null,
            trim($_POST['comment'] ?? '') ?: null,
            $pickupType,
            $isCod,
            $codAmount,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, 'new', ?, '\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430}')")
            ->execute([$newId, $user['id']]);
        crm_ensure_order_packages($pdo, $newId, $placesCount);
        crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $newId . " \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430}.");
        crm_redirect('/crm/order.php?id=' . $newId);
    }
}

// &#x41e;&#x431;&#x43d;&#x43e;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x435; &#x441;&#x443;&#x449;&#x435;&#x441;&#x442;&#x432;&#x443;&#x44e;&#x449;&#x435;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (&#x434;&#x430;&#x43d;&#x43d;&#x44b;&#x435;)
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $pickupType = ($_POST['pickup_type'] ?? '') === 'courier' ? 'courier' : 'self';
    $placesCount = max(1, (int) ($_POST['places_count'] ?? 1));
    $isCod = !empty($_POST['is_cod']) ? 1 : 0;
    $codAmount = $isCod && $_POST['cod_amount'] !== '' ? (float) $_POST['cod_amount'] : null;
    $stmt = $pdo->prepare('UPDATE orders SET from_city=?, to_city=?, from_address=?, to_address=?, cargo_description=?, weight_kg=?, places_count=?, declared_value=?, price=?, planned_date=?, comment=?, pickup_type=?, is_cod=?, cod_amount=? WHERE id=?');
    $stmt->execute([
        trim($_POST['from_city'] ?? '') ?: "\u{414}\u{435}\u{440}\u{431}\u{435}\u{43d}\u{442}",
        trim($_POST['to_city'] ?? '') ?: "\u{421}\u{430}\u{43d}\u{43a}\u{442}-\u{41f}\u{435}\u{442}\u{435}\u{440}\u{431}\u{443}\u{440}\u{433}",
        trim($_POST['from_address'] ?? '') ?: null,
        trim($_POST['to_address'] ?? '') ?: null,
        trim($_POST['cargo_description'] ?? '') ?: null,
        $_POST['weight_kg'] !== '' ? (float) $_POST['weight_kg'] : null,
        $placesCount,
        $_POST['declared_value'] !== '' ? (float) $_POST['declared_value'] : null,
        $_POST['price'] !== '' ? (float) $_POST['price'] : null,
        $_POST['planned_date'] !== '' ? $_POST['planned_date'] : null,
        trim($_POST['comment'] ?? '') ?: null,
        $pickupType,
        $isCod,
        $codAmount,
        $id,
    ]);
    crm_ensure_order_packages($pdo, $id, $placesCount);
    crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43e}\u{431}\u{43d}\u{43e}\u{432}\u{43b}\u{435}\u{43d}\u{430}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x435; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x430;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_courier') {
    crm_csrf_check();
    $courierId = $_POST['courier_id'] !== '' ? (int) $_POST['courier_id'] : null;
    $pdo->prepare('UPDATE orders SET courier_id = ? WHERE id = ?')->execute([$courierId, $id]);
    crm_flash_set("\u{41a}\u{443}\u{440}\u{44c}\u{435}\u{440} \u{43e}\u{431}\u{43d}\u{43e}\u{432}\u{43b}\u{451}\u{43d}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x435;/&#x441;&#x43d;&#x44f;&#x442;&#x438;&#x435; &#x441;&#x431;&#x43e;&#x440;&#x43d;&#x43e;&#x433;&#x43e; &#x440;&#x435;&#x439;&#x441;&#x430;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_shipment_run') {
    crm_csrf_check();
    $runId = $_POST['shipment_run_id'] !== '' ? (int) $_POST['shipment_run_id'] : null;
    $pdo->prepare('UPDATE orders SET shipment_run_id = ? WHERE id = ?')->execute([$runId, $id]);
    crm_flash_set($runId ? "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{434}\u{43e}\u{431}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430} \u{432} \u{441}\u{431}\u{43e}\u{440}\u{43d}\u{44b}\u{439} \u{440}\u{435}\u{439}\u{441}." : "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{443}\u{431}\u{440}\u{430}\u{43d}\u{430} \u{438}\u{437} \u{441}\u{431}\u{43e}\u{440}\u{43d}\u{43e}\u{433}\u{43e} \u{440}\u{435}\u{439}\u{441}\u{430}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x421;&#x43c;&#x435;&#x43d;&#x430; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    crm_csrf_check();
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['new','accepted','collecting','in_transit','delivered','cancelled'];
    if (in_array($newStatus, $allowed, true)) {
        if ($newStatus === 'delivered' && !empty($_POST['signature_data'])
            && str_starts_with($_POST['signature_data'], 'data:image/png;base64,')) {
            $pdo->prepare('UPDATE orders SET status = ?, recipient_signature = ? WHERE id = ?')
                ->execute([$newStatus, $_POST['signature_data'], $id]);
        } else {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        }
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?,?,?,?)')
            ->execute([$id, $newStatus, $user['id'], trim($_POST['status_comment'] ?? '') ?: null]);
        crm_notify_client_status($pdo, $order, $newStatus);
        if ($newStatus === 'delivered') {
            crm_notify_owner("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $id . ' (' . $order['from_city'] . " \u{2192} " . $order['to_city'] . ") \u{43e}\u{442}\u{43c}\u{435}\u{447}\u{435}\u{43d}\u{430} \u{43a}\u{430}\u{43a} \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{43d}\u{430}\u{44f}.");
        }
        crm_flash_set("\u{421}\u{442}\u{430}\u{442}\u{443}\u{441} \u{438}\u{437}\u{43c}\u{435}\u{43d}\u{451}\u{43d} \u{43d}\u{430} \u{ab}" . crm_order_status_label($newStatus) . "\u{bb}.");
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41e;&#x442;&#x43c;&#x435;&#x442;&#x43a;&#x430; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b; &#x43f;&#x440;&#x44f;&#x43c;&#x43e; &#x438;&#x437; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    crm_csrf_check();
    $allowedMethods = ['online', 'cash', 'terminal', 'invoice'];
    $method = $_POST['payment_method'] ?? '';
    if (!in_array($method, $allowedMethods, true)) {
        crm_flash_set("\u{412}\u{44b}\u{431}\u{435}\u{440}\u{438}\u{442}\u{435} \u{441}\u{43f}\u{43e}\u{441}\u{43e}\u{431} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b}.", 'err');
    } else {
        $pdo->prepare('UPDATE orders SET payment_status = ?, payment_method = ? WHERE id = ?')->execute(['paid', $method, $id]);
        crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43e}\u{442}\u{43c}\u{435}\u{447}\u{435}\u{43d}\u{430} \u{43e}\u{43f}\u{43b}\u{430}\u{447}\u{435}\u{43d}\u{43d}\u{43e}\u{439} (" . crm_payment_method_label($method) . ').');
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_paid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ?, payment_method = NULL WHERE id = ?')->execute(['unpaid', $id]);
    crm_flash_set("\u{41e}\u{442}\u{43c}\u{435}\u{442}\u{43a}\u{430} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b} \u{441}\u{43d}\u{44f}\u{442}\u{430}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41f;&#x43e;&#x441;&#x442;&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x2014; &#x434;&#x43e;&#x433;&#x43e;&#x432;&#x43e;&#x440;&#x438;&#x43b;&#x438;&#x441;&#x44c;, &#x447;&#x442;&#x43e; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x437;&#x430;&#x43f;&#x43b;&#x430;&#x442;&#x438;&#x442; &#x43f;&#x43e;&#x441;&#x43b;&#x435; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_postpaid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute(['postpaid', $id]);
    crm_flash_set("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{43e}\u{442}\u{43c}\u{435}\u{447}\u{435}\u{43d}\u{430} \u{43a}\u{430}\u{43a} \u{43f}\u{43e}\u{441}\u{442}\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{430} (\u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442} \u{437}\u{430}\u{43f}\u{43b}\u{430}\u{442}\u{438}\u{442} \u{43f}\u{43e}\u{441}\u{43b}\u{435} \u{434}\u{43e}\u{441}\u{442}\u{430}\u{432}\u{43a}\u{438}).");
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_postpaid') {
    crm_csrf_check();
    $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute(['unpaid', $id]);
    crm_flash_set("\u{41e}\u{442}\u{43c}\u{435}\u{442}\u{43a}\u{430} \u{43f}\u{43e}\u{441}\u{442}\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b} \u{441}\u{43d}\u{44f}\u{442}\u{430}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41d;&#x430;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436;: &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;/&#x43c;&#x435;&#x43d;&#x435;&#x434;&#x436;&#x435;&#x440; &#x43e;&#x442;&#x43c;&#x435;&#x447;&#x430;&#x435;&#x442;, &#x447;&#x442;&#x43e; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x438;&#x43b; &#x43d;&#x430;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x43c;&#x438; &#x43f;&#x440;&#x438; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x438;.
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cod_collect_cash') {
    crm_csrf_check();
    $pdo->prepare("UPDATE orders SET payment_status = 'paid', payment_method = 'cash' WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, ?, ?, '\u{41d}\u{430}\u{43b}\u{43e}\u{436}\u{435}\u{43d}\u{43d}\u{44b}\u{439} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{43f}\u{43e}\u{43b}\u{443}\u{447}\u{435}\u{43d} \u{43d}\u{430}\u{43b}\u{438}\u{447}\u{43d}\u{44b}\u{43c}\u{438} \u{43f}\u{440}\u{438} \u{432}\u{440}\u{443}\u{447}\u{435}\u{43d}\u{438}\u{438}')")
        ->execute([$id, $order['status'], $user['id']]);
    crm_flash_set("\u{41d}\u{430}\u{43b}\u{43e}\u{436}\u{435}\u{43d}\u{43d}\u{44b}\u{439} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{43e}\u{442}\u{43c}\u{435}\u{447}\u{435}\u{43d} \u{43f}\u{43e}\u{43b}\u{443}\u{447}\u{435}\u{43d}\u{43d}\u{44b}\u{43c} \u{43d}\u{430}\u{43b}\u{438}\u{447}\u{43d}\u{44b}\u{43c}\u{438}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x41d;&#x430;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436;: &#x441;&#x433;&#x435;&#x43d;&#x435;&#x440;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x442;&#x44c; &#x441;&#x441;&#x44b;&#x43b;&#x43a;&#x443; &#x43d;&#x430; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x443; (&#x42e;Kassa) &#x438; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x44c; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x443;.
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cod_send_payment_link') {
    crm_csrf_check();
    $amount = (float) ($order['cod_amount'] ?? $order['price'] ?? 0);
    if ($amount <= 0) {
        crm_flash_set("\u{423} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{43d}\u{435} \u{443}\u{43a}\u{430}\u{437}\u{430}\u{43d}\u{430} \u{441}\u{443}\u{43c}\u{43c}\u{430} \u{43d}\u{430}\u{43b}\u{43e}\u{436}\u{435}\u{43d}\u{43d}\u{43e}\u{433}\u{43e} \u{43f}\u{43b}\u{430}\u{442}\u{435}\u{436}\u{430}.", 'err');
    } elseif (!crm_yookassa_ready()) {
        crm_flash_set("\u{41e}\u{43d}\u{43b}\u{430}\u{439}\u{43d}-\u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{441}\u{442}\u{440}\u{43e}\u{435}\u{43d}\u{430} \u{432} config.php (\u{44e}Kassa).", 'err');
    } else {
        $clStmt = $pdo->prepare('SELECT name, phone FROM clients WHERE id = ?');
        $clStmt->execute([$order['client_id']]);
        $client = $clStmt->fetch();
        $cfg = crm_config();
        $baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');
        $returnUrl = $baseUrl . '/app.html?paid_track=' . rawurlencode($order['track_code'] ?? '');
        $phoneDigits = $client ? crm_phone_digits($client['phone']) : null;
        [$payment, $err] = crm_yookassa_create_payment(
            $amount,
            "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}{$id} (\u{43d}\u{430}\u{43b}\u{43e}\u{436}\u{435}\u{43d}\u{43d}\u{44b}\u{439} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436})",
            $returnUrl,
            ['order_id' => $id, 'cod' => 1],
            $phoneDigits ? '+' . $phoneDigits : null
        );
        if ($err !== null || empty($payment['id']) || empty($payment['confirmation']['confirmation_url'])) {
            crm_flash_set($err ?: "\u{41d}\u{435} \u{443}\u{434}\u{430}\u{43b}\u{43e}\u{441}\u{44c} \u{441}\u{43e}\u{437}\u{434}\u{430}\u{442}\u{44c} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436}.", 'err');
        } else {
            $pdo->prepare('INSERT INTO yookassa_payments (order_id, yk_payment_id, amount, confirmation_url, status) VALUES (?,?,?,?,?)')
                ->execute([$id, $payment['id'], $amount, $payment['confirmation']['confirmation_url'], $payment['status'] ?? 'pending']);
            $link = $payment['confirmation']['confirmation_url'];
            $sent = false;
            if ($phoneDigits) {
                $text = "\u{421}\u{441}\u{44b}\u{43b}\u{43a}\u{430} \u{43d}\u{430} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{443} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{438} \u{2116}{$id}: {$link}";
                $sent = crm_send_max_message(crm_phone_to_chat_id($client['phone']), $text) || crm_send_sms('+' . $phoneDigits, $text);
            }
            crm_flash_set("\u{421}\u{441}\u{44b}\u{43b}\u{43a}\u{430} \u{43d}\u{430} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{443} \u{441}\u{43e}\u{437}\u{434}\u{430}\u{43d}\u{430}: {$link}" . ($sent ? " (\u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43b}\u{435}\u{43d}\u{430} \u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442}\u{443})" : " (\u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{438}\u{442}\u{44c} \u{43a}\u{43b}\u{438}\u{435}\u{43d}\u{442}\u{443} \u{432}\u{440}\u{443}\u{447}\u{43d}\u{443}\u{44e} \u{2014} \u{430}\u{432}\u{442}\u{43e}\u{43e}\u{442}\u{43f}\u{440}\u{430}\u{432}\u{43a}\u{430} \u{43d}\u{435} \u{43d}\u{430}\u{441}\u{442}\u{440}\u{43e}\u{435}\u{43d}\u{430})"));
        }
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

// &#x420;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x430; &#x2014; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x435;&#x439;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_installment') {
    crm_csrf_check();
    $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');
    $dueDate = $_POST['due_date'] !== '' ? $_POST['due_date'] : null;
    if ($amount <= 0) {
        crm_flash_set("\u{423}\u{43a}\u{430}\u{436}\u{438}\u{442}\u{435} \u{441}\u{443}\u{43c}\u{43c}\u{443} \u{43f}\u{43b}\u{430}\u{442}\u{435}\u{436}\u{430} \u{431}\u{43e}\u{43b}\u{44c}\u{448}\u{435} \u{43d}\u{443}\u{43b}\u{44f}.", 'err');
    } else {
        $pdo->prepare('INSERT INTO order_installments (order_id, due_date, amount) VALUES (?,?,?)')
            ->execute([$id, $dueDate, $amount]);
        // &#x41e;&#x442;&#x43c;&#x435;&#x447;&#x430;&#x435;&#x43c;, &#x447;&#x442;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x438;&#x432;&#x430;&#x435;&#x442;&#x441;&#x44f; &#x432; &#x440;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x443; (&#x435;&#x441;&#x43b;&#x438; &#x435;&#x449;&#x451; &#x43d;&#x435; &#x43e;&#x442;&#x43c;&#x435;&#x447;&#x435;&#x43d;&#x430; &#x438;&#x43d;&#x430;&#x447;&#x435;).
        if ($order['payment_method'] !== 'installment' || $order['payment_status'] === 'unpaid') {
            $pdo->prepare('UPDATE orders SET payment_method = ?, payment_status = ? WHERE id = ?')
                ->execute(['installment', 'unpaid', $id]);
        }
        crm_flash_set("\u{41f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{434}\u{43e}\u{431}\u{430}\u{432}\u{43b}\u{435}\u{43d} \u{432} \u{433}\u{440}\u{430}\u{444}\u{438}\u{43a} \u{440}\u{430}\u{441}\u{441}\u{440}\u{43e}\u{447}\u{43a}\u{438}.");
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_installment_paid') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $chk = $pdo->prepare('SELECT id FROM order_installments WHERE id = ? AND order_id = ?');
    $chk->execute([$instId, $id]);
    if ($chk->fetch()) {
        $pdo->prepare('UPDATE order_installments SET status = "paid", paid_at = NOW() WHERE id = ?')->execute([$instId]);
        // &#x415;&#x441;&#x43b;&#x438; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a; &#x43f;&#x43e;&#x43b;&#x43d;&#x43e;&#x441;&#x442;&#x44c;&#x44e; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d; &#x2014; &#x43f;&#x435;&#x440;&#x435;&#x432;&#x43e;&#x434;&#x438;&#x43c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443; &#x432; "&#x41e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;".
        $totals = crm_order_installments_totals($pdo, $id);
        if ($totals['cnt'] > 0 && $totals['paid_cnt'] === $totals['cnt']) {
            $pdo->prepare('UPDATE orders SET payment_status = "paid", payment_method = "installment" WHERE id = ?')->execute([$id]);
        }
        crm_flash_set("\u{41f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{43e}\u{442}\u{43c}\u{435}\u{447}\u{435}\u{43d} \u{43e}\u{43f}\u{43b}\u{430}\u{447}\u{435}\u{43d}\u{43d}\u{44b}\u{43c}.");
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unmark_installment_paid') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $chk = $pdo->prepare('SELECT id FROM order_installments WHERE id = ? AND order_id = ?');
    $chk->execute([$instId, $id]);
    if ($chk->fetch()) {
        $pdo->prepare('UPDATE order_installments SET status = "pending", paid_at = NULL WHERE id = ?')->execute([$instId]);
        // &#x415;&#x441;&#x43b;&#x438; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x431;&#x44b;&#x43b;&#x430; &#x43e;&#x442;&#x43c;&#x435;&#x447;&#x435;&#x43d;&#x430; "&#x41e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;" &#x43f;&#x43e; &#x440;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x435; &#x2014; &#x432;&#x43e;&#x437;&#x432;&#x440;&#x430;&#x449;&#x430;&#x435;&#x43c; &#x432; "&#x41d;&#x435; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;".
        if ($order['payment_status'] === 'paid' && $order['payment_method'] === 'installment') {
            $pdo->prepare('UPDATE orders SET payment_status = "unpaid" WHERE id = ?')->execute([$id]);
        }
        crm_flash_set("\u{41e}\u{442}\u{43c}\u{435}\u{442}\u{43a}\u{430} \u{43e}\u{43f}\u{43b}\u{430}\u{442}\u{44b} \u{43f}\u{43e} \u{43f}\u{43b}\u{430}\u{442}\u{435}\u{436}\u{443} \u{441}\u{43d}\u{44f}\u{442}\u{430}.");
    }
    crm_redirect('/crm/order.php?id=' . $id);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_installment') {
    crm_csrf_check();
    $instId = (int) ($_POST['installment_id'] ?? 0);
    $pdo->prepare("DELETE FROM order_installments WHERE id = ? AND order_id = ? AND status = 'pending'")->execute([$instId, $id]);
    crm_flash_set("\u{41f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{443}\u{434}\u{430}\u{43b}\u{451}\u{43d} \u{438}\u{437} \u{433}\u{440}\u{430}\u{444}\u{438}\u{43a}\u{430}.");
    crm_redirect('/crm/order.php?id=' . $id);
}

$history = [];
$installments = [];
$installmentTotals = ['total' => 0, 'paid' => 0, 'remaining' => 0, 'cnt' => 0, 'paid_cnt' => 0];
$orderClaims = [];
if ($order) {
    $h = $pdo->prepare('SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE order_id = ? ORDER BY changed_at DESC');
    $h->execute([$id]);
    $history = $h->fetchAll();

    $instStmt = $pdo->prepare('SELECT * FROM order_installments WHERE order_id = ? ORDER BY due_date IS NULL, due_date, id');
    $instStmt->execute([$id]);
    $installments = $instStmt->fetchAll();
    $installmentTotals = crm_order_installments_totals($pdo, $id);

    $claimsStmt = $pdo->prepare('SELECT * FROM claims WHERE order_id = ? ORDER BY created_at DESC');
    $claimsStmt->execute([$id]);
    $orderClaims = $claimsStmt->fetchAll();
}

$orderPackages = [];
if ($order) {
    $pkgStmt = $pdo->prepare('SELECT * FROM order_packages WHERE order_id = ? ORDER BY seq');
    $pkgStmt->execute([$id]);
    $orderPackages = $pkgStmt->fetchAll();
}

$currentRun = null;
if ($order && $order['shipment_run_id']) {
    $rStmt = $pdo->prepare('SELECT * FROM shipment_runs WHERE id = ?');
    $rStmt->execute([$order['shipment_run_id']]);
    $currentRun = $rStmt->fetch();
}

$preselectClientId = (int) ($_GET['client_id'] ?? 0);

$pageTitle = $order ? ("\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $order['id']) : "\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430}";
$activeNav = 'orders';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/orders.php">&#x2190; &#x412;&#x441;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438;</a></p>

<?php if (!$order): ?>

<div class="card">
  <h3 style="margin-top:0;">&#x41d;&#x43e;&#x432;&#x430;&#x44f; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x43d;&#x430; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x443;</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;</label>
    <select name="client_id" required>
      <option value="">&#x2014; &#x432;&#x44b;&#x431;&#x435;&#x440;&#x438;&#x442;&#x435; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x2014;</option>
      <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $preselectClientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> <?= $c['phone'] ? '(' . e($c['phone']) . ')' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <p class="text-muted" style="margin-top:-10px;">&#x41d;&#x435;&#x442; &#x43d;&#x443;&#x436;&#x43d;&#x43e;&#x433;&#x43e; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430;? <a href="/crm/clients.php">&#x414;&#x43e;&#x431;&#x430;&#x432;&#x44c;&#x442;&#x435; &#x435;&#x433;&#x43e; &#x437;&#x434;&#x435;&#x441;&#x44c;</a>, &#x437;&#x430;&#x442;&#x435;&#x43c; &#x432;&#x435;&#x440;&#x43d;&#x438;&#x442;&#x435;&#x441;&#x44c;.</p>

    <div class="form-row">
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="from_city" value="&#x414;&#x435;&#x440;&#x431;&#x435;&#x43d;&#x442;"></div>
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="to_city" value="&#x421;&#x430;&#x43d;&#x43a;&#x442;-&#x41f;&#x435;&#x442;&#x435;&#x440;&#x431;&#x443;&#x440;&#x433;"></div>
    </div>
    <div class="form-row">
      <div><label>&#x410;&#x434;&#x440;&#x435;&#x441; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="from_address"></div>
      <div><label>&#x410;&#x434;&#x440;&#x435;&#x441; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="to_address"></div>
    </div>
    <label>&#x421;&#x43f;&#x43e;&#x441;&#x43e;&#x431; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43e;&#x442; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;</label>
    <select name="pickup_type">
      <option value="self">&#x421;&#x430;&#x43c;&#x43e;&#x441;&#x442;&#x43e;&#x44f;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e; (&#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x43f;&#x440;&#x438;&#x432;&#x43e;&#x437;&#x438;&#x442; &#x441;&#x430;&#x43c;)</option>
      <option value="courier">&#x412;&#x44b;&#x435;&#x437;&#x434;&#x43d;&#x43e;&#x439; &#x441;&#x431;&#x43e;&#x440; (&#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440; &#x437;&#x430;&#x431;&#x438;&#x440;&#x430;&#x435;&#x442; &#x43f;&#x43e; &#x430;&#x434;&#x440;&#x435;&#x441;&#x443; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;)</option>
    </select>
    <label>&#x41e;&#x43f;&#x438;&#x441;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x430;</label>
    <input type="text" name="cargo_description" placeholder="&#x43d;&#x430;&#x43f;&#x440;&#x438;&#x43c;&#x435;&#x440;: &#x43a;&#x43e;&#x440;&#x43e;&#x431;&#x43a;&#x430;, 2 &#x43c;&#x435;&#x441;&#x442;&#x430;">
    <div class="form-row">
      <div><label>&#x412;&#x435;&#x441;, &#x43a;&#x433;</label><input type="number" step="0.1" name="weight_kg"></div>
      <div><label>&#x41e;&#x431;&#x44a;&#x44f;&#x432;&#x43b;&#x435;&#x43d;&#x43d;&#x430;&#x44f; &#x446;&#x435;&#x43d;&#x43d;&#x43e;&#x441;&#x442;&#x44c;, &#x20bd;</label><input type="number" step="0.01" name="declared_value"></div>
    </div>
    <div class="form-row">
      <div><label>&#x421;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x44c; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;, &#x20bd;</label><input type="number" step="0.01" name="price"></div>
      <div><label>&#x41f;&#x43b;&#x430;&#x43d;&#x438;&#x440;&#x443;&#x435;&#x43c;&#x430;&#x44f; &#x434;&#x430;&#x442;&#x430; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</label><input type="date" name="planned_date"></div>
    </div>
    <div class="form-row">
      <div><label>&#x41a;&#x43e;&#x43b;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e; &#x43c;&#x435;&#x441;&#x442; (&#x433;&#x440;&#x443;&#x437;&#x43e;&#x432;)</label><input type="number" step="1" min="1" name="places_count" value="1"></div>
      <div></div>
    </div>
    <div class="form-row" style="align-items:center;">
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="create_is_cod" name="is_cod" value="1" onchange="document.getElementById('create_cod_amount_wrap').style.display = this.checked ? 'block' : 'none';">
        <label for="create_is_cod" style="margin:0;">&#x41d;&#x430;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436;</label>
      </div>
      <div id="create_cod_amount_wrap" style="display:none;"><label>&#x421;&#x443;&#x43c;&#x43c;&#x430; &#x43a; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44e;, &#x20bd;</label><input type="number" step="0.01" name="cod_amount"></div>
    </div>
    <label>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439;</label>
    <textarea name="comment"></textarea>
    <div class="form-actions"><button class="btn" type="submit">&#x421;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443;</button></div>
  </form>
</div>

<?php else: ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <span class="badge <?= crm_order_status_class($order['status']) ?>" style="font-size:.85rem;"><?= e(crm_order_status_label($order['status'])) ?></span>
      <span class="badge <?= crm_payment_status_class($order['payment_status']) ?>" style="font-size:.85rem;">
        <?= e(crm_payment_status_label($order['payment_status'])) ?><?php if ($order['payment_status'] === 'paid'): ?> &#x2014; <?= e(crm_payment_method_label($order['payment_method'])) ?><?php endif; ?>
      </span>
      <?php if ($order['payment_method'] === 'installment' && $installmentTotals['cnt'] > 0): ?>
        <span class="badge badge-blue" style="font-size:.85rem;">&#x420;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x430;: &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43e; <?= crm_money($installmentTotals['paid']) ?> &#x438;&#x437; <?= crm_money($installmentTotals['total']) ?></span>
      <?php endif; ?>
      <?php if (($order['pickup_type'] ?? 'self') === 'courier'): ?>
        <span class="badge <?= crm_pickup_type_class($order['pickup_type']) ?>" style="font-size:.85rem;"><?= e(crm_pickup_type_label($order['pickup_type'])) ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?php if ($order['payment_status'] === 'paid'): ?>
        <form method="post" class="inline">
          <?= crm_csrf_field() ?>
          <input type="hidden" name="action" value="unmark_paid">
          <button class="btn small secondary" type="submit">&#x421;&#x43d;&#x44f;&#x442;&#x44c; &#x43e;&#x442;&#x43c;&#x435;&#x442;&#x43a;&#x443; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;</button>
        </form>
      <?php else: ?>
        <form method="post" class="inline" style="display:flex;gap:6px;align-items:center;">
          <?= crm_csrf_field() ?>
          <input type="hidden" name="action" value="mark_paid">
          <select name="payment_method" required>
            <option value="">&#x2014; &#x441;&#x43f;&#x43e;&#x441;&#x43e;&#x431; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b; &#x2014;</option>
            <option value="cash">&#x41d;&#x430;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x440;&#x430;&#x441;&#x447;&#x451;&#x442;</option>
            <option value="terminal">&#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x447;&#x435;&#x440;&#x435;&#x437; &#x442;&#x435;&#x440;&#x43c;&#x438;&#x43d;&#x430;&#x43b;</option>
            <option value="invoice">&#x41e;&#x43f;&#x43b;&#x430;&#x442;&#x430; &#x43f;&#x43e; &#x441;&#x447;&#x451;&#x442;&#x443;</option>
            <option value="online">&#x41e;&#x43d;&#x43b;&#x430;&#x439;&#x43d; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435;</option>
          </select>
          <button class="btn small secondary" type="submit">&#x41e;&#x442;&#x43c;&#x435;&#x442;&#x438;&#x442;&#x44c; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43d;&#x44b;&#x43c;</button>
        </form>
        <?php if ($order['payment_status'] === 'postpaid'): ?>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="unmark_postpaid">
            <button class="btn small secondary" type="submit">&#x421;&#x43d;&#x44f;&#x442;&#x44c; &#x43e;&#x442;&#x43c;&#x435;&#x442;&#x43a;&#x443; &#x43f;&#x43e;&#x441;&#x442;&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;</button>
          </form>
        <?php else: ?>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="mark_postpaid">
            <button class="btn small secondary" type="submit">&#x41f;&#x43e;&#x441;&#x442;&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x430; (&#x43f;&#x43e;&#x441;&#x43b;&#x435; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;)</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <a class="btn small" href="/crm/invoice.php?order_id=<?= (int)$order['id'] ?>">&#x412;&#x44b;&#x441;&#x442;&#x430;&#x432;&#x438;&#x442;&#x44c; &#x441;&#x447;&#x451;&#x442;</a>
      <a class="btn small secondary" href="/crm/waybill.php?id=<?= (int)$order['id'] ?>" target="_blank">&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x43d;&#x430;&#x43a;&#x43b;&#x430;&#x434;&#x43d;&#x43e;&#x439;</a>
      <a class="btn small secondary" href="/crm/claim.php?order_id=<?= (int)$order['id'] ?>">+ &#x41f;&#x440;&#x435;&#x442;&#x435;&#x43d;&#x437;&#x438;&#x44f;</a>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x420;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x430; &#x2014; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x435;&#x439;</h3>
  <?php if (!$installments): ?>
    <div class="empty-state">&#x420;&#x430;&#x441;&#x441;&#x440;&#x43e;&#x447;&#x43a;&#x430; &#x43d;&#x435; &#x43e;&#x444;&#x43e;&#x440;&#x43c;&#x43b;&#x435;&#x43d;&#x430;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>&#x414;&#x430;&#x442;&#x430; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x430;</th><th>&#x421;&#x443;&#x43c;&#x43c;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($installments as $inst): ?>
        <tr>
          <td><?= $inst['due_date'] ? crm_date($inst['due_date'], 'd.m.Y') : "\u{2014}" ?></td>
          <td><?= crm_money((float) $inst['amount']) ?></td>
          <td>
            <?php if ($inst['status'] === 'paid'): ?>
              <span class="badge badge-green">&#x41e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d; <?= $inst['paid_at'] ? crm_date($inst['paid_at'], 'd.m.Y') : '' ?></span>
            <?php else: ?>
              <span class="badge badge-grey">&#x41e;&#x436;&#x438;&#x434;&#x430;&#x435;&#x442;</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php if ($inst['status'] === 'paid'): ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="unmark_installment_paid">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small secondary" type="submit">&#x421;&#x43d;&#x44f;&#x442;&#x44c; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x443;</button>
              </form>
            <?php else: ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="mark_installment_paid">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small secondary" type="submit">&#x41e;&#x442;&#x43c;&#x435;&#x442;&#x438;&#x442;&#x44c; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43d;&#x44b;&#x43c;</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm("\u{423}\u{434}\u{430}\u{43b}\u{438}\u{442}\u{44c} \u{43f}\u{43b}\u{430}\u{442}\u{451}\u{436} \u{438}\u{437} \u{433}\u{440}\u{430}\u{444}\u{438}\u{43a}\u{430}?");">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="delete_installment">
                <input type="hidden" name="installment_id" value="<?= (int) $inst['id'] ?>">
                <button class="btn small danger" type="submit">&#x423;&#x434;&#x430;&#x43b;&#x438;&#x442;&#x44c;</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted" style="margin-top:10px;">
      &#x41e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x43e; <?= crm_money($installmentTotals['paid']) ?> &#x438;&#x437; <?= crm_money($installmentTotals['total']) ?>
      (&#x43e;&#x441;&#x442;&#x430;&#x43b;&#x43e;&#x441;&#x44c; <?= crm_money($installmentTotals['remaining']) ?>). &#x41a;&#x43e;&#x433;&#x434;&#x430; &#x43e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#x44b; &#x432;&#x441;&#x435; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x438; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a;&#x430; &#x2014;
      &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x430;&#x432;&#x442;&#x43e;&#x43c;&#x430;&#x442;&#x438;&#x447;&#x435;&#x441;&#x43a;&#x438; &#x43e;&#x442;&#x43c;&#x435;&#x447;&#x430;&#x435;&#x442;&#x441;&#x44f; &#xab;&#x41e;&#x43f;&#x43b;&#x430;&#x447;&#x435;&#x43d;&#xbb;.
    </p>
  <?php endif; ?>

  <form method="post" class="form-row" style="align-items:end;margin-top:12px;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="add_installment">
    <div><label>&#x414;&#x430;&#x442;&#x430; &#x43f;&#x43b;&#x430;&#x442;&#x435;&#x436;&#x430;</label><input type="date" name="due_date"></div>
    <div><label>&#x421;&#x443;&#x43c;&#x43c;&#x430;, &#x20bd;</label><input type="number" step="0.01" name="amount" required></div>
    <div class="form-actions"><button class="btn secondary" type="submit">&#x414;&#x43e;&#x431;&#x430;&#x432;&#x438;&#x442;&#x44c; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436; &#x432; &#x433;&#x440;&#x430;&#x444;&#x438;&#x43a;</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;: <a href="/crm/client.php?id=<?= (int)$order['client_id'] ?>"><?php
    $cl = $pdo->prepare('SELECT name FROM clients WHERE id = ?'); $cl->execute([$order['client_id']]);
    echo e($cl->fetchColumn());
  ?></a></h3>

  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-row">
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="from_city" value="<?= e($order['from_city']) ?>"></div>
      <div><label>&#x413;&#x43e;&#x440;&#x43e;&#x434; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="to_city" value="<?= e($order['to_city']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>&#x410;&#x434;&#x440;&#x435;&#x441; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="from_address" value="<?= e($order['from_address']) ?>"></div>
      <div><label>&#x410;&#x434;&#x440;&#x435;&#x441; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f;</label><input type="text" name="to_address" value="<?= e($order['to_address']) ?>"></div>
    </div>
    <label>&#x421;&#x43f;&#x43e;&#x441;&#x43e;&#x431; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44f; &#x433;&#x440;&#x443;&#x437;&#x430; &#x43e;&#x442; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x435;&#x43b;&#x44f;</label>
    <select name="pickup_type">
      <option value="self" <?= ($order['pickup_type'] ?? 'self') === 'self' ? 'selected' : '' ?>>&#x421;&#x430;&#x43c;&#x43e;&#x441;&#x442;&#x43e;&#x44f;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e; (&#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442; &#x43f;&#x440;&#x438;&#x432;&#x43e;&#x437;&#x438;&#x442; &#x441;&#x430;&#x43c;)</option>
      <option value="courier" <?= ($order['pickup_type'] ?? 'self') === 'courier' ? 'selected' : '' ?>>&#x412;&#x44b;&#x435;&#x437;&#x434;&#x43d;&#x43e;&#x439; &#x441;&#x431;&#x43e;&#x440; (&#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440; &#x437;&#x430;&#x431;&#x438;&#x440;&#x430;&#x435;&#x442; &#x43f;&#x43e; &#x430;&#x434;&#x440;&#x435;&#x441;&#x443; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x43b;&#x435;&#x43d;&#x438;&#x44f;)</option>
    </select>
    <label>&#x41e;&#x43f;&#x438;&#x441;&#x430;&#x43d;&#x438;&#x435; &#x433;&#x440;&#x443;&#x437;&#x430;</label>
    <input type="text" name="cargo_description" value="<?= e($order['cargo_description']) ?>">
    <div class="form-row">
      <div><label>&#x412;&#x435;&#x441;, &#x43a;&#x433;</label><input type="number" step="0.1" name="weight_kg" value="<?= e($order['weight_kg']) ?>"></div>
      <div><label>&#x41e;&#x431;&#x44a;&#x44f;&#x432;&#x43b;&#x435;&#x43d;&#x43d;&#x430;&#x44f; &#x446;&#x435;&#x43d;&#x43d;&#x43e;&#x441;&#x442;&#x44c;, &#x20bd;</label><input type="number" step="0.01" name="declared_value" value="<?= e($order['declared_value']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>&#x421;&#x442;&#x43e;&#x438;&#x43c;&#x43e;&#x441;&#x442;&#x44c; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;, &#x20bd;</label><input type="number" step="0.01" name="price" value="<?= e($order['price']) ?>"></div>
      <div><label>&#x41f;&#x43b;&#x430;&#x43d;&#x438;&#x440;&#x443;&#x435;&#x43c;&#x430;&#x44f; &#x434;&#x430;&#x442;&#x430; &#x434;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x43a;&#x438;</label><input type="date" name="planned_date" value="<?= e($order['planned_date']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>&#x41a;&#x43e;&#x43b;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e; &#x43c;&#x435;&#x441;&#x442; (&#x433;&#x440;&#x443;&#x437;&#x43e;&#x432;)</label><input type="number" step="1" min="1" name="places_count" value="<?= (int) max(1, (int) $order['places_count']) ?>"></div>
      <div></div>
    </div>
    <div class="form-row" style="align-items:center;">
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="update_is_cod" name="is_cod" value="1" <?= !empty($order['is_cod']) ? 'checked' : '' ?> onchange="document.getElementById('update_cod_amount_wrap').style.display = this.checked ? 'block' : 'none';">
        <label for="update_is_cod" style="margin:0;">&#x41d;&#x430;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436;</label>
      </div>
      <div id="update_cod_amount_wrap" style="<?= !empty($order['is_cod']) ? '' : 'display:none;' ?>"><label>&#x421;&#x443;&#x43c;&#x43c;&#x430; &#x43a; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44e;, &#x20bd;</label><input type="number" step="0.01" name="cod_amount" value="<?= e($order['cod_amount']) ?>"></div>
    </div>
    <label>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439;</label>
    <textarea name="comment"><?= e($order['comment']) ?></textarea>
    <div class="form-actions"><button class="btn" type="submit">&#x421;&#x43e;&#x445;&#x440;&#x430;&#x43d;&#x438;&#x442;&#x44c; &#x438;&#x437;&#x43c;&#x435;&#x43d;&#x435;&#x43d;&#x438;&#x44f;</button></div>
  </form>
</div>

<?php
    $codLinkStmt = $pdo->prepare('SELECT confirmation_url, status FROM yookassa_payments WHERE order_id = ? AND confirmation_url IS NOT NULL ORDER BY id DESC LIMIT 1');
    $codLinkStmt->execute([$id]);
    $codLink = $codLinkStmt->fetch();
?>
<?php if (!empty($order['is_cod'])): ?>
<div class="card">
  <h3 style="margin-top:0;">&#x41d;&#x430;&#x43b;&#x43e;&#x436;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43f;&#x43b;&#x430;&#x442;&#x451;&#x436;</h3>
  <p>&#x421;&#x443;&#x43c;&#x43c;&#x430; &#x43a; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x44e;: <strong><?= crm_money((float) ($order['cod_amount'] ?? 0)) ?></strong>
    &#x2014;
    <?php if ($order['payment_status'] === 'paid'): ?>
      <span class="badge badge-green">&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x43e; (<?= e(crm_payment_method_label($order['payment_method'])) ?>)</span>
    <?php else: ?>
      <span class="badge badge-grey">&#x41e;&#x436;&#x438;&#x434;&#x430;&#x435;&#x442; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;</span>
    <?php endif; ?>
  </p>
  <?php if ($order['payment_status'] !== 'paid'): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <form method="post" class="inline">
        <?= crm_csrf_field() ?>
        <input type="hidden" name="action" value="cod_collect_cash">
        <button class="btn small secondary" type="submit">&#x41f;&#x43e;&#x43b;&#x443;&#x447;&#x435;&#x43d;&#x43e; &#x43d;&#x430;&#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x43c;&#x438; &#x43f;&#x440;&#x438; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x438;</button>
      </form>
      <form method="post" class="inline">
        <?= crm_csrf_field() ?>
        <input type="hidden" name="action" value="cod_send_payment_link">
        <button class="btn small secondary" type="submit">&#x421;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x438; &#x43e;&#x442;&#x43f;&#x440;&#x430;&#x432;&#x438;&#x442;&#x44c; &#x441;&#x441;&#x44b;&#x43b;&#x43a;&#x443; &#x43d;&#x430; &#x43e;&#x43d;&#x43b;&#x430;&#x439;&#x43d;-&#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x443;</button>
      </form>
    </div>
    <?php if ($codLink): ?>
      <p class="text-muted" style="margin-top:10px;">&#x41f;&#x43e;&#x441;&#x43b;&#x435;&#x434;&#x43d;&#x44f;&#x44f; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x43d;&#x430;&#x44f; &#x441;&#x441;&#x44b;&#x43b;&#x43a;&#x430; &#x43d;&#x430; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x443; (&#x441;&#x442;&#x430;&#x442;&#x443;&#x441;: <?= e($codLink['status']) ?>): <a href="<?= e($codLink['confirmation_url']) ?>" target="_blank"><?= e($codLink['confirmation_url']) ?></a></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0;">&#x413;&#x440;&#x443;&#x437;&#x43e;&#x43c;&#x435;&#x441;&#x442;&#x430; (<?= (int) count($orderPackages) ?>)</h3>
  <?php if (!$orderPackages): ?>
    <div class="empty-state">&#x413;&#x440;&#x443;&#x437;&#x43e;&#x43c;&#x435;&#x441;&#x442;&#x430; &#x435;&#x449;&#x451; &#x43d;&#x435; &#x441;&#x444;&#x43e;&#x440;&#x43c;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x44b;.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>&#x428;&#x442;&#x440;&#x438;&#x445;&#x43a;&#x43e;&#x434;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x41e;&#x442;&#x441;&#x43a;&#x430;&#x43d;&#x438;&#x440;&#x43e;&#x432;&#x430;&#x43d;&#x43e;</th></tr></thead>
      <tbody>
        <?php foreach ($orderPackages as $pkg): ?>
        <tr>
          <td><?= (int) $pkg['seq'] ?></td>
          <td><code><?= e($pkg['barcode']) ?></code></td>
          <td><span class="badge <?= crm_package_status_class($pkg['status']) ?>"><?= e(crm_package_status_label($pkg['status'])) ?></span></td>
          <td><?= $pkg['scanned_at'] ? e(crm_date($pkg['scanned_at'], 'd.m.Y H:i')) : "\u{2014}" ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin-top:10px;"><a class="btn small secondary" href="/crm/package-labels.php?order_id=<?= (int) $id ?>" target="_blank">&#x41f;&#x435;&#x447;&#x430;&#x442;&#x44c; &#x44d;&#x442;&#x438;&#x43a;&#x435;&#x442;&#x43e;&#x43a;</a></p>
  <?php endif; ?>
</div>

<?php if (!empty($order['recipient_signature'])): ?>
<div class="card">
  <h3 style="margin-top:0;">&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f; &#x43f;&#x440;&#x438; &#x432;&#x440;&#x443;&#x447;&#x435;&#x43d;&#x438;&#x438;</h3>
  <img src="<?= e($order['recipient_signature']) ?>" alt="&#x41f;&#x43e;&#x434;&#x43f;&#x438;&#x441;&#x44c; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x442;&#x435;&#x43b;&#x44f;" style="max-width:320px;border:1px solid #ddd;border-radius:6px;background:#fff;">
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0;">&#x41a;&#x443;&#x440;&#x44c;&#x435;&#x440; &#x438; &#x43c;&#x430;&#x440;&#x448;&#x440;&#x443;&#x442;</h3>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="assign_courier">
    <div>
      <label>&#x41d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d;&#x43d;&#x44b;&#x439; &#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;</label>
      <select name="courier_id">
        <option value="">&#x2014; &#x43d;&#x435; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x435;&#x43d; &#x2014;</option>
        <?php foreach ($couriers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$order['courier_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions"><button class="btn secondary" type="submit">&#x41d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x438;&#x442;&#x44c;</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x421;&#x431;&#x43e;&#x440;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441;</h3>
  <?php if ($currentRun): ?>
    <p>&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x432;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x430; &#x432; &#x440;&#x435;&#x439;&#x441; <a href="/crm/shipments.php?id=<?= (int) $currentRun['id'] ?>">&#x2116;<?= (int) $currentRun['id'] ?> &#x2014; <?= e($currentRun['from_city']) ?> &#x2192; <?= e($currentRun['to_city']) ?><?= $currentRun['run_date'] ? ', ' . e(crm_date($currentRun['run_date'], 'd.m.Y')) : '' ?></a>
    <span class="badge <?= crm_shipment_run_status_class($currentRun['status']) ?>"><?= e(crm_shipment_run_status_label($currentRun['status'])) ?></span></p>
  <?php else: ?>
    <div class="empty-state">&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x430; &#x43f;&#x43e;&#x43a;&#x430; &#x43d;&#x435; &#x432;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x430; &#x43d;&#x438; &#x432; &#x43e;&#x434;&#x438;&#x43d; &#x441;&#x431;&#x43e;&#x440;&#x43d;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441;.</div>
  <?php endif; ?>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="assign_shipment_run">
    <div>
      <label>&#x41d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x438;&#x442;&#x44c; &#x432; &#x440;&#x435;&#x439;&#x441;</label>
      <select name="shipment_run_id">
        <option value="">&#x2014; &#x43d;&#x435; &#x432;&#x43a;&#x43b;&#x44e;&#x447;&#x435;&#x43d;&#x430; &#x2014;</option>
        <?php foreach ($formingRuns as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= $currentRun && (int)$currentRun['id'] === (int)$r['id'] ? 'selected' : '' ?>>&#x2116;<?= (int)$r['id'] ?> &#x2014; <?= e($r['from_city']) ?> &#x2192; <?= e($r['to_city']) ?><?= $r['run_date'] ? ', ' . e(crm_date($r['run_date'], 'd.m.Y')) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions"><button class="btn secondary" type="submit">&#x421;&#x43e;&#x445;&#x440;&#x430;&#x43d;&#x438;&#x442;&#x44c;</button></div>
  </form>
  <p class="text-muted" style="margin-top:10px;margin-bottom:0;">&#x41d;&#x43e;&#x432;&#x44b;&#x439; &#x440;&#x435;&#x439;&#x441; &#x43c;&#x43e;&#x436;&#x43d;&#x43e; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x442;&#x44c; &#x43d;&#x430; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x435; <a href="/crm/shipments.php">&#xab;&#x420;&#x435;&#x439;&#x441;&#x44b;&#xbb;</a>.</p>
</div>

<div class="card">
  <h3 style="margin-top:0;">&#x418;&#x437;&#x43c;&#x435;&#x43d;&#x438;&#x442;&#x44c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;</h3>
  <form method="post" class="form-row" style="align-items:end;">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="change_status">
    <div>
      <label>&#x41d;&#x43e;&#x432;&#x44b;&#x439; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;</label>
      <select name="status">
        <?php foreach (['new','accepted','collecting','in_transit','delivered','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(crm_order_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439; (&#x43d;&#x435;&#x43e;&#x431;&#x44f;&#x437;&#x430;&#x442;&#x435;&#x43b;&#x44c;&#x43d;&#x43e;)</label>
      <input type="text" name="status_comment">
    </div>
    <div class="form-actions"><button class="btn secondary" type="submit">&#x41e;&#x431;&#x43d;&#x43e;&#x432;&#x438;&#x442;&#x44c; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;</button></div>
  </form>

  <?php if ($history): ?>
  <table style="margin-top:10px;">
    <thead><tr><th>&#x414;&#x430;&#x442;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x41a;&#x442;&#x43e; &#x438;&#x437;&#x43c;&#x435;&#x43d;&#x438;&#x43b;</th><th>&#x41a;&#x43e;&#x43c;&#x43c;&#x435;&#x43d;&#x442;&#x430;&#x440;&#x438;&#x439;</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= crm_date($h['changed_at']) ?></td>
        <td><span class="badge <?= crm_order_status_class($h['status']) ?>"><?= e(crm_order_status_label($h['status'])) ?></span></td>
        <td><?= e($h['user_name'] ?? "\u{2014}") ?></td>
        <td><?= e($h['comment']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">
    &#x41f;&#x440;&#x435;&#x442;&#x435;&#x43d;&#x437;&#x438;&#x438; &#x43f;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435;
    <a class="btn small" style="float:right;" href="/crm/claim.php?order_id=<?= (int) $order['id'] ?>">+ &#x41d;&#x43e;&#x432;&#x430;&#x44f; &#x43f;&#x440;&#x435;&#x442;&#x435;&#x43d;&#x437;&#x438;&#x44f;</a>
  </h3>
  <?php if (!$orderClaims): ?>
    <div class="empty-state">&#x41f;&#x440;&#x435;&#x442;&#x435;&#x43d;&#x437;&#x438;&#x439; &#x43f;&#x43e; &#x44d;&#x442;&#x43e;&#x439; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x435; &#x43d;&#x435;&#x442;.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>&#x2116;</th><th>&#x41f;&#x440;&#x438;&#x447;&#x438;&#x43d;&#x430;</th><th>&#x421;&#x442;&#x430;&#x442;&#x443;&#x441;</th><th>&#x41a;&#x43e;&#x43c;&#x43f;&#x435;&#x43d;&#x441;&#x430;&#x446;&#x438;&#x44f;</th><th>&#x421;&#x440;&#x43e;&#x43a; &#x43e;&#x442;&#x432;&#x435;&#x442;&#x430;</th><th>&#x421;&#x43e;&#x437;&#x434;&#x430;&#x43d;&#x430;</th></tr></thead>
    <tbody>
      <?php foreach ($orderClaims as $cl): ?>
      <tr>
        <td><a href="/crm/claim.php?id=<?= (int) $cl['id'] ?>">&#x2116;<?= (int) $cl['id'] ?></a></td>
        <td><?= e(crm_claim_reason_label($cl['reason'])) ?></td>
        <td><span class="badge <?= crm_claim_status_class($cl['status']) ?>"><?= e(crm_claim_status_label($cl['status'])) ?></span></td>
        <td><?= crm_money($cl['compensation_amount'] !== null ? (float) $cl['compensation_amount'] : null) ?></td>
        <td><?= $cl['response_due_date'] ? crm_date($cl['response_due_date'], 'd.m.Y') : "\u{2014}" ?></td>
        <td><?= crm_date($cl['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
