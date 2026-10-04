# Royal Snacks — wholesale ordering platform (prototype)

A manufacturer-to-shopkeeper ordering system for FMCG / snack products.

| Part | Stack | Where |
|---|---|---|
| Shopkeeper mobile app | Flutter 3 (Material 3, Riverpod, go_router) | [`mobile/`](mobile) |
| Server, REST API, website, staff dashboards | Laravel 12 · PostgreSQL · Livewire 3 · Sanctum | [`server/`](server) |
| Shared configuration (single source of truth) | Apple **Pkl** → PHP + YAML | [`shared/`](shared) |

"Royal Snacks" is a placeholder brand — change it in `shared/config.pkl` and run `make config`.

## What's in it

**Shopkeepers (app and website — same account, same cart)**
- Registration: owner, mobile, email, password, shop name/type, **PAN** (required), **GSTIN** (optional, cross-checked against the PAN), address, shop photo → mobile **OTP** verification → **waits for admin approval** before it can order.
- Serviceable PIN prefixes: Prayagraj `211`, Varanasi `221`, Kanpur `208`, Lucknow `226` (defined in pkl).
- Catalogue by category. Products are sold **per case** with variants (pack size), MRP, GST rate, HSN, a **minimum number of cases per line**, and live stock at the shopkeeper's hub.
- Pricing: **product deals** (percent, flat per case, buy-X-get-Y-free), **volume slabs** (2 / 4 / 6 % above ₹10k / 25k / 50k), **coupons** (public list, or private codes shared by sales), a **minimum order value (₹2,000) and minimum total cases (5)**, and GST (CGST + SGST, or IGST for other states) rounded to the rupee.
- Payment: Cash on delivery · **credit** (limit set by admin) · **online (sandbox gateway)**.
- Order tracking: Placed → Confirmed → Packing → Packed → Left warehouse → Out for delivery → Delivered (plus Cancelled / Returned). Live timeline, a **delivery OTP** the shopkeeper gives the driver, driver contact, **GST PDF invoice**, reorder, and cancellation until packing starts.
- Support: grievance tickets (category, linked order, photos), threaded replies, ratings; in-app notifications.

**Our side (Livewire, role-based — everyone signs in at `/login` on the website)**

| Role | Lands on | Does |
|---|---|---|
| Super admin | `/staff/admin` | Analytics (revenue, funnel, fulfilment speed, top products/shops, offer cost, support KPIs), shop approvals & credit, products, inventory & stock transfers between hubs, offers/coupons, team, audit log — plus every screen below |
| Order manager | `/staff/orders` | Confirms/cancels orders, approves shopkeepers |
| Packaging station | `/staff/packaging` | Pick-list per order → *Start packing* → *Packed* (hub-scoped) |
| Warehouse | `/staff/warehouse` | *Parcel left the warehouse* — assigns (or auto-assigns) a driver; stock is deducted here |
| Delivery agent | `/staff/delivery` | Own deliveries, call/map, collect COD, *Mark delivered* with the customer's OTP |
| Customer support | `/staff/support` | Ticket queue (auto-assigned to the least-loaded agent), internal notes, canned replies, reassign |

**Hubs (umbrella model):** Prayagraj main warehouse + Varanasi / Kanpur / Lucknow distribution centres. Each shop is served by the hub for its PIN code; stock, staff and orders are per hub, and the admin can transfer stock from the main warehouse to a DC.

## Run it

```bash
# 1. server (PHP 8.3, Composer, PostgreSQL 16)
cd server
composer install
cp .env.example .env && php artisan key:generate
createdb wholesale        # and a role matching DB_USERNAME / DB_PASSWORD in .env
php artisan migrate --seed   # demo catalogue, hubs, staff, shops, 4 weeks of order history
php artisan storage:link
php artisan serve            # http://localhost:8000 — website + /api/v1

# 2. mobile
cd mobile
flutter pub get
flutter run --dart-define=API_BASE=http://<server-host>:8000   # Android emulator default: http://10.0.2.2:8000
```

**Demo logins** (password `password`; the mobile number is the username on both web and app):

| Who | Mobile |
|---|---|
| Shopkeepers (approved) | `9100000001` Prayagraj · `9100000002` Lucknow · `9100000003` Varanasi |
| Shopkeeper awaiting approval | `9100000004` (Kanpur) |
| Super admin · Order manager | `9000000001` · `9000000002` |
| Support agents | `9000000003`, `9000000004` |
| Prayagraj packaging · warehouse · driver | `9000000010` · `9000000011` · `9000000012` |
| Other hubs (packaging/warehouse/driver) | VNS `…20/21/22` · KNP `…30/31/32` · LKO `…40/41/42` |

Prototype OTP is always **`123456`**. Sandbox payments: card `4111 1111 1111 1111` succeeds and `4000 0000 0000 0002` fails; UPI `success@sandbox` / `failure@sandbox`. Coupons: `WELCOME500`, `FESTIVE5`, `SNACKUP10`, and the private `KIRANA200`.

## Shared config with Pkl

[`shared/config.pkl`](shared/config.pkl) (schema in `AppConfig.pkl`) holds brand colours, GST rates, order stages, minimums, volume slabs, serviceable areas, ticket categories, locales, sandbox test instruments and OTP settings.

```bash
make config         # needs the `pkl` CLI; renders
                    #   server/config/shared.php          → Laravel: config('shared.…')
                    #   mobile/assets/config/shared.yaml  → Flutter: loaded at start-up; drives the theme and the stage tracker
make check-config   # CI guard: fails if the generated files are stale
```

Server-only values (the prototype OTP) are stripped from the Flutter YAML. The generated files are committed, so neither side needs Pkl just to build.

## Languages

English only today, with the scaffolding for more: Flutter `gen-l10n` (`mobile/lib/l10n/app_en.arb`), Laravel `lang/en/app.php`, a per-user `locale`, `Accept-Language` handling, and `locales` in pkl. To add Hindi: add `app_hi.arb` and `lang/hi/app.php`, set `enabled = true` for `hi` in pkl, run `make config`. Long-form copy and dashboard text are still hard-coded English.

## API (`/api/v1`, Sanctum bearer token)

`GET meta` · `POST auth/register | otp/send | otp/verify | login | password/reset | logout` · `GET/PUT me`, `POST me/password` · `GET categories | products | products/{id} | offers` · `GET/DELETE cart`, `PUT cart/items`, `DELETE cart/items/{variant}`, `POST/DELETE cart/coupon` · `GET/POST orders`, `GET orders/{id}`, `POST orders/{id}/cancel | reorder | payments`, `GET orders/{id}/invoice-link`, `POST payments/{id}/confirm` · `GET/POST tickets`, `GET tickets/{id}`, `POST tickets/{id}/messages | close | rating` · `GET notifications`, `POST notifications/read`.

Ordering endpoints require an **approved** shop (otherwise 403 with `code: shop_pending` etc.). Money is always rupees (float) in JSON; the pricing engine works in integer paise internally.

## Tests

```bash
cd server && php artisan test          # 37 tests: pricing & GST maths, order lifecycle and role checks, stock, credit, coupons, API, every Livewire page and the station flows
cd mobile && flutter analyze && flutter test
```

Server tests use a separate `wholesale_test` PostgreSQL database (see `server/phpunit.xml`).

## Prototype limits — before going to production

- **OTP is a stub** (`OTP_PROTOTYPE=true`): nothing is sent by SMS. Implement `OtpService::deliver()` with a provider and turn the flag off.
- **Payments are a sandbox simulator** behind `PaymentService` (create intent → confirm). Swap in a real gateway (e.g. Razorpay) there and verify signatures / webhooks server-side.
- **Notifications are in-app only** (database notifications; the app and web poll). No push.
- Service areas match the first three PIN digits, not an exact PIN list.
- Credit is simple: usage is tracked per order; repayments are recorded manually by an admin.
- Free cases from buy-X-get-Y deals carry no extra GST — confirm the treatment with your accountant. E-invoicing and e-way bills are not implemented.
- Change every demo password, drop `DemoShopSeeder`, serve over HTTPS, and review rate limits before launch.
