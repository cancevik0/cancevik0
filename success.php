<?php
require_once 'includes/db.php';

if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

// Clear the cart here — payment has been confirmed
$_SESSION['cart']                 = [];
$_SESSION['pending_tebex_ident']  = null;
unset($_SESSION['pending_tebex_ident']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — NACScripts</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #0d0d0d; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #1a1a2e; padding: 2.5rem; border-radius: 14px; text-align: center; max-width: 480px; width: 90%; border: 1px solid #2a2a4a; }
        .icon { font-size: 3.5rem; margin-bottom: 1rem; }
        h1 { margin-bottom: 0.5rem; color: #2ecc71; }
        p  { color: #aaa; margin-bottom: 1.5rem; line-height: 1.6; }
        .btn { display: inline-block; padding: 0.65rem 1.6rem; background: #5865f2; color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn:hover { background: #4752c4; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your purchase. Your scripts will be delivered shortly.<br>Check your Discord DMs or the #downloads channel.</p>
        <a href="index.php" class="btn">← Back to Store</a>
    </div>
</body>
</html>
