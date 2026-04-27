# Sevra Collection — Symfony Frontend

Headless Symfony 8.0 frontend for Sevra Collection, connecting to a WordPress/WooCommerce REST API backend with JWT authentication.

## Stack

| Layer         | Technology                                           |
|---------------|------------------------------------------------------|
| Framework     | Symfony 8.0 (PHP 8.4+)                              |
| Templates     | Twig 3                                               |
| CSS           | Tailwind CSS (CDN) + `public/css/sevra.css`         |
| JS            | Vanilla JS + Stimulus controllers + Turbo            |
| Asset Pipeline| Symfony AssetMapper (importmap)                      |
| Database      | PostgreSQL (via Docker)                              |
| Mail Dev      | Mailpit (via Docker)                                 |
| Backend API   | WordPress + WooCommerce REST (headless) + JWT        |
| ORM           | Doctrine ORM                                         |

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
│   ├── routes.yaml                      # Route definitions (attributes + YAML)
│   ├── services.yaml                    # Service configuration
│   ├── bundles.php                      # Registered bundles
│   └── packages/                        # Framework package configs
│       ├── doctrine.yaml                # Doctrine ORM + PostgreSQL
│       ├── framework.yaml               # Session, error controller
│       ├── security.yaml                # JWT + WordPress auth guard
│       ├── twig.yaml                    # Twig configuration
│       ├── ux_turbo.yaml                # Hotwired Turbo integration
│       └── ... (cache, mailer, validator, etc.)
├── src/
│   ├── Controller/
│   │   ├── AboutController.php
│   │   ├── AccountController.php
│   │   ├── CartController.php
│   │   ├── CheckoutController.php
│   │   ├── EditorialController.php
│   │   ├── ErrorController.php
│   │   ├── GalleryController.php
│   │   ├── HomeController.php
│   │   ├── PasswordResetController.php
│   │   ├── RegistrationController.php
│   │   ├── SecurityController.php       # WordPress JWT login
│   │   └── ShopController.php
│   ├── Security/
│   │   └── WordPress/                   # JWT authentication integration
│   │       ├── Authenticator.php
│   │       ├── AuthenticationEntryPoint.php
│   │       ├── User.php
│   │       └── UserProvider.php
│   ├── Service/
│   │   ├── ApiService.php               # All WooCommerce API calls
│   │   ├── AccountService.php
│   │   └── PasswordResetService.php
│   ├── Entity/                          # Doctrine entities (if using local DB)
│   ├── Repository/                      # Doctrine repositories
│   ├── Form/                            # Symfony Form types
│   ├── EventListener/                   # Request/response listeners
│   └── Kernel.php
├── public/
│   ├── css/sevra.css                    # Design tokens + custom CSS
│   ├── js/sevra.js                      # UI interactions (modal, cart, etc.)
│   ├── images/                          # Static assets (logo, hero, etc.)
│   ├── videos/heroVideo.mp4
│   └── index.php
├── templates/
│   ├── base.html.twig                   # Master layout (head, header, footer, modals)
│   ├── bundles/
│   │   └── TwigBundle/Exception/
│   │       ├── error.twig
│   │       └── error404.twig
│   ├── components/
│   │   ├── cart/cart_drawer.twig
│   │   ├── footer/footer.twig
│   │   ├── header/header.twig
│   │   ├── modal/
│   │   │   ├── auth_modal.twig          # Login / Register / Reset (tabbed)
│   │   │   └── search_modal.twig
│   │   └── product/product_card.twig
│   └── pages/
│       ├── about/index.twig
│       ├── account/index.twig
│       ├── cart/index.twig
│       ├── checkout/index.twig
│       ├── editorial/
│       │   ├── index.twig
│       │   └── show.twig
│       ├── gallery/index.twig           # New Arrivals
│       ├── home/index.twig
│       ├── reset_password/reset_password.twig
│       ├── shop/
│       │   ├── index.twig
│       │   └── product.twig
│       └── ... (additional pages)
├── assets/
│   ├── app.js                           # Stimulus controllers entrypoint
│   ├── controllers/                     # Stimulus controllers
│   │   ├── csrf_protection_controller.js
│   │   └── hello_controller.js
│   ├── styles/app.css                   # Source CSS (if build step added)
│   └── vendor/                          # AssetMapper vendor libs (auto-installed)
│       ├── @hotwired/stimulus/
│       └── @hotwired/turbo/
├── migrations/                          # Doctrine migrations (if using local DB)
├── translations/                        # Translation files (i18n)
├── var/                                 # Cache & logs (gitignored)
├── compose.yaml                         # Docker services (PostgreSQL)
├── compose.override.yaml                # Docker overrides (Mailpit, port mapping)
├── importmap.php                        # JS dependency import map
├── phpunit.dist.xml                     # PHPUnit configuration
├── .editorconfig
├── .env                                 # Environment variables (gitignored)
├── .env.dev                             # Development env overrides (gitignored)
├── .env.test                            # Test env overrides (gitignored)
├── composer.json
├── composer.lock
├── symfony.lock
└── README.md
```

---

## Pages & Routes

| Route                          | Name                      | Controller | Template |
|--------------------------------|---------------------------|------------|----------|
| `/`                            | `home`                    | HomeController | `pages/home/index.twig` |
| `/about`                      | `about`                   | AboutController | `pages/about/index.twig` |
| `/shop`                       | `shop`                    | ShopController | `pages/shop/index.twig` |
| `/shop/{slug}`                | `product_detail`          | ShopController::product | `pages/shop/product.twig` |
| `/gallery`                    | `gallery`                 | GalleryController | `pages/gallery/index.twig` |
| `/cart`                       | `cart`                    | CartController | `pages/cart/index.twig` |
| `/checkout`                   | `checkout`                | CheckoutController | `pages/checkout/index.twig` |
| `/checkout/confirm`           | `checkout_confirm`        | CheckoutController::confirm | — |
| `/account`                    | `account`                 | AccountController | `pages/account/index.twig` |
| `/account/update`             | `account_update`          | AccountController::update | — |
| `/editorial`                  | `editorial`               | EditorialController | `pages/editorial/index.twig` |
| `/editorial/{slug}`           | `editorial_show`          | EditorialController::show | `pages/editorial/show.twig` |
| `/login`                      | `login`                   | SecurityController::login | — (JSON response) |
| `/logout`                     | `logout`                  | SecurityController::logout | — (redirect) |
| `/register`                   | `register`                | RegistrationController::register | — (JSON/form) |
| `/reset-password/request`     | `reset_password_request`  | PasswordResetController::request | — (POST) |
| `/reset-password/update`      | `reset_password_update`   | PasswordResetController::update | — (POST) |
| `/reset-password/{token}`     | `reset_password_form`     | PasswordResetController::showResetForm | `pages/reset_password/reset_password.twig` |

**Modals:** Auth modal (login/register/reset) is a persistent overlay in `base.html.twig`. Trigger with `data-modal="login"`, `data-modal="register"`, or `data-modal="reset"` on any element.

**Cart drawer:** Persistent slide-in cart. Open with `data-cart-open`, close with `data-cart-close`.

---

## Environment Variables

| Variable       | Description                                  |
|----------------|----------------------------------------------|
| `APP_SECRET`   | Symfony secret (change for production)       |
| `DATABASE_URL` | PostgreSQL connection string                 |
| `MAILER_DSN`   | Mailpit SMTP (`smtp://localhost:1025`)       |
| `API_BASE_URL` | WordPress/WooCommerce REST API base URL      |
| `API_KEY`      | WooCommerce consumer key                     |
| `API_SECRET`   | WooCommerce consumer secret                  |
| `JWT_TOKEN`    | Optional: static JWT for API auth            |

---

## Installation & Setup

### Prerequisites

- PHP 8.4+ with PostgreSQL (pdo_pgsql) and ctype extensions
- Composer
- Docker & Docker Compose (for database and mailer)

### 1. Clone & Install Dependencies

```bash
cd SevraCollectionFrontend
composer install
```

### 2. Configure Environment

Copy and modify `.env` (or use `.env.dev` for local):

```bash
cp .env .env.local
```

Edit `.env.local`:

```dotenv
APP_SECRET=your-secure-random-string
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app"
MAILER_DSN="smtp://mailpit:1025"
API_BASE_URL="https://your-wordpress-site.com"
API_KEY="your_woocommerce_consumer_key"
API_SECRET="your_woocommerce_consumer_secret"
```

### 3. Start Docker Services

```bash
docker compose up -d
```

This starts:
- **PostgreSQL 16** on port `5432` (database: `app`, user: `app`)
- **Mailpit** SMTP on port `1025`, web UI on `http://localhost:8025`

### 4. Initialize Database (if using local entities)

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

*(Skip if the app is purely headless and uses WordPress for all data.)*

### 5. Install Frontend Assets

Symfony AssetMapper will auto-install Stimulus & Turbo:

```bash
php bin/console importmap:install
```

The `assets/app.js` entrypoint registers all Stimulus controllers from `assets/controllers/`.

### 6. Clear Cache & Warm Up

```bash
php bin/console cache:clear
php bin/console cache:warmup
```

---

## Development

### Run the Local Server

```bash
symfony server:start -d
# or
php -S 127.0.0.1:8000 -t public
```

Access: `http://localhost:8000`

### Asset Debugging

```bash
php bin/console debug:asset-map   # List all mapped assets
php bin/console importmap:require <package>  # Add new npm package
```

### Database Migrations (if using Doctrine)

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Tests

```bash
php bin/phpunit
```

Configuration: `phpunit.dist.xml`

---

## WordPress/WooCommerce API Integration

All API calls are centralized in `src/Service/ApiService.php`. Current implementation status:

| Method               | WooCommerce Endpoint                      | Status   |
|----------------------|-------------------------------------------|----------|
| `getProducts()`      | `GET /wp-json/wc/v3/products`            | Stub     |
| `getProduct(slug)`   | `GET /wp-json/wc/v3/products?slug={slug}`| Stub     |
| `getNewArrivals()`   | `GET /wp-json/wc/v3/products?tag=new`    | Stub     |
| `login()`            | `POST /wp-json/jwt-auth/v1/token`        | Stub     |
| `register()`         | `POST /wp-json/wc/v3/customers`          | Stub     |
| `resetPassword()`    | `POST /wp-json/sevra/v1/reset-password`  | Stub     |
| `getCustomer(id)`    | `GET /wp-json/wc/v3/customers/{id}`      | Stub     |
| `updateCustomer()`   | `PUT /wp-json/wc/v3/customers/{id}`      | Stub     |
| `createOrder()`      | `POST /wp-json/wc/v3/orders`             | Stub     |

### Authentication Flow

1. User submits credentials → `SecurityController::login()` calls `ApiService::login()`
2. On success, JWT token is stored in session and used for subsequent API calls via `HttpClient`
3. `Security/WordPress/Authenticator.php` validates token on protected routes
4. `UserProvider` loads WordPress user data into a `User` object

---

## Docker Notes

**compose.yaml** — defines `database` (PostgreSQL 16) service.
**compose.override.yaml** — exposes port 5432 and adds `mailer` (Mailpit).

To stop all services:

```bash
docker compose down
```

To reset database volume:

```bash
docker compose down -v
```

---

## Production Deployment (OVH Shared Hosting)

1. Upload all files to your OVH `/www` or subdirectory.
2. Set the document root to `public/` (OVH Manager → Web hosting → Multisite).
3. Create a `.htaccess` in `public/`:

   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteRule ^(.*)$ index.php [QSA,L]
   </IfModule>
   ```
4. Set environment variables via OVH environment configuration (or `.env.local.php`):

   ```dotenv
   APP_SECRET=productions-secret-here
   DATABASE_URL="postgresql://user:pass@host:5432/dbname"
   API_BASE_URL="https://your-wordpress-site.com"
   API_KEY="prod_consumer_key"
   API_SECRET="prod_consumer_secret"
   ```

   *(If PostgreSQL isn't available on your host, switch to SQLite: `DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"`)*
5. Run: `composer install --no-dev --optimize-autoloader`
6. Clear cache: `php bin/console cache:clear --env=prod --no-debug`

---

## Customization

- **Logo**: Replace the `Sevra` text in `templates/components/header/header.twig` with an `<img>` tag.
- **Hero video**: Replace `public/videos/heroVideo.mp4`.
- **Colors**: All design tokens in `public/css/sevra.css` under `:root { }`.
- **Fonts**: Google Fonts loaded in `templates/base.html.twig`. Adjust weights or swap fonts there.
- **Stimulus controllers**: Add new controllers in `assets/controllers/` — they auto-register.
- **API endpoints**: Update `src/Service/ApiService.php` with actual endpoint URLs and authentication.

---

## Security Notes

- `APP_SECRET` must be unique and secret in production.
- WooCommerce API keys should have **read-only** permissions for public-facing endpoints.
- JWT tokens are stored in the session; enable HTTPS in production.
- CSRF protection is enabled for state-changing operations (`csrf_token` Twig function).

---

## Troubleshooting

**"Class not found" after composer install:**  
Run `php bin/console cache:clear` and `php bin/console assets:install`.

**AssetMapper not loading Stimulus:**  
Run `php bin/console importmap:install` to vendor-install Stimulus & Turbo.

**Database connection refused:**  
Ensure Docker services are running: `docker compose ps`. Check `DATABASE_URL` in `.env.local`.

**Mail not sending:**  
Verify Mailpit UI at `http://localhost:8025`. Set `MAILER_DSN="smtp://mailpit:1025"`.

**404 on pretty URLs (OVH):**  
Confirm `.htaccess` exists in `public/` and `AllowOverride All` is enabled.

---

## License

Proprietary — All rights reserved.
