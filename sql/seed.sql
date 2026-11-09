-- Play Duo 開発用テストデータ（schema.sql を流したあとに実行する）
-- 何度流しても件数は変わらない（固定 ID の INSERT … ON DUPLICATE KEY UPDATE。DELETE はしない）
--
-- 管理者：login_id = admin ／ 開発用パスワード = admin1234（ローカル開発専用。本番では使わない）
--
-- 商品：assets/games/products.csv の 8 件を次の変換表で入れる
--   CSV product_id (g01 など) → product_id は 1〜8 の固定値（file_path に gNN を残す）
--   title → name ／ title_en → title_en ／ price_yen → price ／ size_gb → capacity
--   genre → genre（PVP は対応表の値「対戦(PVP)」に正規化）
--   windows / mac → os_windows / os_mac ／ developer・release_date・description はそのまま
--   cover_image → image ／ screenshot1〜3 → screenshot1〜3（assets/games/ からの相対パス）
-- CSV に無い列は【仮値】：min_players / max_players / play_style / crossplay_flag（両OS対応のものだけ 1）
--   / age_rating / play_time / recommend_order / file_path / public_flag
USE playduo;

INSERT INTO admins (admin_id, login_id, password) VALUES
    (1, 'admin', '$2y$10$GOlIWV8RQvdayO4xqqmW7.jnzuTxZZl30UgFG3R/pIKC4YIWRj2mK')
ON DUPLICATE KEY UPDATE login_id = VALUES(login_id), password = VALUES(password);

INSERT INTO products (
    product_id, name, title_en, price, description, genre, capacity, age_rating, developer, release_date,
    image, screenshot1, screenshot2, screenshot3,
    min_players, max_players, play_style, crossplay_flag, os_windows, os_mac,
    play_time, recommend_order, file_path, public_flag, created_at
) VALUES
    (1, '鉄骨前線', 'IRONLINE', 4180, '崩れかけた鉄骨都市を舞台にした5対5のチーム戦FPS。壁や床を壊して射線をつくる「破壊戦術」が特徴。', 'FPS', 62.4, 'C', 'Steelbeam Works', '2026-03-12',
     'windows_only/fps/g01_cover.png', 'windows_only/fps/g01_ss1.png', 'windows_only/fps/g01_ss2.png', 'windows_only/fps/g01_ss3.png',
     2, 10, '対戦', 0, 1, 0, '約30分', 1, 'downloads/g01.zip', 1, NOW()),
    (2, 'ネオンサイト', 'NEONSIGHT', 3300, '夜のネオン街で戦うアリーナ型FPS。1試合5分、壁走りと二段ジャンプで立体的に動き回る。', 'FPS', 38.0, 'C', 'Lumen Arc Studio', '2025-11-20',
     'both/fps/g02_cover.png', 'both/fps/g02_ss1.png', 'both/fps/g02_ss2.png', 'both/fps/g02_ss3.png',
     2, 8, '対戦', 1, 1, 1, '約5分', 2, 'downloads/g02.zip', 1, NOW()),
    (3, '千年樹オンライン', 'THOUSAND TREE ONLINE', 2200, '世界の中心にそびえる千年樹を巡るMMORPG。最大40人のレイドと、季節ごとに変わるフィールドイベントを楽しめる。', 'MMO', 31.7, 'B', 'Rootgate', '2025-07-01',
     'both/mmo/g03_cover.png', 'both/mmo/g03_ss1.png', 'both/mmo/g03_ss2.png', 'both/mmo/g03_ss3.png',
     2, 40, '協力', 1, 1, 1, '約60分', 3, 'downloads/g03.zip', 1, NOW()),
    (4, '雲路の旅団', 'CLOUDPATH BRIGADE', 2860, '浮遊島をつなぐ雲の道を旅するMMO。飛空艇をギルドで共同開発し、まだ誰も行っていない空域を開拓していく。', 'MMO', 24.5, 'A', 'Skyloom Games', '2026-01-28',
     'mac_only/mmo/g04_cover.png', 'mac_only/mmo/g04_ss1.png', 'mac_only/mmo/g04_ss2.png', 'mac_only/mmo/g04_ss3.png',
     2, 20, '協力', 0, 0, 1, '約60分', 4, 'downloads/g04.zip', 1, NOW()),
    (5, 'ブロックフィールド開拓記', 'BLOCKFIELD FRONTIER', 2480, 'ブロックを掘って、集めて、組み立てるサンドボックス。最大8人の協力プレイで村づくりや地下探索に挑む。', 'クラフト', 3.2, 'A', 'Cubeworks', '2025-05-16',
     'both/craft/g05_cover.png', 'both/craft/g05_ss1.png', 'both/craft/g05_ss2.png', 'both/craft/g05_ss3.png',
     2, 8, '協力', 1, 1, 1, '約30分', 5, 'downloads/g05.zip', 1, NOW()),
    (6, 'アリーナ・ゼロ', 'ARENA ZERO', 3080, '1対1の対戦アクション。16人のファイターから選び、ランクマッチで最高位「ゼロ」を目指す。', '対戦(PVP)', 12.8, 'B', 'Zero Frame', '2025-09-09',
     'windows_only/pvp/g07_cover.png', 'windows_only/pvp/g07_ss1.png', 'windows_only/pvp/g07_ss2.png', 'windows_only/pvp/g07_ss3.png',
     2, 2, '対戦', 0, 1, 0, '約10分', 6, 'downloads/g07.zip', 1, NOW()),
    (7, '双月決闘', 'TWIN MOON DUEL', 2640, '二つの月の下で剣を交える和風の対戦ゲーム。月の満ち欠けで技の性能が変わり、読み合いが深い。', '対戦(PVP)', 9.4, 'B', 'Moonlit Forge', '2026-04-24',
     'both/pvp/g08_cover.png', 'both/pvp/g08_ss1.png', 'both/pvp/g08_ss2.png', 'both/pvp/g08_ss3.png',
     2, 2, '対戦', 1, 1, 1, '約10分', 7, 'downloads/g08.zip', 1, NOW()),
    (8, '湾岸シグナル', 'BAYLINE SIGNAL', 3960, '深夜の湾岸線を走るストリートレース。最大8人のオンライン対戦に対応し、信号とレーンを読んで追い抜く公道風の周回コースを収録。', 'レース', 45.1, 'A', 'Redline Harbor', '2026-02-19',
     'windows_only/race/g11_cover.png', 'windows_only/race/g11_ss1.png', 'windows_only/race/g11_ss2.png', 'windows_only/race/g11_ss3.png',
     2, 8, '対戦', 0, 1, 0, '約15分', 8, 'downloads/g11.zip', 1, NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name), title_en = VALUES(title_en), price = VALUES(price), description = VALUES(description),
    genre = VALUES(genre), capacity = VALUES(capacity), age_rating = VALUES(age_rating), developer = VALUES(developer),
    release_date = VALUES(release_date), image = VALUES(image), screenshot1 = VALUES(screenshot1),
    screenshot2 = VALUES(screenshot2), screenshot3 = VALUES(screenshot3), min_players = VALUES(min_players),
    max_players = VALUES(max_players), play_style = VALUES(play_style), crossplay_flag = VALUES(crossplay_flag),
    os_windows = VALUES(os_windows), os_mac = VALUES(os_mac), play_time = VALUES(play_time),
    recommend_order = VALUES(recommend_order), file_path = VALUES(file_path), public_flag = VALUES(public_flag);
