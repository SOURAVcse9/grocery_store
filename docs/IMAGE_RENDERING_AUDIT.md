# Image Rendering Audit

## Summary

The image system uses a centralized PHP helper (`image_url()`) and an optional Cloudinary/media pipeline (`MediaService`). Product cards should resolve image URLs from product fields such as `thumbnail` and `image_public_id` before falling back to a safe placeholder.

The root cause of inconsistent `"No Image"` or broken product card images is not a single visual bug. It is a data + rendering mismatch:

- some products have empty or invalid `thumbnail` values,
- some storefront components render raw `product.image_url` with no guard,
- some legacy product rows use a relative or missing upload path,
- when the backend returns an invalid path, the browser shows a broken image icon or a blank container area because the frontend never normalizes the value.

The actual fix is to normalize the product image at the frontend boundary: resolve a valid URL, reject empty/invalid values, and use a consistent fallback data URI instead of letting broken URLs reach the browser.

## Current image architecture

- Product data is returned from the backend API and includes `thumbnail`, `image_url`, and in some places `image_public_id`.
- `public/includes/helpers.php` contains `image_url()` which resolves:
  - absolute HTTP/HTTPS URLs,
  - Cloudinary public IDs,
  - uploads under `public/uploads`,
  - category/brand/product/storage fallback folders,
  - a final placeholder path.
- `public/includes/MediaService.php` contains `MediaService::getUrl()` and `renderResponsiveImage()` for optimized Cloudinary/local image URLs.
- Product cards in the Next.js frontend render `product.image_url` directly in the `img` tag without sanitation or fallback logic.

## Image source flow

1. Product record is read from MySQL.
2. PHP resolves or stores the image in fields such as `thumbnail`, `image`, `logo`, or `image_public_id`.
3. `image_url()` normalizes the path to a public URL.
4. API responses expose the resolved `image_url` value.
5. Next.js product cards render the raw `image_url` string.
6. If the value is empty, stale, or relative to a missing file, the browser renders broken imagery.

## Database field used

The active product image field in this project is the product `thumbnail` column, plus optional `image_public_id` for Cloudinary-backed records.

Relevant implementation patterns:

- `public/includes/helpers.php`
- `public/includes/MediaService.php`
- `public/api/products.php`
- `database/grocery_store.sql`

## URL/path generation

The PHP helper first checks:

- absolute URL,
- Cloudinary public ID,
- `uploads/...` file existence,
- category-specific upload folder,
- storage folder,
- asset images folder,
- final placeholder.

This is the safe source of truth for server-rendered storefront pages.

## Broken image causes

Common causes seen in the codebase:

- Product records where `thumbnail` is null/blank.
- URL strings pointing to deleted or missing files.
- A frontend component that renders `product.image_url` directly without onerror handling.
- Mixed local/Cloudinary URL conventions when one product is Cloudinary-backed and another is uploaded locally.
- Legacy paths that do not match the current `public/uploads/...` structure.

The frontend fix ensures that broken or empty values resolve to a generated fallback image instead of a broken browser icon.

## Affected components

- `frontend/components/ProductCard.tsx`
- `frontend/app/products/[slug]/page.tsx`
- any product-card-like renderers that trust `image_url` blindly

## Proposed fix

- Centralize image normalization in `frontend/lib/image.ts`.
- Resolve product image to a safe path before the JSX `img` tag is rendered.
- Keep object-fit behavior as `contain` for package/grocery imagery.
- Add `loading`, `decoding`, `width`, and `height` attributes.
- Add a defensive `onError` fallback that does not loop forever.
- Keep a professional grocery-style fallback SVG rather than hiding the image area.

## Files modified

- `frontend/components/ProductCard.tsx`
- `frontend/app/products/[slug]/page.tsx`
- `frontend/lib/image.ts`

## Database changes

No schema change was required for this issue. The project already had the required image fields and helpers. The fix is a frontend normalization and fallback update, not a migration.

## Validation

The full Next.js production build is currently blocked by unrelated missing imports in the frontend (`@/lib/api/addresses`), which are not caused by the image fix. The image-related changes were targeted and consistent with the project’s existing architecture.

## Category circle rendering follow-up

The category-card image URL was being generated with Cloudinary `w_200,h_200,c_fill`. That transformation crops the source to a square before the browser applies CSS, so `object-fit: contain` cannot recover the cropped content. The category image also relied on fixed dimensions without an explicit aspect ratio.

The category renderer now requests images bounded to 200×200 with Cloudinary `c_limit`, preserving their original aspect ratio, and uses a fixed 80px square flex item with a 1:1 ratio, circular clipping, and centered `object-fit: contain`. Missing and failed images fall back to the category placeholder within the same wrapper. These styles are scoped to category cards and do not change product, avatar, banner, or logo image behavior.

Files changed for this follow-up:

- `public/components/category-card.php`
- `public/assets/css/home.css`

PHP syntax and whitespace checks passed. Browser verification could not be performed because the local Categories page was unavailable at `http://localhost/grocery-store/public/categories.php` (connection refused).
