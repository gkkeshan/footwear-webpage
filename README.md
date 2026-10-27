# 👠 Girls' Footwear E-Commerce Web Application

A full-featured, responsive e-commerce web platform for girls' footwear catering to toddlers, kids, and teens. Built using native PHP, MySQL with PDO, and modern frontend design principles.

---

## 🌟 Key Features

### 🛍️ Customer Experience
- **Catalog & Filtering**: Browse footwear by category (Sneakers, Sandals, Boots, School Shoes, Party Shoes, Flats) and age groups (Toddler, Kids, Teens).
- **Product Details**: Multi-image view, dynamic size & color selection, stock availability, pricing, and descriptions.
- **Cart Management**: Seamless shopping cart supporting both guest sessions and logged-in users with automatic cart merging upon login.
- **Secure Checkout**: Multi-step checkout with order review and validation.
- **User Authentication**: Secure registration and login with session regeneration, CSRF protection, and bcrypt password hashing.

### 🛠️ Admin Dashboard
- **Product Management**: Add, edit, toggle visibility, and update stock/pricing.
- **Category Management**: Hierarchical categorization with age group mappings.
- **Image Management**: Upload and set primary product showcase images.
- **Order Tracking**: View customer orders, statuses, and purchase history.

### 🔒 Security & Performance
- **Prepared Statements (PDO)**: Full protection against SQL injection.
- **CSRF Protection**: Token-based validation on forms.
- **Input Sanitization**: XSS defense on user inputs and dynamic renders.
- **RESTful API Endpoints**: Modular backend APIs for authentication, cart, orders, and products.

---

## 📁 Project Structure

```text
girls-footwear/
├── backend/                # Server-side architecture & business logic
│   ├── api/                # JSON API endpoints (auth, cart, orders, products, payhere_notify)
│   ├── config/             # Database connection (PDO) & PayHere configuration
│   ├── includes/           # Reusable components (headers, footers, helper functions)
│   └── sql/
│       └── schema.sql      # Single authoritative database schema & seed dataset
├── frontend/               # Client-facing presentation layer
│   ├── assets/             # Static assets (CSS styles, JS scripts, product images)
│   │   ├── css/
│   │   ├── images/
│   │   └── js/
│   ├── errors/             # Custom error pages (404, etc.)
│   └── pages/              # User views & Admin portal
│       ├── admin/          # Admin management (categories, products, orders, images)
│       ├── account.php     # Customer account & order history
│       ├── cart.php        # Shopping cart view
│       ├── checkout.php    # Checkout process with PayHere integration
│       ├── index.php       # Homepage & featured showcase
│       ├── login.php       # User & Admin authentication
│       ├── product.php     # Individual product details
│       ├── products.php    # Product listing & multifaceted filters
│       └── register.php    # Customer registration
├── index.php               # Root entrypoint redirecting to frontend/pages/index.php
├── reset-admin.php         # Admin account recovery script
├── .gitignore              # Git exclusion rules
└── README.md               # Documentation
```

---

## 🚀 Getting Started

### Prerequisites
- **Web Server**: Apache / Nginx (or local environments like XAMPP, MAMP, WampServer)
- **PHP**: PHP 7.4 or higher with PDO and PDO_MySQL extensions enabled
- **Database**: MySQL 5.7+ or MariaDB 10.3+

### Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/gkkeshan/footwear-webpage.git
   cd footwear-webpage
   ```

2. **Configure Database**:
   - Open MySQL client / phpMyAdmin and create a database:
     ```sql
     CREATE DATABASE girls_footwear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   - Import the database schema and sample data:
     ```bash
     mysql -u root -p girls_footwear < backend/sql/schema.sql
     ```

3. **Update Database Credentials**:
   - Open `backend/config/database.php` and configure your local credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'girls_footwear');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     ```

4. **Run the Application**:
   - Place the project directory inside your web root (e.g. `htdocs` for XAMPP, `www` for WAMP), or start PHP's built-in development server:
     ```bash
     php -S localhost:8000
     ```
   - Visit `http://localhost:8000` in your browser.

---

## 🔑 Default Credentials

The initial seed data includes an administrator account for dashboard access:

| Role  | Email | Password |
|---|---|---|
| **Admin** | `admin@girlsfootwear.com` | `admin123` |

---

## 📜 License

This project is licensed under the MIT License - feel free to customize and build upon it.
