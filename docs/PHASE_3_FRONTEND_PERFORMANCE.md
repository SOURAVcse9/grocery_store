# GroCo Grocery Store — Frontend Performance & Core Web Vitals Audit

## 1. Core Web Vitals Targets & Measured Architecture

| Metric | Google "Good" Threshold | GroCo Target | GroCo Architectural Mechanism |
| :--- | :--- | :--- | :--- |
| **LCP** (Largest Contentful Paint) | $\le 2.5\text{s}$ | **$\le 0.9\text{s}$** | Next.js ISR pre-rendering + Cloudinary WebP/AVIF auto-format + Next/Image priority loading |
| **FID / INP** (Interaction to Next Paint) | $\le 200\text{ms}$ | **$\le 50\text{ms}$** | Minimal client bundle, debounced search (250ms), pure CSS micro-transitions |
| **CLS** (Cumulative Layout Shift) | $\le 0.1$ | **$\le 0.02$** | Explicit image aspect ratios (`aspect-square`), skeleton loaders, font pre-loading |
| **TTFB** (Time to First Byte) | $\le 800\text{ms}$ | **$\le 120\text{ms}$** | Edge CDN caching for static assets + ISR cached HTML responses |

---

## 2. Key Optimization Strategies

### A. Responsive Image Optimization
- All images are rendered using responsive `srcset` and CSS `sizes` attributes with `decoding="async"`.
- Cloudinary auto-formatting (`f_auto,q_auto`) converts raw raster assets to modern WebP / AVIF formats, reducing bandwidth consumption by up to 70%.

### B. Font & Asset Delivery
- Google Fonts (`Inter`) loaded with `font-display: swap` and preconnect hints to eliminate render-blocking typography delay.
- Modern SVG Lucide icon tree-shaking ensures only referenced icons are included in the JavaScript bundle.

### C. Bundle Optimization
- Tree-shaken utility modules (`lib/utils.ts`).
- Zero heavy dependencies (e.g. replaced large UI component libraries with lightweight Tailwind CSS utilities).
