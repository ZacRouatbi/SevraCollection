# Sevra Collection — Symfony Frontend

Headless Symfony frontend for Sevra Collection, connecting to a WordPress/WooCommerce REST API backend.

## Stack

| Layer       | Technology                                        |
|-------------|---------------------------------------------------|
| Framework   | Symfony 6.4 (PHP 8.1+)                           |
| Templates   | Twig 3                                            |
| CSS         | Tailwind CSS (CDN) + `public/css/sevra.css`      |
| JS          | Vanilla JS (`public/js/sevra.js`)                 |
| Backend API | WordPress + WooCommerce REST (headless)           |
| Hosting     | OVH Shared Hosting                               |

## Design Tokens

| Name         | Hex       | Usage                         |
|--------------|-----------|-------------------------------|
| Dusty Olive  | `#768064` | Accents, badges, buttons      |
| Linen        | `#F2E5D9` | Page background               |
| Shadow Grey  | `#202030` | Text, dark sections, header   |
| Dusty Mauve  | `#94849B` | Labels, secondary text        |

**Fonts:** Cormorant Garamond (italic display) · DM Sans (body)

---

## Project Structure

```
sevra/
├── config/
│   ├── routes.yaml                  # Route definitions
│   └── packages/
│       ├── framework.yaml
│       └── twig.yaml
├── public/
│   ├── css/sevra.css                # Design tokens + custom CSS
│   ├── js/sevra.js                  # UI interactions (modal, cart, etc.)
│   └── images/                      # Static images (logo, hero, etc.)
├── src/
│   ├── Controller/
│   │   ├── HomeController.php
│   │   ├── ShopController.php
│   │   ├── GalleryController.php
│   │   ├── CartController.php
│   │   └── AccountController.php
│   ├── Service/
│   │   └── ApiService.php           # All WooCommerce API calls (TODO)
│   ├── Entity/                      # Empty — add when needed
│   ├── Repository/                  # Empty — add when needed
│   ├── Security/                    # Empty — add JWT auth guard here
│   ├── EventListener/               # Empty — add request/response listeners
│   └── Form/                        # Empty — add Symfony Form types if needed
├── templates/
│   ├── layouts/
│   │   └── base.twig                # Master layout (head, header, footer, modal)
│   ├── components/
│   │   ├── header/header.twig
│   │   ├── footer/footer.twig
│   │   ├── modal/auth_modal.twig    # Login / Register / Reset (tabbed)
│   │   ├── cart/cart_drawer.twig    # Slide-in cart drawer
│   │   └── product/product_card.twig
│   └── pages/
│       ├── home/index.twig
│       ├── shop/index.twig
│       ├── shop/product.twig        # Product detail page
│       ├── gallery/index.twig       # New Arrivals
│       ├── cart/index.twig
│       ├── checkout/index.twig
│       └── account/index.twig       # Info + Orders + Password update
├── assets/
│   ├── styles/                      # Source styles (if build step added later)
│   └── scripts/                     # Source scripts
├── var/                             # Cache and logs (gitignored)
├── .env                             # Environment variables
└── composer.json
```

---

## Pages

| Route             | Name              | Template                        |
|-------------------|-------------------|---------------------------------|
| `/`               | `home`            | `pages/home/index.twig`         |
| `/shop`           | `shop`            | `pages/shop/index.twig`         |
| `/shop/{slug}`    | `product_detail`  | `pages/shop/product.twig`       |
| `/new-arrivals`   | `gallery`         | `pages/gallery/index.twig`      |
| `/cart`           | `cart`            | `pages/cart/index.twig`         |
| `/checkout`       | `checkout`        | `pages/checkout/index.twig`     |
| `/account`        | `account`         | `pages/account/index.twig`      |
| `/logout`         | `logout`          | *(redirect)*                    |

Auth modal (login / register / reset) is a persistent overlay included in `base.twig`. Trigger it with `data-modal="login"`, `data-modal="register"`, or `data-modal="reset"` on any element.

Cart drawer is also persistent. Open with `data-cart-open`, close with `data-cart-close`.

---

## OVH Shared Hosting Setup

1. Upload all files to your OVH `/www` or subdirectory.
2. Set the document root to `public/` (via OVH manager → Web hosting → Multisite).
3. Create a `.htaccess` in `public/` with:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteRule ^(.*)$ index.php [QSA,L]
   </IfModule>
   ```
4. Set environment variables in `.env` (or OVH environment config):
   - `APP_SECRET` — change to a secure random string
   - `API_BASE_URL` — your WordPress API URL
5. Run `composer install --no-dev --optimize-autoloader`.
6. Clear cache: `php bin/console cache:clear --env=prod`.

---

## API Integration (TODO)

All API calls are stubbed in `src/Service/ApiService.php`. Each method has a comment showing the expected WooCommerce endpoint. Wire them up once the WordPress backend is ready.

Authentication: implement JWT token handling in `src/Security/` using `symfony/security-bundle`.

---

## Customization

- **Logo**: Replace the text `Sevra` in `header.twig` with an `<img>` tag pointing to your SVG logo.
- **Hero image**: Replace the placeholder div in `pages/home/index.twig` hero section.
- **Product images**: Populated from API; local placeholder at `public/images/placeholder.jpg`.
- **Colors**: All tokens in `public/css/sevra.css` under `:root { }`.
- **Fonts**: Loaded via Google Fonts in `layouts/base.twig`. Adjust weights there.
