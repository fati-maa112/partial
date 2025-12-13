# Order Management System Documentation

## Overview

The **Order Management Module** is a production-ready e-commerce order system for NATURAÉ Skin-Care that implements a complete order lifecycle from cart creation through order completion.

---

## Architecture & Components

### 1. **Data Entities**

#### Order Entity (`src/Entity/Order.php`)
- **Status Flow**: `PENDING` → `PREPARING` → `COMPLETED` (or `CANCELLED` at any stage)
- **Key Fields**:
  - `id`: Unique identifier
  - `customer_id`: References the customer (not nullable)
  - `total`: Decimal(10,2) - calculated server-side
  - `status`: Current order status
  - `created_at`: Order creation timestamp
  - `updated_at`: Last modification timestamp
  - `createdBy`: Admin/staff user who created the order
  - `orderItems`: Collection of OrderItem entities

#### OrderItem Entity (`src/Entity/OrderItem.php`)
- **Key Fields**:
  - `id`: Unique identifier
  - `order_id`: Foreign key to Order (required)
  - `product_id`: Foreign key to Product (optional but recommended)
  - `productName`: Product name snapshot (immutable for history)
  - `price`: Product price at order time (immutable - DECIMAL(10,2))
  - `quantity`: Number of units ordered
  - `subtotal`: Calculated via `getSubtotal()` (price × quantity)

#### Product Entity (`src/Entity/Product.php`)
- **Stock Management**:
  - `quantity`: Current available stock
  - Methods: `addQuantity()`, `subtractQuantity()`
- **Price**: Stored as DECIMAL(10,2), never modified when item prices change

---

## Functional Flow

### Step 1: Cart Management
**Controller**: `CartController`  
**Service**: `CartService`

```
1. Admin clicks "Add to Cart" on product
   → CartService::addToCart($productId, $quantity)
   → Item stored in SESSION (key: 'admin_order_cart')

2. Admin views cart at /cart
   → CartController::view()
   → Displays items with real-time totals
   → Shows validation errors (stock, prices)

3. Admin can:
   - Update quantity: CartService::updateQuantity()
   - Remove item: CartService::removeFromCart()
   - Clear cart: CartService::clearCart()
```

**Session Structure**:
```php
$_SESSION['admin_order_cart'] = [
    123 => [
        'product_id' => 123,
        'quantity' => 2,
        'price' => '29.99',
        'name' => 'Serum X'
    ],
    456 => [
        'product_id' => 456,
        'quantity' => 1,
        'price' => '49.99',
        'name' => 'Moisturizer Y'
    ]
];
```

### Step 2: Customer Selection & Validation

**Controller**: `CartController::checkout()`

```
1. Admin selects customer from dropdown (required)
2. System validates:
   - Cart not empty
   - Customer exists
   - Stock available for all items
   - Prices unchanged (warning only)
```

### Step 3: Order Creation (With Transactions)

**Controller**: `CartController::checkout()`  
**Database**: Transaction-protected

```php
// Pseudo-code of transaction flow
DB::beginTransaction();
try {
    // Create Order with PENDING status
    $order = new Order();
    $order->setStatus(Order::STATUS_PENDING);
    $order->setCustomer($customer);
    $order->setCreatedBy($currentAdmin);
    
    // Add OrderItems with price snapshots
    foreach ($cartItems as $item) {
        $orderItem = new OrderItem();
        $orderItem->setPrice($product->getPrice()); // Snapshot
        $orderItem->setProductName($product->getName()); // Snapshot
        $orderItem->setQuantity($item['quantity']);
        $order->addOrderItem($orderItem);
    }
    
    // Calculate total server-side
    $order->calculateTotal(); // Sums all item subtotals
    
    // Persist order
    em()->persist($order);
    em()->flush();
    
    // Deduct stock (in same transaction)
    foreach ($cartItems as $item) {
        $product->subtractQuantity($item['quantity']);
    }
    em()->flush();
    
    // Commit all changes
    DB::commit();
    
} catch (Exception $e) {
    DB::rollback(); // Revert everything
    throw $e;
}

// Clear cart only after successful commit
cartService->clearCart();
```

**Why Transactions?**
- Ensures order and stock updates happen atomically
- If stock deduction fails, order is rolled back
- Prevents data corruption or overselling

---

### Step 4: Order Status Workflow

**Status Transitions** (enforced in controller):

```
PENDING
   ├→ PREPARING (admin clicks "Start Processing")
   │     └→ COMPLETED (admin clicks "Complete Order")
   └→ CANCELLED (at any stage, stock restored)

Key Rules:
- COMPLETED orders: cannot be modified or cancelled
- CANCELLED orders: stock is restored if they had already deducted stock
- Price snapshots: never change (historical accuracy)
```

**Status Transition Methods**:

| Method | Route | Status Flow | Description |
|--------|-------|------------|-------------|
| `markProcessing()` | POST `/order/{id}/mark-processing` | PENDING → PREPARING | Admin starts processing |
| `markCompleted()` | POST `/order/{id}/mark-completed` | PREPARING → COMPLETED | Admin marks complete |
| `cancel()` | POST `/order/{id}/cancel` | Any → CANCELLED | Cancels and restores stock |

---

## Services

### CartService (`src/Service/CartService.php`)

**Public Methods**:

```php
// Add product to session cart
addToCart(int $productId, int $quantity = 1): bool

// Update quantity (or remove if qty ≤ 0)
updateQuantity(int $productId, int $quantity): bool

// Remove product from cart
removeFromCart(int $productId): bool

// Clear entire cart
clearCart(): void

// Calculate totals
calculateTotals(): array // ['subtotal' => string, 'total' => string, 'item_count' => int]

// Get cart items with DB-refreshed product data
getCartSummary(): array

// Validate cart (stock, prices, non-empty)
validateCart(): array // ['valid' => bool, 'errors' => string[]]

// Get item count
getItemCount(): int
```

### OrderStatusHelper (`src/Service/OrderStatusHelper.php`)

**Purpose**: Centralized status management logic

```php
// Get allowed transitions FROM a status
getAllowedTransitions(string $currentStatus): array

// Check if transition is legal
isTransitionAllowed(string $fromStatus, string $toStatus): bool

// Get status labels and colors
getStatusLabel(string $status): array
// Returns: ['label' => 'Pending', 'icon' => 'fa-...', 'color' => '#...']

// Check if order can be modified
isOrderModifiable(string $status): bool
```

### OrderValidator (`src/Service/OrderValidator.php`)

**Purpose**: Comprehensive order validation

```php
// Validate entire order
validateOrder(Order $order): array
// Returns: ['valid' => bool, 'errors' => string[]]

// Validate stock availability
validateStock(Order $order): array
// Returns: ['valid' => bool, 'errors' => [], 'warnings' => []]

// Check price changes (warning)
validatePrices(Order $order): array

// Full comprehensive validation
validateOrderComprehensive(Order $order): array
```

---

## Validations

### 1. Cart Validations (CartService)
- ✅ Cart is not empty
- ✅ All products exist in database
- ✅ Stock sufficient for quantities
- ✅ Price warnings if changed

### 2. Order Creation Validations (CartController)
- ✅ Customer selected and exists
- ✅ Order items valid (prices, quantities)
- ✅ Total calculation matches
- ✅ Stock double-checked before commit
- ✅ CSRF token valid
- ✅ Database transaction integrity

### 3. Status Transition Validations (OrderController)
- ✅ Only allowed transitions permitted
- ✅ COMPLETED/CANCELLED orders immutable
- ✅ Stock restored on cancellation
- ✅ CSRF protection on all state changes

---

## Best Practices Implemented

### ✅ Database Integrity
- **Transactions**: Order creation wrapped in `beginTransaction()` / `commit()`
- **Cascades**: OrderItems cascade delete with Order
- **Foreign Keys**: Enforced at DB level

### ✅ Price History
- Order items store **price snapshot** (never modified)
- Product price changes don't affect past orders
- Full audit trail of historical prices

### ✅ Stock Management
- Stock deducted **only after order persisted**
- Cancellation **restores stock automatically**
- Double-check before commit prevents race conditions

### ✅ Validation
- Server-side calculation (no frontend math)
- Total validation (item subtotals must match order total)
- Stock verified at checkout and order status change

### ✅ Error Handling
- Clear error messages for user
- Graceful rollback on failure
- Activity logging for all state changes

### ✅ Security
- CSRF tokens on all POST/state-changing actions
- Role-based access (`#[IsGranted('ROLE_ADMIN')]`)
- Input validation and sanitization

---

## Database Schema

```sql
-- orders table
CREATE TABLE `order` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status VARCHAR(100) NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customer(id),
    FOREIGN KEY (created_by) REFERENCES user(id),
    INDEX idx_status (status),
    INDEX idx_customer (customer_id),
    INDEX idx_created_at (created_at)
);

-- order_items table
CREATE TABLE order_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES `order`(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
);

-- products table (existing)
CREATE TABLE product (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 0,
    ...
);
```

---

## API Routes

### Cart Management
| Route | Method | Handler |
|-------|--------|---------|
| `/cart` | GET | CartController::view() |
| `/cart/add/{id}` | POST | CartController::add() |
| `/cart/update/{id}` | POST | CartController::update() |
| `/cart/remove/{id}` | POST | CartController::remove() |
| `/cart/clear` | POST | CartController::clear() |
| `/cart/checkout` | POST | CartController::checkout() |

### Order Management
| Route | Method | Handler |
|-------|--------|---------|
| `/order` | GET | OrderController::index() |
| `/order/{id}` | GET | OrderController::show() |
| `/order/{id}/edit` | GET/POST | OrderController::edit() |
| `/order/{id}/mark-processing` | POST | OrderController::markProcessing() |
| `/order/{id}/mark-completed` | POST | OrderController::markCompleted() |
| `/order/{id}/cancel` | POST | OrderController::cancel() |
| `/order/{id}/receipt` | GET | OrderController::receipt() |
| `/order/{id}/delete` | POST | OrderController::delete() |

---

## Testing Checklist

### 1. Cart Operations
- [ ] Add product to cart
- [ ] Update quantity (increase/decrease)
- [ ] Remove item from cart
- [ ] Clear entire cart
- [ ] Totals calculate correctly

### 2. Order Creation
- [ ] Cannot create order without customer selection
- [ ] Cannot create order with empty cart
- [ ] Cannot create order if stock insufficient
- [ ] Order created with PENDING status
- [ ] Stock deducted immediately
- [ ] Price snapshot stored correctly
- [ ] Cart cleared after successful order

### 3. Status Transitions
- [ ] PENDING → PREPARING works
- [ ] PREPARING → COMPLETED works
- [ ] Can cancel from any status except COMPLETED
- [ ] Stock restored when cancelled
- [ ] Cannot modify COMPLETED orders

### 4. Edge Cases
- [ ] Product deleted after added to cart
- [ ] Price changes between cart and checkout
- [ ] Concurrent orders on same product
- [ ] Transaction rollback on error
- [ ] Database transaction integrity

---

## Example Workflow

**Scenario**: Admin creates order for 2 Serums (₱29.99 each) and 1 Moisturizer (₱49.99)

```
1. Admin @ /product → clicks "Add to Cart" on Serum
   → CartService adds to session

2. Admin clicks "Add to Cart" on Moisturizer
   → CartService adds to session

3. Admin updates Serum quantity to 2
   → CartService updates quantity

4. Admin @ /cart → selects "Jane Doe" customer
   → CartService validates (stock ✓, prices ✓)

5. Admin clicks "Confirm & Create Order"
   → CartController::checkout() begins transaction
   → Creates Order #1234 with status='PENDING'
   → Creates OrderItem for Serum (qty 2, price ₱29.99)
   → Creates OrderItem for Moisturizer (qty 1, price ₱49.99)
   → Calculates total: (29.99×2) + 49.99 = ₱109.97
   → Deducts stock: Serum -2, Moisturizer -1
   → Commits transaction
   → Clears cart

6. Order #1234 created successfully! Redirects to /order/1234

7. Admin @ /order/1234 → clicks "Start Processing"
   → Status changes to PREPARING
   → Timestamp updated

8. Admin → clicks "Complete Order"
   → Status changes to COMPLETED
   → Order locked (cannot edit/delete)

9. Customer receives order ✓
```

---

## Future Enhancements

- [ ] PDF receipt generation (Dompdf/wkhtmltopdf)
- [ ] Email notifications (order confirmed, shipped, delivered)
- [ ] Refund/partial refund support
- [ ] Inventory reservations (soft-reserve on pending orders)
- [ ] Return/RMA workflow
- [ ] Shipping address and tracking
- [ ] Payment gateway integration
- [ ] Automated status updates via webhooks
- [ ] Analytics & reporting dashboard
