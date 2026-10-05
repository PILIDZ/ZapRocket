# Changelog

## 0.1.0-dev — 2026-09-25

> **话术**：`0.1.0-dev` **仅供架构试用**。正式 `0.1.0` tag 待人测 A1–A5 / 双实例 §B 通过。

### 可靠性 / i18n 修复（S1–S7）
- C1 开箱路径探测；C2 禁 `window.xun*`（`PILI.boot` / `PILI.bag`）
- H2–H4：`pili_asset_handle` / migrate option 按实例前缀
- H5 greenfield 参数化；H6 loading 禁 `$(document)` 回退
- i18n：空 msgid 清零；domain 禁 `pilipost`；JS UI fallback 英文化；msgid 默认英文
- 文档：中文 `00`–`15` + `docs/en/` 归档

### M0
- 独立目录、`PILI_Config`、文档骨架、options-sdk 模板迁入

### M1
- Fork 壳与基础字段；`pili-framework.min.js`；Demo 插件骨架；单一保存 + `domain_write_ok`；去掉 `$root||document` 回退

### M3
- `PILI_Section_Registry` + `pili_register_section_meta`；300 压力夹具；壳打开不 flatten fields

### M4
- 通用字段 35/35；`functions/table-sql.php`；Demo `server_paged`；本地 echarts ensure

### M5
- 双实例：`option_prefix` / `event_ns` / `ajax_ns` / `write_ok_key` / `css_scope`；`use_bound_instance`；主题骨架

### M6
- `tests/run-probes.php`；FAQ / UPGRADE；GETTING-STARTED 扩写；README 里程碑收口

### 2026-09-25 热修 · 开箱见菜单
- 插件骨架挂载改为 `plugins_loaded`；主题注释强化挂载约定  
- `PILI_Options::add_actions`：若已 `did_action('admin_menu')` 则立即 `add_admin_menu()`  
- 探针 `m6-skeleton-mount-probe`：两骨架必须含 `createOptions` + `add_action` 挂载  
- 开箱验收条写入 GETTING-STARTED / FAQ
