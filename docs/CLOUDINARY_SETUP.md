# GroCo Grocery Store — Cloudinary CDN & Image Optimization Guide

This guide details the integration, configuration, and migration procedures for the **Cloudinary High-Performance Image Optimization and CDN system** in GroCo Grocery Store.

---

## 1. Cloudinary Setup & Credentials

### Step 1: Create Account
1. Sign up for a free or paid plan at [Cloudinary](https://cloudinary.com).
2. Go to your **Cloudinary Dashboard**.
3. Locate your **Product Environment Credentials**:
   - **Cloud Name** (e.g. `groco-prod`)
   - **API Key** (e.g. `123456789012345`)
   - **API Secret** (e.g. `abcdefghijklmnopqrstuv_wxyz`)

### Step 2: Configure `.env`
Add your credentials to your server's root `.env` file:
```ini
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

> [!CAUTION]
> Cloudinary credentials are strictly server-side. Never expose the API Key or API Secret to the frontend, browser HTML, JavaScript, or public Git repositories.

---

## 2. Directory & Public ID Structure

All uploaded assets are neatly categorized into dedicated folders on Cloudinary:

| Asset Type | Cloudinary Folder Path | Example Public ID |
| :--- | :--- | :--- |
| **Products** | `groco/products/` | `groco/products/fresh-milk-1-liter` |
| **Categories** | `groco/categories/` | `groco/categories/dairy-products` |
| **Brands** | `groco/brands/` | `groco/brands/pran` |
| **Banners** | `groco/banners/` | `groco/banners/eid-special-sale` |
| **User Avatars** | `groco/users/` | `groco/users/avatar_user_42` |

---

## 3. High-Performance Delivery & Transformations

Images are dynamically delivered with automatic next-gen formatting, adaptive compression, and device pixel ratio adjustments:

- **Format Optimization (`f_auto`)**: Automatically delivers WebP or AVIF to supported browsers (Chrome, Edge, Firefox, Safari).
- **Quality Optimization (`q_auto`)**: Intelligently compresses images to the visual threshold without noticeable artifacts.
- **Pixel Density Scaling (`dpr_auto`)**: Serves sharp images for Retina / high-DPI smartphone displays.

### Standard Breakpoint Helpers (`public/includes/image.php`)

```php
// 1. Standard Product Card & Search (500x500)
$imageUrl = getProductImageUrl($product['thumbnail']);

// 2. Thumbnail / Cart Mini Preview (300x300)
$thumbUrl = getThumbnailUrl($product['thumbnail']);

// 3. Product Gallery Hero (800x800)
$heroUrl = getProductDetailImageUrl($product['thumbnail']);

// 4. Social Share Open Graph Card (1200x630)
$ogUrl = getOgImageUrl($product['thumbnail']);

// 5. Complete Responsive HTML <img> Tag with srcset & Web Vitals attributes
echo render_optimized_product_image(
    $product['thumbnail'],
    $product['name'],
    $width = 300,
    $height = 300,
    ['is_lcp' => false, 'class' => 'product-card-img']
);
```

---

## 4. Batch Image Migration Tool

To migrate existing local product images from `public/uploads/products/` to Cloudinary:

```bash
# Preview what would be uploaded without making changes
php tools/migrate_images_to_cloudinary.php --dry-run

# Run migration and automatically update database image columns
php tools/migrate_images_to_cloudinary.php
```

### Features of the Migration Tool:
- Checks each database record (`products.thumbnail`, `product_images.image_url`).
- Validates local file existence.
- Uploads to Cloudinary with SEO-friendly public IDs derived from the product slug.
- Updates database references atomically.
- Preserves local copies as offline fallback.

---

## 5. Failover & Local Fallback Architecture

If Cloudinary credentials are not configured or the remote API is unreachable:
1. `CloudinaryService::isConfigured()` returns `false`.
2. Uploads gracefully fall back to local disk storage (`public/uploads/{category}/`).
3. Delivery helpers automatically route to local asset URLs via `image_url()`.
4. Missing images display a clean local SVG/PNG placeholder (`images/ui/placeholder.png`).
5. The application remains 100% operational in offline local environments without third-party dependencies.
