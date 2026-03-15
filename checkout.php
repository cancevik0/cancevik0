<?php
require_once 'includes/db.php';

if (!isset($_SESSION['discord_user']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

$env = parse_ini_file(__DIR__ . '/.env');
$tebex_public_token = trim($env['TEBEX_PUBLIC_TOKEN'] ?? '');
$site_url            = rtrim($env['SITE_URL'] ?? 'https://store.nacscripts.dev', '/');

// =========================================================================
// STEP 2: USER RETURNED FROM CFX.RE AUTH — ADD PACKAGES & LAUNCH EMBED
// =========================================================================
if (isset($_GET['step']) && $_GET['step'] == '2' && isset($_GET['ident'])) {
    $tebex_ident = preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['ident']);

    if (empty($tebex_ident)) {
        http_response_code(400);
        exit('Invalid basket identifier.');
    }

    $in_clause  = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt       = $db->prepare("SELECT * FROM scripts WHERE id IN ($in_clause)");
    $stmt->execute($_SESSION['cart']);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_error = false;
    $error_msg = '';

    foreach ($cart_items as $item) {
        $package_id   = (int)($item['tebex_package_id'] ?? $item['id']);
        $package_data = ['package_id' => $package_id, 'quantity' => 1];

        $ch_pkg = curl_init("https://headless.tebex.io/api/baskets/{$tebex_ident}/packages");
        curl_setopt_array($ch_pkg, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($package_data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response_pkg  = curl_exec($ch_pkg);
        $httpcode_pkg  = curl_getinfo($ch_pkg, CURLINFO_HTTP_CODE);
        curl_close($ch_pkg);

        if ($httpcode_pkg !== 200 && $httpcode_pkg !== 201) {
            $has_error  = true;
            $error_msg .= "Package ($package_id) could not be added. "
                        . "Code: $httpcode_pkg | Response: "
                        . htmlspecialchars($response_pkg, ENT_QUOTES, 'UTF-8') . "\n";
        }
    }

    if ($has_error) {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Error</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0d0d0d; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-box { background: #1a1a2e; padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; text-align: center; border: 1px solid #e74c3c; }
        .error-box h2 { color: #e74c3c; }
        .error-box pre { text-align: left; background: #0d0d0d; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.6rem 1.4rem; background: #5865f2; color: #fff; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>⚠️ Checkout Error</h2>
        <p>One or more packages could not be added to your basket.</p>
        <pre><?= nl2br(htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8')) ?></pre>
        <a href="cart.php" class="btn">← Back to Cart</a>
    </div>
</body>
</html>
        <?php
        exit;
    }

    // Cart is cleared only after the payment embed is launched successfully.
    // The actual cart session is cleared on the success page (success.php).
    $_SESSION['pending_tebex_ident'] = $tebex_ident;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — NACScripts</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #0d0d0d; color: #fff; min-height: 100vh; }

        .checkout-header {
            background: #1a1a2e;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid #2a2a4a;
        }
        .checkout-header h1 { font-size: 1.2rem; }

        /* Tebex embedded checkout fills the remaining viewport height */
        #tebex-checkout-container {
            width: 100%;
            min-height: calc(100vh - 60px);
            background: #0d0d0d;
        }

        .loading-overlay {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 60px);
            gap: 1rem;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #2a2a4a;
            border-top-color: #5865f2;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="checkout-header">
    <img src="assets/logo.png" alt="NACScripts" height="36" onerror="this.style.display='none'">
    <h1>Secure Checkout</h1>
</div>

<div id="tebex-checkout-container">
    <div class="loading-overlay" id="loading">
        <div class="spinner"></div>
        <p>Loading secure checkout…</p>
    </div>
</div>

<!--
    Tebex.js — Headless Checkout Embed
    Docs: https://docs.tebex.io/developers/headless-api/checkout-embed
-->
<script src="https://checkout.tebex.io/js/tebex.js"></script>
<script>
    Tebex.checkout.init({
        ident: <?= json_encode($tebex_ident) ?>,
        theme: 'dark',
    });

    // Remove loading spinner once the embed is ready
    Tebex.checkout.on('open', function () {
        var loading = document.getElementById('loading');
        if (loading) { loading.style.display = 'none'; }
    });

    // Redirect to success page when payment is complete
    Tebex.checkout.on('payment:complete', function (e) {
        window.location.href = <?= json_encode($site_url . '/success.php') ?>;
    });

    // Redirect to cart if the user closes / cancels
    Tebex.checkout.on('close', function () {
        window.location.href = <?= json_encode($site_url . '/cart.php') ?>;
    });

    // Display the checkout inline inside our container
    Tebex.checkout.launch();
</script>

</body>
</html>
    <?php
    exit;
}

// =========================================================================
// STEP 1: CREATE BASKET AND REDIRECT TO CFX.RE AUTH
// =========================================================================

$in_clause  = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
$stmt       = $db->prepare("SELECT * FROM scripts WHERE id IN ($in_clause)");
$stmt->execute($_SESSION['cart']);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_price     = 0;
$purchased_items = [];

foreach ($cart_items as $item) {
    $total_price       += $item['price'];
    $purchased_items[]  = $item['title'];
}

$items_string     = implode(', ', $purchased_items);
$user_id = isset($_SESSION['discord_user']['db_id']) ? (int)$_SESSION['discord_user']['db_id'] : null;
if (!$user_id) {
    header('Location: index.php');
    exit;
}
$discord_username = $_SESSION['discord_user']['username'];

$insert_order = $db->prepare(
    "INSERT INTO orders (user_id, discord_username, purchased_items, total_amount, status)
     VALUES (?, ?, ?, ?, 'Pending')"
);
$insert_order->execute([$user_id, $discord_username, $items_string, $total_price]);
$local_order_id = (int)$db->lastInsertId();

$basket_data = [
    'complete_url' => $site_url . '/success.php',
    'cancel_url'   => $site_url . '/cart.php',
    'custom'       => ['local_order_id' => $local_order_id],
];

$ch = curl_init("https://headless.tebex.io/api/accounts/{$tebex_public_token}/baskets");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($basket_data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
]);
$response_basket = curl_exec($ch);
$httpcode_basket = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result_basket = json_decode($response_basket, true);

if ($httpcode_basket == 200
    && (isset($result_basket['ident']) || isset($result_basket['data']['ident']))
) {
    $tebex_ident = $result_basket['ident'] ?? $result_basket['data']['ident'];

    $return_url    = urlencode($site_url . "/checkout.php?step=2&ident={$tebex_ident}");
    $auth_endpoint = "https://headless.tebex.io/api/accounts/{$tebex_public_token}"
                   . "/baskets/{$tebex_ident}/auth?returnUrl={$return_url}";

    $ch_auth = curl_init($auth_endpoint);
    curl_setopt_array($ch_auth, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response_auth = curl_exec($ch_auth);
    $httpcode_auth = curl_getinfo($ch_auth, CURLINFO_HTTP_CODE);
    curl_close($ch_auth);

    if ($httpcode_auth == 200) {
        $auth_data = json_decode($response_auth, true);

        if (is_array($auth_data) && count($auth_data) > 0 && isset($auth_data[0]['url'])) {
            $login_url = $auth_data[0]['url'];
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecting to Cfx.re…</title>
    <meta http-equiv="refresh" content="2;url=<?= htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #0d0d0d; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #1a1a2e; padding: 2.5rem; border-radius: 14px; text-align: center; max-width: 420px; width: 90%; border: 1px solid #2a2a4a; }
        .card h2 { margin-bottom: 0.75rem; }
        .card p  { color: #aaa; margin-bottom: 1.5rem; }
        .spinner { width: 40px; height: 40px; border: 4px solid #2a2a4a; border-top-color: #5865f2; border-radius: 50%; animation: spin 0.9s linear infinite; margin: 0 auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h2 style="margin-top:1.5rem;">Connecting to Cfx.re (FiveM)</h2>
        <p>Authenticating your account for secure checkout.<br>You will be redirected automatically.</p>
        <a href="<?= htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') ?>" style="color:#5865f2;">Click here if not redirected</a>
    </div>
</body>
</html>
            <?php
            exit;
        } else {
            // Auth not required — go straight to the embed step
            header("Location: checkout.php?step=2&ident=" . urlencode($tebex_ident));
            exit;
        }
    } else {
        http_response_code(502);
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auth Error</title>
    <style>body{font-family:Arial,sans-serif;background:#0d0d0d;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;}
    .box{background:#1a1a2e;padding:2rem;border-radius:12px;max-width:520px;width:90%;border:1px solid #e74c3c;}</style>
</head>
<body>
    <div class="box">
        <h2 style="color:#e74c3c;">⚠️ Auth Connection Error</h2>
        <p>HTTP <?= (int)$httpcode_auth ?></p>
        <pre style="background:#0d0d0d;padding:1rem;border-radius:8px;overflow-x:auto;font-size:.85rem;"><?= htmlspecialchars($response_auth, ENT_QUOTES, 'UTF-8') ?></pre>
        <a href="cart.php" style="color:#5865f2;">← Back to Cart</a>
    </div>
</body>
</html>
        <?php
        exit;
    }
} else {
    http_response_code(502);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Basket Creation Failed</title>
    <style>body{font-family:Arial,sans-serif;background:#0d0d0d;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;}
    .box{background:#1a1a2e;padding:2rem;border-radius:12px;max-width:520px;width:90%;border:1px solid #e74c3c;}</style>
</head>
<body>
    <div class="box">
        <h2 style="color:#e74c3c;">⚠️ Basket Creation Failed</h2>
        <p>HTTP <?= (int)$httpcode_basket ?></p>
        <pre style="background:#0d0d0d;padding:1rem;border-radius:8px;overflow-x:auto;font-size:.85rem;"><?= htmlspecialchars($response_basket, ENT_QUOTES, 'UTF-8') ?></pre>
        <a href="cart.php" style="color:#5865f2;">← Back to Cart</a>
    </div>
</body>
</html>
    <?php
    exit;
}
