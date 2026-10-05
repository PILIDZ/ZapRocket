=== ZapRocket (闪电WP性能) ===
Contributors: pilidz
Donate link: https://www.pilipost.net/
Tags: performance, cache, minify, lazyload, oss
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress performance: site slim, CDN / preload / lazy load / scripts, optional page cache, object storage, database cleanup.
针对 WordPress 的性能优化：站点瘦身、CDN / 预载 / 懒加载 / 脚本、可选整页缓存、对象存储、数据库清理。

== Description ==

= English =

ZapRocket (Chinese name: 闪电WP性能) is a WordPress performance plugin by Pili Design. It is not affiliated with WP Rocket.

Administrators can slim the site, speed up assets, optionally enable full-page cache, offload media to object storage, and clean the database. High-risk options (Delay JS, minify, full-page cache) are **off by default**. Turn on the overview master switch first, then enable each section as needed. Do not run the same kind of full-page cache or minify together with WP Rocket, LiteSpeed Cache, or similar plugins.

**Features**

* Site slim: extra head output, Emoji, visitor REST, Heartbeat, comment avatars
* Speed: CDN, preload, lazy load, CSS / JS minify and delay, optional full-page cache
* Object storage: Alibaba Cloud OSS, Tencent Cloud COS, Cloudflare R2, Qiniu Kodo, and S3-compatible storage; custom domain, object prefix, helpers, one-click historical media migration
* Database cleanup and scheduled tasks, with a run log for failures and cleanup results

More products: [WP Lightning / Pili Design](https://www.pilipost.net/).

= 中文 =

针对 WordPress 的性能优化：站点瘦身、CDN / 预载 / 懒加载 / 脚本、可选整页缓存、对象存储（阿里云、腾讯云、R2、七牛与兼容 S3）、数据库清理。本插件由霹雳设计开发，与 WP Rocket 无关。

**主要能力**

* 站点瘦身：页头冗余、Emoji、访客 REST、Heartbeat、评论头像
* 速度优化：CDN、资源预载、懒加载、CSS / JS 压缩与延迟、可选整页缓存
* 对象存储：自定义域名、桶内前缀、快捷操作、历史媒体一键迁移
* 数据库清理与定时任务，运行日志记录失败与清理结果

Delay JS、压缩、整页缓存等较高风险项默认关闭，由管理员按需打开。不要与 WP Rocket、LiteSpeed Cache 等同时开启同类整页缓存或压缩。

更多产品见 [WP闪电快稿 / 霹雳设计](https://www.pilipost.net/)。

== Installation ==

= English =

1. Upload the `zaprocket` folder to `wp-content/plugins/`
2. Confirm the folder contains `pili-core/bootstrap.php`
3. Activate **ZapRocket (闪电WP性能)** on the Plugins screen
4. Open the plugin settings: enable the overview master switch, then turn on each section as needed
5. Full-page cache, Delay JS, and minify stay off by default. Confirm the site works, then enable them if you want

= 中文 =

1. 将 `zaprocket` 目录放到 `wp-content/plugins/`
2. 确认目录内包含 `pili-core/bootstrap.php`
3. 在「插件」中启用「闪电WP性能」
4. 打开「闪电WP性能」设置：先开概览总开关，再按需打开各分区
5. 整页缓存、Delay JS、压缩默认关闭，确认站点正常后再开

== Frequently Asked Questions ==

= Does it turn on cache and minify as soon as I install it? / 会不会一装上就把缓存、压缩全打开？ =

No. After the master switch is on, only basic slimming is available. Delay JS, CSS / JS minify, and full-page cache stay off until an administrator enables them.

不会。总开关打开后，基础瘦身才可用。Delay JS、CSS / JS 压缩、整页缓存默认关闭，需要管理员自己打开。

= Can I use it with WP Rocket or LiteSpeed Cache? / 可以和 WP Rocket、LiteSpeed Cache 一起用吗？ =

Do not enable the same kind of full-page cache or script minify in two plugins at once. They can overwrite each other and break the page. Pick one stack.

不要同时开启同类整页缓存或脚本压缩，容易互相覆盖、页面错乱。选一套即可。

= Which object storage providers are supported? / 对象存储支持哪些厂商？ =

Alibaba Cloud OSS, Tencent Cloud COS, Cloudflare R2, Qiniu Kodo, and S3-compatible storage. Enter keys, test the connection, then set a custom domain and object prefix.

阿里云 OSS、腾讯云 COS、Cloudflare R2、七牛 Kodo，以及兼容 S3 的存储。先填密钥并测试连接，再配自定义域名与桶内前缀。

= How do I migrate existing images? / 历史图片怎么迁到对象存储？ =

Use **Object storage → One-click migrate**. To fix dead links in post content only, use the helper actions. Back up the database first.

用「对象存储 → 一键迁移」。只改正文里的死链，用「快捷操作」。动手前请备份数据库。

= Is database cleanup safe? / 数据库清理安全吗？ =

Scan and estimate first, and back up the database. Deleted data cannot be restored. You can schedule cleanup; results appear in the run log.

清理前请先扫描预估并备份数据库。删除后不可恢复。可用定时清理，运行日志里能看到清理结果。

= Does uninstall delete settings and cache? / 卸载会删配置和缓存吗？ =

No. By default, settings and cache are kept.

默认不会删除配置和缓存。

== Screenshots ==

1. Welcome: plugin intro and feature links / 欢迎页：插件简介与功能入口
2. Overview: master switch, status cards, low-risk suggestions / 概览：总开关、状态卡片与低风险推荐
3. Site slim: head cleanup / 站点瘦身：页头清理
4. Site slim: feature toggles / 站点瘦身：功能开关
5. Speed: media lazy load / 速度优化：媒体懒加载
6. Speed: full-page cache and advanced rules / 速度优化：整页缓存与高级规则
7. Object storage: settings and cloud providers / 对象存储：存储设置与多云厂商
8. Database: junk cleanup and size estimate / 数据库：垃圾清理与体积预估
9. English welcome screen / 英文界面欢迎页

== Changelog ==

= 1.2.1 =
* Welcome screen matches real features (slim / speed / object storage / database / logs)
  欢迎页改为插件真实功能（瘦身 / 速度 / 对象存储 / 数据库 / 日志）
* Object storage: Aliyun, Tencent, R2, Qiniu, S3; helpers and one-click migrate
  对象存储：阿里云、腾讯云、R2、七牛、S3，快捷操作与一键迁移
* Run log and sidebar contact link
  运行日志、侧栏联系入口

= 0.1.0-dev =
* First development build: slim, speed, database, overview suggestions, cache purge
  首个开发版：瘦身、速度、数据库、概览推荐与清缓存

== Upgrade Notice ==

= 1.2.1 =
Feature release: slim, speed, object storage, database cleanup, run log. After upgrade, review the switches; high-risk options stay off by default.
正式功能版：瘦身、速度、对象存储、数据库清理与运行日志。升级后请打开设置核对开关；高风险项仍默认关闭。
