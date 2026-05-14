-- ============================================================
-- ANOVEX Label System v2 — SQL schéma
-- Verze 3.0 — od nuly, hodnoty přesně z původního HTML
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables (clean restart)
DROP TABLE IF EXISTS `translations`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `stacks`;
DROP TABLE IF EXISTS `size_presets`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

CREATE TABLE `settings` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `skey`       VARCHAR(100) NOT NULL UNIQUE,
  `value`      TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`skey`, `value`) VALUES
  ('distributor_name',    'ANOVEX by AGENA Reality s.r.o.'),
  ('distributor_address', 'Pod radnicí 1328/1, Praha 5 ČR'),
  ('distributor_ico',     '24164941'),
  ('warn_color',          '#d06060'),
  ('default_lang',        'cs');

CREATE TABLE `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','editor') DEFAULT 'editor',
  `active`     TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `suppliers` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `code`       VARCHAR(50)  NOT NULL UNIQUE,
  `address`    VARCHAR(255),
  `sku_prefix` VARCHAR(20),
  `active`     TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `suppliers` (`name`, `code`, `address`, `sku_prefix`) VALUES
  ('Chance2Brand / Brands on Demand UG', 'c2b', 'Muthesiusstr. 6, 12163 Berlin, Deutschland', 'C2B');

-- ============================================================
-- SIZE PRESETS — hodnoty PŘESNĚ podle původního HTML
-- (anovex_label_system_2026-04-24_09-35.html, řádky 643-686)
-- ============================================================
CREATE TABLE `size_presets` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`    VARCHAR(20) NOT NULL UNIQUE,
  `label`   VARCHAR(60) NOT NULL,
  `vw`      INT NOT NULL, `vh`    INT NOT NULL,
  `c2b_w`   INT NOT NULL, `c2b_h` INT NOT NULL,
  `lx1` INT NOT NULL, `lx2` INT NOT NULL,
  `cx1` INT NOT NULL, `cx2` INT NOT NULL,
  `rx1` INT NOT NULL, `rx2` INT NOT NULL,
  `sep1` INT NOT NULL, `sep2` INT NOT NULL,
  `f_min` INT NOT NULL, `f_xs`  INT NOT NULL,
  `f_sm`  INT NOT NULL, `f_md`  INT NOT NULL,
  `f_lg`  INT NOT NULL, `f_xl`  INT NOT NULL,
  `f_xxl` INT NOT NULL, `f_big` INT NOT NULL, `f_ttl` INT NOT NULL,
  `w_l`  INT NOT NULL, `w_c`  INT NOT NULL,
  `w_r`  INT NOT NULL, `w_z`  INT NOT NULL,
  `lh_xs` INT NOT NULL, `lh_sm` INT NOT NULL,
  `lh_md` INT NOT NULL, `lh_lg` INT NOT NULL,
  `sort_order` INT DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hodnoty 1:1 podle SIZE_PRESETS v původním HTML
INSERT INTO `size_presets`
(code,label,vw,vh,c2b_w,c2b_h,lx1,lx2,cx1,cx2,rx1,rx2,sep1,sep2,f_min,f_xs,f_sm,f_md,f_lg,f_xl,f_xxl,f_big,f_ttl,w_l,w_c,w_r,w_z,lh_xs,lh_sm,lh_md,lh_lg,sort_order)
VALUES
('110x50','110×50 mm',     1140, 520,1347, 616, 21, 354, 360, 768, 774,1119, 357, 771, 14,18,22,26,32,42,58,48,64, 28,22,26,32, 22,27,32,40, 1),
('180x70','180×70 mm',     1850, 720,2184, 850, 21, 574, 580,1264,1270,1829, 577,1267, 18,22,28,34,42,56,76,64,82, 40,30,38,44, 27,34,42,52, 2),
('200x80','200×80 mm',     2050, 820,2422, 969, 21, 626, 632,1418,1424,2029, 629,1421, 20,25,31,38,47,62,84,70,92, 44,34,42,48, 30,38,46,58, 3),
('110x40','110×40 mm',     1100, 400,1300, 472, 21, 340, 346, 754, 760,1079, 343, 757, 12,15,18,22,27,36,50,40,54, 26,20,24,30, 19,23,28,34, 4),
('205x82','205×82 mm (shots tisk)',  2132, 852,2422, 969, 21, 401, 407,1193,1199,2111, 404,1196, 14,18,24,30,47,62,84,70,92, 38,36,44,50, 22,30,38,58, 5),
('205x82sym','205×82 mm (shots C2B)',2132, 852,2422, 969, 21, 626, 632,1500,1506,2111, 629,1503, 14,18,24,30,47,62,84,70,92, 44,36,44,50, 22,30,38,58, 6),
('280x112','280×112 mm (prášek)',     3306,1322,3306,1322, 21, 963, 969,2225,2231,3285, 966,2228, 32,40,50,61,76,100,135,113,148, 70,55,68,78, 48,62,75,94, 7);

-- ============================================================
-- STACKS — 7 výchozích formulací
-- ============================================================
CREATE TABLE `stacks` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT UNSIGNED NOT NULL,
  `code`        VARCHAR(50)  NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `sub`         VARCHAR(150),
  `series`      ENUM('premium','formula','select') DEFAULT 'formula',
  `bg`          VARCHAR(10) DEFAULT '#0f0d08',
  `accent`      VARCHAR(10) DEFAULT '#c9a84c',
  `logo_color`  VARCHAR(20) DEFAULT 'gold',
  `sort_order`  INT DEFAULT 0,
  `active`      TINYINT(1) DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  UNIQUE KEY `supplier_code` (`supplier_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `stacks` (supplier_id, code, name, sub, series, bg, accent, logo_color, sort_order) VALUES
  (1, 'longevity',  'LONGEVITY BASE', 'dlouhověkost',     'premium', '#0f0d08', '#c9a84c', 'gold',   1),
  (1, 'mens',       "MEN'S PRIME",    'vitalita pro muže', 'premium', '#0f0d08', '#c13a3a', 'red',    2),
  (1, 'womens',     "WOMEN'S PRIME",  'vitalita pro ženy', 'premium', '#0f0d08', '#c4547a', 'orange', 3),
  (1, 'deepsleep',  'DEEP SLEEP',     'kvalitní spánek',   'formula', '#0f0d08', '#4a7fc1', 'blue',   4),
  (1, 'corevit',    'CORE VITALITY',  'energie a výkon',   'formula', '#0f0d08', '#e8832a', 'orange', 5),
  (1, 'sharpmind',  'SHARP MIND',     'paměť a soustředění','formula','#0f0d08', '#8b5fc1', 'purple', 6),
  (1, 'select',     'SELECT',         'jednotlivé produkty','select', '#0a2535', '#e0c97a', 'select', 7);

CREATE TABLE `products` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stack_id`       INT UNSIGNED NOT NULL,
  `supplier_id`    INT UNSIGNED NOT NULL,
  `sort_order`     INT DEFAULT 0,
  `ean`            VARCHAR(20),
  `sku`            VARCHAR(50),
  `refid`          VARCHAR(50),
  `orig_name`      VARCHAR(200),
  `preset_code`    VARCHAR(20) DEFAULT '180x70',
  `name`           VARCHAR(150) NOT NULL,
  `sub`            VARCHAR(200),
  `count`          VARCHAR(100),
  `num`            VARCHAR(20),
  `name_full`      VARCHAR(200),
  `net`            VARCHAR(100),
  `doplnek_stravy` VARCHAR(50) DEFAULT 'DOPLNĚK STRAVY',
  `davkovani`      TEXT,
  `upozorneni`     TEXT,
  `skladovani`     TEXT,
  `obsah_baleni`   VARCHAR(150),
  `sarze`          VARCHAR(150) DEFAULT 'Č. šarže / Min. trvanlivost: viz. obal',
  `serv`           VARCHAR(150),
  `slozeni`        TEXT,
  `storage`        TEXT,
  `ing_mode`       ENUM('table','text') DEFAULT 'table',
  `ings`           JSON,
  `feats`          JSON,
  `fs`             JSON,
  `active`         TINYINT(1) DEFAULT 1,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`stack_id`)    REFERENCES `stacks`(`id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `translations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`   INT UNSIGNED NOT NULL,
  `lang`         VARCHAR(5) NOT NULL,
  `name`         VARCHAR(150),
  `sub`          VARCHAR(200),
  `count`        VARCHAR(100),
  `name_full`    VARCHAR(200),
  `davkovani`    TEXT,
  `upozorneni`   TEXT,
  `skladovani`   TEXT,
  `obsah_baleni` VARCHAR(150),
  `serv`         VARCHAR(150),
  `slozeni`      TEXT,
  `storage`      TEXT,
  `ings`         JSON,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `product_lang` (`product_id`, `lang`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
