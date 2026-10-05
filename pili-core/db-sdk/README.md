# db-sdk

建表 / schema 版本 / 安全查询。判定「数据放哪」见 `docs/16-数据存储与建表规范.md`。

入口：`bootstrap.php` → `pili_db_register_table` / `pili_db_install` / `pili_db_upgrade` / `pili_db_table`。
