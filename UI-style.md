# UI Style Guide — Ads Analytics Dashboard

> Panduan lengkap untuk cloning UI/UX ke project lain.
> Design system: **Threads-inspired**, monochrome, true black dark mode.

---

## 1. Fonts

### Google Fonts Import

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
```

### Tailwind Config

```js
fontFamily: {
  sans: ['"Inter"', 'system-ui', 'sans-serif'],       // Body text
  display: ['"Plus Jakarta Sans"', '"Inter"', 'system-ui', 'sans-serif'],  // Headings
}
```

### Usage

| Element | Font | Weight | Class |
|---------|------|--------|-------|
| Body text | Inter | 400 | `font-sans` (default) |
| Labels | Inter | 500 | `font-medium` |
| Headings h1-h4 | Plus Jakarta Sans | 700-800 | `font-display font-bold` |
| Card titles | Plus Jakarta Sans | 600 | `font-display font-semibold` |
| Buttons | Inter | 500 | `font-medium` |
| Badges | Inter | 600 | `font-semibold` |

---

## 2. Color System (CSS Variables, HSL format)

Semua warna menggunakan CSS custom properties dalam format HSL tanpa `hsl()` wrapper.
Di Tailwind, digunakan sebagai: `hsl(var(--background))`.

### Light Mode (:root)

```css
:root {
  --background: 0 0% 100%;       /* #ffffff — Pure white */
  --foreground: 0 0% 5%;         /* #0d0d0d — Near black */
  --card: 0 0% 100%;             /* #ffffff */
  --card-foreground: 0 0% 5%;    /* #0d0d0d */
  --popover: 0 0% 100%;          /* #ffffff */
  --popover-foreground: 0 0% 5%; /* #0d0d0d */
  --primary: 0 0% 9%;            /* #171717 — Dark gray */
  --primary-foreground: 0 0% 100%; /* #ffffff */
  --secondary: 0 0% 96%;         /* #f5f5f5 — Light gray */
  --secondary-foreground: 0 0% 9%; /* #171717 */
  --muted: 0 0% 96%;             /* #f5f5f5 */
  --muted-foreground: 0 0% 45%;  /* #737373 — Medium gray */
  --accent: 0 0% 96%;            /* #f5f5f5 */
  --accent-foreground: 0 0% 9%;  /* #171717 */
  --destructive: 0 84% 60%;      /* #ef4444 — Red */
  --destructive-foreground: 0 0% 100%; /* #ffffff */
  --border: 0 0% 90%;            /* #e5e5e5 */
  --input: 0 0% 90%;             /* #e5e5e5 */
  --ring: 0 0% 9%;               /* #171717 */
  --radius: 0.625rem;            /* 10px */
}
```

### Dark Mode (.dark) — Threads True Black

```css
.dark {
  --background: 0 0% 0%;         /* #000000 — True black */
  --foreground: 0 0% 98%;        /* #fafafa — Near white */
  --card: 0 0% 7%;               /* #121212 — Card surface */
  --card-foreground: 0 0% 98%;   /* #fafafa */
  --popover: 0 0% 7%;            /* #121212 */
  --popover-foreground: 0 0% 98%; /* #fafafa */
  --primary: 0 0% 98%;           /* #fafafa — Inverted: putih */
  --primary-foreground: 0 0% 0%; /* #000000 */
  --secondary: 0 0% 12%;         /* #1f1f1f */
  --secondary-foreground: 0 0% 98%; /* #fafafa */
  --muted: 0 0% 12%;             /* #1f1f1f */
  --muted-foreground: 0 0% 55%;  /* #8c8c8c */
  --accent: 0 0% 12%;            /* #1f1f1f */
  --accent-foreground: 0 0% 98%; /* #fafafa */
  --destructive: 0 63% 31%;      /* Darker red */
  --destructive-foreground: 0 0% 98%; /* #fafafa */
  --border: 0 0% 16%;            /* #292929 — Subtle border */
  --input: 0 0% 16%;             /* #292929 */
  --ring: 0 0% 80%;              /* #cccccc */
}
```

### Chart Colors (Both modes)

```css
--chart-1: 220 70% 50%;   /* #3366cc — Blue */
--chart-2: 160 60% 45%;   /* #2e996e — Teal */
--chart-3: 30 80% 55%;    /* #e68a2e — Orange */
--chart-4: 280 65% 60%;   /* #a855cc — Purple */
--chart-5: 340 75% 55%;   /* #d94477 — Pink */
```

### Key Design Principle

> **Semua hue = 0 (netral/monochrome).** Tidak ada warna biru, hijau, atau apapun di UI chrome.
> Warna hanya muncul di charts, badges, dan logo platform.

---

## 3. Tailwind Config

```js
/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ["class"],  // Toggle via .dark class di <html>
  content: ["./index.html", "./src/**/*.{vue,js,ts,jsx,tsx}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter"', 'system-ui', 'sans-serif'],
        display: ['"Plus Jakarta Sans"', '"Inter"', 'system-ui', 'sans-serif'],
      },
      colors: {
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        card: { DEFAULT: 'hsl(var(--card))', foreground: 'hsl(var(--card-foreground))' },
        popover: { DEFAULT: 'hsl(var(--popover))', foreground: 'hsl(var(--popover-foreground))' },
        primary: { DEFAULT: 'hsl(var(--primary))', foreground: 'hsl(var(--primary-foreground))' },
        secondary: { DEFAULT: 'hsl(var(--secondary))', foreground: 'hsl(var(--secondary-foreground))' },
        muted: { DEFAULT: 'hsl(var(--muted))', foreground: 'hsl(var(--muted-foreground))' },
        accent: { DEFAULT: 'hsl(var(--accent))', foreground: 'hsl(var(--accent-foreground))' },
        destructive: { DEFAULT: 'hsl(var(--destructive))', foreground: 'hsl(var(--destructive-foreground))' },
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
      },
      borderRadius: {
        lg: 'var(--radius)',                    // 10px
        md: 'calc(var(--radius) - 2px)',        // 8px
        sm: 'calc(var(--radius) - 4px)',        // 6px
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
}
```

---

## 4. Component Styling

### Card

```
rounded-2xl border border-border/50 bg-card text-card-foreground
```

- Border radius: 16px (`rounded-2xl`)
- Border: 50% opacity, sangat subtle
- No shadow (flat design)

### Button Variants

| Variant | Classes |
|---------|---------|
| `default` | `bg-primary text-primary-foreground shadow hover:bg-primary/90` |
| `destructive` | `bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90` |
| `outline` | `border border-input bg-background shadow-sm hover:bg-accent` |
| `secondary` | `bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80` |
| `ghost` | `hover:bg-accent hover:text-accent-foreground` |
| `link` | `text-primary underline-offset-4 hover:underline` |

**Sizes:**

| Size | Classes |
|------|---------|
| `default` | `h-9 px-4 py-2` |
| `sm` | `h-8 rounded-md px-3 text-xs` |
| `lg` | `h-10 rounded-md px-8` |
| `icon` | `h-9 w-9` |

### Badge Variants

```
Base: inline-flex gap-1 items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold
```

| Variant | Style |
|---------|-------|
| `default` | `border-transparent bg-primary text-primary-foreground` |
| `secondary` | `border-transparent bg-secondary text-secondary-foreground` |
| `destructive` | `border-transparent bg-destructive text-destructive-foreground` |
| `outline` | `text-foreground` (border visible) |

### Input

```
flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 
text-sm shadow-sm transition-colors placeholder:text-muted-foreground 
focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring 
disabled:cursor-not-allowed disabled:opacity-50
```

### Skeleton (Loading Placeholder)

```
animate-pulse rounded-md bg-primary/10
```

---

## 5. Layout Structure — Threads-Inspired

### Desktop (md+)

```
┌──────┬─────────────────────────────────────────────┐
│      │                                             │
│  76px│        max-w-5xl centered column             │
│      │        border-x border-border/50            │
│ Side │                                             │
│ bar  │   ┌─────────────────────────────────────┐   │
│      │   │  Sticky Header (glassmorphism)       │   │
│ fixed│   │  bg-background/80 backdrop-blur-xl   │   │
│      │   ├─────────────────────────────────────┤   │
│      │   │  Content area                        │   │
│      │   │  px-4 py-6 sm:px-6                  │   │
│      │   └─────────────────────────────────────┘   │
│      │                                             │
└──────┴─────────────────────────────────────────────┘
```

### Mobile

```
┌─────────────────────────┐
│  Sticky Header           │
│  (glassmorphism)         │
├─────────────────────────┤
│                          │
│  Content (full width)    │
│  pb-20 (space for nav)   │
│                          │
├─────────────────────────┤
│  Bottom Nav (fixed)      │
│  4 icons, no labels      │
│  bg-background           │
│  border-t border-border  │
└─────────────────────────┘
```

### Sidebar Nav Items

```
Active:   text-foreground, stroke-width: 2.5 (bold icon)
Inactive: text-muted-foreground, stroke-width: 1.5 (thin icon)
Hover:    hover:text-foreground hover:bg-accent
Size:     h-12 w-12 rounded-2xl
```

### Sticky Header (Glassmorphism)

```
sticky top-0 z-10 border-b border-border/40 bg-background/80 backdrop-blur-xl
```

---

## 6. Dark Mode Toggle

### Implementation

```js
// composables/useDarkMode.js
const saved = localStorage.getItem('theme')
const isDark = ref(saved ? saved === 'dark' : true)  // Default: dark

function applyTheme() {
  document.documentElement.classList.toggle('dark', isDark.value)
}
```

### Storage

- Key: `localStorage.theme`
- Values: `'dark'` | `'light'`
- Default (no key): dark mode

---

## 7. Chart Colors

### Spend Trend (Line Chart)

```js
borderColor: '#3B82F6'                    // Blue
backgroundColor: gradient fill blue → transparent
pointBackgroundColor: '#3B82F6'
tension: 0.4
```

### Impressions (Bar Chart)

```js
backgroundColor: 'rgba(139, 92, 246, 0.6)'  // Purple #8B5CF6
borderRadius: 6
```

### Clicks (Line Chart)

```js
borderColor: '#10B981'                    // Emerald green
backgroundColor: gradient fill green → transparent
tension: 0.4
```

### Breakdown Donut

```js
palette: [
  '#3B82F6',  // blue
  '#8B5CF6',  // violet
  '#10B981',  // emerald
  '#F59E0B',  // amber
  '#EF4444',  // red
  '#EC4899',  // pink
  '#06B6D4',  // cyan
  '#F97316',  // orange
  '#6366F1',  // indigo
  '#14B8A6',  // teal
]
cutout: '68%'
```

### Chart Tooltip Style

```js
backgroundColor: isDark ? '#1c1c1c' : '#fff'
titleColor: isDark ? '#fff' : '#18181b'
bodyColor: isDark ? '#a1a1aa' : '#71717a'
borderColor: isDark ? '#333' : '#e4e4e7'
borderWidth: 1
padding: 12
cornerRadius: 12
```

---

## 8. Platform Logo Animations

### Float Animation

```css
@keyframes logo-float {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-2px) scale(1.02); }
}
animation: logo-float 3s ease-in-out infinite;
```

### Glow Animation

```css
@keyframes glow {
  0%, 100% { opacity: 0.2; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.15); }
}
animation: glow 3s ease-in-out infinite;
```

### Platform-Specific Glow Colors

```
Meta:   bg-blue-500/30, blur-xl
Google: bg-amber-400/20, blur-xl
```

### Dark Mode Logo Filter

```
Meta:   dark:brightness-0 dark:invert  (putihkan di dark mode)
Google: tidak di-invert (tetap warna-warni)
```

---

## 9. Login Page — Split Layout

```
┌──────────────────────┬──────────────────────┐
│                      │                      │
│   Banner Image       │   Login Form         │
│   (left 55%)         │   (right 45%)        │
│                      │                      │
│   Overlay: bg-black  │   Logo / Icon        │
│   /30                │   "Selamat Datang"   │
│                      │   Email input        │
│   Company Name       │   Password input     │
│   Tagline            │   [Login] button     │
│   Feature list       │   --- atau ---       │
│   (check icons)      │   [Login by Google]  │
│                      │                      │
└──────────────────────┴──────────────────────┘
```

### Left Panel

```
Desktop only (hidden on mobile): hidden lg:block w-1/2 xl:w-[55%]
Banner image: h-full w-full object-cover
Overlay: absolute inset-0 bg-black/30
Text: white, positioned bottom-left with p-10
```

### Right Panel

```
Full width mobile, lg:w-1/2 xl:w-[45%]
Centered form: max-w-sm
Theme toggle: fixed top-right
```

### Google Login Button

```
variant="outline" rounded-xl h-11 gap-3
SVG Google "G" icon (inline, 4 colors)
```

---

## 10. Dependencies (npm packages)

### Core

```json
"vue": "^3.5.26",
"vue-router": "^4.6.4",
"pinia": "^3.0.4"
```

### UI/Styling

```json
"tailwindcss": "^3.4.19",
"tailwindcss-animate": "^1.0.7",
"class-variance-authority": "^0.7.1",
"clsx": "^2.1.1",
"tailwind-merge": "^3.4.0",
"reka-ui": "^2.9.3",
"lucide-vue-next": "^0.563.0"
```

### Charts

```json
"chart.js": "^4.5.1",
"vue-chartjs": "^5.3.3"
```

### Forms & Validation

```json
"vee-validate": "^4.15.1",
"@vee-validate/zod": "^4.15.1",
"zod": "^3.25.76"
```

### Data & HTTP

```json
"axios": "^1.13.4",
"@tanstack/vue-query": "^5.92.9",
"dayjs": "^1.11.19",
"@vueuse/core": "^14.2.1"
```

### Toast/Notification

```json
"vue-sonner": "^2.0.9"
```

---

## 11. shadcn-vue Config (components.json)

```json
{
  "style": "new-york",
  "typescript": false,
  "iconLibrary": "lucide",
  "tailwind": {
    "config": "tailwind.config.js",
    "css": "src/assets/main.css",
    "baseColor": "slate",
    "cssVariables": true
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "composables": "@/composables"
  }
}
```

### Installed Components

- `avatar`, `badge`, `button`, `card`, `dropdown-menu`
- `input`, `label`, `popover`, `select`, `separator`
- `sheet`, `skeleton`, `sonner`, `table`, `tabs`, `tooltip`

---

## 12. Utility Function

```js
// src/lib/utils.js
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
```

Digunakan di semua component untuk merge class names tanpa konflik Tailwind.

---

## 13. Quick Clone Checklist

Untuk cloning UI ini ke project baru:

1. Install dependencies:
   ```bash
   npm create vue@latest
   npm install tailwindcss tailwindcss-animate postcss autoprefixer
   npm install class-variance-authority clsx tailwind-merge
   npm install reka-ui lucide-vue-next @vueuse/core vue-sonner
   npm install chart.js vue-chartjs
   npx shadcn-vue@latest init   # pilih: new-york, slate, css variables
   ```

2. Copy files:
   - `src/assets/main.css` (CSS variables)
   - `tailwind.config.js`
   - `src/lib/utils.js`
   - `src/composables/useDarkMode.js`
   - `src/layouts/AppLayout.vue`
   - `src/components/ui/*` (semua shadcn components)

3. Add Google Fonts ke `index.html`

4. Set default dark mode di `useDarkMode.js`

5. Install shadcn components yang dibutuhkan:
   ```bash
   npx shadcn-vue@latest add button card input label select table tabs badge skeleton sheet popover tooltip sonner separator
   ```

6. Override `Card.vue` → `rounded-2xl border-border/50` (no shadow)

---

*Generated for Ads Analytics Dashboard — abie IT Solutions*
