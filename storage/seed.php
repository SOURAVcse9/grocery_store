<?php
require_once __DIR__ . '/../public/dbconnect.php';

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Check if products already exist
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count > 0) {
        echo "Database already seeded with products.\n";
        $pdo->rollBack();
        exit;
    }

    echo "Seeding products...\n";

    $products = [
        [
            'category_id' => 1, // Fruits
            'brand_id' => null,
            'name' => 'Organic Gala Apples',
            'slug' => 'organic-gala-apples',
            'description' => 'Crisp, sweet organic gala apples imported from premium orchards. Rich in fiber and vitamin C.',
            'short_description' => 'Sweet, crisp and fresh organic gala apples.',
            'sku' => 'FRU-APP-001',
            'barcode' => '8801234560012',
            'price' => 250.00,
            'discount_price' => 220.00,
            'unit' => 'kg',
            'stock' => 150,
            'thumbnail' => 'products/apple.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Organic Gala Apples - Fresh Fruits',
            'meta_description' => 'Buy organic gala apples online at best price. Fresh and crisp.'
        ],
        [
            'category_id' => 2, // Vegetables
            'brand_id' => null,
            'name' => 'Fresh Broccoli',
            'slug' => 'fresh-broccoli',
            'description' => 'Green and nutrient-rich fresh broccoli. Perfect for steaming, salads, and healthy stir-fries.',
            'short_description' => 'Fresh green broccoli crowns.',
            'sku' => 'VEG-BRO-002',
            'barcode' => '8801234560029',
            'price' => 120.00,
            'discount_price' => 99.00,
            'unit' => 'pcs',
            'stock' => 80,
            'thumbnail' => 'products/broccoli.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Fresh Broccoli Online',
            'meta_description' => 'Get fresh and healthy broccoli delivered.'
        ],
        [
            'category_id' => 3, // Dairy
            'brand_id' => 4, // Danone
            'name' => 'Pasteurized Whole Milk (1L)',
            'slug' => 'pasteurized-whole-milk-1l',
            'description' => 'Rich and creamy pasteurized whole milk, sourced from local organic dairy farms.',
            'short_description' => '1 Liter whole milk pack.',
            'sku' => 'DAI-MIL-003',
            'barcode' => '8801234560036',
            'price' => 90.00,
            'discount_price' => null,
            'unit' => 'pcs',
            'stock' => 120,
            'thumbnail' => 'products/milk.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Whole Milk 1L - Fresh Dairy',
            'meta_description' => 'Shop farm fresh milk online.'
        ],
        [
            'category_id' => 4, // Bakery
            'brand_id' => null,
            'name' => 'Artisan Sourdough Bread',
            'slug' => 'artisan-sourdough-bread',
            'description' => 'Naturally fermented artisan sourdough bread with a crusty exterior and chewy crumb.',
            'short_description' => 'Freshly baked sourdough bread.',
            'sku' => 'BAK-SOU-004',
            'barcode' => '8801234560043',
            'price' => 180.00,
            'discount_price' => 165.00,
            'unit' => 'pcs',
            'stock' => 30,
            'thumbnail' => 'products/bread.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Artisan Sourdough Bread',
            'meta_description' => 'Freshly baked sourdough bread.'
        ],
        [
            'category_id' => 7, // Meat
            'brand_id' => null,
            'name' => 'Boneless Chicken Breast',
            'slug' => 'boneless-chicken-breast',
            'description' => 'Lean, high-protein boneless chicken breast. Processed under strict hygiene controls.',
            'short_description' => 'Fresh antibiotic-free chicken breast.',
            'sku' => 'MEA-CHI-005',
            'barcode' => '8801234560050',
            'price' => 360.00,
            'discount_price' => 330.00,
            'unit' => 'kg',
            'stock' => 45,
            'thumbnail' => 'products/chicken.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Boneless Chicken Breast',
            'meta_description' => 'Buy premium boneless chicken breast online.'
        ],
        [
            'category_id' => 1, // Fruits
            'brand_id' => null,
            'name' => 'Navel Oranges',
            'slug' => 'navel-oranges',
            'description' => 'Sweet, juicy and seedless Navel Oranges. Packed with Vitamin C.',
            'short_description' => 'Sweet, juicy navel oranges.',
            'sku' => 'FRU-ORA-006',
            'barcode' => '8801234560067',
            'price' => 280.00,
            'discount_price' => 260.00,
            'unit' => 'kg',
            'stock' => 200,
            'thumbnail' => 'products/orange.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Navel Oranges - Fresh Fruits',
            'meta_description' => 'Juicy sweet navel oranges.'
        ],
        [
            'category_id' => 2, // Vegetables
            'brand_id' => null,
            'name' => 'Fresh Spinach Bunch',
            'slug' => 'fresh-spinach-bunch',
            'description' => 'Organic leafy green spinach. Rich in iron, vitamins, and minerals.',
            'short_description' => 'Leafy green fresh spinach.',
            'sku' => 'VEG-SPI-007',
            'barcode' => '8801234560074',
            'price' => 40.00,
            'discount_price' => 30.00,
            'unit' => 'pcs',
            'stock' => 110,
            'thumbnail' => 'products/spinach.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Fresh Spinach Online',
            'meta_description' => 'Buy organic spinach online.'
        ],
        [
            'category_id' => 10, // Cooking
            'brand_id' => null,
            'name' => 'Miniket Rice Premium (5kg)',
            'slug' => 'miniket-rice-premium-5kg',
            'description' => 'Double-polished premium Miniket rice. High-quality grains with beautiful aroma.',
            'short_description' => 'Premium Miniket rice 5kg pack.',
            'sku' => 'COO-RIC-008',
            'barcode' => '8801234560081',
            'price' => 420.00,
            'discount_price' => 395.00,
            'unit' => 'pcs',
            'stock' => 95,
            'thumbnail' => 'products/miniket.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Miniket Rice 5kg Pack',
            'meta_description' => 'Best miniket rice price in Bangladesh.'
        ],
        [
            'category_id' => 10, // Cooking
            'brand_id' => null,
            'name' => 'Red Lentils (Masoor Dal) 1kg',
            'slug' => 'red-lentils-masoor-dal-1kg',
            'description' => 'High-quality, clean, and protein-packed red lentils. A staple in traditional cooking.',
            'short_description' => 'Premium Masoor Dal 1kg.',
            'sku' => 'COO-DAL-009',
            'barcode' => '8801234560098',
            'price' => 140.00,
            'discount_price' => 130.00,
            'unit' => 'pcs',
            'stock' => 150,
            'thumbnail' => 'products/masoor_dal.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Red Lentils 1kg Online',
            'meta_description' => 'Buy premium masoor dal.'
        ],
        [
            'category_id' => 10, // Cooking
            'brand_id' => null,
            'name' => 'Soybean Oil (1L)',
            'slug' => 'soybean-oil-1l',
            'description' => 'Refined soybean oil containing vitamin A and D. Excellent for daily cooking.',
            'short_description' => 'Refined soybean oil 1 Liter.',
            'sku' => 'COO-OIL-010',
            'barcode' => '8801234560104',
            'price' => 165.00,
            'discount_price' => 160.00,
            'unit' => 'pcs',
            'stock' => 100,
            'thumbnail' => 'products/soybean_oil.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Soybean Oil 1L',
            'meta_description' => 'Pure refined cooking oil.'
        ],
        [
            'category_id' => 6, // Snacks
            'brand_id' => 5, // PepsiCo
            'name' => 'Potato Chips Classic (100g)',
            'slug' => 'potato-chips-classic-100g',
            'description' => 'Thinly sliced potatoes fried to a perfect golden crispiness and salted lightly.',
            'short_description' => 'Crisp potato chips 100g.',
            'sku' => 'SNA-CHI-015',
            'barcode' => '8801234560159',
            'price' => 50.00,
            'discount_price' => 45.00,
            'unit' => 'pcs',
            'stock' => 300,
            'thumbnail' => 'products/chips.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Classic Potato Chips',
            'meta_description' => 'Crispy and tasty snacks.'
        ],
        [
            'category_id' => 5, // Beverages
            'brand_id' => 3, // Unilever
            'name' => 'Black Tea Premium Blend (200g)',
            'slug' => 'black-tea-premium-blend-200g',
            'description' => 'Freshly plucked leaves from the gardens of Sylhet, blended to give a strong color and aroma.',
            'short_description' => 'Strong black tea blend 200g.',
            'sku' => 'BEV-TEA-016',
            'barcode' => '8801234560166',
            'price' => 110.00,
            'discount_price' => 95.00,
            'unit' => 'pcs',
            'stock' => 140,
            'thumbnail' => 'products/tea.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Premium Black Tea',
            'meta_description' => 'Authentic Bangladeshi tea.'
        ],
        [
            'category_id' => 9, // Breakfast
            'brand_id' => 1, // Nestle
            'name' => 'Maggi Masala Noodles (Pack of 8)',
            'slug' => 'maggi-masala-noodles-pack-of-8',
            'description' => 'Your favorite 2-minute Maggi noodles with an authentic blend of spices. Convenient and delicious.',
            'short_description' => 'Instant masala noodles pack of 8.',
            'sku' => 'BRE-NOD-018',
            'barcode' => '8801234560180',
            'price' => 160.00,
            'discount_price' => 150.00,
            'unit' => 'pcs',
            'stock' => 180,
            'thumbnail' => 'products/noodles.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Maggi Masala Noodles',
            'meta_description' => 'Quick breakfast noodles.'
        ],
        [
            'category_id' => 8, // Frozen
            'brand_id' => null,
            'name' => 'Frozen Chicken Nuggets (500g)',
            'slug' => 'frozen-chicken-nuggets-500g',
            'description' => 'Breaded chicken nuggets ready to deep fry or bake. Crispy on the outside, juicy inside.',
            'short_description' => 'Frozen chicken nuggets 500g.',
            'sku' => 'FRO-NUG-020',
            'barcode' => '8801234560203',
            'price' => 320.00,
            'discount_price' => 280.00,
            'unit' => 'pcs',
            'stock' => 70,
            'thumbnail' => 'products/nuggets.jpg',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Frozen Chicken Nuggets 500g',
            'meta_description' => 'Quick snacks for kids.'
        ],
        [
            'category_id' => 13, // Personal Care
            'brand_id' => 3, // Unilever
            'name' => 'Lifebuoy Soap Total (100g)',
            'slug' => 'lifebuoy-soap-total-100g',
            'description' => 'Germ protection bar soap. Keeps your family safe from bacteria and viruses.',
            'short_description' => 'Antibacterial bar soap.',
            'sku' => 'PER-SOA-022',
            'barcode' => '8801234560227',
            'price' => 45.00,
            'discount_price' => 40.00,
            'unit' => 'pcs',
            'stock' => 250,
            'thumbnail' => 'products/lifebuoy.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Lifebuoy Soap 100g',
            'meta_description' => 'Germ protection soap.'
        ],
        [
            'category_id' => 12, // Cleaning
            'brand_id' => 3, // Unilever
            'name' => 'Surf Excel Detergent (1kg)',
            'slug' => 'surf-excel-detergent-1kg',
            'description' => 'Surf Excel Quick Wash removes tough stains easily with the power of bleach. Gentle on clothes.',
            'short_description' => 'Stain removing detergent powder 1kg.',
            'sku' => 'CLE-DET-024',
            'barcode' => '8801234560241',
            'price' => 180.00,
            'discount_price' => 165.00,
            'unit' => 'pcs',
            'stock' => 100,
            'thumbnail' => 'products/surf_excel.jpg',
            'is_featured' => 0,
            'is_active' => 1,
            'meta_title' => 'Surf Excel 1kg Price',
            'meta_description' => 'Laundry detergent powder.'
        ],
        [
            'category_id' => 14, // Baby Care
            'brand_id' => 1, // Nestle
            'name' => 'Nestle Cerelac Rice (400g)',
            'slug' => 'nestle-cerelac-rice-400g',
            'description' => 'Nestle Cerelac infant cereal with milk and rice, enriched with iron and essential nutrients for babies from 6 months.',
            'short_description' => 'Infant cereal rice flavor.',
            'sku' => 'BAB-CER-026',
            'barcode' => '8801234560265',
            'price' => 380.00,
            'discount_price' => 350.00,
            'unit' => 'pcs',
            'stock' => 50,
            'thumbnail' => 'products/cerelac.png',
            'is_featured' => 1,
            'is_active' => 1,
            'meta_title' => 'Nestle Cerelac Rice 400g',
            'meta_description' => 'Infant cereal baby food.'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO products (category_id, brand_id, name, slug, description, short_description, sku, barcode, price, discount_price, unit, stock, thumbnail, is_featured, is_active, meta_title, meta_description)
        VALUES (:category_id, :brand_id, :name, :slug, :description, :short_description, :sku, :barcode, :price, :discount_price, :unit, :stock, :thumbnail, :is_featured, :is_active, :meta_title, :meta_description)
    ");

    foreach ($products as $prod) {
        $stmt->execute($prod);
    }
    echo "Products seeded successfully!\n";

    // Seed some reviews
    echo "Seeding reviews...\n";
    $product_ids = $pdo->query("SELECT id FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $review_stmt = $pdo->prepare("
        INSERT INTO product_reviews (product_id, user_id, rating, review, is_approved)
        VALUES (:product_id, :user_id, :rating, :review, 1)
    ");

    foreach ($product_ids as $pid) {
        // user_id = 1 is the Admin user
        $review_stmt->execute([
            'product_id' => $pid,
            'user_id' => 1,
            'rating' => rand(4, 5),
            'review' => 'Excellent quality product! Very fresh and fast delivery. Highly recommended.'
        ]);
    }
    echo "Reviews seeded successfully!\n";

    // Seed homepage banners in settings
    echo "Seeding homepage banners...\n";
    $banners = [
        [
            'title' => 'Fresh Organic Vegetables',
            'subtitle' => 'Get up to 30% Off on Daily Essentials',
            'button_text' => 'Shop Now',
            'button_link' => 'products.php?category=vegetables',
            'image' => 'banners/banner1.png'
        ],
        [
            'title' => 'Premium Dairy & Eggs',
            'subtitle' => 'Farm fresh milk and butter at your door',
            'button_text' => 'Explore Deals',
            'button_link' => 'products.php?category=dairy',
            'image' => 'banners/banner2.png'
        ],
        [
            'title' => 'Fresh Fruits & Juices',
            'subtitle' => '100% natural, sweet and juicy fruits',
            'button_text' => 'View Collection',
            'button_link' => 'products.php?category=fruits',
            'image' => 'banners/banner3.png'
        ]
    ];

    $banners_json = json_encode($banners, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // Check if the setting exists
    $exists = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = 'homepage_banners'");
    $exists->execute();
    if ($exists->fetchColumn() > 0) {
        $update = $pdo->prepare("UPDATE settings SET value = :val WHERE key_name = 'homepage_banners'");
        $update->execute(['val' => $banners_json]);
    } else {
        $insert = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('homepage_banners', :val)");
        $insert->execute(['val' => $banners_json]);
    }
    echo "Homepage banners seeded successfully!\n";

    $pdo->commit();
    echo "Seeding completed successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error during seeding: " . $e->getMessage() . "\n";
}
