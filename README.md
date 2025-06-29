# Cloudreve Cashier

**一个为 Cloudreve V4 设计的现代化、功能丰富的易支付收银台。**

<p align="center">
<img alt="PHP Version" src="https://img.shields.io/badge/PHP-%3E%3D%207.4-8892BF?style=for-the-badge&logo=php" />
<img alt="License" src="https://img.shields.io/badge/License-GPL%20v3-blue?style=for-the-badge" />
<a href="https://discord.gg/RSj63kNVwK" >
<img alt="Discord" src="https://img.shields.io/badge/Discord-Join%20Chat-7289DA?style=for-the-badge&logo=discord" />
</a>
</p>

-----

Cloudreve Cashier 是一个专为 [Cloudreve V4](https://github.com/cloudreve/Cloudreve) 设计的支付解决方案，通过集成易支付（Epay）接口，为您的云存储服务提供无缝的收款体验。它拥有强大的管理后台、灵活的配置选项和现代化的响应式界面。

## ✨ 主要特性

  - **🚀 多渠道支付**：全面支持支付宝、微信支付、QQ钱包、云闪付等主流支付方式。
  - **💼 可视化管理**：内置强大的管理后台，轻松查询订单、管理配置、追踪日志。
  - **🔧 高度兼容**：同时兼容 Epay SDK v1.0 和 v2.0，迁移无忧。
  - **📱 响应式设计**：完美适配 PC 和移动设备，提供一致的用户体验。
  - **🔒 安全可靠**：内置签名验证与防重放攻击机制，保障每一笔交易的安全。
  - **⚙️ 智能路由**：自动检测用户环境（微信/支付宝/QQ），并推荐最佳支付方式。

## 🛠️ 系统要求

  - **PHP** \>= `7.4`
  - **SQLite** `3`
  - PHP 扩展: `cURL`, `JSON`, `OpenSSL`

## 🚀 快速安装

只需简单的几步即可完成安装和部署。

1.  **下载源码**
    将项目文件下载并解压到您的 Web 服务器根目录。

2.  **访问安装程序**
    在浏览器中打开 `http(s)://your-domain/install.php`。

3.  **填写配置信息**
    根据页面提示，配置 Epay 接口信息（如接口地址、商户ID、密钥等）。

4.  **设置管理员密码**
    为您的管理后台设置一个安全的密码。

5.  **完成**
    安装程序将自动完成剩余步骤，之后即可开始使用！

## 🔌 Cloudreve 集成

将收银台与您的 Cloudreve 网站连接起来非常简单：

1.  登录您的 Cloudreve 管理后台。
2.  导航到 **“参数设置”** -\> **“支付与充值”** -\> **“支付接口”**。
3.  添加一个新的 **“自定义支付接口”**。
4.  将 **“支付接口地址”** 设置为：
    ```
    http(s)://your-domain/api.php
    ```
5.  **“通信密钥”** 在当前版本中可以任意填写。
6.  保存设置即可生效。

## ⚙️ 详细配置

所有系统配置项均存储在数据库中，您可以通过以下任一方式进行管理：

  - **安装时配置**：在 `install.php` 页面进行初始配置。
  - **后台管理**：登录 `admin.php` 后，在配置管理页面随时修改。

<details>
<summary>
<strong>点击展开查看主要配置分组</strong>
</summary>
  - **Epay 配置**：接口地址、商户ID、密钥。
  - **收银台配置**：收银台URL、网站名称。
  - **支付配置**：支付方式开关、SDK 兼容性、默认推荐设置。
  - **安全配置**：请求来源域名白名单、安全选项。
  - **UI 配置**：主题颜色、布局样式。
  - **调试配置**：日志级别、错误信息显示开关。
</details>

## 🤔 故障排查

遇到问题时，请首先尝试以下步骤：

1.  **安装失败**

      - 检查 PHP 版本是否 `>= 7.4`。
      - 确保 `database/` 和 `logs/` 目录拥有写入权限。
      - 确认 `php-sqlite3` 扩展已正确安装并启用。

2.  **支付失败**

      - 核对 Epay 配置信息（商户ID、密钥）是否准确无误。
      - 查看 `logs/error.log` 文件以获取详细的错误信息。

3.  **Cloudreve 无法调用**

      - 检查您的 Cloudreve 域名是否已添加到后台的“域名白名单”中。
      - 确认在 Cloudreve 中填写的 `api.php` URL 是否可以公开访问。

## 💬 加入社群

遇到问题需要帮助，或是想分享您的想法？欢迎加入我们的社群！

| 平台 | 链接 |
| :---: | :--- |
| **Discord** | [**点击加入**](https://discord.gg/RSj63kNVwK) |
| **QQ群** | **565715364** ([点击加入](https://qm.qq.com/q/jmyvgV4rOE)) |

<p align="center"\>
<img src="https://static-smikuy-oss.lucloud.top/img/upload/PicGo202411191830583.webp?x-oss-process=style/webp" alt="QQ群二维码" width="200"/>
</p>

## 📄 许可证

本项目基于GPL-3.0 License开源。

-----

> **重要提示**: 为了您的资产安全，请务必在生产环境中修改默认的管理员密码，并定期备份 `database/` 目录下的数据库文件。
