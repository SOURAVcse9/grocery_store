<?php
/**
 * ==========================================================================
 * public/includes/seo.php — Production SEO Engine & Schema Generator
 * ==========================================================================
 * Provides dynamic title/meta generation, canonical URL normalization,
 * Open Graph / Twitter Cards compiler, robots indexing directive resolver,
 * and Schema.org JSON-LD structured data generators.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * Resolve the canonical base URL (prioritizes APP_URL environment variable).
 */
function get_canonical_base_url(): string
{
    $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
    if (!empty($envUrl)) {
        return rtrim((string)$envUrl, '/');
    }
    if (defined('APP_URL') && !empty(APP_URL)) {
        return rtrim((string)APP_URL, '/');
    }
    if (defined('BASE_URL') && !empty(BASE_URL)) {
        return rtrim((string)BASE_URL, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'groco.site.je';
    return $scheme . '://' . $host;
}

/**
 * Generate a clean, canonical URL for any storefront route.
 * Automatically strips tracking, session, filter, and pagination parameters unless allowed.
 */
function build_canonical_url(?string $path = null, array $params = []): string
{
    $baseUrl = get_canonical_base_url();

    if ($path === null) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        $cleanPath = ($script === 'index.php' || $script === '') ? '' : $script;
    } else {
        $cleanPath = ltrim($path, '/');
        // Normalize public/ prefix if present
        $cleanPath = preg_replace('#^public/#i', '', $cleanPath);
        if ($cleanPath === 'index.php') {
            $cleanPath = '';
        }
    }

    $url = empty($cleanPath) ? $baseUrl . '/' : $baseUrl . '/' . $cleanPath;

    if (!empty($params)) {
        $allowed = [];
        // Only keep valid indexing query parameters (e.g. slug, category, brand, id)
        foreach (['slug', 'category', 'brand', 'id'] as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $allowed[$k] = $params[$k];
            }
        }
        if (!empty($allowed)) {
            $url .= '?' . http_build_query($allowed);
        }
    }

    return $url;
}

/**
 * Resolves the appropriate robots meta tag for the current request.
 */
function get_meta_robots(?string $pageRobots = null): string
{
    if ($pageRobots !== null && $pageRobots !== '') {
        return $pageRobots;
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $_SERVER['QUERY_STRING'] ?? '';

    // Non-indexable utility, auth, and checkout pages
    $privatePages = [
        'login.php', 'register.php', 'forgot-password.php', 'reset-password.php',
        'cart.php', 'checkout.php', 'thank-you.php', 'account.php', 'profile.php',
        'orders.php', 'order-details.php', 'addresses.php', 'wishlist.php',
        'compare.php', 'notifications.php', 'activate.php', 'license_status.php',
        'offline.php', 'update_password.php', 'update_profile.php'
    ];

    if (in_array($script, $privatePages, true)) {
        return 'noindex, nofollow';
    }

    // Internal search results page or search query string: noindex, follow
    if ($script === 'search.php' || !empty($_GET['q']) || !empty($_GET['search'])) {
        return 'noindex, follow';
    }

    // Admin pages
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/')) {
        return 'noindex, nofollow';
    }

    // Standard public catalog & static pages
    return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
}

/**
 * Format clean, human-readable, SEO-friendly meta description.
 */
function format_meta_description(?string $text, int $maxChars = 160): string
{
    if (empty($text)) {
        return 'Order fresh groceries, fruits, vegetables, dairy, and daily essentials online at GroCo Grocery Store with fast home delivery in Bangladesh.';
    }

    $clean = strip_tags($text);
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = trim($clean);

    if (mb_strlen($clean) <= $maxChars) {
        return $clean;
    }

    $truncated = mb_substr($clean, 0, $maxChars);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false && $lastSpace > 100) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return rtrim($truncated, '.,;:-') . '...';
}

/**
 * Formats a standardized page title.
 */
function format_seo_title(string $title, string $fallback = ''): string
{
    $siteName = site_name();
    $title = trim($title);

    if (empty($title)) {
        return !empty($fallback) ? $fallback . ' | ' . $siteName : $siteName . ' | Online Grocery Shopping in Bangladesh';
    }

    if (str_contains($title, $siteName)) {
        return $title;
    }

    return $title . ' | ' . $siteName;
}

/**
 * Compiles specific schema formats into valid JSON-LD script blocks.
 */
function get_json_ld_schema(string $type, array $data = []): string
{
    $schema = [
        '@context' => 'https://schema.org'
    ];

    $baseUrl = get_canonical_base_url();

    if ($type === 'organization') {
        $sameAs = [];
        $fb = get_setting('site_facebook');
        $tw = get_setting('site_twitter');
        $ig = get_setting('site_instagram');
        if (!empty($fb)) $sameAs[] = $fb;
        if (!empty($tw)) $sameAs[] = $tw;
        if (!empty($ig)) $sameAs[] = $ig;

        $schema = array_merge($schema, [
            '@type' => 'Organization',
            '@id'   => $baseUrl . '/#organization',
            'name'  => site_name(),
            'url'   => $baseUrl . '/',
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => image_url('ui/logo.png'),
                'caption' => site_name() . ' Logo'
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => defined('CONTACT_PHONE') ? CONTACT_PHONE : '+880 1712 345678',
                'contactType' => 'customer service',
                'email' => defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'support@groco.com.bd',
                'areaServed' => 'BD',
                'availableLanguage' => ['English', 'Bengali']
            ]
        ]);

        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }
    } 
    
    elseif ($type === 'local_business' || $type === 'store') {
        $schema = array_merge($schema, [
            '@type' => 'GroceryStore',
            '@id'   => $baseUrl . '/#store',
            'name'  => site_name(),
            'url'   => $baseUrl . '/',
            'image' => image_url('ui/logo.png'),
            'telephone' => defined('CONTACT_PHONE') ? CONTACT_PHONE : '+880 1712 345678',
            'email' => defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'support@groco.com.bd',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => defined('CONTACT_ADDRESS') ? CONTACT_ADDRESS : 'Flat 4A, House 12, Road 4, Banani',
                'addressLocality' => 'Dhaka',
                'addressRegion' => 'Dhaka Division',
                'postalCode' => '1212',
                'addressCountry' => 'BD'
            ],
            'priceRange' => '৳৳',
            'currenciesAccepted' => 'BDT',
            'paymentAccepted' => 'Cash, bKash, Nagad, Credit Card, Debit Card'
        ]);
    } 

    elseif ($type === 'website') {
        $schema = array_merge($schema, [
            '@type' => 'WebSite',
            '@id'   => $baseUrl . '/#website',
            'url'   => $baseUrl . '/',
            'name'  => site_name(),
            'description' => 'Online grocery shopping in Bangladesh with fast home delivery.',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/products.php?q={search_term_string}'
                ],
                'query-input' => 'required name=search_term_string'
            ]
        ]);
    }
    
    elseif ($type === 'product' && !empty($data['product'])) {
        $prod = $data['product'];
        $price = (float) ($prod['discount_price'] ?? $prod['price']);
        $rating = isset($prod['avg_rating']) ? (float)$prod['avg_rating'] : 0.0;
        $reviewCount = isset($prod['review_count']) ? (int)$prod['review_count'] : 0;
        $inStock = (int)($prod['stock'] ?? 0) > 0;
        $productUrl = build_canonical_url('product.php', ['slug' => $prod['slug'] ?? '']);

        // Images array
        $images = [];
        if (!empty($prod['thumbnail'])) {
            $images[] = image_url($prod['thumbnail'], 'products');
        }
        if (!empty($data['gallery']) && is_array($data['gallery'])) {
            foreach ($data['gallery'] as $gImg) {
                $gUrl = image_url($gImg, 'products');
                if (!in_array($gUrl, $images, true)) {
                    $images[] = $gUrl;
                }
            }
        }
        if (empty($images)) {
            $images[] = image_url('ui/placeholder.png');
        }

        $schema = array_merge($schema, [
            '@type' => 'Product',
            '@id'   => $productUrl . '#product',
            'name'  => $prod['name'],
            'image' => count($images) === 1 ? $images[0] : $images,
            'description' => format_meta_description($prod['description'] ?? ($prod['short_description'] ?? $prod['name'])),
            'sku'   => !empty($prod['sku']) ? (string)$prod['sku'] : ('GROCO-PRD-' . $prod['id']),
            'brand' => [
                '@type' => 'Brand',
                'name'  => !empty($prod['brand_name']) ? $prod['brand_name'] : site_name()
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $productUrl,
                'priceCurrency' => 'BDT',
                'price' => number_format($price, 2, '.', ''),
                'priceValidUntil' => date('Y-12-31', strtotime('+1 year')),
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => site_name(),
                    'url'  => $baseUrl . '/'
                ]
            ]
        ]);

        if (!empty($prod['category_name'])) {
            $schema['category'] = $prod['category_name'];
        }

        if (!empty($prod['barcode'])) {
            $schema['gtin13'] = $prod['barcode'];
        }

        // Only emit aggregateRating when real approved ratings exist
        if ($reviewCount > 0 && $rating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($rating, 1, '.', ''),
                'reviewCount' => $reviewCount,
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }

        // Emit real customer reviews if passed
        if (!empty($data['reviews']) && is_array($data['reviews'])) {
            $reviewItems = [];
            foreach (array_slice($data['reviews'], 0, 5) as $rev) {
                if (!empty($rev['review_comment']) && (int)($rev['rating'] ?? 0) > 0) {
                    $reviewItems[] = [
                        '@type' => 'Review',
                        'author' => [
                            '@type' => 'Person',
                            'name' => !empty($rev['user_name']) ? $rev['user_name'] : 'Verified Customer'
                        ],
                        'datePublished' => !empty($rev['created_at']) ? date('Y-m-d', strtotime((string)$rev['created_at'])) : date('Y-m-d'),
                        'reviewBody' => strip_tags((string)$rev['review_comment']),
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => (int)$rev['rating'],
                            'bestRating' => '5',
                            'worstRating' => '1'
                        ]
                    ];
                }
            }
            if (!empty($reviewItems)) {
                $schema['review'] = $reviewItems;
            }
        }
    } 
    
    elseif ($type === 'breadcrumbs' && !empty($data['breadcrumbs'])) {
        $items = [];
        $position = 1;

        // Position 1: Home
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => $baseUrl . '/'
        ];

        foreach ($data['breadcrumbs'] as $crumb) {
            $title = $crumb['title'] ?? '';
            $link  = $crumb['link'] ?? '';

            if (empty($title)) continue;

            $itemUrl = !empty($link) ? build_canonical_url($link) : $baseUrl . '/' . basename($_SERVER['SCRIPT_NAME'] ?? '');

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $title,
                'item' => $itemUrl
            ];
        }

        $schema = array_merge($schema, [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ]);
    }

    elseif ($type === 'item_list' && !empty($data['items'])) {
        $items = [];
        $position = 1;
        foreach ($data['items'] as $item) {
            $pUrl = build_canonical_url('product.php', ['slug' => $item['slug'] ?? '']);
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => $pUrl,
                'name' => $item['name'] ?? ''
            ];
        }

        $schema = array_merge($schema, [
            '@type' => 'ItemList',
            'itemListElement' => $items
        ]);
    }

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}
