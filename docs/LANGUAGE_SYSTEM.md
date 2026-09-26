# Multi-Language (Localization) System Specification

## 1. Overview & Localization Architecture

GroCo Grocery Store includes native bilingual support for **English (`en`)** and **Bengali (`bn`)** (Bangla), catering directly to the domestic Bangladeshi e-commerce demographic.

Localization covers:
1. Static UI labels and navigation items.
2. Form fields, placeholders, and error messages.
3. System notifications and email templates.
4. Currency formatting and date localization.

---

## 2. Directory & File Organization

- **`public/lang/en.php`**: English language key-value dictionary array.
- **`public/lang/bn.php`**: Bengali (Bangla) language key-value dictionary array.
- **`public/includes/language.php`**: Locale resolver, session persistence, and global translation helper function `t()`.

---

## 3. Translation Helper Engine (`public/includes/language.php`)

### 3.1 Language Resolution Algorithm
1. Check `$_GET['lang']` query parameter (e.g. `?lang=bn` or `?lang=en`).
2. If set and valid (`in_array($lang, ['en', 'bn'])`), save to `$_SESSION['site_lang']` and set persistent cookie `groco_lang` (valid for 30 days).
3. If not in query, check `$_SESSION['site_lang']`.
4. If not in session, check `$_COOKIE['groco_lang']`.
5. Fallback to default setting in database `settings.default_language` (defaults to `en`).

### 3.2 Global Helper Function `t()`
```php
function t(string $key, array $replacements = []): string {
    global $translations;
    $text = $translations[$key] ?? $key;
    
    if (!empty($replacements)) {
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace(':' . $placeholder, (string)$value, $text);
        }
    }
    return $text;
}
```

---

## 4. Key Dictionary Comparison Matrix

| Translation Key | English (`en.php`) | Bengali (`bn.php`) |
| :--- | :--- | :--- |
| `nav_home` | Home | হোম |
| `nav_categories` | Categories | ক্যাটাগরি |
| `nav_offers` | Offers & Deals | অফার এবং ডিল |
| `nav_contact` | Contact Us | যোগাযোগ |
| `btn_add_to_cart` | Add to Cart | কার্টে যোগ করুন |
| `btn_buy_now` | Buy Now | এখনই কিনুন |
| `btn_checkout` | Proceed to Checkout | চেকআউট করুন |
| `cart_empty` | Your cart is currently empty | আপনার কার্ট বর্তমানে খালি আছে |
| `price_currency` | ৳ | ৳ |
| `stock_in_stock` | In Stock | স্টকে আছে |
| `stock_out_of_stock`| Out of Stock | স্টক শেষ |
| `search_placeholder`| Search fresh vegetables, fruits, groceries... | তাজা শাকসবজি, ফলমূল ও মুদি সামগ্রী খুঁজুন... |
| `order_success_msg` | Thank you! Your order has been placed successfully. | ধন্যবাদ! আপনার অর্ডারটি সফলভাবে সম্পন্ন হয়েছে। |
| `coupon_invalid` | Invalid or expired coupon code. | কুপন কোডটি সঠিক নয় বা মেয়াদ শেষ হয়ে গেছে। |

---

## 5. Number and Date Localization (Bangla Numerals)

The system includes a numeral converter converting standard Arabic digits (`0-9`) to Bengali numerals (`০-৯`) when Bengali locale is active:

```php
function format_bn_number($number): string {
    $en_digits = ['0','1','2','3','4','5','6','7','8','9'];
    $bn_digits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($en_digits, $bn_digits, (string)$number);
}
```

---

## 6. Guidelines for Modernized Stack (i18n)
1. **Frontend i18n**: Export translations as JSON for client-side frameworks (e.g. `next-intl`, `react-i18next`, or `vue-i18n`).
2. **Database Multilingual Fields**: For full internationalization of dynamic catalog content, migrate product and category tables to support multilingual columns (`name_en`, `name_bn`, `desc_en`, `desc_bn`) or a dedicated `translations` table.
3. **RTL Support**: Future-proofing for Arabic or Urdu with automated `dir="ltr"` / `dir="rtl"` CSS attribute switching.
