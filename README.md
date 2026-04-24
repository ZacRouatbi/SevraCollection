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

## Security Posture

### SQL Injection Protection ✅

No direct database queries exist in this Symfony frontend. All data access occurs through:
- **WordPress/WooCommerce REST API** via `HttpClient` (parameterized HTTP requests)
- **Doctrine ORM** with parameterized DQL queries (if local database is used)
- No raw SQL, `mysqli`, or PDO connections present

**API Service (`src/Service/ApiService.php`):**
- All WooCommerce endpoints use query parameters and JSON request bodies
- HTTP client properly encodes parameters via Symfony HttpClient
- No string concatenation in API URLs

### Input Validation ✅

Strong validation is implemented across all user inputs:

- **Email validation:** `filter_var($email, FILTER_VALIDATE_EMAIL)`
- **Password complexity:** Minimum 8 chars with uppercase, lowercase, numbers, and special chars
- **Sanitization:** `trim()` and `strip_tags()` applied to text inputs
- **Type safety:** `declare(strict_types=1)` with explicit type hints throughout

**Key validation locations:**
- `src/Controller/RegistrationController.php` — email and password validation
- `src/Controller/PasswordResetController.php` — token and email validation
- `src/Controller/AccountController.php` — user data updates

### CSRF Protection ✅

All state-changing operations require CSRF tokens:
- Registration form
- Login form
- Password reset request and update
- Account update
- Checkout process

Implementation: `csrf_token('...')` in Twig + `$this->isCsrfTokenValid()` in controllers.

### Authentication & Authorization ✅

- **JWT-based authentication** integrated with WordPress/WooCommerce
- Custom `WordPress/Authenticator.php` validates JWT tokens
- Protected routes require `IS_AUTHENTICATED_FULLY` or `IS_AUTHENTICATED_ANONYMOUSLY`
- Session-based JWT storage with secure cookie configuration

### Password Security ✅

- Passwords validated with strong complexity requirements
- Password reset tokens generated via `random_bytes(32)` (cryptographically secure)
- Tokens expire after 1 hour and have rate limiting (3 attempts/hour)
- Passwords never logged or exposed in error messages

### Error Handling ✅

- Exceptions caught and logged without exposing internal details
- Generic error messages displayed to users
- Proper HTTP status codes used (400, 401, 403, 404, 500)

---

## Security Recommendations (Improvements Needed)

### 🔴 High Priority — Reset Token Exposure

**Issue:** Password reset tokens are passed as URL parameters (`/reset-password/{token}`), exposing them in:
- Browser history
- Server logs
- Referer headers when clicking external links
- Analytics platforms

**Mitigation:**
- Store reset token in session instead of URL
- Use POST-only forms with token in hidden field
- Alternatively: use one-time-use tokens that are immediately invalidated after first access

**Files:** `PasswordResetController::showResetForm()` and `PasswordResetController::update()`

---

### 🟡 Medium Priority — Additional Hardening

1. **Email Uniqueness Pre-Check**
   - Before calling `register()` API, explicitly check if email already exists using `checkUserExists()`
   - Currently relies on API response; a pre-check saves a round trip

2. **Rate Limiting on Reset Endpoint**
   - Rate limiting only applies to token *generation* (3/hour)
   - The actual password update endpoint (`/reset-password/update`) has no rate limiting
   - Brute-force attackers could try unlimited passwords against a known token

3. **Password Change Logic**
   - `AccountService::validatePasswordChange()` allows changing to a new password *without* providing the current password if `currentPassword` is null
   - Require current password whenever a password change is requested

4. **API Call Ownership Validation**
   - Some API calls use user-supplied IDs without verifying ownership (e.g., `getCustomer($userId)`)
   - Validate that the authenticated user matches the requested resource ID

---

### 🟢 Low Priority — Cryptographic hash choice

- MD5 is used for cache keys and non-security purposes (`PasswordResetService::canRequestReset()`, `CartService::getItemKey()`)
- MD5 is cryptographically broken but acceptable for non-crypto uses (e.g., cache keys, array indexing)
- Consider SHA-256 for future-proofing if semantic clarity is desired

---

## Current Configuration Security

### `config/packages/security.yaml`
- ✅ Custom WordPress authenticator properly configured
- ✅ Logout invalidates session and clears security token
- ✅ Access control protects `/account/*`, `/checkout`, `/cart/*` routes
- ⚠️ Remember-me functionality not configured (intentional for this setup)

### `config/packages/framework.yaml`
- ✅ Session cookie configuration present
- ⚠️ Consider adding `cookie_secure: auto` (already auto in prod) and `cookie_samesite: lax` (already set)

### `config/packages/csrf.yaml`
- ✅ CSRF protection enabled with proper token storage

---

## Security Checklist for Production

- [ ] **HTTPS enforced** — Configure web server to redirect HTTP → HTTPS
- [ ] **Secure cookies** — Set `session.cookie_secure = true` in prod
- [ ] **HTTP security headers** — Add CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- [ ] **Reset token storage** — Move from URL to session or POST-only
- [ ] **Rate limit all sensitive endpoints** — Password update, account changes
- [ ] **Ownership validation** — Verify user owns requested resources
- [ ] **Log sensitive actions** — Failed login attempts, password changes, account modifications
- [ ] **Input validation on IDs** — Ensure all numeric IDs are valid integers (`filter_var($id, FILTER_VALIDATE_INT)`)
- [ ] **Content Security Policy** — Restrict inline scripts and external sources

---

## Dependencies & Supply Chain

Run `composer audit` regularly to check for known vulnerabilities:

```bash
composer audit
```

Keep dependencies updated:
```bash
composer update --with-dependencies
```

---

**Overall Security Rating:** ✅ GOOD (with recommended improvements)

The codebase demonstrates solid security practices. Addressing the medium-priority recommendations will bring security to excellent levels.

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
