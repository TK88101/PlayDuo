-- Play Duo DB 定義（趙 作成・山﨑さんの ER 図 v1.1 ＋ 第2回レビュー対応）
-- 元: PlayDuo_ER図_修正版 1.xlsx（エンティティ定義書 v1.1）
-- 追加: members.totp_secret / members.totp_last_step / recovery_codes（パスワード再設定の認証アプリ対応）
-- 使い方: phpMyAdmin の「SQL」タブにこのファイルの中身を貼って実行する（既存の playduo DB は作り直しになる）

DROP DATABASE IF EXISTS playduo;
CREATE DATABASE playduo DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE playduo;

-- 管理者情報
CREATE TABLE admins (
    admin_id   INT          NOT NULL AUTO_INCREMENT,
    login_id   VARCHAR(50)  NOT NULL,
    password   VARCHAR(255) NOT NULL COMMENT 'password_hash() で保存',
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admins_login_id (login_id)
) ENGINE=InnoDB;

-- 会員情報
CREATE TABLE members (
    member_id           INT          NOT NULL AUTO_INCREMENT,
    name                VARCHAR(50)  NOT NULL,
    email               VARCHAR(255) NOT NULL,
    password            VARCHAR(255) NOT NULL COMMENT 'password_hash() で保存',
    birth_date          DATE         NOT NULL COMMENT '登録後は変更不可（年齢区分の判定に使う）',
    nickname            VARCHAR(50)  NULL,
    phone               VARCHAR(50)  NULL,
    totp_secret         VARCHAR(64)  NOT NULL COMMENT '認証アプリの秘密鍵（Base32）。6桁の確認が通ってから登録する',
    totp_last_step      BIGINT       NULL     COMMENT '最後に使った6桁コードの時間ステップ（同じコードの再利用防止）',
    withdrawal_flag     TINYINT      NOT NULL DEFAULT 0 COMMENT '0:有効 / 1:退会済み',
    login_failure_count INT          NOT NULL DEFAULT 0 COMMENT 'ログイン・パスワード再設定の失敗回数（共通）',
    locked_at           DATETIME     NULL     COMMENT '5回目の失敗時刻。ここから10分間ロック',
    created_at          DATETIME     NOT NULL COMMENT '会員登録日時',
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_members_email (email)
) ENGINE=InnoDB;

-- 復旧コード（会員1人につき10件・各1回限り）
CREATE TABLE recovery_codes (
    recovery_code_id INT          NOT NULL AUTO_INCREMENT,
    member_id        INT          NOT NULL,
    code_hash        VARCHAR(255) NOT NULL COMMENT 'password_hash() で保存',
    used_at          DATETIME     NULL     COMMENT '使用日時（NULL＝未使用）',
    created_at       DATETIME     NOT NULL,
    PRIMARY KEY (recovery_code_id),
    KEY idx_recovery_codes_member (member_id),
    CONSTRAINT fk_recovery_codes_member FOREIGN KEY (member_id) REFERENCES members (member_id)
) ENGINE=InnoDB;

-- 商品情報
CREATE TABLE products (
    product_id      INT           NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100)  NOT NULL,
    title_en        VARCHAR(100)  NULL,
    price           INT           NOT NULL,
    description     TEXT          NOT NULL,
    genre           VARCHAR(50)   NOT NULL,
    capacity        DECIMAL(5,1)  NOT NULL COMMENT '単位GB',
    age_rating      VARCHAR(20)   NOT NULL,
    developer       VARCHAR(100)  NOT NULL,
    release_date    DATE          NOT NULL,
    image           VARCHAR(255)  NOT NULL COMMENT 'カバー画像',
    screenshot1     VARCHAR(255)  NOT NULL,
    screenshot2     VARCHAR(255)  NOT NULL,
    screenshot3     VARCHAR(255)  NOT NULL,
    min_players     INT           NOT NULL,
    max_players     INT           NOT NULL,
    play_style      VARCHAR(10)   NOT NULL COMMENT '協力 / 対戦',
    crossplay_flag  TINYINT       NOT NULL DEFAULT 0 COMMENT '0:不可 / 1:可',
    os_windows      TINYINT       NOT NULL COMMENT '1:対応',
    os_mac          TINYINT       NOT NULL COMMENT '1:対応',
    play_time       VARCHAR(20)   NOT NULL COMMENT '表示専用',
    recommend_order INT           NOT NULL COMMENT '昇順で表示',
    file_path       VARCHAR(255)  NOT NULL,
    public_flag     TINYINT       NOT NULL DEFAULT 1 COMMENT '1:公開 / 0:非公開',
    created_at      DATETIME      NOT NULL,
    PRIMARY KEY (product_id),
    CONSTRAINT chk_products_players CHECK (min_players <= max_players),
    CONSTRAINT chk_products_os CHECK (os_windows = 1 OR os_mac = 1)
) ENGINE=InnoDB;

-- カート情報
CREATE TABLE carts (
    member_id  INT NOT NULL,
    product_id INT NOT NULL,
    PRIMARY KEY (member_id, product_id),
    CONSTRAINT fk_carts_member  FOREIGN KEY (member_id)  REFERENCES members (member_id),
    CONSTRAINT fk_carts_product FOREIGN KEY (product_id) REFERENCES products (product_id)
) ENGINE=InnoDB;

-- 注文
CREATE TABLE orders (
    order_id   INT      NOT NULL AUTO_INCREMENT,
    member_id  INT      NOT NULL,
    order_date DATETIME NOT NULL COMMENT '合計金額は持たず、注文詳細の price の SUM で出す',
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_orders_order_member (order_id, member_id),
    CONSTRAINT fk_orders_member FOREIGN KEY (member_id) REFERENCES members (member_id)
) ENGINE=InnoDB;

-- 注文詳細（購入時点の商品名・価格を保存）
CREATE TABLE order_details (
    order_id     INT          NOT NULL,
    product_id   INT          NOT NULL,
    member_id    INT          NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    price        INT          NOT NULL,
    PRIMARY KEY (order_id, product_id),
    UNIQUE KEY uq_order_details_member_product (member_id, product_id),
    CONSTRAINT fk_order_details_order   FOREIGN KEY (order_id, member_id) REFERENCES orders (order_id, member_id),
    CONSTRAINT fk_order_details_member  FOREIGN KEY (member_id)  REFERENCES members (member_id),
    CONSTRAINT fk_order_details_product FOREIGN KEY (product_id) REFERENCES products (product_id)
) ENGINE=InnoDB;
