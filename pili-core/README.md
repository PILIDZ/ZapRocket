# 霹雳（PILI）框架

独立公司级 WordPress 后台框架。**现网 pilipost `vendor-xun` / `includes/options` 零 diff，不回灌。**

- 版本：`VERSION` → **0.1.0-dev**（**仅供架构试用**；正式 `0.1.0` tag 等人测 A1–A5 / §B 通过后再打）
- 入口：`bootstrap.php` → `pili_boot( $config )`
- 多实例：`PILI_Config`（单份 PHP + Config）
- **文档入口（中文为准）**：[`docs/00-总览与导航.md`](docs/00-总览与导航.md)  
  AI / 人类先读总览，再按任务打开 `01`～`16`。英文归档仅参考：[`docs/en/`](docs/en/)。
  业务数据进表见 [`docs/16-数据存储与建表规范.md`](docs/16-数据存储与建表规范.md) + `db-sdk/`。

## 快速试跑

```bat
mklink /J "...\wp-content\plugins\pili-demo" "...\packages\pili-core\examples\wp-plugin-skeleton"
mklink /J "...\wp-content\themes\pili-theme-demo" "...\packages\pili-core\examples\wp-theme-skeleton"
```

启用插件「PILI Demo」与主题「PILI Theme Demo」；后台分别打开对应菜单。

## 里程碑

| 阶段 | 状态 |
|---|---|
| M0 目录+Config+文档骨架 | ✅ |
| M1 壳+基础字段+骨架可跑 | ✅ |
| M2 options-sdk / domain_write_ok | ✅（随 M1） |
| M3 分区按需加载+300 压力 | ✅ |
| M4 通用字段 35 + table 真分页模板 | ✅ |
| M5 双实例 + 主题骨架 | ✅ |
| M6 探针套件 + FAQ/上手收口 | ✅ |
| 文档中文导航体系（00–16） | ✅ 2026-09-25（含 16 + db-sdk） |

## 自检

```bat
php packages/pili-core/tests/run-probes.php
```

## 文档速查

| 任务 | 文档 |
|---|---|
| 上手 | [01-快速上手](docs/01-快速上手.md) |
| 存储 / 保存 | [02](docs/02-存储规范.md) · [05](docs/05-保存与UI规范.md) |
| 业务数据进表 | [16](docs/16-数据存储与建表规范.md) + `db-sdk/` |
| 新组件 / 新页面 | [12](docs/12-新建组件规范.md) · [13](docs/13-新建页面规范.md) |
| 样式 / i18n | [14](docs/14-样式规范.md) · [15](docs/15-i18n翻译规范.md) |
