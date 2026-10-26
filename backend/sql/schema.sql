-- Girls' Footwear E-Commerce Database Schema
-- Drops all tables if they exist for fresh install
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  address TEXT,
  city VARCHAR(100),
  postal_code VARCHAR(20),
  role ENUM('customer', 'admin') DEFAULT 'customer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories with age-group hierarchy
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  slug VARCHAR(50) UNIQUE NOT NULL,
  parent_id INT NULL,
  age_group ENUM('toddler', 'kids', 'teens', 'all') DEFAULT 'all',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Products
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(200) UNIQUE NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  stock INT DEFAULT 0,
  sizes JSON NOT NULL,
  colors JSON,
  featured BOOLEAN DEFAULT FALSE,
  active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Product Images
CREATE TABLE product_images (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary BOOLEAN DEFAULT FALSE,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders
CREATE TABLE orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  subtotal DECIMAL(10,2) NOT NULL,
  shipping_cost DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  shipping_name VARCHAR(100) NOT NULL,
  shipping_address TEXT NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_postal VARCHAR(20) NOT NULL,
  shipping_phone VARCHAR(20) NOT NULL,
  payment_method VARCHAR(50),
  payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Order Items
CREATE TABLE order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  size VARCHAR(10),
  color VARCHAR(50),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Cart Items
CREATE TABLE cart_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  session_id VARCHAR(100) NOT NULL,
  user_id INT NULL,
  product_id INT NOT NULL,
  quantity INT DEFAULT 1,
  size VARCHAR(10),
  color VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Reviews
CREATE TABLE reviews (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Insert main categories
INSERT INTO categories (name, slug, age_group, created_at) VALUES
('Sneakers', 'sneakers', 'all', NOW()),
('Sandals', 'sandals', 'all', NOW()),
('Boots', 'boots', 'all', NOW()),
('School Shoes', 'school-shoes', 'all', NOW()),
('Party Shoes', 'party-shoes', 'all', NOW()),
('Flats', 'flats', 'all', NOW());

-- Insert age group subcategories for Sneakers (parent_id = 1)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Toddler', 'sneakers-toddler', 1, 'toddler', NOW()),
('Kids', 'sneakers-kids', 1, 'kids', NOW()),
('Teens', 'sneakers-teens', 1, 'teens', NOW());

-- Insert age group subcategories for Sandals (parent_id = 2)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Toddler', 'sandals-toddler', 2, 'toddler', NOW()),
('Kids', 'sandals-kids', 2, 'kids', NOW()),
('Teens', 'sandals-teens', 2, 'teens', NOW());

-- Insert age group subcategories for Boots (parent_id = 3)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Toddler', 'boots-toddler', 3, 'toddler', NOW()),
('Kids', 'boots-kids', 3, 'kids', NOW()),
('Teens', 'boots-teens', 3, 'teens', NOW());

-- Insert age group subcategories for School Shoes (parent_id = 4)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Kids', 'school-shoes-kids', 4, 'kids', NOW()),
('Teens', 'school-shoes-teens', 4, 'teens', NOW());

-- Insert age group subcategories for Party Shoes (parent_id = 5)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Toddler', 'party-shoes-toddler', 5, 'toddler', NOW()),
('Kids', 'party-shoes-kids', 5, 'kids', NOW()),
('Teens', 'party-shoes-teens', 5, 'teens', NOW());

-- Insert age group subcategories for Flats (parent_id = 6)
INSERT INTO categories (name, slug, parent_id, age_group, created_at) VALUES
('Toddler', 'flats-toddler', 6, 'toddler', NOW()),
('Kids', 'flats-kids', 6, 'kids', NOW()),
('Teens', 'flats-teens', 6, 'teens', NOW());

-- Sample products (with images)
INSERT INTO products (id, category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(1, 10, 'Toddler Pink Sneakers', 'toddler-pink-sneakers', 'Comfortable pink canvas sneakers perfect for little feet. Easy velcro closure makes dressing a breeze.', 2500.00, 50, '["3", "4", "5"]', '["Pink"]', TRUE, TRUE, NOW()),
(2, 10, 'Toddler White Sneakers', 'toddler-white-sneakers', 'Classic white leather sneakers with velcro closure. Durable rubber sole for active play.', 2800.00, 30, '["3", "4", "5"]', '["White"]', FALSE, TRUE, NOW()),
(3, 11, 'Kids Purple Sparkle Sneakers', 'kids-purple-sparkle-sneakers', 'Fun purple sneakers with glitter details for school or play. Memory foam insole for all-day comfort.', 3200.00, 40, '["8", "9", "10", "11", "12", "13"]', '["Purple"]', TRUE, TRUE, NOW()),
(4, 11, 'Kids Black Athletic Sneakers', 'kids-black-athletic-sneakers', 'Supportive athletic sneakers for active girls. Breathable mesh upper with cushioned sole.', 3500.00, 35, '["8", "9", "10", "11", "12", "13"]', '["Black"]', FALSE, TRUE, NOW()),
(5, 12, 'Teen White Platform Sneakers', 'teen-white-platform-sneakers', 'Trendy white platform sneakers with thick sole. Perfect for casual outings and everyday style.', 3800.00, 25, '["1", "2", "3", "4", "5", "6"]', '["White"]', TRUE, TRUE, NOW()),
(6, 12, 'Teen Black High-Top Sneakers', 'teen-black-high-top-sneakers', 'Classic black high-top sneakers for everyday wear. Chunky design with extra ankle support.', 3600.00, 20, '["1", "2", "3", "4", "5", "6"]', '["Black"]', FALSE, TRUE, NOW()),
(7, 10, 'Toddler Pink Strappy Sandals', 'toddler-pink-strappy-sandals', 'Adorable pink strappy sandals with cushioned footbed. Perfect for summer adventures.', 1200.00, 40, '["3", "4", "5"]', '["Pink"]', FALSE, TRUE, NOW()),
(8, 10, 'Toddler White Mary Janes', 'toddler-white-mary-janes', 'Classic white Mary Jane sandals with buckle closure. Soft leather construction.', 1450.00, 30, '["3", "4", "5"]', '["White"]', FALSE, TRUE, NOW()),
(9, 11, 'Kids Gold Glitter Sandals', 'kids-gold-glitter-sandals', 'Sparkly gold sandals perfect for parties and special occasions. Glitter finish with star embellishments.', 1800.00, 35, '["8", "9", "10", "11", "12", "13"]', '["Gold"]', TRUE, TRUE, NOW()),
(10, 11, 'Cotton Candy Cloud', 'cotton-candy-cloud', 'Charming cotton candy pastel sneakers with ultra-soft cushioning and lightweight sole.', 2900.00, 35, '["8", "9", "10", "11", "12"]', '["Pink", "Sky Blue"]', TRUE, TRUE, NOW()),
(11, 11, 'Twinkle Toes', 'twinkle-toes', 'Sparkly sneakers designed to bring shine to every step with comfortable arch support.', 3100.00, 40, '["8", "9", "10", "11", "12", "13"]', '["Silver", "Pink"]', TRUE, TRUE, NOW()),
(12, 11, 'Berry Bounce', 'berry-bounce', 'Vibrant berry pink sneakers with high rebound soles for energetic outdoor activities.', 3300.00, 25, '["8", "9", "10", "11"]', '["Berry Pink", "Purple"]', FALSE, TRUE, NOW()),
(13, 10, 'Unicorn Whiskers', 'unicorn-whiskers', 'Magical unicorn themed sneakers with pastel gradient straps and glitter accents.', 3400.00, 30, '["3", "4", "5", "6", "7"]', '["Pastel Multi"]', TRUE, TRUE, NOW()),
(14, 11, 'Butterfly Bloom', 'butterfly-bloom', 'Delicate floral and butterfly detailed sneakers with breathable mesh upper.', 2950.00, 20, '["8", "9", "10", "11", "12"]', '["White", "Pink"]', FALSE, TRUE, NOW()),
(15, 12, 'Sugar Rush Sneakers', 'sugar-rush-sneakers', 'Sweet and stylish sneakers with colorful accent laces and memory foam insoles.', 3250.00, 45, '["1", "2", "3", "4", "5", "6"]', '["Multi", "White"]', TRUE, TRUE, NOW()),
(16, 10, 'Daisy Dash', 'daisy-dash', 'Playful daisy flower print sneakers with flexible non-slip soles.', 2700.00, 30, '["3", "4", "5", "6", "7"]', '["Yellow", "White"]', FALSE, TRUE, NOW()),
(17, 11, 'Fairy Steps', 'fairy-steps', 'Lightweight and dreamlike fairy themed sneakers with soft padded lining.', 3150.00, 28, '["8", "9", "10", "11", "12"]', '["Light Pink", "Silver"]', FALSE, TRUE, NOW()),
(18, 12, 'Marshmallow Mist', 'marshmallow-mist', 'Super soft marshmallow foam cushioned sneakers in soothing pastel tones.', 3500.00, 50, '["1", "2", "3", "4", "5", "6"]', '["Lavender", "Mint"]', TRUE, TRUE, NOW()),
(19, 10, 'Peach Puff', 'peach-puff', 'Cute peach colored slip-on sneakers for easy daily wear.', 2600.00, 35, '["3", "4", "5", "6", "7"]', '["Peach", "White"]', FALSE, TRUE, NOW()),
(20, 11, 'Bubblegum Burst', 'bubblegum-burst', 'Bold bubblegum pink active sneakers with reinforced toe cap.', 3350.00, 40, '["8", "9", "10", "11", "12"]', '["Hot Pink"]', FALSE, TRUE, NOW()),
(21, 11, 'Lavender Lullaby', 'lavender-lullaby', 'Soft lavender tone sneakers featuring elastic quick-lace design.', 3000.00, 30, '["8", "9", "10", "11", "12"]', '["Lavender"]', FALSE, TRUE, NOW()),
(22, 12, 'Rainbow Ripple', 'rainbow-ripple', 'Eye-catching rainbow accent sneakers with durable rubber outsoles.', 3600.00, 25, '["1", "2", "3", "4", "5", "6"]', '["Rainbow Multi"]', TRUE, TRUE, NOW()),
(23, 12, 'Starry Sparkle', 'starry-sparkle', 'Shimmering star details on a sleek low-top sneaker body.', 3450.00, 22, '["1", "2", "3", "4", "5", "6"]', '["Black", "Silver"]', FALSE, TRUE, NOW()),
(24, 11, 'Sunshine Stride', 'sunshine-stride', 'Bright and cheerful sunshine yellow sneakers with comfortable stride tech.', 2850.00, 38, '["8", "9", "10", "11", "12"]', '["Yellow", "White"]', FALSE, TRUE, NOW());

-- Product Images
INSERT INTO product_images (id, product_id, image_path, is_primary, sort_order) VALUES
(1, 1, 'assets/images/products/01.jpeg', 1, 1),
(2, 2, 'assets/images/products/02.jpeg', 1, 1),
(3, 3, 'assets/images/products/03.jpeg', 1, 1),
(4, 4, 'assets/images/products/04.jpeg', 1, 1),
(5, 5, 'assets/images/products/05.jpeg', 1, 1),
(6, 6, 'assets/images/products/06.jpeg', 1, 1),
(7, 7, 'assets/images/products/07.jpeg', 1, 1),
(8, 8, 'assets/images/products/08.jpeg', 1, 1),
(9, 9, 'assets/images/products/09.jpeg', 1, 1),
(10, 10, 'assets/images/products/10.jpg', 1, 1),
(11, 11, 'assets/images/products/11.jpg', 1, 1),
(12, 12, 'assets/images/products/12.jpg', 1, 1),
(13, 13, 'assets/images/products/13.jpg', 1, 1),
(14, 14, 'assets/images/products/14.jpg', 1, 1),
(15, 15, 'assets/images/products/15.jpg', 1, 1),
(16, 16, 'assets/images/products/16.jpg', 1, 1),
(17, 17, 'assets/images/products/17.jpg', 1, 1),
(18, 18, 'assets/images/products/18.jpg', 1, 1),
(19, 19, 'assets/images/products/19.jpg', 1, 1),
(20, 20, 'assets/images/products/20.jpg', 1, 1),
(21, 21, 'assets/images/products/21.jpg', 1, 1),
(22, 22, 'assets/images/products/22.jpg', 1, 1),
(23, 23, 'assets/images/products/23.jpg', 1, 1),
(24, 24, 'assets/images/products/24.jpg', 1, 1);

-- Sample admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role, created_at) VALUES
('Admin User', 'admin@girlsfootwear.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());