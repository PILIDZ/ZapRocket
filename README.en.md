# ZapRocket (闪电WP性能)

**Language:** [简体中文](README.zh-CN.md) · [English](README.en.md)

A WordPress performance plugin: site slim-down, CDN / preload / lazy load / scripts, optional page cache, object storage, and database cleanup.

> English name: **ZapRocket** · Chinese name: **闪电WP性能** · Version: **1.2.1**  
> Not affiliated with [WP Rocket](https://wp-rocket.me/).

<p align="center">
  <img src=".wordpress-org/banner-1544x500.png" alt="ZapRocket banner" width="100%" />
</p>

## Screenshots

### Welcome

The welcome screen summarizes the plugin and the main entry points.

![Welcome](screenshot-1.png)

### Overview

Master switch, status cards, and low-risk recommendations.

![Overview](screenshot-2.png)

### Site slim-down

Head cleanup and feature switches. Leave anything you are unsure about off.

| Head cleanup | Feature switches |
|:---:|:---:|
| ![Head cleanup](screenshot-3.png) | ![Feature switches](screenshot-4.png) |

### Speed

Media lazy-load, optional full-page cache, and advanced rules.

| Media optimization | Advanced rules |
|:---:|:---:|
| ![Media optimization](screenshot-5.png) | ![Advanced rules](screenshot-6.png) |

### Object storage

Alibaba Cloud OSS, Tencent Cloud COS, Cloudflare R2, Qiniu Kodo, and S3-compatible storage.

![Object storage](screenshot-7.png)

### Database cleanup

Scan estimated size, then clean selected items. Back up first.

![Database cleanup](screenshot-8.png)

### English admin UI

The plugin admin can switch between Chinese and English. This does not change the WordPress site language.

![English welcome](screenshot-9.png)

## Features

| Module | What it does |
|--------|----------------|
| Site slim-down | Extra head output, Emoji, visitor REST, Heartbeat, comment avatars |
| Speed | CDN, preload, lazy load, CSS/JS minify and delay, optional full-page cache |
| Object storage | Aliyun OSS, Tencent COS, Cloudflare R2, Qiniu, S3-compatible; custom domain, bucket prefix, helpers, one-click migrate |
| Database | Cleanup, scheduled cleanup, size estimates |
| Run log | Failures and cleanup results (front-end cache hits are not logged) |

**High-risk options stay off by default** (Delay JS, minify, full-page cache). Turn on the overview master switch first, then enable each section as needed. Do not run the same kind of full-page cache or minify together with WP Rocket, LiteSpeed Cache, or similar plugins.

## Requirements

- WordPress **5.8+**
- PHP **7.4+** (PHP **8.1+** recommended for some object-storage providers)
- Administrator capability to configure settings

## Installation

1. Place this repository folder in `wp-content/plugins/` (or install the zip from [Releases](https://gitcode.com/pilidz/ZapRocket/releases))
2. Confirm the folder includes `pili-core/bootstrap.php`
3. Activate **ZapRocket / 闪电WP性能** under Plugins
4. Open settings: turn on the overview master switch, then enable sections as needed

## Links

- Product page: https://www.pilipost.net/products/zaprocket/
- More products: https://www.pilipost.net/
- Author: https://gitcode.com/pilidz
- WordPress.org plugin header file (bilingual): [`readme.txt`](./readme.txt)

## Layout

```
zaprocket.php       # plugin bootstrap
admin/              # admin sections and welcome page
assets/             # admin CSS/JS
includes/           # modules (slim / speed / OSS / database)
languages/          # language packs (text domain: zaprocket-wp)
pili-core/          # bundled settings framework
vendor/             # Composer dependencies (e.g. AWS SDK)
screenshot-*.png    # UI screenshots
.wordpress-org/     # WordPress.org banner assets
README.md           # repo home (Simplified Chinese)
README.zh-CN.md     # Simplified Chinese
README.en.md        # English
```

## Development

This repository matches the **WordPress.org distribution**. Internal docs, Cursor files, and i18n build sources are not included.

## License

GPLv2 or later. See [`license.txt`](./license.txt) and [GNU GPL 2.0](https://www.gnu.org/licenses/gpl-2.0.html).

## Disclaimer

Back up the site and database before use. Database cleanup and media migration cannot be easily undone. The authors are not liable for damage from misuse or conflicts with other cache plugins.
