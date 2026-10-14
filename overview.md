# BAMED backend: API overview

Source of truth for what the Next.js frontend (`~/bamed/frontend`) expects from the Laravel API (`~/bamed/backend`).
Everything below is derived from the frontend code: `services/`, `lib/dashboard/store.tsx`, `types/`, `lib/products.ts`, `lib/sizes.ts`.

Status legend: ⬜ to build · 🟨 in progress · ✅ done

---

## 1. Global rules

| Topic | Rule |
|---|---|
| Base URL | `http://localhost:8000/api` (frontend env: `NEXT_PUBLIC_API_URL`). Paths below are relative to it. |
| Format | JSON in, JSON out. **camelCase** keys in every response and request. |
| IDs | Always strings in JSON. |
| Money | MAD, plain numbers (`490`, `1290.5`). Stored as `decimal(10,2)`, returned as numbers. |
| Dates | Stored as timestamps. Returned as **epoch milliseconds** (`createdAt`, `at`), because the frontend does `Date` maths on numbers. |
| Auth | `Authorization: Bearer <accessToken>` (Laravel Sanctum). Only admins authenticate. Customers never sign in. |
| Public routes | `GET /categories`, `GET /products`, `GET /products/{id}`. Everything else needs auth. |
| Lists | Return a plain array **or** `{ "data": [...] }`. Both work. `size` is capped at 100. |
| Empty success | `204 No Content` (delete, logout). |
| Validation error | `422` with `{ "message": "...", "errors": { "field": ["msg"] } }`. The frontend reads `errors[field][0]`. |
| Other errors | `{ "message": "..." }` with the right status: 400, 401, 403, 404. |
| 401 | The frontend clears the session and sends the admin to `/admin/auth`. |
| CORS | Allow `http://localhost:3000` (and the production domain later), headers `Authorization, Content-Type, Accept`. |
| Role checks | Enforced by the backend on every protected route. The frontend only hides buttons. |
| Activity log | Written **by the backend** on every write, with the actor taken from the token (see §8). |

---

## 2. Size scales (fixed, not a table)

A category has one `sizeScale`. A product's `sizes` must all belong to that scale. Validate on create and update.

| `sizeScale` | Allowed `sizes` |
|---|---|
| `letters` | S, M, L, XL, XXL, XXXL |
| `pants` | 38, 40, 42, 44, 46, 48, 50 |
| `jeans` | 28, 29, 30, 31, 32, 33, 34, 36, 38, 40 |
| `shoes` | 38, 39, 40, 41, 42, 43, 44, 45, 46 |
| `unique` | TU |

Default when missing: `letters`.

---

## 3. Auth

Phone format: international Moroccan, `+212XXXXXXXXX` (mobile 06/07, landline 05). The frontend normalizes before sending. The backend should still validate with `^\+212[5-7]\d{8}$`.

| Status | Method | Path | Body | Response |
|---|---|---|---|---|
| ⬜ | POST | `/auth/login` | `{ phone, password }` | `{ accessToken, user }` |
| ⬜ | GET | `/auth/me` | none | `User` |
| ⬜ | POST | `/auth/logout` | none | `204` (revokes the current token) |
| ⬜ | POST | `/auth/refresh` | `{ refreshToken }` | optional, skipped for now |

Errors: wrong credentials give `401 { message }`. Inactive admin gives `403`.

```ts
type User = {
  id: string;
  name: string;
  phone: string;      // +212612345678
  role: "admin";      // the only role
};
```

No register endpoint. The first admin is created by a seeder (`AdminSeeder`).

---

## 4. Categories

| Status | Method | Path | Auth | Notes |
|---|---|---|---|---|
| ⬜ | GET | `/categories` | public | storefront list |
| ⬜ | POST | `/categories` | admin | create |
| ⬜ | PUT | `/categories/{id}` | admin | update |
| ⬜ | DELETE | `/categories/{id}` | admin | `422` if the category still has products |

Public shape (`StoreCategory`):

```ts
type StoreCategory = {
  id: string;            // the slug, e.g. "hoodies" (the shop filters use it in the URL)
  label: string;         // "Hoodies"
  sizeScale: "letters" | "pants" | "jeans" | "shoes" | "unique";
  image?: string;        // optional tile photo for the home page
};
```

Admin shape (`Category`), returned to admins and used by the create/update body:

```ts
type Category = {
  id: string;
  name: string;
  slug: string;          // unique, generated from name (lowercase, no accents, dashes)
  description: string;
  sizeScale: SizeScaleId;
  image?: string;
  createdAt: number;
};
```

Rules: name required, slug unique ("Une catégorie porte déjà ce nom."), valid `sizeScale`.

---

## 5. Products

| Status | Method | Path | Auth | Notes |
|---|---|---|---|---|
| ⬜ | GET | `/products` | public | active products only, public shape |
| ⬜ | GET | `/products/{id}` | public | `404` if missing or inactive |
| ⬜ | GET | `/admin/products` | admin | all products, admin shape (stock, cost, sku) |
| ⬜ | POST | `/products` | admin | create |
| ⬜ | PUT | `/products/{id}` | admin | update (never changes stock) |
| ⬜ | DELETE | `/products/{id}` | admin | sales history is kept (snapshots) |
| ⬜ | POST | `/products/{id}/stock` | admin | `{ delta, reason }` |
| ⬜ | POST | `/uploads` | admin | multipart image upload, returns `{ url }` |

`GET /products` query params: `query`, `type` (category id), `minPrice`, `maxPrice`, `sale` (bool), `featured` (bool), `sort` (`nouveautes` | `prix-asc` | `prix-desc`), `page`, `size` (max 100).
The shop fetches `size=100` and filters client-side, so keep that working.

**Public shape** (`Product`). It must **never** expose `cost`, `stock` or `sku`.

```ts
type Product = {
  id: string;
  name: string;
  category: string;        // label of the category, e.g. "Chemises"
  type: string;            // id of the category, e.g. "chemises"
  sizeScale: SizeScaleId;  // copied from the category
  price: number;
  oldPrice?: number;       // present = promo
  tag?: "Nouveau" | "Promo";
  featured?: boolean;
  addedAt: number;         // epoch ms, used by the "Nouveautés" sort
  colors: ProductColor[];
  sizes: string[];
};

type ProductColor = {
  name: string;            // "Marine"
  hex: string;             // "#1f2a44"
  images: { src: string; alt: string }[];   // first = cover
};
```

How `tag` is computed by the backend:
- `Promo` when `oldPrice` is set.
- `Nouveau` when created in the last 30 days. If both apply, `Nouveau` wins.
- The frontend's "Nouveautés" page and the home section filter on `tag === "Nouveau"`.

**Admin shape** (`StockProduct`), also the create/update body:

```ts
type StockProduct = {
  id: string;
  sku: string;             // unique, auto "BAM-1001", "BAM-1002"... when empty
  name: string;
  categoryId: string;
  colors: ProductColor[];  // names unique per product, hex = #rrggbb
  price: number;           // > 0
  oldPrice?: number;       // must be > price
  cost: number;            // >= 0, purchase cost, used for profit
  stock: number;           // integer >= 0, set only on create
  lowStockAt: number;      // integer >= 0, stock <= this raises an alert
  sizes: string[];         // must belong to the category scale
  active: boolean;         // inactive = hidden from the shop and not sellable
  createdAt: number;
};
```

Validation messages the frontend already shows (reuse them):
- "Le nom est obligatoire." · "Choisissez une catégorie."
- "Le prix doit être supérieur à 0." · "L'ancien prix doit être supérieur au prix actuel."
- "Le coût d'achat est invalide." · "Le stock doit être un entier positif."
- "Ce SKU existe déjà." · "Donnez un nom à chaque couleur." · "La couleur « X » existe déjà."

**Stock adjustment** (`POST /products/{id}/stock`): body `{ delta: number, reason?: string }`. `delta` is a non-zero integer, and the result can't go below 0 ("Le stock ne peut pas être négatif."). Log action `stock`.

**Images:** the frontend currently stores data URLs in localStorage (demo only). With the backend: upload each file to `POST /uploads` (multipart field `file`, images only, 10 MB max), get back `{ url }`, and put that URL in `colors[].images[].src`. Max 8 photos per colour.

---

## 6. Admins (team)

| Status | Method | Path | Notes |
|---|---|---|---|
| ⬜ | GET | `/admins` | list |
| ⬜ | POST | `/admins` | create |
| ⬜ | PUT | `/admins/{id}` | update, also used for activate and deactivate |
| ⬜ | DELETE | `/admins/{id}` | |

```ts
type AdminUser = {
  id: string;
  name: string;
  phone: string;       // +212..., unique, this is the login
  active: boolean;
  createdAt: number;
};
```

Create body: `{ name, phone, password, active }`. Update body: `{ name, phone, active, password? }`.

Rules:
- You can't deactivate or delete **your own** account.
- There must always be at least one active admin ("Il doit rester au moins un administrateur actif.").
- Phone unique ("Ce numéro est déjà utilisé.").
- Deleting an admin keeps their sales (the seller name is a snapshot).

---

## 7. Sales

In-shop only: there is no online ordering, so there is **no channel field**.

| Status | Method | Path | Notes |
|---|---|---|---|
| ⬜ | GET | `/sales` | query: `from`, `to` (epoch ms), `sellerId`, `query` (product name), `page`, `size` |
| ⬜ | POST | `/sales` | record a sale |

```ts
type Sale = {
  id: string;
  at: number;
  productId: string;
  productName: string;   // snapshot at sale time
  categoryId: string;
  qty: number;
  unitPrice: number;     // snapshot
  unitCost: number;      // snapshot
  total: number;         // unitPrice * qty
  soldById: string;
  soldByName: string;    // snapshot
};
```

`POST /sales` body: `{ productId, qty, soldById }`.
Rules, all inside **one DB transaction**:
- Product must exist and be active ("Produit introuvable ou inactif.").
- `qty` integer >= 1 and <= stock ("Stock insuffisant (N disponible).").
- Seller must be an active admin ("Choisissez un vendeur actif.").
- Copy name, price and cost onto the sale, then decrement stock, then write the log entry.

The dashboard computes revenue, profit, orders, average basket, top products, per-seller ranking and the weekly candles **client-side** from `GET /sales` (all sales of the period). Add a stats endpoint later only if the volume gets big.

---

## 8. Activity log

| Status | Method | Path | Notes |
|---|---|---|---|
| ⬜ | GET | `/logs` | query: `actorId`, `entity`, `query`, `page`, `size`. Newest first. Keep the latest 500. |

No write endpoint. The backend writes entries itself.

```ts
type LogEntry = {
  id: string;
  at: number;
  actorId: string;
  actorName: string;                                  // snapshot
  action: "create" | "update" | "delete" | "sale" | "stock";
  entity: "product" | "category" | "admin" | "sale";
  entityId: string;
  summary: string;                                    // French sentence
};
```

Summary wording to match the frontend:
- `Produit « X » créé (stock N)` · `Produit « X » modifié` · `Produit « X » supprimé`
- `Catégorie « X » créée / modifiée / supprimée`
- `Administrateur « X » créé / modifié / supprimé`
- `Stock « X » : +20 (réassort)`
- `Vente : 2 × X (980 MAD) par Y`

---

## 9. Laravel structure (planned)

```
app/
  Models/            Admin, Category, Product, ProductColor, ProductImage, Sale, ActivityLog
  Http/
    Controllers/Api/ AuthController, CategoryController, ProductController,
                     StockController, UploadController, AdminController,
                     SaleController, LogController
    Requests/        LoginRequest, CategoryRequest, ProductRequest, StockRequest,
                     AdminRequest, SaleRequest
    Resources/       UserResource, StoreCategoryResource, CategoryResource,
                     ProductResource (public), StockProductResource (admin),
                     AdminResource, SaleResource, LogResource
  Services/          ActivityLogger, SaleService (transaction), SkuGenerator
  Support/           SizeScales (the table from §2)
database/
  migrations/        admins, categories, products, product_colors, product_images,
                     sales, activity_logs
  seeders/           AdminSeeder, CategorySeeder, ProductSeeder
routes/api.php
```

Tables, quick view:
- `admins`: id, name, phone (unique), password, active, timestamps
- `categories`: id, name, slug (unique), description, size_scale, image, timestamps
- `products`: id, sku (unique), name, category_id, price, old_price, cost, stock, low_stock_at, sizes (json), featured, active, timestamps
- `product_colors`: id, product_id, name, hex, position
- `product_images`: id, product_color_id, src, alt, position
- `sales`: id, product_id (nullable, kept if the product is deleted), product_name, category_id, qty, unit_price, unit_cost, total, sold_by_id, sold_by_name, created_at
- `activity_logs`: id, actor_id, actor_name, action, entity, entity_id, summary, created_at

---

## 10. Open items and mismatches

1. **Team page uses email, login uses phone.** `AdminUser` in the frontend has `email`, but admins log in with a phone number and the client has no email. The backend uses `phone` as above. The team page, `types/dashboard.ts` and the store need the same switch (frontend task, to do when we wire the API).
2. **"en-ligne" channel.** Removed from this contract. The frontend still has it in `sales/page.tsx`, `types/dashboard.ts`, `seed.ts` and the store (frontend cleanup task).
3. **Tokens.** Sanctum gives one access token and no refresh. Set a long expiry (e.g. 30 days). If we want refresh later, `POST /auth/refresh` is already in the frontend client.
4. **Dashboard store.** `lib/dashboard/store.tsx` is the single swap point: each action becomes an API call, and the local log writing goes away because the backend does it.
5. **Public vs admin product shape.** Two resources, two routes (`/products` and `/admin/products`), so cost and stock never leak to the shop.
6. **Photos.** Production needs real file storage (local `storage` link first, S3 or Cloudinary later). The `src` field only ever holds a URL.

---

## 11. Build order

1. ⬜ Install Sanctum, CORS for `localhost:3000`, JSON error format
2. ⬜ Admins table, `AdminSeeder`, `/auth/login`, `/auth/me`, `/auth/logout`
3. ⬜ Categories (migration, seeder, public GET, admin CRUD)
4. ⬜ Products (migrations, public GET, admin CRUD, SKU generator, size validation)
5. ⬜ Uploads
6. ⬜ Stock adjustment and activity logger
7. ⬜ Sales (transaction) and `/logs`
8. ⬜ Admins CRUD
9. ⬜ Frontend: set `NEXT_PUBLIC_API_URL=http://localhost:8000/api`, swap the dashboard store to API calls