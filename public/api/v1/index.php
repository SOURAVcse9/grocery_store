<?php
/**
 * ==============================================================================
 * GroCo RESTful API v1 Master Router & Controller Dispatcher
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../dbconnect.php';
require_once __DIR__ . '/../../includes/CacheService.php';
require_once __DIR__ . '/../../includes/MediaService.php';
require_once __DIR__ . '/ApiResponse.php';

// Handle CORS Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ApiResponse::send(200, 'success', ['message' => 'Preflight OK']);
}

$pdo = Database::getConnection();

// Rate limiting for API: 120 requests per minute
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateKey = 'api_rate:' . md5($clientIp);
$currentHits = (int)CacheService::get($rateKey, 0);
if ($currentHits > 120) {
    ApiResponse::rateLimited();
}
CacheService::set($rateKey, $currentHits + 1, 60);

// Parse Route & Method
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/grocery-store/public/api/v1';

$path = parse_url($requestUri, PHP_URL_PATH);
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

$resource = $segments[0] ?? '';
$subId = $segments[1] ?? null;
$action = $segments[2] ?? null;

// JSON Request Body Parser
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

try {
    switch ($resource) {
        // ----------------------------------------------------------------------
        // GET /api/v1/products & GET /api/v1/products/{id}
        // ----------------------------------------------------------------------
        case 'products':
            if ($method === 'GET') {
                if ($subId && is_numeric($subId)) {
                    // Single Product Details
                    $stmt = $pdo->prepare("
                        SELECT p.*, c.name as category_name, b.name as brand_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN brands b ON p.brand_id = b.id
                        WHERE p.id = ? AND (p.status = 'active' OR p.is_active = 1)
                    ");
                    $stmt->execute([(int)$subId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        ApiResponse::notFound('Product not found or inactive');
                    }

                    $imageKey = (string)($product['thumbnail'] ?? ($product['image'] ?? ''));
                    $product['image_url'] = MediaService::getUrl($imageKey);
                    $product['responsive_images'] = MediaService::getResponsiveUrls($imageKey);
                    ApiResponse::success($product);
                } else {
                    // Product Listing with Faceted Filtering
                    $page = max(1, (int)($_GET['page'] ?? 1));
                    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
                    $offset = ($page - 1) * $perPage;

                    $where = ["(p.status = 'active' OR p.is_active = 1)"];
                    $params = [];

                    if (!empty($_GET['category_id'])) {
                        $where[] = "p.category_id = ?";
                        $params[] = (int)$_GET['category_id'];
                    }
                    if (!empty($_GET['brand_id'])) {
                        $where[] = "p.brand_id = ?";
                        $params[] = (int)$_GET['brand_id'];
                    }
                    if (!empty($_GET['search'])) {
                        $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
                        $searchTerm = '%' . trim($_GET['search']) . '%';
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                    }
                    if (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') {
                        $where[] = "p.stock > 0";
                    }

                    $whereSql = implode(' AND ', $where);

                    // Count total
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
                    $countStmt->execute($params);
                    $total = (int)$countStmt->fetchColumn();

                    $limitInt = max(1, (int)$perPage);
                    $offsetInt = max(0, (int)$offset);

                    // Query Items
                    $query = "
                        SELECT p.id, p.name, p.slug, p.sku, p.price, p.discount_price, 
                               p.stock, p.thumbnail, p.avg_rating, p.review_count,
                               c.name as category_name, b.name as brand_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN brands b ON p.brand_id = b.id
                        WHERE {$whereSql}
                        ORDER BY p.id DESC
                        LIMIT {$limitInt} OFFSET {$offsetInt}
                    ";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($products as &$p) {
                        $img = (string)($p['thumbnail'] ?? '');
                        $p['image_url'] = MediaService::getUrl($img);
                    }

                    ApiResponse::success($products, [
                        'page'        => $page,
                        'per_page'    => $perPage,
                        'total'       => $total,
                        'total_pages' => ceil($total / max(1, $perPage))
                    ]);
                }
            }
            break;

        // ----------------------------------------------------------------------
        // GET /api/v1/categories
        // ----------------------------------------------------------------------
        case 'categories':
            if ($method === 'GET') {
                $categories = CacheService::remember('api_categories_all', CacheService::TTL_MEDIUM, function() use ($pdo) {
                    $stmt = $pdo->query("SELECT id, name, slug, parent_id, image, icon FROM categories WHERE is_active = 1 ORDER BY name ASC");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as &$cat) {
                        $cat['image_url'] = MediaService::getUrl((string)($cat['image'] ?? ''), [], 'categories');
                    }
                    return $rows;
                }, ['categories']);

                ApiResponse::success($categories);
            }
            break;

        // ----------------------------------------------------------------------
        // GET /api/v1/brands
        // ----------------------------------------------------------------------
        case 'brands':
            if ($method === 'GET') {
                $brands = CacheService::remember('api_brands_all', CacheService::TTL_MEDIUM, function() use ($pdo) {
                    $stmt = $pdo->query("SELECT id, name, slug, logo FROM brands WHERE is_active = 1 ORDER BY name ASC");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as &$b) {
                        $b['logo_url'] = MediaService::getUrl((string)($b['logo'] ?? ''), [], 'brands');
                    }
                    return $rows;
                }, ['brands']);

                ApiResponse::success($brands);
            }
            break;

        // ----------------------------------------------------------------------
        // GET /api/v1/search/autocomplete
        // ----------------------------------------------------------------------
        case 'search':
            if ($subId === 'autocomplete' && $method === 'GET') {
                $q = trim((string)($_GET['q'] ?? ''));
                if (strlen($q) < 2) {
                    ApiResponse::success([]);
                }

                $searchService = new SearchService($pdo);
                $results = $searchService->autocomplete($q, 8);

                foreach ($results as &$r) {
                    $img = (string)($r['thumbnail'] ?? '');
                    $r['image_url'] = MediaService::getUrl($img);
                }

                ApiResponse::success($results);
            }
            break;

        // ----------------------------------------------------------------------
        // GET & POST /api/v1/cart
        // ----------------------------------------------------------------------
        case 'cart':
            $cartItems = $_SESSION['cart'] ?? [];
            if ($method === 'GET') {
                $items = [];
                $subtotal = 0.0;

                if (!empty($cartItems)) {
                    $ids = array_keys($cartItems);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, name, price, discount_price, thumbnail, stock FROM products WHERE id IN ({$placeholders})");
                    $stmt->execute($ids);
                    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($dbProducts as $p) {
                        $qty = (int)($cartItems[$p['id']] ?? 1);
                        $effectivePrice = ($p['discount_price'] !== null && $p['discount_price'] > 0 && $p['discount_price'] < $p['price'])
                            ? (float)$p['discount_price']
                            : (float)$p['price'];
                        $lineTotal = $effectivePrice * $qty;
                        $subtotal += $lineTotal;

                        $items[] = [
                            'product_id' => (int)$p['id'],
                            'name'       => $p['name'],
                            'price'      => $effectivePrice,
                            'quantity'   => $qty,
                            'stock'      => (int)$p['stock'],
                            'line_total' => $lineTotal,
                            'image_url'  => MediaService::getUrl((string)($p['thumbnail'] ?? ''))
                        ];
                    }
                }

                ApiResponse::success([
                    'items'      => $items,
                    'item_count' => count($items),
                    'subtotal'   => $subtotal,
                    'vat_amount' => 0.00, // Strictly Zero-VAT compliant
                    'total'      => $subtotal
                ]);
            } elseif ($method === 'POST') {
                $productId = (int)($input['product_id'] ?? 0);
                $quantity = max(1, (int)($input['quantity'] ?? 1));

                if ($productId <= 0) {
                    ApiResponse::error('Valid product_id required', 400);
                }

                $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND (status = 'active' OR is_active = 1)");
                $stmt->execute([$productId]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod) {
                    ApiResponse::notFound('Product not found');
                }

                if ((int)$prod['stock'] < $quantity) {
                    ApiResponse::error('Requested quantity exceeds available stock', 400, 'INSUFFICIENT_STOCK');
                }

                $_SESSION['cart'][$productId] = $quantity;
                ApiResponse::success(['message' => 'Cart updated successfully', 'product_id' => $productId, 'quantity' => $quantity]);
            }
            break;

        // ----------------------------------------------------------------------
        // POS API /api/v1/pos/products & /api/v1/pos/sale (Admin Session / Token Guard)
        // ----------------------------------------------------------------------
        case 'pos':
            if (!is_admin_logged_in()) {
                ApiResponse::unauthorized('POS API requires active staff session');
            }

            if ($subId === 'products' && $method === 'GET') {
                $q = trim((string)($_GET['query'] ?? ''));
                $sql = "SELECT id, name, sku, barcode, price, discount_price, stock, thumbnail 
                        FROM products 
                        WHERE (status = 'active' OR is_active = 1)";
                $params = [];
                if ($q !== '') {
                    $sql .= " AND (name LIKE ? OR sku LIKE ? OR barcode = ?)";
                    $params = ['%' . $q . '%', '%' . $q . '%', $q];
                }
                $sql .= " ORDER BY name ASC LIMIT 50";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($products as &$p) {
                    $p['image_url'] = MediaService::getUrl((string)($p['thumbnail'] ?? ''));
                }

                ApiResponse::success($products);
            } elseif ($subId === 'sale' && $method === 'POST') {
                $idempotencyKey = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ($input['client_tx_id'] ?? null);
                if (empty($idempotencyKey)) {
                    ApiResponse::error('Missing X-Idempotency-Key header or client_tx_id', 400, 'MISSING_IDEMPOTENCY_KEY');
                }

                // Check for duplicate processing
                $checkStmt = $pdo->prepare("SELECT id, order_number, total_amount, status FROM orders WHERE note LIKE ? LIMIT 1");
                $checkStmt->execute(['%IDEMPOTENCY:' . $idempotencyKey . '%']);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    ApiResponse::success([
                        'message'      => 'Transaction already processed (Idempotent response)',
                        'order_id'     => $existing['id'],
                        'order_number' => $existing['order_number'],
                        'total'        => (float)$existing['total_amount'],
                        'is_duplicate' => true
                    ]);
                }

                $items = $input['items'] ?? [];
                if (empty($items) || !is_array($items)) {
                    ApiResponse::error('Order items array cannot be empty', 400);
                }

                // Process POS sale in transaction
                $pdo->beginTransaction();
                $totalAmount = 0.0;

                foreach ($items as $item) {
                    $pId = (int)($item['id'] ?? 0);
                    $qty = (int)($item['qty'] ?? 1);
                    $price = (float)($item['price'] ?? 0.0);
                    $totalAmount += ($price * $qty);

                    // Deduct stock with lock
                    $stockStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
                    $stockStmt->execute([$pId]);
                    $currStock = (int)$stockStmt->fetchColumn();

                    if ($currStock < $qty) {
                        $pdo->rollBack();
                        ApiResponse::error("Insufficient stock for product ID #{$pId}", 400, 'STOCK_DEPLETED');
                    }

                    $deductStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $deductStmt->execute([$qty, $pId]);
                }

                $orderNumber = 'POS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymd');
                $note = 'POS In-store checkout | IDEMPOTENCY:' . $idempotencyKey;
                $paymentMethod = $input['payment_method'] ?? 'cash';

                $orderStmt = $pdo->prepare("
                    INSERT INTO orders (order_number, user_id, total_amount, subtotal, payment_method, payment_status, status, note, created_at)
                    VALUES (?, NULL, ?, ?, ?, 'paid', 'delivered', ?, NOW())
                ");
                $orderStmt->execute([$orderNumber, $totalAmount, $totalAmount, $paymentMethod, $note]);
                $orderId = (int)$pdo->lastInsertId();

                $pdo->commit();
                CacheService::invalidateCatalog();

                ApiResponse::success([
                    'message'      => 'POS sale completed successfully',
                    'order_id'     => $orderId,
                    'order_number' => $orderNumber,
                    'total'        => $totalAmount,
                    'is_duplicate' => false
                ], null, 201);
            }
            break;

        // ----------------------------------------------------------------------
        // REVIEWS: GET /api/v1/reviews?product_id={id} & POST /api/v1/reviews
        // ----------------------------------------------------------------------
        case 'reviews':
            $reviewService = new ReviewService($pdo);
            if ($method === 'GET') {
                $pId = (int)($_GET['product_id'] ?? 0);
                if ($pId <= 0) {
                    ApiResponse::error('Valid product_id parameter required', 400);
                }
                $reviews = $reviewService->getProductReviews($pId, 25);
                ApiResponse::success($reviews);
            } elseif ($method === 'POST') {
                if (!is_logged_in()) {
                    ApiResponse::unauthorized('Customer login required to post a review');
                }
                $userId = (int)get_current_user_id();
                $pId = (int)($input['product_id'] ?? 0);
                $rating = (int)($input['rating'] ?? 5);
                $comment = trim((string)($input['comment'] ?? ''));

                if ($pId <= 0 || $comment === '') {
                    ApiResponse::error('product_id and comment are required', 400);
                }

                $reviewId = $reviewService->addReview($pId, $userId, $rating, $comment);
                ApiResponse::success(['message' => 'Review submitted successfully', 'review_id' => $reviewId], null, 201);
            }
            break;

        // ----------------------------------------------------------------------
        // ORDERS: GET /api/v1/orders & GET /api/v1/orders/{id}
        // ----------------------------------------------------------------------
        case 'orders':
            if (!is_logged_in()) {
                ApiResponse::unauthorized('Customer login required');
            }
            $userId = (int)get_current_user_id();
            $orderService = new OrderService($pdo);

            if ($method === 'GET') {
                if ($subId && is_numeric($subId)) {
                    $order = $orderService->getById((int)$subId, $userId);
                    if (!$order) {
                        ApiResponse::notFound('Order not found or access denied');
                    }
                    ApiResponse::success($order);
                } else {
                    $page = max(1, (int)($_GET['page'] ?? 1));
                    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
                    $ordersData = $orderService->getUserOrders($userId, $page, $perPage);
                    ApiResponse::success($ordersData['items'], [
                        'page'        => $ordersData['page'],
                        'per_page'    => $ordersData['per_page'],
                        'total'       => $ordersData['total'],
                        'total_pages' => $ordersData['total_pages']
                    ]);
                }
            }
            break;

        // ----------------------------------------------------------------------
        // ADDRESSES: GET /api/v1/addresses & POST /api/v1/addresses
        // ----------------------------------------------------------------------
        case 'addresses':
            if (!is_logged_in()) {
                ApiResponse::unauthorized('Customer login required');
            }
            $userId = (int)get_current_user_id();
            $custService = new CustomerService($pdo);

            if ($method === 'GET') {
                $addresses = $custService->getAddresses($userId);
                ApiResponse::success($addresses);
            } elseif ($method === 'POST') {
                $addrId = $custService->addAddress($userId, $input);
                ApiResponse::success(['message' => 'Address added successfully', 'address_id' => $addrId], null, 201);
            }
            break;

        // ----------------------------------------------------------------------
        // COUPONS: POST /api/v1/coupons/validate
        // ----------------------------------------------------------------------
        case 'coupons':
            if ($subId === 'validate' && $method === 'POST') {
                $couponService = new CouponService($pdo);
                $code = (string)($input['code'] ?? '');
                $subtotal = (float)($input['subtotal'] ?? 0.0);
                $res = $couponService->validate($code, $subtotal);
                if ($res['valid']) {
                    ApiResponse::success($res);
                } else {
                    ApiResponse::error($res['message'], 400, 'INVALID_COUPON', $res);
                }
            }
            break;

        default:
            ApiResponse::notFound("API endpoint [{$resource}] not found. Refer to /docs/API_ARCHITECTURE.md");
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API v1 Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ApiResponse::error('Internal API processing error: ' . $e->getMessage(), 500, 'SERVER_ERROR');
}
