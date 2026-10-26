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

-- Sample products (Toddler Sneakers - category 7)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(7, 'Toddler Pink Sneakers', 'toddler-pink-sneakers', 'Comfortable pink canvas sneakers perfect for little feet. Easy velcro closure makes dressing a breeze.', 29.99, 50, '["3", "4", "5"]', '["Pink"]', TRUE, TRUE, NOW()),
(7, 'Toddler White Sneakers', 'toddler-white-sneakers', 'Classic white leather sneakers with velcro closure. Durable rubber sole for active play.', 34.99, 30, '["3", "4", "5"]', '["White"]', FALSE, TRUE, NOW());

-- Kids Sneakers (category 8)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(8, 'Kids Purple Sparkle Sneakers', 'kids-purple-sparkle-sneakers', 'Fun purple sneakers with glitter details for school or play. Memory foam insole for all-day comfort.', 39.99, 40, '["8", "9", "10", "11", "12", "13"]', '["Purple"]', TRUE, TRUE, NOW()),
(8, 'Kids Black Athletic Sneakers', 'kids-black-athletic-sneakers', 'Supportive athletic sneakers for active girls. Breathable mesh upper with cushioned sole.', 44.99, 35, '["8", "9", "10", "11", "12", "13"]', '["Black"]', FALSE, TRUE, NOW());

-- Teen Sneakers (category 9)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(9, 'Teen White Platform Sneakers', 'teen-white-platform-sneakers', 'Trendy white platform sneakers with thick sole. Perfect for casual outings and everyday style.', 59.99, 25, '["1", "2", "3", "4", "5", "6"]', '["White"]', TRUE, TRUE, NOW()),
(9, 'Teen Black High-Top Sneakers', 'teen-black-high-top-sneakers', 'Classic black high-top sneakers for everyday wear. Chunky design with extra ankle support.', 54.99, 20, '["1", "2", "3", "4", "5", "6"]', '["Black"]', FALSE, TRUE, NOW());

-- Toddler Sandals (category 10)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(10, 'Toddler Pink Strappy Sandals', 'toddler-pink-strappy-sandals', 'Adorable pink strappy sandals with cushioned footbed. Perfect for summer adventures.', 24.99, 40, '["3", "4", "5"]', '["Pink"]', FALSE, TRUE, NOW()),
(10, 'Toddler White Mary Janes', 'toddler-white-mary-janes', 'Classic white Mary Jane sandals with buckle closure. Soft leather construction.', 27.99, 30, '["3", "4", "5"]', '["White"]', FALSE, TRUE, NOW());

-- Kids Sandals (category 11)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(11, 'Kids Gold Glitter Sandals', 'kids-gold-glitter-sandals', 'Sparkly gold sandals perfect for parties and special occasions. Glitter finish with star embellishments.', 32.99, 35, '["8", "9", "10", "11", "12", "13"]', '["Gold"]', TRUE, TRUE, NOW()),
(11, 'Kids Brown Leather Sandals', 'kids-brown-leather-sandals', 'Durable brown leather sandals with adjustable straps. Great for everyday wear.', 29.99, 45, '["8", "9", "10", "11", "12", "13"]', '["Brown"]', FALSE, TRUE, NOW());

-- Teen Sandals (category 12)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(12, 'Teen Silver Strappy Heels', 'teen-silver-strappy-heels', 'Elegant silver strappy heels for formal events. Block heel for stability and comfort.', 49.99, 20, '["1", "2", "3", "4", "5", "6"]', '["Silver"]', TRUE, TRUE, NOW()),
(12, 'Teen Black Block Heel Sandals', 'teen-black-block-heel-sandals', 'Comfortable black block heel sandals for all-day wear. Ankle strap for secure fit.', 44.99, 25, '["1", "2", "3", "4", "5", "6"]', '["Black"]', FALSE, TRUE, NOW());

-- Toddler Boots (category 13)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(13, 'Toddler Pink Rain Boots', 'toddler-pink-rain-boots', 'Waterproof pink rain boots with easy pull-on design. Non-slip sole for safety.', 22.99, 50, '["3", "4", "5"]', '["Pink"]', FALSE, TRUE, NOW()),
(13, 'Toddler Brown Winter Boots', 'toddler-brown-winter-boots', 'Warm brown winter boots with faux fur lining. Keeps feet cozy in cold weather.', 34.99, 30, '["3", "4", "5"]', '["Brown"]', FALSE, TRUE, NOW());

-- Kids Boots (category 14)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(14, 'Kids Purple Snow Boots', 'kids-purple-snow-boots', 'Insulated purple snow boots for cold weather play. Waterproof and warm.', 39.99, 35, '["8", "9", "10", "11", "12", "13"]', '["Purple"]', TRUE, TRUE, NOW()),
(14, 'Kids Black Chelsea Boots', 'kids-black-chelsea-boots', 'Stylish black Chelsea boots with elastic side panels. Versatile for any outfit.', 42.99, 25, '["8", "9", "10", "11", "12", "13"]', '["Black"]', FALSE, TRUE, NOW());

-- Teen Boots (category 15)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(15, 'Teen Brown Combat Boots', 'teen-brown-combat-boots', 'Edgy brown combat boots with lace-up front. Chunky sole for a bold look.', 59.99, 20, '["1", "2", "3", "4", "5", "6"]', '["Brown"]', TRUE, TRUE, NOW()),
(15, 'Tan Ankle Booties', 'tan-ankle-booties', 'Versatile tan ankle booties with side zipper. Perfect for fall and winter style.', 54.99, 30, '["1", "2", "3", "4", "5", "6"]', '["Tan"]', FALSE, TRUE, NOW());

-- Kids School Shoes (category 16)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(16, 'Kids Black Mary Jane School Shoes', 'kids-black-mary-jane-school-shoes', 'Durable black Mary Jane school shoes with velcro strap. Meets school dress code.', 34.99, 50, '["8", "9", "10", "11", "12", "13"]', '["Black"]', FALSE, TRUE, NOW()),
(16, 'Kids Navy Loafers', 'kids-navy-loafers', 'Smart navy loafers perfect for uniform requirements. Classic slip-on design.', 36.99, 35, '["8", "9", "10", "11", "12", "13"]', '["Navy"]', FALSE, TRUE, NOW());

-- Teen School Shoes (category 17)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(17, 'Teen Black Oxford Shoes', 'teen-black-oxford-shoes', 'Polished black Oxford shoes for formal school events. Genuine leather construction.', 44.99, 25, '["1", "2", "3", "4", "5", "6"]', '["Black"]', FALSE, TRUE, NOW()),
(17, 'Teen Brown Brogues', 'teen-brown-brogues', 'Stylish brown brogue detailing for a polished look. Perfect for interviews and presentations.', 46.99, 20, '["1", "2", "3", "4", "5", "6"]', '["Brown"]', FALSE, TRUE, NOW());

-- Toddler Party Shoes (category 18)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(18, 'Toddler Silver Sparkle Party Shoes', 'toddler-silver-sparkle-party-shoes', 'Glittery silver party shoes with T-strap closure. Perfect for special occasions.', 26.99, 30, '["3", "4", "5"]', '["Silver"]', TRUE, TRUE, NOW()),
(18, 'Toddler Pink Ballerina Flats', 'toddler-pink-ballerina-flats', 'Soft pink ballerina flats with elastic ankle strap. Comfortable for dancing.', 24.99, 40, '["3", "4", "5"]', '["Pink"]', FALSE, TRUE, NOW());

-- Kids Party Shoes (category 19)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(19, 'Kids Gold Sequin Party Shoes', 'kids-gold-sequin-party-shoes', 'Dazzling gold sequin shoes for birthdays and holidays. Sparkle finish with bow detail.', 34.99, 25, '["8", "9", "10", "11", "12", "13"]', '["Gold"]', TRUE, TRUE, NOW()),
(19, 'Kids Pink Satin Party Shoes', 'kids-pink-satin-party-shoes', 'Elegant pink satin shoes with pearl embellishments. Perfect for flower girl duties.', 32.99, 30, '["8", "9", "10", "11", "12", "13"]', '["Pink"]', FALSE, TRUE, NOW());

-- Teen Party Shoes (category 20)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(20, 'Teen Silver Metallic Heels', 'teen-silver-metallic-heels', 'Show-stopping silver metallic heels for special occasions. 3-inch block heel.', 52.99, 15, '["1", "2", "3", "4", "5", "6"]', '["Silver"]', TRUE, TRUE, NOW()),
(20, 'Teen Black Lace-Up Heels', 'teen-black-lace-up-heels', 'Sophisticated black lace-up heels with ankle support. Perfect for proms and formals.', 48.99, 20, '["1", "2", "3", "4", "5", "6"]', '["Black"]', FALSE, TRUE, NOW());

-- Toddler Flats (category 21)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(21, 'Toddler Pink Ballet Flats', 'toddler-pink-ballet-flats', 'Soft pink ballet flats with non-slip sole. Great for dance class and everyday.', 22.99, 45, '["3", "4", "5"]', '["Pink"]', FALSE, TRUE, NOW()),
(21, 'Toddler White Canvas Flats', 'toddler-white-canvas-flats', 'Casual white canvas flats perfect for everyday wear. Machine washable.', 19.99, 50, '["3", "4", "5"]', '["White"]', FALSE, TRUE, NOW());

-- Kids Flats (category 22)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(22, 'Kids Purple Printed Flats', 'kids-purple-printed-flats', 'Fun purple printed flats with elastic back. Features cute star pattern.', 26.99, 40, '["8", "9", "10", "11", "12", "13"]', '["Purple"]', TRUE, TRUE, NOW()),
(22, 'Kids Black Patent Flats', 'kids-black-patent-flats', 'Shiny black patent leather flats for dressier occasions. Classic design.', 29.99, 35, '["8", "9", "10", "11", "12", "13"]', '["Black"]', FALSE, TRUE, NOW());

-- Teen Flats (category 23)
INSERT INTO products (category_id, name, slug, description, price, stock, sizes, colors, featured, active, created_at) VALUES
(23, 'Teen White Espadrille Flats', 'teen-white-espadrille-flats', 'Summer-ready white espadrille flats with jute sole. Perfect for beach days.', 34.99, 30, '["1", "2", "3", "4", "5", "6"]', '["White"]', TRUE, TRUE, NOW()),
(23, 'Teen Navy Stripe Flats', 'teen-navy-stripe-flats', 'Classic navy and white stripe flats for preppy style. Boat shoe inspired.', 32.99, 25, '["1", "2", "3", "4", "5", "6"]', '["Navy"]', FALSE, TRUE, NOW());

-- Sample admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role, created_at) VALUES
('Admin User', 'admin@girlsfootwear.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());