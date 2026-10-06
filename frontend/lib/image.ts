const PRODUCT_FALLBACK_IMAGE = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="640" viewBox="0 0 640 640">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#edf5ef"/>
      <stop offset="100%" stop-color="#eef3f8"/>
    </linearGradient>
  </defs>
  <rect width="640" height="640" rx="32" fill="url(#g)"/>
  <rect x="150" y="120" width="340" height="360" rx="28" fill="#ffffff" stroke="#dbe9dc" stroke-width="8"/>
  <path d="M220 280c0-38 32-70 72-70h56c40 0 72 32 72 70v22h38c38 0 68 30 68 68v64c0 38-30 68-68 68H182c-38 0-68-30-68-68v-64c0-38 30-68 68-68h38v-22zm94-44v66h48v-66c0-18-14-32-32-32s-32 14-32 32z" fill="#dfeadf"/>
  <circle cx="320" cy="310" r="18" fill="#b7d2b2"/>
  <path d="M240 420h160" stroke="#b7d2b2" stroke-width="16" stroke-linecap="round"/>
</svg>
`)}`;

export function resolveProductImage(value: string | null | undefined): string {
  if (!value) return PRODUCT_FALLBACK_IMAGE;

  if (/^https?:\/\//i.test(value) || /^data:/i.test(value) || value.startsWith('/')) {
    return value;
  }

  if (value.startsWith('uploads/') || value.startsWith('images/')) {
    return `/${value}`;
  }

  return PRODUCT_FALLBACK_IMAGE;
}

export function getProductImageFallback(): string {
  return PRODUCT_FALLBACK_IMAGE;
}
