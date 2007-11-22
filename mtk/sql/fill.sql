/* Изначально в базе создаётся один пользователь - admin (пароль: test). Все права у него включены */

INSERT INTO ___users  (user_id, login, name, email, hp, access_level, pwd, custom_data, description, cached_description)
	VALUES (0, "admin", "Blog Administrator", "admin@example.com", 
			"http://example.com", 65535, "098f6bcd4621d373cade4e832627b4f6", "", "", "");

INSERT INTO ___cats  (cat_id, shortcut, title, description, cached_cnt, cached_cnt_total, cached_description) 
	VALUES (0, 'uncategorized', 'Uncategorized posts', 'Default category for all unsorted data. All new posts with undefined category and posts from deleted categories are stores here.', 0, 0, 'Default category for all unsorted data. All new posts with undefined category and posts from deleted categories are stores here.')