<?php
require_once 'includes/db.php';

if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

// ── Cart actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && isset($_POST['script_id'])) {
        $id = (int)$_POST['script_id'];
        if (!in_array($id, $_SESSION['cart'] ?? [], true)) {
            $_SESSION['cart'][] = $id;
        }
    }

    if ($action === 'remove' && isset($_POST['script_id'])) {
        $id = (int)$_POST['script_id'];
        $_SESSION['cart'] = array_values(
            array_filter($_SESSION['cart'] ?? [], fn($v) => $v !== $id)
        );
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
    }

    header('Location: cart.php');
    exit;
}

// ── Load cart items ───────────────────────────────────────────────────────────
$cart_items  = [];
$total_price = 0;

if (!empty($_SESSION['cart'])) {
    $in_clause  = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt       = $db->prepare("SELECT * FROM scripts WHERE id IN ($in_clause)");
    $stmt->execute($_SESSION['cart']);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cart_items as $item) {
        $total_price += $item['price'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart — NACScripts</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #0d0d0d; color: #fff; min-height: 100vh; }

        header { background: #1a1a2e; padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid #2a2a4a; }
        header h1 { font-size: 1.2rem; flex: 1; }
        header a  { color: #5865f2; text-decoration: none; }

        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }

        .empty { text-align: center; padding: 3rem; color: #aaa; }

        .cart-item {
            display: flex; align-items: center; gap: 1rem;
            background: #1a1a2e; border-radius: 10px; padding: 1rem 1.2rem;
            margin-bottom: 0.75rem; border: 1px solid #2a2a4a;
        }
        .cart-item .name  { flex: 1; font-weight: 600; }
        .cart-item .price { color: #2ecc71; font-weight: 700; min-width: 80px; text-align: right; }
        .cart-item form   { margin: 0; }
        .btn-remove {
            background: #c0392b; color: #fff; border: none; border-radius: 6px;
            padding: 0.4rem 0.8rem; cursor: pointer; font-size: 0.85rem;
        }
        .btn-remove:hover { background: #e74c3c; }

        .cart-footer {
            background: #1a1a2e; border-radius: 10px; padding: 1.2rem 1.5rem;
            border: 1px solid #2a2a4a; display: flex; align-items: center;
            justify-content: space-between; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;
        }
        .cart-footer .total { font-size: 1.15rem; font-weight: 700; }
        .cart-footer .total span { color: #2ecc71; }

        .btn-checkout {
            background: #5865f2; color: #fff; padding: 0.7rem 2rem;
            border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 1rem;
        }
        .btn-checkout:hover { background: #4752c4; }

        .btn-clear {
            background: transparent; color: #aaa; border: 1px solid #444;
            border-radius: 8px; padding: 0.5rem 1rem; cursor: pointer; font-size: 0.85rem;
        }
        .btn-clear:hover { color: #fff; border-color: #888; }
    </style>
</head>
<body>

<header>
    <img src="assets/logo.png" alt="NACScripts" height="36" onerror="this.style.display='none'">
    <h1>Your Cart</h1>
    <a href="index.php">← Store</a>
</header>

<div class="container">
    <?php if (empty($cart_items)): ?>
        <div class="empty">
            <p style="font-size:3rem;">🛒</p>
            <p style="margin-top:1rem;">Your cart is empty.</p>
            <a href="index.php" style="color:#5865f2;">Browse scripts →</a>
        </div>
    <?php else: ?>
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <span class="name"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="price">€<?= number_format((float)$item['price'], 2) ?></span>
                <form method="POST">
                    <input type="hidden" name="action"    value="remove">
                    <input type="hidden" name="script_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn-remove">Remove</button>
                </form>
            </div>
        <?php endforeach; ?>

        <div class="cart-footer">
            <div>
                <div class="total">Total: <span>€<?= number_format($total_price, 2) ?></span></div>
                <form method="POST" style="display:inline;margin-top:0.5rem;">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn-clear">Clear cart</button>
                </form>
            </div>
            <a href="checkout.php" class="btn-checkout">Proceed to Checkout →</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
