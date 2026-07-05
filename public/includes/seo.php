<?php
/**
 * ==========================================================================
 * public/includes/seo.php — SEO Optimization Helpers
 * ==========================================================================
 * Defines title, description, canonical url builders, Open Graph tag compilers,
 * and JSON-LD schema generators.
 * ==========================================================================
 */

declare(strict_types=1);

/**
 * get_json_ld_schema()
 *
 * Compiles specific schema formats into valid JSON-LD script blocks.
 */
function get_json_ld_schema(string $type, array $data = []): string
{
    $schema = [
        '@context' => 'https://schema.org'
    ];

    if ($type === 'organization') {
        $schema = array_merge($schema, [
            '@type' => 'Organization',
            'name'  => site_name(),
            'url'   => site_url(),
            'logo'  => asset('images/ui/logo.png'),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => CONTACT_PHONE,
                'contactType' => 'customer service',
                'email' => CONTACT_EMAIL
            ],
            'sameAs' => [
                'https://www.facebook.com/grocerystore',
                'https://www.twitter.com/grocerystore',
                'https://www.instagram.com/grocerystore'
            ]
        ]);
    } 
    
    elseif ($type === 'local_business') {
        $schema = array_merge($schema, [
            '@type' => 'GroceryStore',
            'name'  => site_name(),
            'image' => asset('images/ui/storefront.jpg'),
            'telephone' => CONTACT_PHONE,
            'email' => CONTACT_EMAIL,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => CONTACT_ADDRESS,
                'addressLocality' => 'Dhaka',
                'addressRegion' => 'Dhaka Division',
                'postalCode' => '1212',
                'addressCountry' => 'BD'
            ],
            'priceRange' => '$$',
            'openingHoursSpecification' => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => [
                    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
                ],
                'opens' => '08:00',
                'closes' => '22:00'
            ]
        ]);
    } 
    
    elseif ($type === 'product' && !empty($data['product'])) {
        $prod = $data['product'];
        $price = (float) ($prod['discount_price'] ?? $prod['price']);
        $rating = isset($prod['avg_rating']) ? (float)$prod['avg_rating'] : 0.0;
        $reviewCount = isset($prod['review_count']) ? (int)$prod['review_count'] : 0;

        $schema = array_merge($schema, [
            '@type' => 'Product',
            'name'  => $prod['name'],
            'image' => image_url($prod['thumbnail'], 'products'),
            'description' => $prod['description'] ?? '',
            'sku'   => $prod['sku'],
            'brand' => [
                '@type' => 'Brand',
                'name'  => $prod['brand_name'] ?? 'Generic'
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => site_url('product.php?slug=' . $prod['slug']),
                'priceCurrency' => 'BDT',
                'price' => $price,
                'priceValidUntil' => date('Y-12-31'),
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => (int)$prod['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => site_name()
                ]
            ]
        ]);

        if ($reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $reviewCount,
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }
    } 
    
    elseif ($type === 'breadcrumbs' && !empty($data['breadcrumbs'])) {
        $items = [];
        $position = 1;

        // Start with Home
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => site_url()
        ];

        foreach ($data['breadcrumbs'] as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['title'],
                'item' => !empty($crumb['link']) ? site_url($crumb['link']) : site_url()
            ];
        }

        $schema = array_merge($schema, [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ]);
    }

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}
